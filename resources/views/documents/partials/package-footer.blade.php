{{-- Package/Modern footer: "Thank You!", closing line, company name and tagline. --}}
<div class="page-foot">
<div class="footer">
    <table>
        <tr>
            <td>
                <div class="thanks xbold">Thank You!</div>
                @if ($invoice->footer_note)<div class="thanks-sub">{{ $invoice->footer_note }}</div>@endif
            </td>
            <td class="right">
                <div class="foot-name semi">{{ $company->name }}</div>
                @if ($company->tagline)<div class="foot-tag">{{ $company->tagline }}</div>@endif
            </td>
        </tr>
    </table>
</div>
<table class="bar-bottom"><tr><td style="background: {{ $accent }}; width: 25%"></td><td style="background: #232a7a; width: 15%"></td><td style="background: {{ $navy }}"></td></tr></table>
</div>
