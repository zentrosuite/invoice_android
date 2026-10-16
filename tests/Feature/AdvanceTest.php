<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdvanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();
        $this->customer = Customer::factory()->create(['name' => 'Zentro Builders']);
    }

    public function test_an_advance_is_recorded_on_the_customer(): void
    {
        $this->actingAs($this->user)
            ->post(route('customers.advances.store', $this->customer), [
                'amount' => '5,000', 'received_on' => now()->toDateString(), 'method' => 'upi', 'reference' => 'UTR123',
            ])
            ->assertRedirect(route('customers.show', $this->customer))
            ->assertSessionHas('success');

        $this->assertSame('5000.00', $this->customer->advanceBalance());
        $this->actingAs($this->user)->get(route('customers.show', $this->customer))
            ->assertOk()->assertSee('Advance available')->assertSee('UTR123');
    }

    public function test_an_advance_cannot_be_recorded_as_an_advance_method(): void
    {
        $this->actingAs($this->user)
            ->post(route('customers.advances.store', $this->customer), [
                'amount' => '100', 'received_on' => now()->toDateString(), 'method' => 'advance',
            ])
            ->assertSessionHasErrorsIn('advance', ['method']);
    }

    public function test_the_builder_adjusts_the_advance_when_saving_the_invoice(): void
    {
        $this->advance('5000');

        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoiceData([
            'apply_advance' => '2,000', 'status' => 'sent',
        ]))->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('2000.00', $invoice->amount_paid);
        $this->assertSame('2000.00', $invoice->advanceApplied());
        $this->assertSame('3000.00', $this->customer->advanceBalance());

        $payment = $invoice->payments()->sole();
        $this->assertSame(PaymentMethod::Advance, $payment->method);
        $this->assertSame($this->customer->id, $payment->customer_id);
    }

    public function test_the_adjustment_is_capped_at_the_invoice_and_the_advance(): void
    {
        $this->advance('10000');

        // The invoice is 3,000: asking for 9,000 adjusts 3,000 and marks it paid.
        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoiceData(['apply_advance' => '9000', 'status' => 'sent']));
        $invoice = Invoice::sole();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('3000.00', $invoice->amount_paid);
        $this->assertSame('7000.00', $this->customer->advanceBalance());
    }

    public function test_no_advance_means_nothing_is_adjusted(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoiceData(['apply_advance' => '500', 'status' => 'sent']));

        $this->assertSame('0.00', Invoice::sole()->amount_paid);
    }

    public function test_an_advance_can_be_adjusted_from_the_invoice_page(): void
    {
        $this->advance('1000');
        $invoice = $this->savedInvoice();

        $this->actingAs($this->user)->get(route('invoices.show', $invoice))->assertSee('Adjust advance');

        $this->actingAs($this->user)
            ->post(route('invoices.advance', $invoice), ['amount' => '1,500'])
            ->assertSessionHasErrorsIn('advance', ['amount']);

        $this->actingAs($this->user)
            ->post(route('invoices.advance', $invoice), ['amount' => '600'])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('600.00', $invoice->refresh()->amount_paid);
        $this->assertSame('400.00', $this->customer->advanceBalance());
    }

    public function test_removing_the_adjustment_returns_the_money_to_the_customer(): void
    {
        $this->advance('1000');
        $invoice = $this->savedInvoice();
        $this->actingAs($this->user)->post(route('invoices.advance', $invoice), ['amount' => '1000']);

        $payment = $invoice->payments()->sole();
        $this->actingAs($this->user)->delete(route('invoices.payments.destroy', [$invoice, $payment]));

        $this->assertSame('1000.00', $this->customer->advanceBalance());
        $this->assertSame(InvoiceStatus::Sent, $invoice->refresh()->status);
    }

    public function test_an_advance_already_used_cannot_be_removed(): void
    {
        $advance = $this->advance('1000');
        $invoice = $this->savedInvoice();
        $this->actingAs($this->user)->post(route('invoices.advance', $invoice), ['amount' => '400']);

        $this->actingAs($this->user)
            ->delete(route('customers.advances.destroy', [$this->customer, $advance]))
            ->assertSessionHas('error');

        $this->assertModelExists($advance);
    }

    public function test_an_unused_advance_can_be_removed(): void
    {
        $advance = $this->advance('1000');

        $this->actingAs($this->user)
            ->delete(route('customers.advances.destroy', [$this->customer, $advance]))
            ->assertSessionHas('success');

        $this->assertModelMissing($advance);
    }

    public function test_the_customer_cannot_be_changed_once_their_advance_is_adjusted(): void
    {
        $this->advance('1000');
        $invoice = $this->savedInvoice();
        $this->actingAs($this->user)->post(route('invoices.advance', $invoice), ['amount' => '500']);
        $other = Customer::factory()->create();

        $this->actingAs($this->user)
            ->put(route('invoices.update', $invoice), $this->invoiceData(['customer_id' => $other->id]))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame($this->customer->id, $invoice->refresh()->customer_id);
    }

    public function test_the_payment_form_does_not_offer_advance_as_a_method(): void
    {
        $invoice = $this->savedInvoice();

        $this->actingAs($this->user)
            ->post(route('invoices.payments.store', $invoice), ['amount' => '10', 'paid_on' => now()->toDateString(), 'method' => 'advance'])
            ->assertSessionHasErrorsIn('payment', ['method']);
    }

    public function test_the_customer_picker_reports_the_advance(): void
    {
        $this->advance('750');

        $this->actingAs($this->user)->getJson(route('customers.search', ['q' => 'Zentro']))
            ->assertOk()
            ->assertJsonPath('0.advance', '750.00');
    }

    public function test_the_pdf_shows_the_advance_apart_from_other_payments(): void
    {
        $this->advance('1000');
        $invoice = $this->savedInvoice();
        $this->actingAs($this->user)->post(route('invoices.advance', $invoice), ['amount' => '1000']);
        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'amount' => '500', 'paid_on' => now()->toDateString(), 'method' => 'cash',
        ]);

        $html = app(InvoiceDocument::class)->html($invoice->refresh(), true);
        $this->assertStringContainsString('Advance adjusted', $html);
        $this->assertMatchesRegularExpression('/Paid<\/td><td class="v">&minus; ₹500\.00/', $html);
    }

    private function advance(string $amount): \App\Models\CustomerAdvance
    {
        return $this->customer->advances()->create([
            'amount' => $amount, 'received_on' => now()->toDateString(), 'method' => PaymentMethod::Upi,
        ]);
    }

    private function savedInvoice(): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoiceData(['status' => 'sent']));

        return Invoice::query()->latest('id')->firstOrFail();
    }

    /** A 3,000 classic invoice for the customer. */
    private function invoiceData(array $overrides = []): array
    {
        return array_merge([
            'pricing_mode' => 'itemised',
            'template' => 'classic',
            'invoice_date' => now()->toDateString(),
            'show_quantity' => '1',
            'customer_id' => $this->customer->id,
            'items' => [['description' => 'Cement 50kg', 'quantity' => '10', 'rate' => '300']],
        ], $overrides);
    }
}
