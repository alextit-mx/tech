<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>{{ $order->folio }}</title>
@include('pdf.partials.head')
</head>
<body>
@include('pdf.partials.top')

<table class="meta">
    <tr>
        <td>
            <div class="label">Orden de servicio</div>
            <div class="folio">{{ $order->folio }}</div>
            <div>Recibido: {{ $order->received_at->format('d/m/Y H:i') }}</div>
            @if ($order->promised_at)<div>Fecha estimada: {{ $order->promised_at->format('d/m/Y') }}</div>@endif
            @if ($order->user)<div>Recibió: {{ $order->user->name }}</div>@endif
        </td>
        <td>
            <div class="label">Cliente</div>
            <div><strong>{{ $order->customer->name }}</strong></div>
            @if ($order->customer->phone)<div>Tel. {{ $order->customer->phone }}</div>@endif
            @if ($order->customer->email)<div>{{ $order->customer->email }}</div>@endif
        </td>
    </tr>
</table>

<table class="items">
    <thead><tr><th>Equipo</th><th>Marca y modelo</th><th>Serie</th><th>Accesorios recibidos</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ \App\Models\ServiceOrder::DEVICES[$order->device_type] ?? $order->device_type }}</td>
            <td>{{ trim($order->brand.' '.$order->model) ?: '—' }}</td>
            <td>{{ $order->serial ?: '—' }}</td>
            <td>{{ $order->accessories ?: 'Ninguno' }}</td>
        </tr>
    </tbody>
</table>

<div class="box"><span class="label">Falla reportada</span><br />{!! nl2br(e($order->reported_issue)) !!}</div>
@if ($order->diagnosis)<div class="box"><span class="label">Diagnóstico</span><br />{!! nl2br(e($order->diagnosis)) !!}</div>@endif
@if ($order->estimate)<div class="box"><span class="label">Presupuesto</span><br />{{ money($order->estimate) }} con IVA</div>@endif

<div class="terms">
    Consulta el estado de tu equipo en {{ url('/estado') }} con este folio y los últimos 4 dígitos de tu teléfono.<br />
    El diagnóstico no incluye reparación; toda reparación se realiza solo con tu autorización. Respaldar tu información es tu responsabilidad:
    no nos hacemos responsables por datos perdidos. Los equipos no recogidos 30 días después de avisar que están listos pueden generar
    cargo por almacenaje. Presenta esta orden para recoger tu equipo.
</div>

<table class="signs">
    <tr>
        <td><div class="line">Firma del cliente</div></td>
        <td><div class="line">Recibe por Tech Computer</div></td>
    </tr>
</table>
</body>
</html>
