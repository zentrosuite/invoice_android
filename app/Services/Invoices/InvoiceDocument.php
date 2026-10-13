<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Models\Invoice;
use App\Services\UpiQrService;
use App\Settings\CompanySettings;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Dompdf\Frame;
use Illuminate\Support\Str;

/**
 * Renders an invoice in its design — as a page for the screen, or as a PDF.
 *
 * Both come from the same Blade view so they cannot drift apart. That view is written for
 * dompdf, which understands CSS 2.1 and little else: layout is tables, not flexbox or grid,
 * and every image is embedded as a data URI so nothing depends on a URL resolving.
 */
final readonly class InvoiceDocument
{
    private const PT_PER_MM = 72 / 25.4;

    /** 80 mm till-roll paper. */
    private const RECEIPT_WIDTH_PT = 80 * self::PT_PER_MM;

    public function __construct(
        private CompanySettings $company,
        private UpiQrService $upi,
        private AmountInWords $words,
    ) {}

    public function html(Invoice $invoice, bool $forPdf = false): string
    {
        return view('documents.' . $this->view($invoice), $this->data($invoice, $forPdf))->render();
    }

    public function pdf(Invoice $invoice): DomPdf
    {
        $html = $this->html($invoice, true);

        if ($this->template($invoice) !== InvoiceTemplate::Receipt) {
            return Pdf::loadHTML($html)->setPaper('a4');
        }

        /*
         | A receipt is one 80 mm strip exactly as long as its content. Estimating the length from
         | line counts was wrong whenever text wrapped or a block was taller than assumed: too long
         | left blank paper, too short pushed the last few millimetres onto an empty-looking second
         | page. So it is measured instead — drawn once on a sheet far longer than any receipt, the
         | lowest point any element reaches is found, and it is drawn again on that length.
         */
        $probe = Pdf::loadHTML($html)->setPaper([0, 0, self::RECEIPT_WIDTH_PT, 5000 * self::PT_PER_MM])->getDomPDF();
        $bottom = 0.0;

        // Measured while drawing: dompdf discards the frame tree once render() returns.
        // <html> and <body> are skipped — they stretch to the probe sheet's full length.
        $probe->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame) use (&$bottom): void {
                if (! $frame instanceof Frame || in_array($frame->get_node()->nodeName, ['html', 'body', '#document'], true)) {
                    return;
                }
                $box = $frame->get_border_box();
                $bottom = max($bottom, (float) $box['y'] + (float) $box['h']);
            },
        ]]);
        $probe->render();

        // + the 5 mm page margin below the content, and a hair of slack for rounding.
        $height = $bottom + 5 * self::PT_PER_MM + 2;

        return Pdf::loadHTML($html)->setPaper([0, 0, self::RECEIPT_WIDTH_PT, max($height, 80 * self::PT_PER_MM)]);
    }

    /**
     * "Invoice INV-0001 - Rasi.pdf"
     */
    public function filename(Invoice $invoice): string
    {
        $who = Str::of($invoice->billedTo())->ascii()->replaceMatches('/[^A-Za-z0-9 \-]/', '')->squish()->limit(40, '');

        return trim("Invoice {$invoice->number}" . ($who->isNotEmpty() ? " - {$who}" : '')) . '.pdf';
    }

    /**
     * Everything the design views use.
     *
     * @return array<string, mixed>
     */
    public function data(Invoice $invoice, bool $forPdf = false): array
    {
        $invoice->loadMissing('items');

        // What is still owed: the QR, the "amount due" panel and the pay button all ask for the
        // balance, so a part-paid invoice never asks for money already received.
        $balance = $invoice->balance();
        $payable = $invoice->status->isPayable() && (float) $balance > 0;
        $upiPayload = $payable ? $this->upi->payload($balance, $invoice->number) : null;

        return [
            'invoice' => $invoice,
            'company' => $this->company,
            'forPdf' => $forPdf,
            'fontUrl' => fn (string $file): string => $forPdf
                ? public_path('assets/fonts/inter/' . $file)
                : asset('assets/fonts/inter/' . $file),
            'logo' => $this->dataUri($this->company->logoFile()),
            // Null means: print no signatory block at all.
            'signature' => $this->dataUri($this->company->signatureFile()),
            'words' => $this->words->convert($invoice->total, AmountInWords::currencyWordFor($invoice->currency_symbol)),
            'money' => fn ($amount): string => Money::format($amount, $invoice->currency_symbol),
            'short' => fn ($amount): string => Money::short($amount, $invoice->currency_symbol),
            'isPaid' => $invoice->status === InvoiceStatus::Paid,
            'isCancelled' => $invoice->status === InvoiceStatus::Cancelled,
            'isDraft' => $invoice->status === InvoiceStatus::Draft,
            'isPartial' => $invoice->status === InvoiceStatus::Partial,
            'balance' => $balance,
            'payable' => $payable,
            'qr' => $upiPayload === null ? null : $this->upi->renderPng($upiPayload),
            'upiLink' => $upiPayload,
            'rateHeading' => strcasecmp($invoice->rate_label, 'Amount') === 0 ? 'Rate' : $invoice->rate_label,
        ];
    }

    private function template(Invoice $invoice): InvoiceTemplate
    {
        return $invoice->templateEnum();
    }

    /**
     * Modern is the Package layout with an itemised table, so the two share one view.
     */
    private function view(Invoice $invoice): string
    {
        return match ($this->template($invoice)) {
            InvoiceTemplate::Package, InvoiceTemplate::Modern => 'package',
            default => $this->template($invoice)->value,
        };
    }

    private function dataUri(?string $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $mime = mime_content_type($file) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
    }
}
