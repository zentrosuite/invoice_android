{{--
    Package/Modern footer: "Thank You!", then the closing line and the business name together on
    one line in one size, then the phone as a tap-to-call link (live in the PDF and the online
    copy alike). The block has a fixed height and clips, so text can never run into the bar.
--}}
@php
    $closing = trim((string) $invoice->footer_note);
    // Both halves of the line share one size; a long message brings the whole line down a step
    // rather than wrapping into a second line the footer has no room for.
    $lineSize = mb_strlen($closing . $company->name) > 105 ? '7.2pt' : '8pt';
    $tel = $company->phone ? preg_replace('/[^0-9+]/', '', $company->phone) : null;
@endphp
<div class="page-foot">
    <div class="footer">
        <div class="thanks xbold">Thank You!</div>
        <div class="foot-line" style="font-size: {{ $lineSize }}">
            @if ($closing !== ''){{ $closing }} <span class="foot-dot">&middot;</span> @endif<span class="semi foot-name">{{ $company->name }}</span>
        </div>
        @if ($tel || $company->tagline)
            <div class="foot-line" style="font-size: {{ $lineSize }}">
                @if ($tel)<a href="tel:{{ $tel }}" class="foot-tel semi">{{ $company->phone }}</a>@endif
                @if ($tel && $company->tagline) <span class="foot-dot">&middot;</span> @endif
                @if ($company->tagline)<span>{{ $company->tagline }}</span>@endif
            </div>
        @endif
    </div>
    <table class="bar-bottom"><tr><td style="background: {{ $accent }}; width: 25%"></td><td style="background: #232a7a; width: 15%"></td><td style="background: {{ $navy }}"></td></tr></table>
</div>
