{{--
    Package / Modern design — after the 5 Star Sales and Service invoice.

    Package invoices list services as Included / Not included under one package price; the
    same layout with an itemised table is the "Modern" design (documents/modern includes this).
    Sizes and colours are lifted from the reference so the two print the same.
--}}
@extends('documents.layout')

@php
    $navy = '#111633';
    $accent = '#3a44c2';
    $package = $invoice->isPackage();
    $terms = $invoice->termsList();
    $c = $invoice->callout ?? [];

    if ($isPaid) {
        $dueLabel = 'Amount Paid';
        $dueSub = $invoice->paid_at ? 'Paid on ' . $invoice->paid_at->format('d/m/Y') . ' — thank you' : 'Paid — thank you';
    } elseif ($isCancelled) {
        $dueLabel = 'Invoice Total';
        $dueSub = 'This invoice has been cancelled';
    } else {
        $dueLabel = 'Total Amount Due';
        $dueSub = $invoice->payment_terms
            ? 'Payable ' . Str::lcfirst($invoice->payment_terms)
            : ($invoice->due_date ? 'Due by ' . $invoice->due_date->format('d/m/Y') : 'Due on receipt');
        $dueSub .= $qr ? ' via UPI' : '';
    }
@endphp

@section('page')
    @if ($forPdf)
        {{-- Later pages get a top margin; the first runs to the edge for the colour bar. --}}
        @page { margin: 12mm 0 22mm 0; }
        @page :first { margin-top: 0; }
    @else
        @page { margin: 0; }
    @endif
@endsection

@section('styles')
    .sheet { width: 210mm; }

    /*
     | The footer sits at the foot of the page, as in the reference. In the PDF it is fixed inside
     | a reserved bottom margin, so dompdf prints it on every page and flows content above it; on
     | screen it is pinned to the bottom of the sheet.
     */
    @if ($forPdf)
        .page-foot { position: fixed; left: 0; right: 0; bottom: -22mm; height: 22mm; }
    @else
        .sheet { position: relative; min-height: 297mm; padding-bottom: 22mm; box-sizing: border-box; }
        .page-foot { position: absolute; left: 0; right: 0; bottom: 0; height: 22mm; }
    @endif
    .bar-top { height: 5px; background: {{ $navy }}; }
    .bar-top td { height: 5px; padding: 0; }
    .content { padding: 7mm 14mm 0; }

    .brand-name { font-size: 15pt; color: {{ $navy }}; line-height: 1.2; }
    .brand-contact { margin-top: 2px; font-size: 8.4pt; line-height: 1.35; color: #7a7f98; }
    .brand-contact .dot { color: {{ $accent }}; }
    .logo { height: 50px; max-width: 150px; }

    .inv-title { font-size: 25pt; color: {{ $navy }}; letter-spacing: 5px; line-height: 1; text-align: right; }
    .inv-meta { margin-top: 7px; width: auto; margin-left: auto; font-size: 8.8pt; }
    .inv-meta td { padding: 1px 0; text-align: right; }
    .inv-meta td.k { color: #7a7f98; padding-right: 14px; }
    .inv-meta td.v { color: #1f2440; }

    .divider { height: 1px; background: #e4e6ee; margin: 3mm 0; }

    .label { font-size: 7.6pt; letter-spacing: 1.6px; text-transform: uppercase; color: {{ $accent }}; margin-bottom: 5px; }
    .billto { background: #f5f6fa; border-radius: 8px; padding: 9px 15px; }
    .client { font-size: 12pt; color: {{ $navy }}; }
    .biz { font-size: 10pt; color: #1f2440; margin-top: 1px; }
    .kv { margin-top: 5px; font-size: 8.8pt; }
    .kv td { padding: 1px 0; }
    .kv td.k { width: 60px; color: #7a7f98; }
    .kv td.v { color: #1f2440; }

    .due-wrap { position: relative; overflow: hidden; background: {{ $navy }}; border-radius: 8px; padding: 12px 18px; color: #fff; }
    .due-ring { position: absolute; right: -32px; top: -32px; width: 76px; height: 76px; border-radius: 60px; border: 18px solid #2c3283; }
    .due-wrap .label { color: #a9b1f5; margin-bottom: 4px; position: relative; }
    .due-amt { font-size: 25pt; line-height: 1.1; letter-spacing: -0.5px; position: relative; }
    .due-sub { font-size: 8.2pt; color: #c3c7e6; margin-top: 4px; position: relative; }

    table.items { margin-top: 3.5mm; }
    table.items th {
        font-size: 7.6pt; letter-spacing: 1.4px; text-transform: uppercase; color: #7a7f98;
        padding: 0 8px 7px; border-bottom: 1.5px solid {{ $navy }}; font-weight: bold;
    }
    table.items td { padding: 3.5px 8px; border-bottom: 1px solid #e4e6ee; }
    table.items td.num { color: #7a7f98; width: 22px; padding-left: 4px; }
    table.items th.num { padding-left: 4px; }
    table.items .r { text-align: right; }
    table.items td.last, table.items th.last { padding-right: 4px; }
    .item-name { font-size: 9.8pt; color: #1f2440; }
    .item-sub { margin-top: 1px; font-size: 8.2pt; color: #7a7f98; }
    .item-sub .b { color: {{ $accent }}; }
    .tag { font-size: 7.8pt; color: {{ $accent }}; background: #eef0fc; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    .tag-off { color: #7a7f98; background: #f1f2f6; }

    .words-label { font-size: 7.6pt; letter-spacing: 1.6px; text-transform: uppercase; color: #7a7f98; margin-bottom: 3px; }
    .words { font-family: 'Inter SemiBold', 'Inter', sans-serif; font-size: 8.8pt; color: #1f2440; font-style: italic; }
    .total-box { border: 1.5px solid {{ $navy }}; border-radius: 8px; }
    .total-box td { padding: 5px 14px; font-size: 9pt; }
    .total-box td.v { text-align: right; color: #1f2440; }
    .total-box tr.grand td { background: {{ $navy }}; color: #fff; padding: 7px 14px; }
    .total-box tr.grand td.k { font-size: 8pt; letter-spacing: 1.2px; text-transform: uppercase; vertical-align: middle; }
    .total-box tr.grand td.v { font-size: 15pt; color: #fff; }

    .callout { margin-top: 3mm; background: #eef0fc; border-left: 3px solid {{ $accent }}; padding: 7px 14px; }
    .callout td { vertical-align: middle; }
    .callout-ico { width: 30px; height: 30px; border-radius: 15px; background: #fff; text-align: center; line-height: 30px; color: {{ $accent }}; font-size: 13pt; }
    .callout-title { font-size: 9.6pt; color: {{ $navy }}; }
    .callout-text { font-size: 8.6pt; }
    .callout-price { font-size: 14pt; color: {{ $accent }}; white-space: nowrap; }
    .callout-price small { font-size: 8.6pt; color: #3d4260; font-family: 'Inter SemiBold', 'Inter', sans-serif; }
    .callout-meta { font-size: 7.8pt; color: #3d4260; }

    .terms { font-size: 7.9pt; }
    .terms td { padding: 1px 0; line-height: 1.38; }
    .terms td.sq { width: 12px; padding-top: 5px; }
    .terms .sqb { width: 5px; height: 5px; background: #7a80d6; border-radius: 1px; }
    .terms b { color: #1f2440; }
    .notes { font-size: 8.2pt; margin-top: 2.5mm; }

    .pay { background: #f5f6fa; border-radius: 8px; padding: 7px 12px 8px; text-align: center; }
    .pay .label { margin-bottom: 4px; }
    .pay .label { text-align: left; }
    .qr { background: #fff; border: 1px solid #e4e6ee; border-radius: 8px; padding: 3px; width: 24mm; margin: 0 auto; }
    .qr img { width: 24mm; height: 24mm; }
    .scan { margin-top: 4px; font-size: 7.6pt; color: #7a7f98; }
    .scan b { color: {{ $navy }}; }
    .upi { margin-top: 5px; background: #fff; border: 1px solid #e4e6ee; border-radius: 6px; padding: 3px 8px; font-size: 8.4pt; color: #1f2440; }
    .upi-k { font-size: 6.8pt; letter-spacing: 1px; color: #7a7f98; text-transform: uppercase; }

    .footer { height: 15mm; padding: 3.5mm 14mm 0; border-top: 1px solid #e4e6ee; }
    .footer td { vertical-align: bottom; }
    .thanks { font-size: 18pt; color: {{ $navy }}; letter-spacing: -0.3px; line-height: 1; }
    .thanks-sub { font-size: 8.2pt; color: #7a7f98; margin-top: 4px; }
    .foot-name { font-size: 10pt; color: {{ $navy }}; }
    .foot-tag { font-size: 8.2pt; color: #7a7f98; letter-spacing: 0.3px; }
    .bar-bottom td { height: 5px; padding: 0; }
@endsection

@section('document')
{{-- In the PDF the footer is a direct child of <body> and first in the source: dompdf only prints
     a fixed element reliably at that level, and only on pages from where it appears onwards. --}}
@if ($forPdf)
    @include('documents.partials.package-footer')
@endif
<div class="sheet">
    <table class="bar-top"><tr><td style="background: {{ $navy }}; width: 60%"></td><td style="background: #232a7a; width: 25%"></td><td style="background: {{ $accent }}"></td></tr></table>

    <div class="content">
        {{-- Header --}}
        <table>
            <tr>
                <td>
                    <table style="width: auto">
                        <tr>
                            @if ($logo)
                                <td style="padding-right: 14px; vertical-align: middle"><img src="{{ $logo }}" class="logo" alt=""></td>
                            @endif
                            <td style="vertical-align: middle">
                                <div class="brand-name semi">{{ $company->name }}</div>
                                <div class="brand-contact">
                                    @if ($company->phone)<div>{{ $company->phone }}</div>@endif
                                    @if ($company->email)<div>{{ $company->email }}</div>@endif
                                    @if ($company->website)<div>{{ preg_replace('#^https?://#', '', $company->website) }}</div>@endif
                                    @if ($company->addressLines())<div>{{ implode(', ', $company->addressLines()) }}</div>@endif
                                    @if ($company->taxId)<div>GSTIN {{ $company->taxId }}</div>@endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 48%">
                    <div class="inv-title xbold">INVOICE</div>
                    <table class="inv-meta">
                        <tr><td class="k">Invoice No.</td><td class="v semi">{{ $invoice->number }}</td></tr>
                        <tr><td class="k">Invoice Date</td><td class="v semi">{{ $invoice->invoice_date->format('d/m/Y') }}</td></tr>
                        @if ($invoice->due_date)
                            <tr><td class="k">Due Date</td><td class="v semi">{{ $invoice->due_date->format('d/m/Y') }}</td></tr>
                        @endif
                        @if ($invoice->payment_terms)
                            <tr><td class="k">Payment Terms</td><td class="v semi">{{ $invoice->payment_terms }}</td></tr>
                        @endif
                    </table>
                    @if ($isPaid || $isCancelled || $isDraft)
                        <div class="right" style="margin-top: 8px">@include('documents.partials.status-stamp')</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        {{-- Bill to + amount due --}}
        <table>
            <tr>
                <td style="padding-right: 6mm">
                    <div class="billto">
                        <div class="label semi">Bill To</div>
                        @if ($invoice->customer_name || $invoice->customer_business)
                            <div class="client semi">{{ $invoice->customer_name ?: $invoice->customer_business }}</div>
                            @if ($invoice->customer_name && $invoice->customer_business)
                                <div class="biz">{{ $invoice->customer_business }}</div>
                            @endif
                            <table class="kv">
                                @if ($invoice->customer_phone)<tr><td class="k">Phone</td><td class="v">{{ $invoice->customer_phone }}</td></tr>@endif
                                @if ($invoice->customer_email)<tr><td class="k">Email</td><td class="v">{{ $invoice->customer_email }}</td></tr>@endif
                                @if ($invoice->customer_address)<tr><td class="k">Address</td><td class="v">{{ str_replace("\n", ', ', $invoice->customer_address) }}</td></tr>@endif
                                @if ($invoice->customer_tax_id)<tr><td class="k">GSTIN</td><td class="v">{{ $invoice->customer_tax_id }}</td></tr>@endif
                            </table>
                        @else
                            <div class="client semi">Walk-in customer</div>
                        @endif
                    </div>
                </td>
                <td style="width: 72mm">
                    <div class="due-wrap">
                        <div class="due-ring"></div>
                        <div class="label semi">{{ $dueLabel }}</div>
                        <div class="due-amt xbold">{{ $short($invoice->total) }}</div>
                        <div class="due-sub">{{ $dueSub }}</div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Lines --}}
        <table class="items">
            <thead>
            <tr>
                <th class="num">#</th>
                <th>{{ $invoice->item_label }}</th>
                @if ($package)
                    <th class="r last">Status</th>
                @else
                    @if ($invoice->show_quantity)
                        <th class="r">{{ $invoice->quantity_label }}</th>
                        <th class="r">{{ $rateHeading }}</th>
                    @endif
                    <th class="r last">Amount</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="num semi">{{ str_pad((string) $item->position, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div class="item-name semi">{{ $item->description }}</div>
                        @if ($item->detailLines())
                            <div class="item-sub">
                                @foreach ($item->detailLines() as $point)
                                    <span class="nowrap"><span class="b">&bull;</span> {{ $point }}</span>@if (! $loop->last)&nbsp;&nbsp;&nbsp; @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                    @if ($package)
                        <td class="r last" style="width: 90px">
                            @if ($item->inclusion === \App\Enums\Inclusion::Excluded)
                                <span class="tag tag-off semi">&times; Not included</span>
                            @else
                                <span class="tag semi">&#10003; Included</span>
                            @endif
                        </td>
                    @else
                        @if ($invoice->show_quantity)
                            <td class="r nowrap" style="width: 50px">{{ $item->quantityForDisplay() }}</td>
                            <td class="r nowrap" style="width: 80px">{{ $money($item->rate) }}</td>
                        @endif
                        <td class="r last nowrap semi" style="width: 90px; color: #1f2440">{{ $money($item->amount) }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Words + totals --}}
        <table style="margin-top: 3mm; page-break-inside: avoid">
            <tr>
                <td style="padding-right: 6mm; vertical-align: middle">
                    <div class="words-label semi">Amount in Words</div>
                    <div class="words">{{ $words }}</div>
                </td>
                <td style="width: 76mm">
                    <table class="total-box">
                        @if ($package)
                            <tr><td>Project Total (Package)</td><td class="v semi">{{ $short($invoice->subtotal) }}</td></tr>
                        @else
                            <tr><td>Subtotal</td><td class="v semi">{{ $money($invoice->subtotal) }}</td></tr>
                        @endif
                        @if ((float) $invoice->discount_amount > 0)
                            <tr>
                                <td>Discount{{ $invoice->discount_type === 'percent' ? ' (' . rtrim(rtrim((string) $invoice->discount_value, '0'), '.') . '%)' : '' }}</td>
                                <td class="v semi">&minus; {{ $money($invoice->discount_amount) }}</td>
                            </tr>
                        @endif
                        @if ((float) $invoice->tax_amount > 0)
                            <tr><td>Tax ({{ rtrim(rtrim((string) $invoice->tax_percent, '0'), '.') }}%)</td><td class="v semi">{{ $money($invoice->tax_amount) }}</td></tr>
                        @endif
                        <tr class="grand">
                            <td class="k semi">{{ $isPaid ? 'Total Paid' : 'Total Amount Due' }}</td>
                            <td class="v xbold nowrap">{{ $package ? $short($invoice->total) : $money($invoice->total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Highlighted note --}}
        @if ($invoice->hasCallout())
            <div class="callout" style="page-break-inside: avoid">
                <table>
                    <tr>
                        <td style="width: 42px"><div class="callout-ico xbold">&#8635;</div></td>
                        <td>
                            <div class="callout-title semi">{{ $c['title'] }}</div>
                            @if (! empty($c['text']))<div class="callout-text">{{ $c['text'] }}</div>@endif
                        </td>
                        @if (! empty($c['amount']))
                            <td class="right" style="width: 45mm">
                                <div class="callout-price xbold">{{ $short($c['amount']) }} <small>{{ $c['suffix'] ?? '' }}</small></div>
                                @if (! empty($c['meta']))<div class="callout-meta">{{ $c['meta'] }}</div>@endif
                            </td>
                        @endif
                    </tr>
                </table>
            </div>
        @endif

        {{-- Terms + payment --}}
        @if ($terms || $invoice->payment_terms || $qr || $invoice->notes)
            <table style="margin-top: 3.5mm; page-break-inside: avoid">
                <tr>
                    <td style="padding-right: 7mm">
                        @if ($terms || $invoice->payment_terms)
                            <div class="label semi">Terms &amp; Conditions</div>
                            <table class="terms">
                                @foreach ($terms as $term)
                                    <tr><td class="sq"><div class="sqb"></div></td><td>{{ $term }}</td></tr>
                                @endforeach
                                @if ($invoice->payment_terms)
                                    <tr><td class="sq"><div class="sqb"></div></td><td><b class="semi">Payment terms:</b> {{ rtrim($invoice->payment_terms, '.') }}.</td></tr>
                                @endif
                            </table>
                        @endif
                        @if ($invoice->notes)
                            <div class="notes">{!! nl2br(e($invoice->notes)) !!}</div>
                        @endif
                    </td>
                    @if ($qr)
                        <td style="width: 58mm">
                            <div class="pay">
                                <div class="label semi">Payment Details</div>
                                <div class="qr"><img src="{{ $qr }}" alt="UPI QR"></div>
                                <div class="scan">Scan with any UPI app to pay <b class="semi">{{ $short($invoice->total) }}</b></div>
                                <div class="upi semi"><span class="upi-k">UPI ID&nbsp;</span> {{ $company->upiId }}</div>
                            </div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif
    </div>

    @unless ($forPdf)
        @include('documents.partials.package-footer')
    @endunless
</div>
@endsection
