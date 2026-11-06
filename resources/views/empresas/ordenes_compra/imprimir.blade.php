<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $codigo }} · {{ $orden->prov_nom }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #fff; font-size: 13px; }
        .hoja { max-width: 820px; margin: 0 auto; }
        .cab { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #312e81; padding-bottom: 12px; }
        .cab h1 { margin: 0; font-size: 18px; } .cab p { margin: 2px 0; }
        .doc { text-align: right; border: 2px solid #312e81; border-radius: 8px; padding: 8px 14px; }
        .doc b { display: block; font-size: 16px; color: #312e81; }
        .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin: 14px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #eef2ff; text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .der { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; font-size: 15px; border: 0; }
        .firmas { display: flex; justify-content: space-around; margin-top: 70px; text-align: center; }
        .firmas div { border-top: 1px solid #6b7280; width: 220px; padding-top: 4px; font-size: 11px; }
        .acciones { text-align: center; margin-bottom: 16px; }
        .acciones button { background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-weight: bold; cursor: pointer; }
        @media print { .acciones { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="hoja">
    <div class="acciones"><button onclick="window.print()">🖨 Imprimir o guardar como PDF</button></div>
    <div class="cab">
        <div>
            <h1>{{ $empresa->NomEmpresa ?? '' }}</h1>
            <p>RUC {{ $orden->IdEmpresa }}</p>
            <p>{{ $sucursal->direccion ?? ($empresa->DirEmpresa ?? '') }}</p>
        </div>
        <div class="doc">ORDEN DE COMPRA<b>{{ $codigo }}</b>{{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}</div>
    </div>
    <div class="datos">
        <div><b>Proveedor:</b> {{ $orden->prov_nom }}</div>
        <div><b>RUC/DNI:</b> {{ $orden->prov_num }}</div>
        <div><b>Entrega:</b> {{ $orden->fecha_entrega ? \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') : 'A coordinar' }}{{ $almacen ? ' · '.$almacen : '' }}</div>
        <div><b>Moneda:</b> {{ $orden->moneda === 'USD' ? 'Dólares' : 'Soles' }}</div>
        @if ($orden->condiciones)<div style="grid-column: span 2"><b>Condiciones:</b> {{ $orden->condiciones }}</div>@endif
    </div>
    <table>
        <thead><tr><th>#</th><th>Producto</th><th class="der">Cantidad</th><th class="der">Precio unit.</th><th class="der">Total</th></tr></thead>
        <tbody>
            @foreach ($items as $n => $i)
                <tr><td>{{ $n + 1 }}</td><td>{{ $i->pronom }}</td>
                    <td class="der">{{ rtrim(rtrim(number_format($i->cantidad, 3, '.', ''), '0'), '.') }} {{ $i->umecod }}</td>
                    <td class="der">{{ number_format($i->costo, 2) }}</td><td class="der">{{ number_format($i->total, 2) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="4" class="der">TOTAL {{ $orden->moneda === 'USD' ? 'US$' : 'S/' }} (incluye IGV)</td><td class="der">{{ number_format($orden->total, 2) }}</td></tr>
        </tbody>
    </table>
    @if ($orden->observaciones)<p><b>Observaciones:</b> {{ $orden->observaciones }}</p>@endif
    @if ($orden->estado === 'PENDIENTE')<p style="color:#b45309"><b>Borrador: esta orden aún no está aprobada.</b></p>@endif
    <div class="firmas"><div>Solicitado por<br>{{ $pidio }}</div><div>Aprobado por<br>{{ $aprobo ?: '' }}</div></div>
</div>
</body>
</html>
