<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Turno {{ $turno->turno }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; background: #f3f4f6; margin: 0; }
        .hoja { max-width: 380px; margin: 16px auto; background: #fff; padding: 14px; }
        h2 { text-align: center; margin: 0 0 4px; font-size: 15px; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        hr { border: none; border-top: 1px dashed #999; margin: 8px 0; }
        .tit { font-weight: bold; margin-top: 6px; text-transform: uppercase; }
        .btns { text-align: center; margin: 12px 0; }
        .btns a, .btns button { display: inline-block; padding: 8px 14px; border-radius: 8px; border: none; background: #4f46e5; color: #fff; text-decoration: none; font-size: 13px; cursor: pointer; }
        .btns a { background: #6b7280; }
        .ok { color: #15803d; } .mal { color: #dc2626; }
        .aviso { background: #ecfdf5; color: #047857; padding: 6px; border-radius: 6px; text-align: center; margin-bottom: 8px; }
        @media print { body { background: #fff; } .hoja { margin: 0; max-width: none; } .btns, .aviso { display: none; } }
    </style>
</head>
<body>
<div class="btns">
    <button onclick="window.print()">Imprimir</button>
    <a href="{{ route('turnos.listado') }}">Listado de turnos</a>
    <a href="{{ route('turnos.index') }}">Caja</a>
</div>
<div class="hoja">
    @if (session('success'))
        <div class="aviso">{{ session('success') }}</div>
    @endif
    <h2>REPORTE DE TURNO N° {{ $turno->turno }}</h2>
    <p class="c">{{ $turno->usuario->apeusu ?? '' }} · <span class="b">{{ $turno->estado }}</span></p>
    <table>
        <tr><td>Apertura</td><td class="r">{{ $turno->apertura?->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Cierre</td><td class="r">{{ $turno->cierre?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    </table>
    <hr>
    <div class="tit">Ventas por medio de pago</div>
    <table>
        @forelse ($r['ventasPorMedio'] as $v)
            <tr><td>{{ $v->nom_med_pag ?? 'SIN MEDIO' }}</td><td class="r">{{ number_format($v->monto, 2) }}</td></tr>
        @empty
            <tr><td>Sin ventas</td></tr>
        @endforelse
        @if ($r['ventasCredito'] > 0)
            <tr><td>Crédito</td><td class="r">{{ number_format($r['ventasCredito'], 2) }}</td></tr>
        @endif
        <tr class="b"><td>TOTAL VENTAS</td><td class="r">{{ number_format($r['ventasTotal'], 2) }}</td></tr>
    </table>
    @if ($r['comprobantes']->isNotEmpty())
        <div class="tit">Comprobantes</div>
        <table>
            @foreach ($r['comprobantes'] as $c)
                <tr><td>{{ $c->tdodes }} ({{ $c->cantidad }})</td><td class="r">{{ number_format($c->total, 2) }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($r['movimientos']->isNotEmpty())
        <div class="tit">Movimientos de caja</div>
        <table>
            @foreach ($r['movimientos'] as $m)
                <tr style="{{ $m->estado == 'ANULADO' ? 'text-decoration:line-through;color:#999' : '' }}">
                    <td>{{ $m->tip_caj_nom }}: {{ $m->mov_com }}</td>
                    <td class="r">{{ $m->tipo == 'ENTRADA' ? '+' : '-' }}{{ number_format($m->importe, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    <hr>
    <div class="tit">Cuadre de efectivo</div>
    <table>
        <tr><td>Fondo inicial</td><td class="r">{{ number_format($turno->monto, 2) }}</td></tr>
        <tr><td>(+) Ventas en efectivo</td><td class="r">{{ number_format($r['ventasEfectivo'], 2) }}</td></tr>
        <tr><td>(+) Otros ingresos</td><td class="r">{{ number_format($r['ingresos'], 2) }}</td></tr>
        <tr><td>(-) Egresos</td><td class="r">{{ number_format($r['egresos'], 2) }}</td></tr>
        <tr class="b"><td>EFECTIVO ESPERADO</td><td class="r">{{ number_format($r['efectivoEsperado'], 2) }}</td></tr>
        @if ($turno->estado == 'CERRADO')
            @php $dif = round($turno->montocierre - $r['efectivoEsperado'], 2); @endphp
            <tr class="b"><td>EFECTIVO CONTADO</td><td class="r">{{ number_format($turno->montocierre, 2) }}</td></tr>
            <tr class="b {{ $dif == 0 ? 'ok' : 'mal' }}"><td>{{ $dif < 0 ? 'FALTANTE' : ($dif > 0 ? 'SOBRANTE' : 'CUADRE EXACTO') }}</td><td class="r">{{ number_format($dif, 2) }}</td></tr>
        @endif
    </table>
    @if ($turno->estado == 'CERRADO')
        @php $conteo = collect($denominaciones)->filter(fn($v, $campo) => $turno->$campo > 0); @endphp
        @if ($conteo->isNotEmpty())
            <div class="tit">Arqueo</div>
            <table>
                @foreach ($conteo as $campo => $valor)
                    <tr><td>{{ $turno->$campo }} x {{ $valor < 1 ? (int) round($valor * 100) . ' cént.' : 'S/ ' . $valor }}</td><td class="r">{{ number_format($turno->$campo * $valor, 2) }}</td></tr>
                @endforeach
            </table>
        @endif
        <p>Mesas ocupadas al cierre: {{ (int) $turno->totalocupados }} · libres: {{ (int) $turno->totallibres }}</p>
    @endif
</div>
</body>
</html>
