{{--
    Shell for every invoice design, on screen and in the PDF.

    Written for dompdf: CSS 2.1, tables for layout, no flexbox/grid/variables/gradients, all
    images as data URIs. A browser renders it the same way, which is the point — the preview
    IS the PDF. Fonts: Inter, registered as separate families per weight because dompdf only
    distinguishes normal and bold within one family.
--}}
<!DOCTYPE html>
{{-- The class is on <html> as well as <body>: in the preview frame, any space below the body
     must be the grey desk, never white that reads as an empty second page. --}}
<html lang="en" class="{{ $forPdf ? 'pdf' : 'screen' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }} · {{ $company->name }}</title>
    <style>
        @font-face { font-family: 'Inter'; font-weight: normal; font-style: normal; src: url("{{ $fontUrl('Inter-Regular.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: bold; font-style: normal; src: url("{{ $fontUrl('Inter-Bold.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: normal; font-style: italic; src: url("{{ $fontUrl('Inter-Italic.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter SemiBold'; font-weight: normal; font-style: normal; src: url("{{ $fontUrl('Inter-SemiBold.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter SemiBold'; font-weight: normal; font-style: italic; src: url("{{ $fontUrl('Inter-SemiBoldItalic.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Inter ExtraBold'; font-weight: normal; font-style: normal; src: url("{{ $fontUrl('Inter-ExtraBold.ttf') }}") format('truetype'); }
        {{-- Tamil, for names and items typed in Tamil. Inter has no Tamil letters, so every
             stack names Noto Sans Tamil next: a Tamil character is drawn in it, the rest in Inter.
             Same per-weight families as Inter. On the phone the PDF is drawn by Android's own
             browser engine (ZentroPdf plugin), which joins Tamil letters correctly; dompdf
             cannot, and is only the fallback. --}}
        @font-face { font-family: 'Noto Sans Tamil'; font-weight: normal; font-style: normal; src: url("{{ $tamilFontUrl('NotoSansTamil-Regular.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Noto Sans Tamil'; font-weight: bold; font-style: normal; src: url("{{ $tamilFontUrl('NotoSansTamil-Bold.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Noto Sans Tamil SemiBold'; font-weight: normal; font-style: normal; src: url("{{ $tamilFontUrl('NotoSansTamil-SemiBold.ttf') }}") format('truetype'); }
        @font-face { font-family: 'Noto Sans Tamil Bold'; font-weight: normal; font-style: normal; src: url("{{ $tamilFontUrl('NotoSansTamil-Bold.ttf') }}") format('truetype'); }

        {{-- One @page rule only, supplied by the design: dompdf keeps the first it sees rather
             than letting a later one override it. --}}
        @yield('page', '@page { margin: 0; }')
        {{-- Not `*`: dompdf applies @page margins to <html>, and a universal reset would wipe them. --}}
        body, body * { margin: 0; padding: 0; }
        html, body { background: #ffffff; }
        body {
            font-family: 'Inter', 'Noto Sans Tamil', 'DejaVu Sans', sans-serif;
            font-size: 9.4pt;
            line-height: 1.42;
            color: #3d4260;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; text-align: left; }
        img { border: 0; }
        .semi { font-family: 'Inter SemiBold', 'Noto Sans Tamil SemiBold', 'Inter', sans-serif; }
        .xbold { font-family: 'Inter ExtraBold', 'Noto Sans Tamil Bold', 'Inter', sans-serif; }
        .nowrap { white-space: nowrap; }
        .right { text-align: right; }
        .center { text-align: center; }

        /* On screen: a sheet of paper on a grey desk. In the PDF the page is the paper. */
        html.screen, .screen { background: #d9dbe3; }
        body.screen { padding: 24px 0; }
        .screen .sheet { margin: 0 auto; background: #fff; box-shadow: 0 10px 40px -12px rgba(17, 22, 51, .35); }

        /* Status stamps. */
        .stamp {
            display: inline-block;
            padding: 3px 10px;
            border: 2px solid;
            border-radius: 6px;
            font-family: 'Inter ExtraBold', 'Noto Sans Tamil Bold', 'Inter', sans-serif;
            letter-spacing: 2px;
            font-size: 11pt;
        }
        .stamp-paid { color: #16a34a; border-color: #16a34a; }
        .stamp-cancelled { color: #dc2626; border-color: #dc2626; }
        .stamp-partial { color: #0284c7; border-color: #0284c7; font-size: 9pt; }
        .stamp-draft { color: #94a3b8; border-color: #cbd5e1; font-size: 9pt; }

        @yield('styles')
    </style>
</head>
<body class="{{ $forPdf ? 'pdf' : 'screen' }}">
    @yield('document')
</body>
</html>
