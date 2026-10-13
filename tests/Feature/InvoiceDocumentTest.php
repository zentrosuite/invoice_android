<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inclusion;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDocument;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();

        $company = app(CompanySettings::class);
        $company->name = 'Zentro Private Limited';
        $company->tagline = 'Web Design • Branding';
        $company->upiId = 'zentro@ybl';
        $company->save();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function templates(): array
    {
        return array_combine(
            ['package', 'modern', 'classic', 'minimal', 'receipt'],
            [['package'], ['modern'], ['classic'], ['minimal'], ['receipt']],
        );
    }

    #[DataProvider('templates')]
    public function test_every_design_renders_on_screen_and_as_pdf(string $template): void
    {
        $invoice = $this->invoice(['template' => $template]);

        $this->actingAs($this->user)->get(route('invoices.preview', $invoice))
            ->assertOk()
            ->assertSee('INV-0042')
            ->assertSee('Zentro Private Limited')
            ->assertSee('Website design')
            ->assertSee('Three Thousand Five Hundred Ninety-Nine Rupees Only');

        // On the phone the PDF is written into the app's storage for the native share/save calls.
        $file = $this->actingAs($this->user)->postJson(route('invoices.pdf-file', $invoice))->assertOk()->json();
        $this->assertSame('Invoice INV-0042 - 5 Star Sales and Service.pdf', $file['name']);
        $this->assertSame('application/pdf', $file['mime']);
        $this->assertStringStartsWith('%PDF-', (string) file_get_contents($file['path']));
    }

    public function test_an_unpaid_invoice_carries_a_upi_qr_for_the_amount_due(): void
    {
        $data = app(InvoiceDocument::class)->data($this->invoice(['status' => InvoiceStatus::Sent]));

        $this->assertStringStartsWith('data:image/png;base64,', (string) $data['qr']);
        $this->assertStringContainsString('am=3599.00', (string) $data['upiLink']);
        $this->assertStringContainsString('pa=zentro%40ybl', (string) $data['upiLink']);
    }

    public function test_paid_and_cancelled_invoices_have_no_qr_and_say_so(): void
    {
        $paid = $this->invoice(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        $this->assertNull(app(InvoiceDocument::class)->data($paid)['qr']);
        $this->actingAs($this->user)->get(route('invoices.preview', $paid))
            ->assertSee('PAID')->assertSee('Amount Paid')->assertDontSee('Scan with any UPI app');

        $cancelled = $this->invoice(['number' => 'INV-0043', 'status' => InvoiceStatus::Cancelled]);
        $this->assertNull(app(InvoiceDocument::class)->data($cancelled)['qr']);
        $this->actingAs($this->user)->get(route('invoices.preview', $cancelled))->assertSee('CANCELLED');
    }

    public function test_no_upi_id_means_no_payment_card(): void
    {
        $company = app(CompanySettings::class);
        $company->upiId = null;
        $company->save();

        $this->actingAs($this->user)->get(route('invoices.preview', $this->invoice()))
            ->assertOk()
            ->assertDontSee('Payment Details');
    }

    public function test_package_lines_show_their_inclusion(): void
    {
        $this->actingAs($this->user)->get(route('invoices.preview', $this->invoice()))
            ->assertSee('Included')
            ->assertSee('Not included')
            ->assertSee('Project Total (Package)')
            ->assertSee('Annual Domain Renewal');
    }

    public function test_the_signatory_block_appears_only_with_a_signature(): void
    {
        $invoice = $this->invoice(['template' => 'classic']);

        $this->actingAs($this->user)->get(route('invoices.preview', $invoice))
            ->assertOk()
            ->assertDontSee('Authorised signatory');

        // A real file where the app keeps uploads (storage/, which survives app updates).
        $file = \App\Support\Uploads::path('uploads/company/signature-test.png');
        @mkdir(dirname($file), 0o777, true);
        imagepng(imagecreatetruecolor(60, 20), $file);

        $company = app(CompanySettings::class);
        $company->signaturePath = 'uploads/company/signature-test.png';
        $company->save();

        $this->actingAs($this->user)->get(route('invoices.preview', $invoice))
            ->assertSee('Authorised signatory')
            ->assertSee('data:image/png;base64,', false);

        @unlink($file);
    }

    public function test_the_receipt_is_a_narrow_strip(): void
    {
        $pdf = app(InvoiceDocument::class)->pdf($this->invoice(['template' => 'receipt']));
        $paper = $pdf->getDomPDF()->getPaperSize();

        $this->assertEqualsWithDelta(80 * 2.8346, $paper[2], 0.1);
        $this->assertGreaterThan($paper[2], $paper[3]);
    }

    public function test_the_record_page_frames_the_preview(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(route('invoices.preview', $invoice), false)
            ->assertSee('Send on WhatsApp')
            ->assertSee('Save PDF');
    }

    public function test_guests_cannot_fetch_documents(): void
    {
        $invoice = $this->invoice();

        $this->post(route('invoices.pdf-file', $invoice))->assertRedirect(route('login'));
        $this->get(route('invoices.preview', $invoice))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function invoice(array $overrides = []): Invoice
    {
        $invoice = Invoice::factory()->create(array_merge([
            'number' => 'INV-0042',
            'status' => InvoiceStatus::Sent,
            'pricing_mode' => PricingMode::Package,
            'template' => 'package',
            'customer_name' => 'Rasi',
            'customer_business' => '5 Star Sales and Service',
            'payment_terms' => 'On completion',
            'package_total' => '3599.00',
            'subtotal' => '3599.00',
            'total' => '3599.00',
            'callout' => ['title' => 'Annual Domain Renewal', 'amount' => '899', 'suffix' => '/ year'],
            'terms' => "Scope as agreed\nChanges billed separately",
        ], $overrides));

        $invoice->items()->createMany([
            ['position' => 1, 'description' => 'Website design', 'details' => "Responsive\nContact form", 'amount' => 0, 'inclusion' => Inclusion::Included],
            ['position' => 2, 'description' => 'Paid ads', 'amount' => 0, 'inclusion' => Inclusion::Excluded],
        ]);

        return $invoice;
    }
}
