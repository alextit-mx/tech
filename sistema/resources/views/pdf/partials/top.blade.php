@php
    $bizPhone = \App\Support\Mx::phone(setting('phone') ?: setting('whatsapp'));
    $bizDetails = array_filter(['RAZÓN SOCIAL' => setting('legal_name'), 'RFC' => setting('rfc'), 'TEL.' => $bizPhone, 'EMAIL' => setting('email')], 'filled');
@endphp
<table class="doc-head">
    <tr>
        <td class="brand"><img src="{{ $logo }}" alt="Tech Computer" /></td>
        <td class="biz">
            <div class="loc">{{ mb_strtoupper(setting('location_name') ?: setting('business_name', 'Tech Computer')) }}</div>
            @if (filled(setting('address')))<div class="addr">{!! nl2br(e(mb_strtoupper(setting('address')))) !!}</div>@endif
            <table class="diamond"><tr><td></td><th>&#9670;</th><td></td></tr></table>
            @foreach ($bizDetails as $label => $value)
                <div class="detail"><span class="k">{{ $label }}</span> {{ $value }}</div>
            @endforeach
        </td>
        <td class="stripes"><img src="{{ public_path('img/pdf/stripes.svg') }}" alt="" /></td>
    </tr>
</table>
<img class="doc-rule" src="{{ public_path('img/pdf/rule.svg') }}" alt="" />
