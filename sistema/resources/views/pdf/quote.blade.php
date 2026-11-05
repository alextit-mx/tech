@php
    $customer = $quote->customer;
    $seller = $quote->user?->name ?: 'MOSTRADOR';
    $validity = max(0, (int) $quote->created_at->startOfDay()->diffInDays($quote->valid_until->startOfDay(), false));
    $amount = fn ($value) => '$'.number_format((float) $value, 2);
    $same = fn ($a, $b) => mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    $clientName = $customer->company ?: ($customer->legal_name ?: $customer->name);
    $clientRows = [];
    if (filled($customer->legal_name) && ! $same($customer->legal_name, $clientName)) {
        $clientRows['RAZÓN SOCIAL'] = $customer->legal_name;
    }
    if (filled($customer->name) && ! $same($customer->name, $clientName)) {
        $clientRows['CONTACTO'] = $customer->name.($customer->position ? ' · '.$customer->position : '');
    }
    $clientRows += array_filter([
        'TELÉFONO' => \App\Support\Mx::phone($customer->phone),
        'CORREO' => $customer->email,
        'RFC' => $customer->rfc,
        'DIRECCIÓN' => $customer->kind === 'registrado' ? $customer->addressLine() : null,
    ], 'filled');
    $contact = array_filter([setting('website'), filled(setting('whatsapp')) ? 'WhatsApp '.\App\Support\Mx::phone(setting('whatsapp')) : null, setting('email')], 'filled');
    $terms = filled(setting('quote_terms')) ? preg_split('/\R/', trim(setting('quote_terms'))) : [
        "Vigencia: {$validity} ".($validity === 1 ? 'día' : 'días').'.',
        'Disponibilidad: sujeta a existencia al confirmar el pedido.',
        'Tiempo de entrega: sujeto a disponibilidad del proveedor.',
        'Forma de pago: transferencia, efectivo o tarjeta.',
        'Garantía: de acuerdo con las condiciones del fabricante.',
    ];
    $bank = filled(setting('bank_details')) ? preg_split('/\R/', trim(setting('bank_details'))) : [];
    // El pie fijo (condiciones, datos bancarios y quién atendió) se repite en cada hoja; su alto se estima
    // por renglones para reservar el margen inferior y que la tabla nunca quede debajo.
    $lines = fn (array $rows, int $chars) => array_sum(array_map(fn ($row) => max(1, (int) ceil(mb_strlen($row) / $chars)), $rows));
    $footLines = max($lines($terms, $bank ? 92 : 180), $bank ? $lines($bank, 74) : 0);
    $termsHeight = 28 + $footLines * 8.4;
    $bottomMargin = $termsHeight + 44;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>{{ $quote->folio }}</title>
<style>
    @page { margin: 30pt 34pt {{ $bottomMargin }}pt; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 6.3pt; color: #1d1b20; line-height: 1.3; margin: 0; }
    table { border-collapse: collapse; }
    .meta { width: 100%; table-layout: fixed; margin-bottom: 10pt; }
    .meta > tbody > tr > td { vertical-align: top; padding: 0; }
    .meta .client { width: 58%; }
    .meta .gap { width: 10%; }
    .meta .doc { width: 32%; }
    .fields { width: 100%; }
    .fields th { width: 54pt; text-align: left; font-size: 4.9pt; color: #c8000f; letter-spacing: .5pt; font-weight: bold; padding: 3.4pt 0 1.6pt; vertical-align: top; }
    .fields td { font-size: 6.4pt; padding: 2.4pt 0 1.6pt; border-bottom: .4pt solid #e2e2e2; vertical-align: top; }
    .fields td.main { font-size: 7.4pt; font-weight: bold; padding-top: 1.8pt; }
    .doc .fields th { width: 44pt; }
    .doc .fields td { text-align: right; font-weight: bold; }
    .doc .fields .red { color: #c8000f; }
    .doc-title { text-align: right; font-size: 8.6pt; font-weight: bold; letter-spacing: 2pt; margin-bottom: 1pt; }
    .project { font-size: 5.8pt; color: #666; margin: 0 0 3.5pt; }
    .project strong { color: #1d1b20; }
    .items { width: 100%; table-layout: fixed; }
    .items thead { display: table-header-group; }
    .items tr { page-break-inside: avoid; }
    .items th { background: #c8000f; color: #fff; font-size: 5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5pt; padding: 3.2pt 3.5pt; text-align: left; }
    .items td { padding: 2.6pt 3.5pt; border-bottom: .4pt solid #e6e6e6; vertical-align: top; font-size: 6pt; line-height: 1.28; }
    .items tr.alt td { background: #f8f8f8; }
    .items .brand { font-weight: bold; font-size: 5.4pt; text-transform: uppercase; letter-spacing: .2pt; }
    .items .code { font-size: 4.9pt; color: #888; margin-top: .4pt; }
    .items .warranty { font-size: 5.4pt; text-align: center; color: #444; }
    .num { text-align: right; white-space: nowrap; }
    .items th.num { text-align: right; }
    .items th.center { text-align: center; }
    .summary { width: 100%; table-layout: fixed; margin-top: 7pt; page-break-inside: avoid; }
    .summary > tbody > tr > td { vertical-align: top; padding: 0; }
    .summary .left { width: 62%; padding-right: 18pt; }
    .summary .right { width: 38%; }
    .label { font-size: 4.9pt; color: #c8000f; font-weight: bold; letter-spacing: .5pt; }
    .words { font-size: 6pt; font-weight: bold; margin-top: 1.2pt; line-height: 1.35; }
    .note { font-size: 5pt; color: #888; margin-top: 3pt; line-height: 1.35; }
    .totals { width: 100%; }
    .totals td { padding: 2.2pt 4pt; font-size: 6.3pt; }
    .totals td.k { color: #666; font-size: 5.4pt; letter-spacing: .5pt; }
    .totals .grand td { border-top: .9pt solid #1d1b20; font-weight: bold; font-size: 7.8pt; padding-top: 3.2pt; color: #1d1b20; }
    .totals .grand td.num { color: #c8000f; }
    .notes { margin-top: 9pt; padding: 4pt 6pt; background: #f8f8f8; border-left: 1.5pt solid #c8000f; font-size: 5.9pt; line-height: 1.4; page-break-inside: avoid; }
    .foot-terms { position: fixed; left: 0; right: 0; bottom: -{{ $bottomMargin - 30 }}pt; height: {{ $termsHeight }}pt; border-top: .4pt solid #e2e2e2; padding-top: 6pt; }
    .foot-terms table { width: 100%; table-layout: fixed; }
    .foot-terms td { vertical-align: top; padding: 0 16pt 0 0; font-size: 5.6pt; line-height: 1.5; color: #444; }
    .foot-terms h3 { font-size: 4.9pt; color: #c8000f; letter-spacing: .6pt; margin: 0 0 2pt; }
    .foot-contact { position: fixed; left: 0; right: 0; bottom: -{{ $bottomMargin - 16 }}pt; height: 12pt; font-size: 5pt; color: #888; }
    .foot-contact strong { color: #1d1b20; }
    .foot-contact table { width: 100%; }
    .foot-contact td { padding-top: 3.2pt; }
</style>
@include('pdf.partials.header-style')
</head>
<body>
<div class="foot-terms">
    <table><tr>
        <td style="width:{{ $bank ? '55%' : '100%' }}">
            <h3>CONDICIONES COMERCIALES</h3>
            @foreach ($terms as $line)
                {{ $line }}<br />
            @endforeach
        </td>
        @if ($bank)
            <td style="width:45%">
                <h3>DATOS BANCARIOS</h3>
                @foreach ($bank as $line)
                    {{ $line }}<br />
                @endforeach
            </td>
        @endif
    </tr></table>
</div>
<div class="foot-contact"><table><tr><td>Atendió: <strong>{{ $seller }}</strong>@if ($contact)  ·  {{ implode('  ·  ', $contact) }}@endif</td></tr></table></div>

@include('pdf.partials.top')

<table class="meta">
    <tr>
        <td class="client">
            <table class="fields">
                <tr><th>CLIENTE</th><td class="main">{{ $clientName }}</td></tr>
                @foreach ($clientRows as $label => $value)
                    <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </td>
        <td class="gap"></td>
        <td class="doc">
            <div class="doc-title">COTIZACIÓN</div>
            <table class="fields">
                <tr><th>FOLIO</th><td class="red">{{ $quote->folio }}</td></tr>
                <tr><th>FECHA</th><td>{{ $quote->created_at->format('d/m/Y') }}</td></tr>
                <tr><th>VIGENCIA</th><td>{{ $validity }} {{ $validity === 1 ? 'día' : 'días' }} · {{ $quote->valid_until->format('d/m/Y') }}</td></tr>
                <tr><th>VENDEDOR</th><td>{{ mb_strtoupper($seller) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($quote->build_name)<p class="project">Proyecto: <strong>{{ $quote->build_name }}</strong></p>@endif

<table class="items">
    <thead><tr>
        <th class="num" style="width:6%">Cant.</th>
        <th style="width:11%">Marca</th>
        <th style="width:53%">Descripción</th>
        <th class="center" style="width:10%">Garantía</th>
        <th class="num" style="width:10%">P. unitario</th>
        <th class="num" style="width:10%">Importe</th>
    </tr></thead>
    <tbody>
        @foreach ($quote->items as $item)
            @php
                $description = trim($item->description);
                if (filled($item->brand) && mb_stripos($description, $item->brand.' ') === 0) {
                    $description = mb_substr($description, mb_strlen($item->brand) + 1);
                }
                $codes = array_filter(['Código '.$item->sku => filled($item->sku), 'Modelo '.$item->model => filled($item->model)]);
            @endphp
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td class="num">{{ $item->quantity }}</td>
                <td class="brand">{{ $item->brand }}</td>
                <td>{{ $description }}@if ($codes)<div class="code">{{ implode('  ·  ', array_keys($codes)) }}</div>@endif</td>
                <td class="warranty">{{ $item->warranty ?: 'Con fabricante' }}</td>
                <td class="num">{{ $item->unit_price === null ? 'Por confirmar' : $amount((float) $item->unit_price / 1.16) }}</td>
                <td class="num">{{ $item->line_total === null ? '—' : $amount((float) $item->line_total / 1.16) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="summary">
    <tr>
        <td class="left">
            <div class="label">IMPORTE CON LETRA</div>
            <div class="words">{{ \App\Support\Mx::amountInWords((float) $quote->total) }}</div>
            <div class="note">Precios unitarios e importes antes de IVA, en pesos mexicanos (MXN).@if ((float) $quote->discount > 0) Descuento con IVA incluido, ya aplicado (antes de IVA: {{ $amount((float) $quote->discount / 1.16) }}).@endif @if ($quote->has_pending_prices) Hay partidas con precio por confirmar.@endif</div>
        </td>
        <td class="right">
            <table class="totals">
                <tr><td class="k">SUBTOTAL</td><td class="num">{{ $amount($quote->subtotal) }}</td></tr>
                @if ((float) $quote->discount > 0)<tr><td class="k">DESCUENTO</td><td class="num">−{{ $amount($quote->discount) }}</td></tr>@endif
                <tr><td class="k">IVA 16 %</td><td class="num">{{ $amount($quote->tax) }}</td></tr>
                <tr class="grand"><td>{{ $quote->has_pending_prices ? 'TOTAL PARCIAL' : 'TOTAL' }}</td><td class="num">{{ $amount($quote->total) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($quote->notes)<div class="notes"><span class="label">NOTAS</span><br />{!! nl2br(e($quote->notes)) !!}</div>@endif
</body>
</html>
