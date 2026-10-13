{{--
    Receipt design — 80 mm wide, for a shop counter. Reads like a till receipt on a phone and
    prints on a thermal roll. The PDF page height is estimated from the content
    (InvoiceDocument::receiptHeightMm) so a short bill is a short strip.
--}}
@extends('documents.layout')

@php
    $package = $invoice->isPackage();
    $terms = $invoice->termsList();
    $c = $invoice->callout ?? [];
@endphp

@section('page')
    @page { margin: {{ $forPdf ? '5mm 5mm' : '0' }}; }
@endsection

@section('styles')
    body { color: #111; font-size: 8.4pt; line-height: 1.35; }
    .sheet { width: {{ $forPdf ? 'auto' : '80mm' }}; }
    .screen .sheet { padding: 6mm 5mm; box-sizing: border-box; }
    .center { text-align: center; }
    .logo { height: 34px; max-width: 120px; }
    .name { font-size: 11pt; }
    .muted { color: #555; }
    .dash { border-top: 1px dashed #999; margin: 3mm 0; }
    table.lines td { padding: 2px 0; }
    table.lines .r { text-align: right; white-space: nowrap; padding-left: 4px; }
    .sub { color: #666; font-size: 7.4pt; }
    .tot td { padding: 1px 0; }
    .tot td.v { text-align: right; }
    .grand td { font-size: 11pt; padding-top: 3px; }
    .qr img { width: 34mm; height: 34mm; }
@endsection

@section('document')
<div class="sheet">
    <div class="center">
        @if ($logo)<img src="{{ $logo }}" class="logo" alt=""><br>@endif
        <div class="name xbold">{{ $company->name }}</div>
        <div class="muted">
            @foreach ($company->addressLines() as $line){{ $line }}<br>@endforeach
            @if ($company->phone){{ $company->phone }}<br>@endif
            @if ($company->taxId)GSTIN {{ $company->taxId }}@endif
        </div>
    </div>

    <div class="dash"></div>
    <table>
        <tr><td class="semi">{{ $invoice->number }}</td><td class="right">{{ $invoice->invoice_date->format('d/m/Y') }}</td></tr>
        @if ($invoice->customer_name || $invoice->customer_business)
            <tr><td colspan="2">{{ $invoice->customer_name ?: $invoice->customer_business }}@if ($invoice->customer_phone) · {{ $invoice->customer_phone }}@endif</td></tr>
        @endif
    </table>
    @if ($isPaid || $isCancelled || $isDraft || $isPartial)<div class="center" style="margin-top: 2mm">@include('documents.partials.status-stamp')</div>@endif
    <div class="dash"></div>

    <table class="lines">
        @foreach ($invoice->items as $item)
            <tr>
                <td>
                    <span class="semi">{{ $item->description }}</span>
                    @if ($item->detailLines())<div class="sub">{{ implode(', ', $item->detailLines()) }}</div>@endif
                    @if (! $package && $invoice->show_quantity)
                        <div class="sub">{{ $item->quantityForDisplay() }} × {{ $money($item->rate) }}</div>
                    @endif
                </td>
                <td class="r">
                    @if ($package)
                        {{ $item->inclusion === \App\Enums\Inclusion::Excluded ? '×' : '✓' }}
                    @else
                        {{ $money($item->amount) }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <div class="dash"></div>
    <table class="tot">
        @if ((float) $invoice->discount_amount > 0 || (float) $invoice->tax_amount > 0 || $package)
            <tr><td>{{ $package ? 'Package' : 'Subtotal' }}</td><td class="v">{{ $money($invoice->subtotal) }}</td></tr>
        @endif
        @if ((float) $invoice->discount_amount > 0)
            <tr><td>Discount</td><td class="v">&minus; {{ $money($invoice->discount_amount) }}</td></tr>
        @endif
        @if ((float) $invoice->tax_amount > 0)
            <tr><td>Tax {{ rtrim(rtrim((string) $invoice->tax_percent, '0'), '.') }}%</td><td class="v">{{ $money($invoice->tax_amount) }}</td></tr>
        @endif
        <tr class="grand"><td class="xbold">{{ $isPaid ? 'PAID' : 'TOTAL' }}</td><td class="v xbold">{{ $money($invoice->total) }}</td></tr>
        @if ($isPartial)
            <tr><td>Paid</td><td class="v">&minus; {{ $money($invoice->amount_paid) }}</td></tr>
            <tr><td class="semi">Balance</td><td class="v semi">{{ $money($balance) }}</td></tr>
        @endif
    </table>
    <div class="sub" style="margin-top: 1mm">{{ $words }}</div>

    @if ($invoice->hasCallout())
        <div class="dash"></div>
        <div><span class="semi">{{ $c['title'] }}</span>@if (! empty($c['amount'])) — {{ $short($c['amount']) }} {{ $c['suffix'] ?? '' }}@endif</div>
        @if (! empty($c['text']) || ! empty($c['meta']))<div class="sub">{{ $c['text'] ?? '' }} {{ $c['meta'] ?? '' }}</div>@endif
    @endif

    @if ($qr)
        <div class="dash"></div>
        <div class="center qr">
            <div class="semi">Scan to pay {{ $short($balance) }}</div>
            <img src="{{ $qr }}" alt="UPI QR">
            <div class="sub">{{ $company->upiId }}</div>
        </div>
    @endif

    @if ($terms || $invoice->notes)
        <div class="dash"></div>
        @foreach ($terms as $term)<div class="sub">• {{ $term }}</div>@endforeach
        @if ($invoice->notes)<div class="sub">{!! nl2br(e($invoice->notes)) !!}</div>@endif
    @endif

    @if ($invoice->footer_note)
        <div class="dash"></div>
        <div class="center semi">{{ $invoice->footer_note }}</div>
    @endif
</div>
@endsection
