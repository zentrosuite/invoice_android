{{--
    Classic design — a traditional bordered invoice for shops and traders: ruled table, totals
    box, amount in words, payment QR, and an authorised-signatory block when a signature is on file.
--}}
@extends('documents.layout')

@php
    $ink = '#1f2937';
    $rule = '#9ca3af';
    $package = $invoice->isPackage();
    $terms = $invoice->termsList();
    $c = $invoice->callout ?? [];
@endphp

@section('page')
    @page { margin: {{ $forPdf ? '10mm 12mm 10mm' : '0' }}; }
@endsection

@section('styles')
    body { color: {{ $ink }}; font-size: 9pt; }
    .sheet { width: {{ $forPdf ? 'auto' : '210mm' }}; }
    .screen .sheet { padding: 10mm 12mm; box-sizing: border-box; min-height: 297mm; }

    .co-name { font-size: 16pt; color: #111827; line-height: 1.2; }
    .co-line { font-size: 8.4pt; color: #4b5563; }
    .logo { height: 56px; max-width: 160px; }
    .doc-title { font-size: 20pt; letter-spacing: 3px; color: #111827; text-align: right; }
    .doc-meta { margin-top: 6px; width: auto; margin-left: auto; font-size: 8.8pt; }
    .doc-meta td { padding: 1px 0 1px 12px; text-align: right; }
    .doc-meta td.k { color: #6b7280; }

    .rule { border-top: 2px solid #111827; margin: 4mm 0 0; }
    .box { border: 1px solid {{ $rule }}; border-top: 0; }
    .box td { padding: 8px 10px; }
    .box td + td { border-left: 1px solid {{ $rule }}; }
    .cap { font-size: 7.4pt; letter-spacing: 1.2px; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; }
    .party { font-size: 10.5pt; color: #111827; }

    table.lines { margin-top: 4mm; border: 1px solid {{ $rule }}; }
    table.lines th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; letter-spacing: .6px; color: #374151; padding: 6px 8px; border: 1px solid {{ $rule }}; font-weight: bold; }
    table.lines td { padding: 4px 8px; border-left: 1px solid {{ $rule }}; border-right: 1px solid {{ $rule }}; border-bottom: 1px solid #e5e7eb; }
    table.lines .r { text-align: right; }
    table.lines .c { text-align: center; }
    .sub { font-size: 8pt; color: #6b7280; }

    .totals { width: 80mm; margin-left: auto; border: 1px solid {{ $rule }}; border-top: 0; }
    .totals td { padding: 5px 8px; }
    .totals td.v { text-align: right; }
    .totals tr.grand td { background: #111827; color: #fff; font-size: 11pt; }
    .words { border: 1px solid {{ $rule }}; border-top: 0; padding: 6px 8px; font-size: 8.6pt; }

    .callout { margin-top: 4mm; border: 1px dashed {{ $rule }}; padding: 7px 10px; }
    .terms { font-size: 8.2pt; color: #374151; }
    .terms li { margin-left: 12px; margin-bottom: 1px; }
    .qr-box { border: 1px solid {{ $rule }}; padding: 6px; text-align: center; }
    .qr-box img { width: 26mm; height: 26mm; }
    .sign { font-size: 8.6pt; text-align: right; }
    .sign-img { display: block; height: 14mm; max-width: 50mm; margin: 1mm 0 0 auto; }
    .sign-line { border-top: 1px solid {{ $rule }}; padding-top: 3px; width: 55mm; margin-left: auto; text-align: center; }
    .foot { font-size: 9pt; color: #374151; vertical-align: bottom; }
@endsection

@section('document')
<div class="sheet">
    <table>
        <tr>
            <td>
                <table style="width: auto">
                    <tr>
                        @if ($logo)<td style="padding-right: 12px; vertical-align: middle"><img src="{{ $logo }}" class="logo" alt=""></td>@endif
                        <td style="vertical-align: middle">
                            <div class="co-name xbold">{{ $company->name }}</div>
                            @foreach ($company->addressLines() as $line)<div class="co-line">{{ $line }}</div>@endforeach
                            <div class="co-line">
                                {{ collect([$company->phone, $company->email])->filter()->implode('  ·  ') }}
                            </div>
                            @if ($company->taxId)<div class="co-line">GSTIN: <span class="semi">{{ $company->taxId }}</span></div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 42%">
                <div class="doc-title xbold">{{ $invoice->tax_percent > 0 ? 'TAX INVOICE' : 'INVOICE' }}</div>
                <table class="doc-meta">
                    <tr><td class="k">Invoice No.</td><td class="semi">{{ $invoice->number }}</td></tr>
                    <tr><td class="k">Date</td><td class="semi">{{ $invoice->invoice_date->format('d/m/Y') }}</td></tr>
                    @if ($invoice->due_date)<tr><td class="k">Due</td><td class="semi">{{ $invoice->due_date->format('d/m/Y') }}</td></tr>@endif
                </table>
                @if ($isPaid || $isCancelled || $isDraft || $isPartial)<div class="right" style="margin-top: 6px">@include('documents.partials.status-stamp')</div>@endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>
    <table class="box">
        <tr>
            <td style="width: 58%">
                <div class="cap semi">Bill to</div>
                @if ($invoice->customer_name || $invoice->customer_business)
                    <div class="party semi">{{ $invoice->customer_business ?: $invoice->customer_name }}</div>
                    @if ($invoice->customer_business && $invoice->customer_name)<div>{{ $invoice->customer_name }}</div>@endif
                    @if ($invoice->customer_address)<div class="co-line">{{ str_replace("\n", ', ', $invoice->customer_address) }}</div>@endif
                    @if ($invoice->customer_phone)<div class="co-line">{{ $invoice->customer_phone }}</div>@endif
                    @if ($invoice->customer_tax_id)<div class="co-line">GSTIN: {{ $invoice->customer_tax_id }}</div>@endif
                @else
                    <div class="party semi">Cash / walk-in customer</div>
                @endif
            </td>
            <td>
                <div class="cap semi">Payment</div>
                <div>{{ $invoice->payment_terms ?: ($invoice->due_date ? 'Due by ' . $invoice->due_date->format('d/m/Y') : 'Due on receipt') }}</div>
                @if ($company->upiId)<div class="co-line">UPI: {{ $company->upiId }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
        <tr>
            <th class="c" style="width: 26px">#</th>
            <th>{{ $invoice->item_label }}</th>
            @if ($package)
                <th class="c" style="width: 90px">Status</th>
            @else
                @if ($invoice->show_quantity)
                    <th class="r" style="width: 50px">{{ $invoice->quantity_label }}</th>
                    <th class="r" style="width: 80px">{{ $rateHeading }}</th>
                @endif
                <th class="r" style="width: 90px">Amount</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td class="c">{{ $item->position }}</td>
                <td>
                    <div class="semi">{{ $item->description }}</div>
                    @if ($item->detailLines())<div class="sub">{{ implode('  ·  ', $item->detailLines()) }}</div>@endif
                </td>
                @if ($package)
                    <td class="c">{{ $item->inclusion === \App\Enums\Inclusion::Excluded ? '× Not included' : '✓ Included' }}</td>
                @else
                    @if ($invoice->show_quantity)
                        <td class="r">{{ $item->quantityForDisplay() }}</td>
                        <td class="r nowrap">{{ $money($item->rate) }}</td>
                    @endif
                    <td class="r nowrap semi">{{ $money($item->amount) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="page-break-inside: avoid">
        <tr>
            <td style="vertical-align: top">
                <div class="words"><span class="cap semi">In words:</span> <span class="semi">{{ $words }}</span></div>
            </td>
            <td style="width: 80mm">
                <table class="totals">
                    <tr><td>{{ $package ? 'Package total' : 'Subtotal' }}</td><td class="v">{{ $money($invoice->subtotal) }}</td></tr>
                    @if ((float) $invoice->discount_amount > 0)
                        <tr><td>Discount{{ $invoice->discount_type === 'percent' ? ' (' . rtrim(rtrim((string) $invoice->discount_value, '0'), '.') . '%)' : '' }}</td><td class="v">&minus; {{ $money($invoice->discount_amount) }}</td></tr>
                    @endif
                    @if ((float) $invoice->tax_amount > 0)
                        <tr><td>Tax ({{ rtrim(rtrim((string) $invoice->tax_percent, '0'), '.') }}%)</td><td class="v">{{ $money($invoice->tax_amount) }}</td></tr>
                    @endif
                    <tr class="grand"><td class="semi">{{ $isPaid ? 'Total paid' : 'Total' }}</td><td class="v xbold">{{ $money($invoice->total) }}</td></tr>
                    @if ($isPartial)
                        @if ((float) $advance > 0)<tr><td>Advance adjusted</td><td class="v">&minus; {{ $money($advance) }}</td></tr>@endif
                        @if ((float) $paidDirect > 0)<tr><td>Paid</td><td class="v">&minus; {{ $money($paidDirect) }}</td></tr>@endif
                        <tr><td class="semi">Balance due</td><td class="v xbold">{{ $money($balance) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($invoice->hasCallout())
        <div class="callout">
            <table>
                <tr>
                    <td><span class="semi">{{ $c['title'] }}</span>@if (! empty($c['text']))<div class="sub">{{ $c['text'] }}</div>@endif</td>
                    @if (! empty($c['amount']))
                        <td class="right nowrap"><span class="xbold">{{ $short($c['amount']) }}</span> {{ $c['suffix'] ?? '' }}@if (! empty($c['meta']))<div class="sub">{{ $c['meta'] }}</div>@endif</td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    <table style="margin-top: 4mm; page-break-inside: avoid">
        <tr>
            <td style="padding-right: 6mm">
                @if ($terms)
                    <div class="cap semi">Terms &amp; conditions</div>
                    <ol class="terms">@foreach ($terms as $term)<li>{{ $term }}</li>@endforeach</ol>
                @endif
                @if ($invoice->notes)<div class="terms" style="margin-top: 3mm">{!! nl2br(e($invoice->notes)) !!}</div>@endif
            </td>
            @if ($qr)
                <td style="width: 38mm">
                    <div class="qr-box">
                        <div class="cap semi">Scan to pay</div>
                        <img src="{{ $qr }}" alt="UPI QR">
                        <div class="semi" style="font-size: 9pt">{{ $short($balance) }}</div>
                        <div class="sub">{{ $company->upiId }}</div>
                    </div>
                </td>
            @endif
        </tr>
    </table>

    <table style="margin-top: 5mm; page-break-inside: avoid">
        <tr>
            <td class="foot semi">{{ $invoice->footer_note }}</td>
            {{-- Only with a signature on file: an empty signatory line looks unfinished. --}}
            @if ($signature)
                <td class="sign" style="width: 60mm">
                    For <span class="semi">{{ $company->name }}</span>
                    <img src="{{ $signature }}" class="sign-img" alt="">
                    <div class="sign-line">Authorised signatory</div>
                </td>
            @endif
        </tr>
    </table>
</div>
@endsection
