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
        $pdf = Pdf::loadHTML($this->html($invoice, true));

        if ($this->template($invoice) === InvoiceTemplate::Receipt) {
            // 80 mm till-roll width; the height grows with the number of lines so a short bill is
            // not a long strip of white paper.
            $pdf->setPaper([0, 0, 80 * 2.8346, $this->receiptHeightMm($invoice) * 2.8346]);
        } else {
            $pdf->setPaper('a4');
        }

        return $pdf;
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

        $payable = $invoice->status->isPayable() && (float) $invoice->total > 0;
        $upiPayload = $payable ? $this->upi->payload($invoice->total, $invoice->number) : null;

        return [
            'invoice' => $invoice,
            'company' => $this->company,
            'forPdf' => $forPdf,
            'fontUrl' => fn (string $file): string => $forPdf
                ? public_path('assets/fonts/inter/' . $file)
                : asset('assets/fonts/inter/' . $file),
            'logo' => $this->logoDataUri(),
            'words' => $this->words->convert($invoice->total, AmountInWords::currencyWordFor($invoice->currency_symbol)),
            'money' => fn ($amount): string => Money::format($amount, $invoice->currency_symbol),
            'short' => fn ($amount): string => Money::short($amount, $invoice->currency_symbol),
            'isPaid' => $invoice->status === InvoiceStatus::Paid,
            'isCancelled' => $invoice->status === InvoiceStatus::Cancelled,
            'isDraft' => $invoice->status === InvoiceStatus::Draft,
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

    private function logoDataUri(): ?string
    {
        $file = $this->company->logoFile();

        if ($file === null) {
            return null;
        }

        $mime = mime_content_type($file) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
    }

    /**
     * A generous estimate of a receipt's printed length. Over-estimating leaves a little blank
     * paper at the end; under-estimating would push the last lines onto a second "page".
     */
    private function receiptHeightMm(Invoice $invoice): float
    {
        $height = 150.0; // header, totals, footer

        foreach ($invoice->items as $item) {
            $height += 9 + 4 * count($item->detailLines());
        }

        $height += 5 * count($invoice->termsList());
        $height += $invoice->hasCallout() ? 18 : 0;
        $height += $invoice->notes ? 12 : 0;
        $height += ($invoice->status->isPayable() && $this->company->upiId) ? 55 : 0;

        return $height;
    }
}
