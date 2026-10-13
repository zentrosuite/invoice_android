<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Http\Requests\InvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceDocument;
use App\Services\Invoices\InvoiceService;
use App\Services\Invoices\InvoiceSharing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    public function index(Request $request): View
    {
        $status = InvoiceStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $counts = Invoice::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('invoices.index', [
            'invoices' => Invoice::query()
                ->search($search)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'search' => $search,
            'counts' => $counts,
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $request->integer('customer') > 0 ? Customer::query()->find($request->integer('customer')) : null;

        return $this->builder($this->invoices->blank($customer), $customer);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($request->validated());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', $invoice->status === InvoiceStatus::Draft
                ? "Draft {$invoice->number} saved."
                : "Invoice {$invoice->number} saved.");
    }

    public function show(Invoice $invoice, InvoiceSharing $sharing): View
    {
        // The link is made here, not on click: a WhatsApp link opened after a round trip to the
        // server is blocked as a popup, so the button must be a plain link ready on the page.
        return view('invoices.show', [
            'invoice' => $invoice,
            'shareUrl' => $sharing->publicUrl($invoice),
            'whatsappUrl' => $sharing->whatsappUrl($invoice),
            'shareText' => $sharing->message($invoice),
        ]);
    }

    /**
     * Called (fire-and-forget) when a share button is pressed. A draft becomes unpaid.
     */
    public function shared(Invoice $invoice, InvoiceSharing $sharing): JsonResponse
    {
        $sharing->markShared($invoice);

        return response()->json(['status' => $invoice->status->value]);
    }

    public function resetLink(Invoice $invoice, InvoiceSharing $sharing): RedirectResponse
    {
        $sharing->resetToken($invoice);

        return back()->with('success', 'New link created. The old one no longer opens this invoice.');
    }

    /**
     * The invoice in its design, as a standalone page — what the record page frames, and what
     * "open full size" shows. The same markup the PDF is made from.
     */
    public function preview(Invoice $invoice, InvoiceDocument $document): Response
    {
        return response($document->html($invoice))
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    public function pdf(Request $request, Invoice $invoice, InvoiceDocument $document): Response
    {
        $pdf = $document->pdf($invoice);
        $filename = $document->filename($invoice);

        // ?inline=1 opens in the browser's viewer (for printing); otherwise it downloads.
        return $request->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    public function edit(Invoice $invoice): View
    {
        $invoice->load('items', 'customer');

        return $this->builder($invoice, $invoice->customer);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} updated.");
    }

    /**
     * Only drafts can be deleted. A number a customer has seen is cancelled, never removed, so
     * the series has no unexplained gaps.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return back()->with('error', 'Only drafts can be deleted. Cancel this invoice instead.');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', "Draft {$invoice->number} deleted.");
    }

    public function duplicate(Invoice $invoice): RedirectResponse
    {
        $copy = $this->invoices->duplicate($invoice->load('items'));

        return redirect()->route('invoices.edit', $copy)
            ->with('success', "Copied {$invoice->number} to a new draft, {$copy->number}.");
    }

    public function status(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(InvoiceStatus::class)]]);
        $status = InvoiceStatus::from($data['status']);

        $this->invoices->changeStatus($invoice, $status);

        return back()->with('success', match ($status) {
            InvoiceStatus::Paid => "{$invoice->number} marked as paid.",
            InvoiceStatus::Sent => "{$invoice->number} marked as unpaid.",
            InvoiceStatus::Cancelled => "{$invoice->number} cancelled.",
            InvoiceStatus::Draft => "{$invoice->number} moved back to draft.",
        });
    }

    private function builder(Invoice $invoice, ?Customer $customer): View
    {
        return view('invoices.builder', [
            'invoice' => $invoice,
            'customer' => $customer,
            'templates' => InvoiceTemplate::cases(),
        ]);
    }
}
