<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>{{ $quote->folio }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #17151a; }
    .top { width: 100%; border-bottom: 3px solid #E30613; padding-bottom: 10px; }
    .top td { vertical-align: top; }
    .logo { width: 170px; }
    .biz { text-align: right; font-size: 9.5px; color: #555; line-height: 1.5; }
    .folio { font-size: 20px; font-weight: bold; color: #E30613; letter-spacing: 1px; }
    .meta { width: 100%; margin: 14px 0; }
    .meta td { vertical-align: top; width: 50%; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; color: #888; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th { background: #17151a; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; padding: 7px 6px; text-align: left; }
    table.items td { border-bottom: 1px solid #e6e3e3; padding: 7px 6px; }
    .num { text-align: right; white-space: nowrap; }
    .sku { color: #888; font-size: 8.5px; }
    .totals { width: 42%; margin-left: 58%; margin-top: 10px; border-collapse: collapse; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { border-top: 2px solid #17151a; font-size: 13px; font-weight: bold; }
    .box { margin-top: 16px; padding: 10px 12px; background: #f5f3f3; border-left: 3px solid #E30613; }
    .terms { margin-top: 14px; font-size: 8.5px; color: #666; line-height: 1.5; }
</style>
</head>
<body>
<table class="top">
    <tr>
        <td><img class="logo" src="{{ $logo }}" alt="Tech Computer" /></td>
        <td class="biz">
            <strong>{{ setting('legal_name', 'Tech Computer ATS SA de CV') }}</strong><br />
            RFC {{ setting('rfc', 'TCA240830IM3') }}<br />
            {{ setting('address') }}<br />
            WhatsApp {{ setting('whatsapp') }} · {{ setting('email') }}<br />
            {{ setting('website') ?: 'www.techcomputer.com.mx' }}
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td>
            <div class="label">Cotización</div>
            <div class="folio">{{ $quote->folio }}</div>
            @if ($quote->build_name)<div><strong>{{ $quote->build_name }}</strong></div>@endif
            <div>Fecha: {{ $quote->created_at->format('d/m/Y') }} · Vigencia: {{ $quote->valid_until->format('d/m/Y') }}</div>
            @if ($quote->user)<div>Atiende: {{ $quote->user->name }}</div>@endif
        </td>
        <td>
            <div class="label">Cliente</div>
            <div><strong>{{ $quote->customer->name }}</strong></div>
            @if ($quote->customer->phone)<div>Tel. {{ $quote->customer->phone }}</div>@endif
            @if ($quote->customer->email)<div>{{ $quote->customer->email }}</div>@endif
            @if ($quote->customer->rfc)<div>RFC {{ $quote->customer->rfc }}</div>@endif
        </td>
    </tr>
</table>

<table class="items">
    <thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Precio unitario</th><th class="num">Importe</th></tr></thead>
    <tbody>
        @foreach ($quote->items as $item)
            <tr>
                <td>{{ $item->description }}@if ($item->sku)<br /><span class="sku">{{ $item->sku }}</span>@endif</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ $item->unit_price === null ? 'Por confirmar' : money($item->unit_price) }}</td>
                <td class="num">{{ $item->line_total === null ? '—' : money($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    @if ((float) $quote->discount > 0)<tr><td>Descuento</td><td class="num">−{{ money($quote->discount) }}</td></tr>@endif
    <tr><td>Subtotal</td><td class="num">{{ money($quote->subtotal) }}</td></tr>
    <tr><td>IVA 16 %</td><td class="num">{{ money($quote->tax) }}</td></tr>
    <tr class="grand"><td>{{ $quote->has_pending_prices ? 'Total parcial' : 'Total' }}</td><td class="num">{{ money($quote->total) }} MXN</td></tr>
</table>
@if ($quote->has_pending_prices)<p class="sku">Hay partidas con precio por confirmar.</p>@endif

@if ($quote->notes)<div class="box">{!! nl2br(e($quote->notes)) !!}</div>@endif
@if (setting('bank_details'))<div class="box"><span class="label">Datos para transferencia</span><br />{!! nl2br(e(setting('bank_details'))) !!}</div>@endif
<div class="terms">{!! nl2br(e(setting('quote_terms', 'Precios en pesos mexicanos con IVA incluido, sujetos a existencias.'))) !!}</div>
</body>
</html>
