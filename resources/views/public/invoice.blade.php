{{--
    The customer's page, opened from the WhatsApp link. Standalone on purpose: no app shell, no
    back-office script or stylesheet, nothing that hints at the owner's admin area. Phone first,
    because that is where a WhatsApp link is opened.
--}}
@use('App\Enums\InvoiceStatus')
@php
    // A part-paid invoice asks only for what is left.
    $amount = \App\Support\Money::short($invoice->status === InvoiceStatus::Partial ? $invoice->balance() : $invoice->total, $invoice->currency_symbol);
    $status = $invoice->status;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Invoice {{ $invoice->number }} · {{ $company->name }}</title>
    {{-- What WhatsApp shows in the link preview. --}}
    <meta property="og:title" content="Invoice {{ $invoice->number }} — {{ $amount }}">
    <meta property="og:description" content="From {{ $company->name }}. View, download or pay.">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        @font-face { font-family: 'Inter'; font-weight: 400; src: url("{{ asset('assets/fonts/inter/Inter-Regular.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 600; src: url("{{ asset('assets/fonts/inter/Inter-SemiBold.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 800; src: url("{{ asset('assets/fonts/inter/Inter-ExtraBold.ttf') }}") format('truetype'); }

        :root {
            --navy: #111633;
            --accent: #3a44c2;
            --ink: #1f2440;
            --muted: #6b7091;
            --line: #e4e6ee;
            --bg: #eef0f5;
            --card: #ffffff;
            --paid: #16a34a;
            --cancel: #dc2626;
        }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #e6e8f2; --muted: #9ba0bd; --line: #2a3150; --bg: #0c1024; --card: #161b33; }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: var(--bg); }
        body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; color: var(--ink); font-size: 15px; line-height: 1.45; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 16px; }

        .brand { display: flex; align-items: center; gap: 10px; margin: 4px 0 14px; }
        .brand img { height: 36px; width: auto; max-width: 120px; object-fit: contain; background: #fff; border-radius: 6px; padding: 2px; }
        .brand-name { font-weight: 600; }

        .card { background: var(--card); border-radius: 14px; padding: 18px; box-shadow: 0 6px 24px -14px rgba(17, 22, 51, .35); }
        .summary-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .muted { color: var(--muted); font-size: 13px; }
        .number { font-weight: 600; }
        .pill { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 99px; white-space: nowrap; }
        .pill-due { background: #fff4e5; color: #b45309; }
        .pill-paid { background: #e8f8ee; color: var(--paid); }
        .pill-cancelled { background: #fdecec; color: var(--cancel); }
        .amount { font-size: 34px; font-weight: 800; letter-spacing: -0.5px; margin: 10px 0 2px; color: var(--ink); }
        .billto { margin-top: 10px; font-size: 14px; }

        .actions { display: grid; gap: 10px; margin-top: 16px; }
        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 48px; padding: 0 18px; border-radius: 10px; border: 1px solid transparent;
            font: 600 15px Inter, system-ui, sans-serif; text-decoration: none; cursor: pointer;
        }
        .btn-pay { background: var(--accent); color: #fff; }
        .btn-pay:active { background: #2f37a3; }
        .btn-ghost { background: transparent; color: var(--ink); border-color: var(--line); }
        .btn svg { width: 18px; height: 18px; flex: none; }
        .hint { font-size: 13px; color: var(--muted); text-align: center; margin: 4px 0 0; }

        /* The UPI app link only works where a UPI app is installed — a phone. On a computer the
           QR printed on the invoice is the way to pay, so the button gives way to a hint. */
        .only-touch, .only-touch-block { display: none; }
        .only-desk { display: block; }
        @media (hover: none) and (pointer: coarse) {
            .only-touch { display: flex; }
            .only-touch-block { display: block; }
            .only-desk { display: none; }
        }
        @media (min-width: 640px) {
            .actions { grid-template-columns: 1fr 1fr; }
        }

        .doc { margin-top: 16px; border-radius: 14px; overflow: hidden; background: #d9dbe3; }
        .doc-scale { position: relative; overflow: hidden; }
        .doc iframe { position: absolute; top: 0; left: 50%; border: 0; transform-origin: top left; background: #d9dbe3; }
        .foot { text-align: center; font-size: 12px; color: var(--muted); margin: 18px 0 8px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if ($company->logoUrl())<img src="{{ $company->logoUrl() }}" alt="">@endif
        <span class="brand-name">{{ $company->name }}</span>
    </div>

    <div class="card">
        <div class="summary-top">
            <div>
                <div class="number">Invoice {{ $invoice->number }}</div>
                <div class="muted">{{ $invoice->invoice_date->format('d M Y') }}</div>
            </div>
            @if ($status === InvoiceStatus::Paid)
                <span class="pill pill-paid">Paid</span>
            @elseif ($status === InvoiceStatus::Cancelled)
                <span class="pill pill-cancelled">Cancelled</span>
            @elseif ($status === InvoiceStatus::Partial)
                <span class="pill pill-due">Part paid</span>
            @else
                <span class="pill pill-due">
                    {{ $invoice->due_date ? 'Due ' . $invoice->due_date->format('d M') : ($invoice->payment_terms ?: 'Due') }}
                </span>
            @endif
        </div>

        <div class="amount">{{ $amount }}</div>
        <div class="muted">
            @if ($status === InvoiceStatus::Paid)
                Paid{{ $invoice->paid_at ? ' on ' . $invoice->paid_at->format('d M Y') : '' }} — thank you.
            @elseif ($status === InvoiceStatus::Cancelled)
                This invoice has been cancelled. Nothing is due.
            @elseif ($status === InvoiceStatus::Partial)
                Balance due — {{ \App\Support\Money::short($invoice->amount_paid, $invoice->currency_symbol) }} of
                {{ \App\Support\Money::short($invoice->total, $invoice->currency_symbol) }} already received. Thank you.
            @else
                {{ $invoice->isPackage() ? 'Package total' : 'Total' }} due
            @endif
        </div>

        @if ($invoice->customer_name || $invoice->customer_business)
            <div class="billto">
                <span class="muted">Billed to</span>
                {{ collect([$invoice->customer_name, $invoice->customer_business])->filter()->implode(' · ') }}
            </div>
        @endif

        <div class="actions">
            @if ($payable && $upiLink)
                <a class="btn btn-pay only-touch" href="{{ $upiLink }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    Pay {{ $amount }} with UPI
                </a>
            @endif
            <a class="btn btn-ghost" href="{{ route('public.pdf', $token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                Download PDF
            </a>
        </div>
        @if ($payable && $upiLink)
            <p class="hint only-touch-block">Opens GPay, PhonePe, Paytm or any UPI app with the amount filled in.</p>
            <p class="hint only-desk">To pay, scan the QR code on the invoice below with any UPI app.</p>
        @endif
    </div>

    <div class="doc" id="doc">
        <div class="doc-scale" id="doc-scale">
            <iframe id="doc-frame" src="{{ route('public.document', $token) }}" title="Invoice {{ $invoice->number }}" scrolling="no"></iframe>
        </div>
    </div>

    <div class="foot">{{ $company->name }}@if ($company->phone) · {{ $company->phone }}@endif</div>
</div>

<script>
    // Fit the invoice page to the screen width and size the frame to its content.
    (function () {
        var box = document.getElementById('doc');
        var holder = document.getElementById('doc-scale');
        var frame = document.getElementById('doc-frame');
        var width = 842, height = 1123;

        function fit() {
            var scale = Math.min(1, box.clientWidth / width);
            frame.style.width = width + 'px';
            frame.style.height = height + 'px';
            frame.style.transform = 'translateX(' + (-width * scale / 2) + 'px) scale(' + scale + ')';
            holder.style.height = Math.ceil(height * scale) + 'px';
        }

        function measure() {
            var doc = frame.contentDocument;
            if (!doc) { fit(); return; }
            var sheet = doc.querySelector('.sheet');
            width = Math.ceil((sheet ? sheet.getBoundingClientRect().width : 794) + 48);
            height = Math.ceil(doc.documentElement.scrollHeight);
            fit();
        }

        frame.addEventListener('load', function () {
            measure();
            var doc = frame.contentDocument;
            if (doc && doc.fonts) { doc.fonts.ready.then(measure); }
        });
        window.addEventListener('resize', fit, { passive: true });
        fit();
    })();
</script>
</body>
</html>
