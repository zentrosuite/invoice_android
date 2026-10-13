<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoices\InvoiceDocument;
use App\Settings\CompanySettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * What the customer sees from the link in their WhatsApp message. No login: the unguessable
 * token in the URL is the permission, and resetting it on the invoice page revokes the link.
 */
final class PublicInvoiceController extends Controller
{
    public function show(string $token, InvoiceDocument $document, CompanySettings $company): View
    {
        $invoice = $this->find($token);

        // Recorded at most hourly, so a customer refreshing the page does not keep writing.
        if ($invoice->viewed_at === null || $invoice->viewed_at->lt(now()->subHour())) {
            $invoice->viewed_at = now();
            $invoice->saveQuietly();
        }

        $data = $document->data($invoice);

        return view('public.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'token' => $token,
            'upiLink' => $data['upiLink'],
            'payable' => $data['payable'],
        ]);
    }

    public function document(string $token, InvoiceDocument $document): Response
    {
        return response($document->html($this->find($token)))
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pdf(string $token, InvoiceDocument $document): Response
    {
        $invoice = $this->find($token);

        return $document->pdf($invoice)->download($document->filename($invoice));
    }

    private function find(string $token): Invoice
    {
        // Shape check first: a malformed token never reaches the database.
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);

        return Invoice::query()->where('share_token', $token)->firstOrFail();
    }
}
