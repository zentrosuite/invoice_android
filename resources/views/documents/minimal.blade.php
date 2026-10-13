{{--
    Minimal design — black on white, hairlines and space. Prints well on any printer.
--}}
@extends('documents.layout')

@php
    $package = $invoice->isPackage();
    $terms = $invoice->termsList();
    $c = $invoice->callout ?? [];
@endphp

@section('page')
    @page { margin: {{ $forPdf ? '18mm 18mm 18mm' : '0' }}; }
@endsection

@section('styles')
    body { color: #111; font-size: 9.2pt; }
    .sheet { width: {{ $forPdf ? 'auto' : '210mm' }}; }
    .screen .sheet { padding: 18mm; box-sizing: border-box; min-height: 297mm; }
    .muted { color: #666; }
    .cap { font-size: 7.4pt; letter-spacing: 1.4px; text-transform: uppercase; color: #777; margin-bottom: 4px; }
    .name { font-size: 13pt; }
    .title { font-size: 26pt; letter-spacing: -0.5px; text-align: right; }
    .logo { height: 42px; max-width: 140px; margin-bottom: 6px; }
    .meta td { padding-right: 10mm; }
    table.lines { margin-top: 7mm; }
    table.lines th { font-size: 7.4pt; letter-spacing: 1.2px; text-transform: uppercase; color: #777; padding: 0 0 6px; border-bottom: 1px solid #111; font-weight: normal; }
    table.lines td { padding: 5px 0; border-bottom: 1px solid #e5e5e5; }
    table.lines .r { text-align: right; padding-left: 8px; }
    .sub { color: #777; font-size: 8.2pt; }
    .totals { width: 70mm; margin-left: auto; margin-top: 5mm; }
    .totals td { padding: 3px 0; }
    .totals td.v { text-align: right; }
    .totals tr.grand td { border-top: 1px solid #111; padding-top: 7px; font-size: 13pt; }
    .callout { margin-top: 5mm; border-left: 2px solid #111; padding: 2px 0 2px 10px; }
    .terms li { margin-left: 12px; font-size: 8.2pt; color: #444; }
    .qr img { width: 26mm; height: 26mm; }
@endsection

@section('document')
<div class="sheet">
    <table>
        <tr>
            <td>
                @if ($logo)<img src="{{ $logo }}" class="logo" alt=""><br>@endif
                <div class="name semi">{{ $company->name }}</div>
                <div class="muted" style="font-size: 8.4pt">
                    @foreach ($company->addressLines() as $line){{ $line }}<br>@endforeach
                    {{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}
                    @if ($company->taxId)<br>GSTIN {{ $company->taxId }}@endif
                </div>
            </td>
            <td style="width: 40%">
                <div class="title semi">Invoice</div>
                <div class="right muted">{{ $invoice->number }}</div>
                @if ($isPaid || $isCancelled || $isDraft || $isPartial)<div class="right" style="margin-top: 6px">@include('documents.partials.status-stamp')</div>@endif
            </td>
        </tr>
    </table>

    <table class="meta" style="margin-top: 7mm; width: auto">
        <tr>
            <td style="width: 70mm">
                <div class="cap">Billed to</div>
                @if ($invoice->customer_name || $invoice->customer_business)
                    <div class="semi">{{ $invoice->customer_name ?: $invoice->customer_business }}</div>
                    @if ($invoice->customer_name && $invoice->customer_business)<div>{{ $invoice->customer_business }}</div>@endif
                    <div class="muted" style="font-size: 8.4pt">
                        @foreach (\App\Models\Invoice::lines($invoice->customer_address) as $line){{ $line }}<br>@endforeach
                        {{ $invoice->customer_phone }}
                    </div>
                @else
                    <div class="semi">Walk-in customer</div>
                @endif
            </td>
            <td><div class="cap">Date</div>{{ $invoice->invoice_date->format('d M Y') }}</td>
            @if ($invoice->due_date)<td><div class="cap">Due</div>{{ $invoice->due_date->format('d M Y') }}</td>@endif
            @if ($invoice->payment_terms)<td><div class="cap">Terms</div>{{ $invoice->payment_terms }}</td>@endif
        </tr>
    </table>

    <table class="lines">
        <thead>
        <tr>
            <th>{{ $invoice->item_label }}</th>
            @if ($package)
                <th class="r"></th>
            @else
                @if ($invoice->show_quantity)
                    <th class="r">{{ $invoice->quantity_label }}</th>
                    <th class="r">{{ $rateHeading }}</th>
                @endif
                <th class="r">Amount</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>
                    {{ $item->description }}
                    @if ($item->detailLines())<div class="sub">{{ implode(' · ', $item->detailLines()) }}</div>@endif
                </td>
                @if ($package)
                    <td class="r nowrap muted">{{ $item->inclusion === \App\Enums\Inclusion::Excluded ? 'Not included' : 'Included' }}</td>
                @else
                    @if ($invoice->show_quantity)
                        <td class="r">{{ $item->quantityForDisplay() }}</td>
                        <td class="r nowrap">{{ $money($item->rate) }}</td>
                    @endif
                    <td class="r nowrap">{{ $money($item->amount) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals" style="page-break-inside: avoid">
        <tr><td class="muted">{{ $package ? 'Package' : 'Subtotal' }}</td><td class="v">{{ $money($invoice->subtotal) }}</td></tr>
        @if ((float) $invoice->discount_amount > 0)
            <tr><td class="muted">Discount</td><td class="v">&minus; {{ $money($invoice->discount_amount) }}</td></tr>
        @endif
        @if ((float) $invoice->tax_amount > 0)
            <tr><td class="muted">Tax {{ rtrim(rtrim((string) $invoice->tax_percent, '0'), '.') }}%</td><td class="v">{{ $money($invoice->tax_amount) }}</td></tr>
        @endif
        <tr class="grand"><td class="semi">{{ $isPaid ? 'Paid' : 'Total' }}</td><td class="v semi">{{ $money($invoice->total) }}</td></tr>
        @if ($isPartial)
            <tr><td class="muted">Paid</td><td class="v">&minus; {{ $money($invoice->amount_paid) }}</td></tr>
            <tr><td class="semi">Balance due</td><td class="v semi">{{ $money($balance) }}</td></tr>
        @endif
    </table>
    <div class="right muted" style="font-size: 8pt; margin-top: 2px">{{ $words }}</div>

    @if ($invoice->hasCallout())
        <div class="callout">
            <span class="semi">{{ $c['title'] }}</span>@if (! empty($c['amount'])) — {{ $short($c['amount']) }} {{ $c['suffix'] ?? '' }}@endif
            @if (! empty($c['text']) || ! empty($c['meta']))<div class="sub">{{ $c['text'] ?? '' }} {{ $c['meta'] ?? '' }}</div>@endif
        </div>
    @endif

    <table style="margin-top: 6mm; page-break-inside: avoid">
        <tr>
            <td style="padding-right: 8mm">
                @if ($terms)
                    <div class="cap">Terms</div>
                    <ul class="terms">@foreach ($terms as $term)<li>{{ $term }}</li>@endforeach</ul>
                @endif
                @if ($invoice->notes)<div style="font-size: 8.4pt; margin-top: 3mm">{!! nl2br(e($invoice->notes)) !!}</div>@endif
                @if ($invoice->footer_note)<div class="semi" style="margin-top: 6mm">{{ $invoice->footer_note }}</div>@endif
            </td>
            @if ($qr)
                <td style="width: 32mm" class="qr center">
                    <img src="{{ $qr }}" alt="UPI QR">
                    <div class="sub">Pay {{ $short($balance) }} by UPI<br>{{ $company->upiId }}</div>
                </td>
            @endif
        </tr>
    </table>
</div>
@endsection
