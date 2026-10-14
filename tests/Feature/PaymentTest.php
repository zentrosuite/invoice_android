<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDocument;
use App\Services\Invoices\InvoiceSharing;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();

        $company = app(CompanySettings::class);
        $company->upiId = 'zentro@ybl';
        $company->save();
    }

    /** The app sends forms with fetch (app.js); a redirect comes back as JSON to follow. */
    public function test_a_payment_sent_by_the_app_answers_with_where_to_go(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)
            ->post(route('invoices.payments.store', $invoice), [
                'amount' => '1,000', 'paid_on' => now()->toDateString(), 'method' => 'upi',
            ], ['X-Zentro-Form' => '1'])
            ->assertOk()
            ->assertExactJson(['redirect' => route('invoices.show', $invoice)])
            ->assertSessionHas('success');

        $this->assertSame(1, $invoice->payments()->count());
    }

    /** Validation errors go back to the page the form was on — never to the POST-only URL (405). */
    public function test_an_invalid_payment_sent_by_the_app_goes_back_with_errors(): void
    {
        $invoice = $this->invoice();
        $page = route('invoices.show', $invoice);

        $this->actingAs($this->user)
            ->from($page)
            ->post(route('invoices.payments.store', $invoice), ['amount' => '', 'paid_on' => ''], ['X-Zentro-Form' => '1'])
            ->assertOk()
            ->assertExactJson(['redirect' => $page])
            ->assertSessionHasErrorsIn('payment', ['amount', 'paid_on']);
    }

    /** Signing in starts a new session: its cookie must survive the JSON answer. */
    public function test_the_session_cookie_travels_with_the_json_answer(): void
    {
        $this->post(route('login.store'), ['pin' => '1234'], ['X-Zentro-Form' => '1'])
            ->assertOk()
            ->assertJsonStructure(['redirect'])
            ->assertCookie(config('session.cookie'));
    }

    public function test_a_plain_form_post_still_redirects(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => '10', 'paid_on' => now()->toDateString(), 'method' => 'cash',
        ])->assertRedirect(route('invoices.show', $invoice));
    }

    public function test_every_page_states_its_own_address(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)->get(route('invoices.show', $invoice))
            ->assertSee('<meta name="zi-url" content="' . route('invoices.show', $invoice) . '">', false);
    }

    public function test_a_part_payment_leaves_a_balance(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => '1,000', 'paid_on' => now()->toDateString(), 'method' => 'upi', 'reference' => 'UTR123',
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('success', 'Payment recorded. Balance ₹2,599.00.');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertSame('2599.00', $invoice->balance());
        $this->assertNull($invoice->paid_at);
        $this->assertSame('UTR123', $invoice->payments->sole()->reference);
    }

    public function test_paying_the_balance_makes_it_paid(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, '1000', now()->subDays(3)->toDateString());
        $this->pay($invoice, '2599', now()->subDay()->toDateString());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balance());
        $this->assertTrue($invoice->paid_at->isSameDay(now()->subDay()), 'Paid on the date of the last payment.');
    }

    public function test_more_than_the_balance_is_refused(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => '5000', 'paid_on' => now()->toDateString(), 'method' => 'cash',
        ])->assertSessionHasErrorsIn('payment', ['amount' => 'That is more than the balance of ₹3,599.00.']);

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_removing_a_payment_reopens_the_balance(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, '3599');
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $this->actingAs($this->user)
            ->delete(route('invoices.payments.destroy', [$invoice, $invoice->payments->sole()]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
    }

    public function test_mark_paid_records_the_balance_and_mark_unpaid_clears_payments(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, '599');

        $this->actingAs($this->user)->put(route('invoices.status', $invoice), ['status' => 'paid']);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(['599.00', '3000.00'], $invoice->payments->pluck('amount')->all());

        $this->actingAs($this->user)->put(route('invoices.status', $invoice), ['status' => 'sent']);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_raising_the_total_turns_paid_into_part_paid(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'pricing_mode' => 'package', 'template' => 'package', 'invoice_date' => now()->toDateString(),
            'package_total' => '1000', 'status' => 'sent', 'walkin_name' => 'Kumar',
            'items' => [['description' => 'Website', 'inclusion' => 'included']],
        ]);
        $invoice = Invoice::sole();
        $this->pay($invoice, '1000');
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $this->actingAs($this->user)->put(route('invoices.update', $invoice), [
            'number' => $invoice->number, 'pricing_mode' => 'package', 'template' => 'package',
            'invoice_date' => now()->toDateString(), 'package_total' => '1500', 'walkin_name' => 'Kumar',
            'items' => [['description' => 'Website', 'inclusion' => 'included']],
        ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('500.00', $invoice->balance());
    }

    public function test_the_qr_and_the_whatsapp_caption_ask_only_for_the_balance(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, '1000');
        $invoice->refresh();

        $data = app(InvoiceDocument::class)->data($invoice);
        $this->assertStringContainsString('am=2599.00', (string) $data['upiLink']);

        $this->assertStringContainsString('for ₹2,599', app(InvoiceSharing::class)->message($invoice));

        $this->actingAs($this->user)->get(route('invoices.preview', $invoice))
            ->assertSee('Balance Due')
            ->assertSee('PART PAID');
    }

    public function test_a_cancelled_invoice_takes_no_payments(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Cancelled]);

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => '10', 'paid_on' => now()->toDateString(), 'method' => 'cash',
        ])->assertSessionHas('error');

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_a_payment_belongs_to_its_invoice(): void
    {
        $a = $this->invoice();
        $b = $this->invoice(['number' => 'INV-0099']);
        $this->pay($a, '100');

        $this->actingAs($this->user)
            ->delete(route('invoices.payments.destroy', [$b, $a->payments->sole()]))
            ->assertNotFound();
    }

    private function pay(Invoice $invoice, string $amount, ?string $on = null): void
    {
        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => $amount, 'paid_on' => $on ?? now()->toDateString(), 'method' => 'upi',
        ])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function invoice(array $overrides = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'number' => 'INV-0007',
            'status' => InvoiceStatus::Sent,
            'pricing_mode' => PricingMode::Package,
            'template' => 'package',
            'customer_name' => 'Rasi',
            'package_total' => '3599.00',
            'subtotal' => '3599.00',
            'total' => '3599.00',
        ], $overrides));
    }
}
