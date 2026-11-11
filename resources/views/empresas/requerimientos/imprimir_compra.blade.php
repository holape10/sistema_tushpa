<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $codigo }} · Lo comprado</title>
    @php
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
        $unidad = fn ($u) => \App\Support\Recetas::nombreUnidad($u ?: 'NIU');
        // Lo marcado con ✔ se compró; lo sin marcar (0 o aún vacío) no. En los ya comprados de antes, vacío era "tal como se pidió"
        $vacioEsComprado = $r->estado === 'COMPRADO';
        $seCompro = fn ($l) => $l->comprado === null ? $vacioEsComprado : (float) $l->comprado > 0;
        $comprados = $lineas->filter($seCompro)->map(function ($l) {
            $l->cant = (float) ($l->comprado ?? $l->cantidad);
            $l->pu = (float) ($l->precio ?? $l->costo_actual);
            $l->importe = round($l->cant * $l->pu, 2);

            return $l;
        });
        $noComprados = $lineas->reject($seCompro);
        $total = $comprados->sum('importe');
        $ticket = $formato === 'ticket';
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; background: #fff; }
        table { width: 100%; border-collapse: collapse; }
        .der { text-align: right; white-space: nowrap; }
        .acciones { text-align: center; margin: 10px 0 14px; }
        .acciones button { background: #059669; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-weight: bold; cursor: pointer; }
        @media print { .acciones { display: none; } }
        @if ($ticket)
            @page { size: 80mm auto; margin: 0; }
            body { font-size: 12px; }
            .hoja { width: 72mm; margin: 0 auto; padding: 4mm 0; }
            .centro { text-align: center; }
            h1 { font-size: 14px; margin: 0; }
            .raya { border-top: 1px dashed #111; margin: 6px 0; }
            td { padding: 2px 0; vertical-align: top; }
            .total td { font-size: 14px; font-weight: bold; padding-top: 4px; }
        @else
            body { font-size: 13px; padding: 20px; }
            .hoja { max-width: 760px; margin: 0 auto; }
            .cab { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #065f46; padding-bottom: 10px; }
            .cab h1 { margin: 0; font-size: 17px; } .cab p { margin: 2px 0; }
            .doc { text-align: right; border: 2px solid #065f46; border-radius: 8px; padding: 6px 12px; }
            .doc b { display: block; font-size: 16px; color: #065f46; }
            th { background: #ecfdf5; text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
            td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            .cat td { background: #f9fafb; font-weight: bold; font-size: 11px; text-transform: uppercase; color: #6b7280; }
            .total td { font-size: 15px; font-weight: bold; border-bottom: 0; border-top: 2px solid #111; }
            h2 { font-size: 13px; margin: 18px 0 4px; color: #9f1239; }
            .firmas { display: flex; justify-content: space-around; margin-top: 60px; text-align: center; }
            .firmas div { border-top: 1px solid #6b7280; width: 200px; padding-top: 4px; font-size: 11px; }
        @endif
    </style>
</head>
<body>
<div class="hoja">
    <div class="acciones"><button onclick="window.print()">🖨 Imprimir</button></div>
    @if ($ticket)
        <div class="centro">
            <h1>{{ $empresa->NomEmpresa ?? '' }}</h1>
            <div>{{ $sucursal->nombre_comercial ?? '' }}</div>
            <div class="raya"></div>
            <b>LO COMPRADO · {{ $codigo }}</b>
            <div>Para el {{ \Carbon\Carbon::parse($r->fecha_para)->format('d/m/Y') }} · {{ $almacen }}</div>
            <div>Impreso {{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div class="raya"></div>
        <table>
            @foreach ($comprados as $l)
                <tr><td colspan="2"><b>{{ $l->pronom }}</b></td></tr>
                <tr><td>{{ $num($l->cant) }} {{ $unidad($l->umecod) }} x S/ {{ number_format($l->pu, 2) }}</td><td class="der">{{ number_format($l->importe, 2) }}</td></tr>
            @endforeach
            <tr class="total"><td>TOTAL S/</td><td class="der">{{ number_format($total, 2) }}</td></tr>
        </table>
        <div>{{ $comprados->count() }} producto(s) comprado(s)</div>
        @if ($noComprados->isNotEmpty())
            <div class="raya"></div>
            <b>No se compró:</b>
            @foreach ($noComprados as $l)<div>- {{ $l->pronom }} ({{ $num($l->cantidad) }} {{ $unidad($l->umecod) }})</div>@endforeach
        @endif
        <div class="raya"></div>
        <div>Pidió: {{ $pidio }}</div>
        @if ($atendio)<div>Compró: {{ $atendio }}</div>@endif
    @else
        <div class="cab">
            <div>
                <h1>{{ $empresa->NomEmpresa ?? '' }}</h1>
                <p>{{ $sucursal->nombre_comercial ?? '' }} · {{ $sucursal->direccion ?? '' }}</p>
                <p>Almacén: {{ $almacen }} · Impreso {{ now()->format('d/m/Y H:i') }}</p>
            </div>
            <div class="doc">LO COMPRADO<b>{{ $codigo }}</b>Para el {{ \Carbon\Carbon::parse($r->fecha_para)->format('d/m/Y') }}</div>
        </div>
        <table style="margin-top:10px">
            <thead><tr><th>Producto / insumo</th><th class="der">Pedido</th><th class="der">Comprado</th><th class="der">Precio unit.</th><th class="der">Importe</th></tr></thead>
            <tbody>
                @foreach ($comprados->groupBy(fn ($l) => $l->cat_nom ?? 'Sin categoría') as $categoria => $grupo)
                    <tr class="cat"><td colspan="5">{{ $categoria }}</td></tr>
                    @foreach ($grupo as $l)
                        <tr><td>{{ $l->pronom }}</td><td class="der">{{ $num($l->cantidad) }}</td>
                            <td class="der"><b>{{ $num($l->cant) }} {{ $unidad($l->umecod) }}</b></td>
                            <td class="der">S/ {{ number_format($l->pu, 2) }}</td><td class="der">S/ {{ number_format($l->importe, 2) }}</td></tr>
                    @endforeach
                @endforeach
                <tr class="total"><td colspan="4">TOTAL ({{ $comprados->count() }} productos)</td><td class="der">S/ {{ number_format($total, 2) }}</td></tr>
            </tbody>
        </table>
        @if ($noComprados->isNotEmpty())
            <h2>✖ No se compró</h2>
            <table>
                @foreach ($noComprados as $l)<tr><td>{{ $l->pronom }}</td><td class="der">{{ $num($l->cantidad) }} {{ $unidad($l->umecod) }}</td></tr>@endforeach
            </table>
        @endif
        <div class="firmas"><div>Pidió<br>{{ $pidio }}</div><div>Compró<br>{{ $atendio ?: '' }}</div></div>
    @endif
</div>
</body>
</html>
