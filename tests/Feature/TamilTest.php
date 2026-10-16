<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TamilTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();
    }

    public function test_every_font_stack_in_the_designs_falls_back_to_tamil(): void
    {
        foreach (['classic', 'modern', 'minimal', 'receipt', 'package'] as $template) {
            $html = app(InvoiceDocument::class)->html($this->invoice($template), true);

            $this->assertStringContainsString("font-family: 'Noto Sans Tamil'", $html, $template);
            $this->assertFileExists(public_path('assets/fonts/noto-tamil/NotoSansTamil-Regular.ttf'));
            $this->assertStringContainsString('சுரேஷ் கண்ணன்', $html, $template);
            // Every Inter stack names a Tamil family right after it.
            $this->assertDoesNotMatchRegularExpression("/font-family: 'Inter[^']*', 'Inter'/", $html, $template);
        }
    }

    public function test_the_print_file_is_html_with_local_fonts_for_the_phone_engine(): void
    {
        $invoice = $this->invoice('classic');

        $job = $this->actingAs($this->user)->postJson(route('invoices.print-file', $invoice))
            ->assertOk()
            ->assertJson(['widthMm' => 210, 'heightMm' => 297, 'mime' => 'application/pdf'])
            ->json();

        $this->assertFileExists($job['htmlPath']);
        $html = file_get_contents($job['htmlPath']);
        $this->assertStringContainsString('file://' . public_path('assets/fonts/noto-tamil/NotoSansTamil-Regular.ttf'), $html);
        $this->assertStringContainsString('சுரேஷ் கண்ணன்', $html);
        $this->assertStringEndsWith('.pdf', $job['pdfPath']);
    }

    public function test_a_receipt_is_printed_as_long_as_its_content(): void
    {
        $this->actingAs($this->user)->postJson(route('invoices.print-file', $this->invoice('receipt')))
            ->assertJson(['widthMm' => 80, 'heightMm' => 0]);
    }

    public function test_the_package_footer_stays_inside_the_page_for_the_phone_engine(): void
    {
        $html = app(InvoiceDocument::class)->printHtml($this->invoice('package'));

        $this->assertStringContainsString('class="print-flow"', $html);
        $this->assertStringContainsString('@page { margin: 12mm 0 0 0; }', $html);
        $this->assertStringNotContainsString('bottom: -22mm', $html);
    }

    public function test_the_app_screens_carry_tamil_under_the_theme_fonts(): void
    {
        $css = file_get_contents(public_path('assets/app/app.css'));

        $this->assertStringContainsString("font-family: 'Public Sans'", $css);
        $this->assertStringContainsString('unicode-range: U+0B80-0BFF', $css);
    }

    private function invoice(string $template): Invoice
    {
        $package = $template === 'package';

        return Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'template' => $template,
            'pricing_mode' => $package ? 'package' : 'itemised',
            'package_total' => $package ? '3599.00' : null,
            'customer_name' => 'சுரேஷ் கண்ணன்',
            'total' => '3599.00',
            'subtotal' => '3599.00',
        ]);
    }
}
