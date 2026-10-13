<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceService;
use App\Services\Invoices\InvoiceSharing;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();

        $company = app(CompanySettings::class);
        $company->name = 'Zentro Private Limited';
        $company->upiId = 'zentro@ybl';
        $company->save();
    }

    public function test_the_invoice_page_offers_whatsapp_straight_to_the_customer(): void
    {
        $invoice = $this->invoice();

        $response = $this->actingAs($this->user)->get(route('invoices.show', $invoice))->assertOk();

        $invoice->refresh();
        $this->assertNotNull($invoice->share_token, 'Viewing the invoice prepares its link.');
        $this->assertSame(10, strlen($invoice->share_token));

        $response->assertSee('https://wa.me/919688527308?text=', false)
            ->assertSee(route('public.invoice', $invoice->share_token), false)
            ->assertSee('Send on WhatsApp');
    }

    public function test_the_message_fills_its_placeholders(): void
    {
        $invoice = $this->invoice();

        $message = app(InvoiceSharing::class)->message($invoice);

        $this->assertStringContainsString('Hi Rasi,', $message);
        $this->assertStringContainsString('invoice INV-0007 from Zentro Private Limited for ₹3,599', $message);
        $this->assertStringContainsString(route('public.invoice', $invoice->share_token), $message);
    }

    public function test_without_a_phone_whatsapp_asks_for_the_contact(): void
    {
        $invoice = $this->invoice(['customer_phone' => null]);

        $this->assertStringStartsWith('https://wa.me/?text=', app(InvoiceSharing::class)->whatsappUrl($invoice));
    }

    public function test_sending_a_draft_finalises_it(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Draft]);

        $this->actingAs($this->user)->postJson(route('invoices.shared', $invoice))
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->shared_at);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_sending_a_paid_invoice_keeps_it_paid(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($this->user)->postJson(route('invoices.shared', $invoice))->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_the_customer_can_open_the_link_without_logging_in(): void
    {
        $invoice = $this->invoice();
        $token = app(InvoiceSharing::class)->ensureToken($invoice);

        $this->get(route('public.invoice', $token))
            ->assertOk()
            ->assertSee('Invoice INV-0007')
            ->assertSee('₹3,599')
            ->assertSee('upi://pay?', false)
            ->assertSee('Download PDF')
            // Nothing from the back office leaks onto the customer's page.
            ->assertDontSee('Mark paid')
            ->assertDontSee(route('invoices.show', $invoice), false);

        $this->assertNotNull($invoice->refresh()->viewed_at);

        $this->get(route('public.document', $token))->assertOk()->assertSee('INV-0007');

        $pdf = $this->get(route('public.pdf', $token));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_the_customer_page_offers_a_call_link(): void
    {
        $company = app(CompanySettings::class);
        $company->phone = '+91 85264 48260';
        $company->save();

        $invoice = $this->invoice(['footer_note' => 'We look forward to serving you again.']);
        $token = app(InvoiceSharing::class)->ensureToken($invoice);

        $this->get(route('public.invoice', $token))->assertSee('href="tel:+918526448260"', false);

        // The invoice footer: closing line and business name on one line, then the call link.
        $this->get(route('public.document', $token))
            ->assertSeeInOrder(['Thank You!', 'We look forward to serving you again.', 'Zentro Private Limited', '+91 85264 48260'])
            ->assertSee('href="tel:+918526448260"', false);
    }

    public function test_a_paid_invoice_link_has_no_pay_button(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        $token = app(InvoiceSharing::class)->ensureToken($invoice);

        $this->get(route('public.invoice', $token))
            ->assertOk()
            ->assertSee('Paid')
            ->assertDontSee('upi://pay?', false);
    }

    public function test_wrong_or_malformed_tokens_are_not_found(): void
    {
        $this->invoice();

        $this->get('/i/' . str_repeat('a', 10))->assertNotFound();
        $this->get('/i/' . str_repeat('a', 40))->assertNotFound();
        $this->get('/i/short')->assertNotFound();
        $this->get('/i/' . str_repeat('a', 9) . '%27')->assertNotFound();
    }

    public function test_resetting_the_link_kills_the_old_one(): void
    {
        $invoice = $this->invoice();
        $old = app(InvoiceSharing::class)->ensureToken($invoice);

        $this->actingAs($this->user)->post(route('invoices.reset-link', $invoice))->assertSessionHas('success');

        $new = $invoice->refresh()->share_token;
        $this->assertNotSame($old, $new);

        auth()->logout();
        $this->get(route('public.invoice', $old))->assertNotFound();
        $this->get(route('public.invoice', $new))->assertOk();
    }

    public function test_links_made_before_tokens_were_shortened_still_open(): void
    {
        $invoice = $this->invoice();
        $invoice->share_token = str_repeat('Ab3', 13) . 'x';
        $invoice->save();

        $this->get('/i/' . $invoice->share_token)->assertOk();
    }

    public function test_a_duplicate_gets_its_own_link(): void
    {
        $invoice = $this->invoice();
        app(InvoiceSharing::class)->ensureToken($invoice);

        $copy = app(InvoiceService::class)->duplicate($invoice);

        $this->assertNull($copy->share_token);
        $this->assertNull($copy->shared_at);
    }

    public function test_the_token_never_appears_in_json(): void
    {
        $invoice = $this->invoice();
        app(InvoiceSharing::class)->ensureToken($invoice);

        $this->assertArrayNotHasKey('share_token', $invoice->refresh()->toArray());
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
            'customer_business' => '5 Star Sales and Service',
            'customer_phone' => '+91 96885 27308',
            'package_total' => '3599.00',
            'subtotal' => '3599.00',
            'total' => '3599.00',
        ], $overrides));
    }
}
