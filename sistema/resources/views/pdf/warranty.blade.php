<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>{{ $warranty->folio }}</title>
@include('pdf.partials.head')
</head>
<body>
@include('pdf.partials.top')

<table class="meta">
    <tr>
        <td>
            <div class="label">Póliza de garantía</div>
            <div class="folio">{{ $warranty->folio }}</div>
            <div>Inicio de garantía: {{ $warranty->starts_at->format('d/m/Y') }}</div>
            @if ($warranty->voided_at)<div><strong>ANULADA el {{ $warranty->voided_at->format('d/m/Y') }}: {{ $warranty->void_reason }}</strong></div>@endif
        </td>
        <td>
            <div class="label">Cliente</div>
            <div><strong>{{ $warranty->customer->name }}</strong></div>
            @if ($warranty->customer->phone)<div>Tel. {{ $warranty->customer->phone }}</div>@endif
        </td>
    </tr>
</table>

<table class="items">
    <thead><tr><th>Pieza o servicio</th><th>Serie</th><th class="num">Meses</th><th class="num">Vence</th></tr></thead>
    <tbody>
        @foreach ($warranty->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ $item->serial ?: '—' }}</td>
                <td class="num">{{ $item->months }}</td>
                <td class="num">{{ $item->endsAt()->format('d/m/Y') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="terms">
    {!! nl2br(e($warranty->terms ?: setting('warranty_terms', 'Garantía sujeta a las condiciones del fabricante.'))) !!}<br /><br />
    Para hacerla válida presenta esta póliza y la pieza en nuestro local. Consulta la vigencia en {{ url('/garantia') }}.
</div>

<table class="signs">
    <tr>
        <td><div class="line">Firma del cliente</div></td>
        <td><div class="line">Tech Computer</div></td>
    </tr>
</table>
</body>
</html>
