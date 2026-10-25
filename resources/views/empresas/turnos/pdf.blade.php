{{-- Turno de caja completo en PDF (A4): resumen, medios de pago, comprobantes, productos, categorías, cortesías y movimientos --}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    $cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 26px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0; color: #312e81; }
        .sub { color: #64748b; font-size: 9px; margin: 2px 0 12px; border-bottom: 2px solid #4338ca; padding-bottom: 6px; }
        h2 { font-size: 11px; color: #312e81; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px 6px; background: #f8fafc; }
        td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
        .d { text-align: right; white-space: nowrap; }
        .b { font-weight: bold; }
        .cat td { background: #eef2ff; font-weight: bold; }
        .col { width: 49%; vertical-align: top; }
    </style>
</head>
<body>
    <h1>Turno de caja N° {{ $turno->turno }} · {{ $negocio }}</h1>
    <p class="sub">{{ $turno->usuario->apeusu ?? '' }} · {{ $turno->apertura?->format('d/m/Y H:i') }} – {{ $turno->cierre?->format('d/m/Y H:i') ?? 'ABIERTO' }} · Estado: {{ $turno->estado }}</p>

    <table><tr>
        <td class="col" style="border:0;padding:0 8px 0 0">
            <h2>Resumen</h2>
            <table>
                @foreach (array_slice($resumen, 4) as [$concepto, $valor])
                    <tr><td>{{ $concepto }}</td><td class="d {{ in_array($concepto, ['Ventas del turno', 'Efectivo esperado']) ? 'b' : '' }}">{{ is_numeric($valor) ? 'S/ '.$n($valor) : $valor }}</td></tr>
                @endforeach
            </table>
        </td>
        <td class="col" style="border:0;padding:0 0 0 8px">
            <h2>Medios de pago</h2>
            <table>
                <tr><th>Medio</th><th class="d">Operaciones</th><th class="d">Monto</th></tr>
                @forelse ($r['ventasPorMedio'] as $v)
                    <tr><td>{{ $v->nom_med_pag }}</td><td class="d">{{ $v->operaciones }}</td><td class="d">S/ {{ $n($v->monto) }}</td></tr>
                @empty
                    <tr><td colspan="3">Sin cobros.</td></tr>
                @endforelse
            </table>
            <h2>Comprobantes</h2>
            <table>
                <tr><th>Tipo</th><th class="d">Cantidad</th><th class="d">Total</th></tr>
                @foreach ($r['comprobantes'] as $c)
                    <tr><td>{{ $c->tdodes }}</td><td class="d">{{ $c->cantidad }}</td><td class="d">S/ {{ $n($c->total) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr></table>

    <h2>Productos vendidos (por categoría)</h2>
    <table>
        <tr><th>Producto</th><th class="d">Cant.</th><th class="d">Precio</th><th class="d">Total</th></tr>
        @forelse ($productos->groupBy('categoria')->sortKeys() as $categoria => $grupo)
            <tr class="cat"><td>{{ $categoria }}</td><td class="d">{{ $cant($grupo->sum('cantidad')) }}</td><td></td><td class="d">S/ {{ $n($grupo->sum('total')) }}</td></tr>
            @foreach ($grupo as $p)
                <tr><td>{{ $p->nombre }}</td><td class="d">{{ $cant($p->cantidad) }}</td><td class="d">{{ $n($p->precio) }}</td><td class="d">{{ $n($p->total) }}</td></tr>
            @endforeach
        @empty
            <tr><td colspan="4">Sin ventas en este turno.</td></tr>
        @endforelse
        <tr><td class="b">TOTAL VENTA PRODUCTOS</td><td></td><td></td><td class="d b">S/ {{ $n($productos->sum('total')) }}</td></tr>
    </table>

    @if ($cortesias->isNotEmpty())
        <h2>Cortesías (no cobradas)</h2>
        <table>
            <tr><th>Producto</th><th class="d">Cant.</th><th class="d">Valor</th></tr>
            @foreach ($cortesias as $c)
                <tr><td>{{ $c->nombre }}</td><td class="d">{{ $cant($c->cantidad) }}</td><td class="d">S/ {{ $n($c->valor) }}</td></tr>
            @endforeach
            <tr><td class="b">TOTAL REGALADO</td><td></td><td class="d b">S/ {{ $n($cortesias->sum('valor')) }}</td></tr>
        </table>
    @endif

    @if ($r['movimientos']->isNotEmpty())
        <h2>Movimientos de caja</h2>
        <table>
            <tr><th>Tipo</th><th>Concepto</th><th>Descripción</th><th class="d">Importe</th><th>Estado</th></tr>
            @foreach ($r['movimientos'] as $m)
                <tr><td>{{ $m->tipo }}</td><td>{{ $m->tip_caj_nom }}</td><td>{{ $m->mov_com }}</td><td class="d">S/ {{ $n($m->importe) }}</td><td>{{ $m->estado }}</td></tr>
            @endforeach
        </table>
    @endif
    <p style="margin-top:12px;color:#94a3b8;font-size:8px">Impreso: {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
