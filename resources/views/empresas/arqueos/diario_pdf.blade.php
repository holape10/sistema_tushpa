{{-- Arqueo diario de caja en PDF (A4), con el formato del sistema antiguo --}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    $fechaTxt = \Carbon\Carbon::parse($fecha)->format('d-m-Y');
    $cols = $medios->count();
    $t = $totales;
    $doc = fn ($f) => $f->serdoc.'-'.$f->numdoc;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #111; }
        .cab td { border: 0; padding: 0; vertical-align: top; }
        .empresa { font-size: 10px; font-weight: bold; }
        h1 { font-size: 12px; text-align: center; margin: 10px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7.5px; text-transform: uppercase; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 3px 4px; text-align: left; }
        td { padding: 2px 4px; vertical-align: top; }
        .d { text-align: right; white-space: nowrap; }
        .sec td { font-weight: bold; padding-top: 8px; }
        .tot td { font-weight: bold; border-top: 1px solid #999; }
        .grupo td { font-style: italic; color: #444; }
        h2 { font-size: 9.5px; text-align: center; margin: 14px 0 4px; text-transform: uppercase; }
        .resumen { width: 70%; margin: 0 auto; }
        .resumen td { border-bottom: 1px dotted #ccc; }
        .gran td { font-weight: bold; font-size: 9.5px; border-top: 1.5px solid #111; border-bottom: 0; }
        .nota { color: #555; font-size: 7.5px; }
    </style>
</head>
<body>
    <table class="cab"><tr>
        @if ($logoReporte = \App\Support\Impresion\Impresion::logoReporte())
            <td style="width:1%; padding-right:10px"><img src="{{ $logoReporte }}" alt="" style="max-height:50px; max-width:150px"></td>
        @endif
        <td>
            <div class="empresa">{{ $empresa }}</div>
            @if ($direccion)<div>{{ $direccion }}</div>@endif
            <div>{{ $ruc }}</div>
        </td>
        <td class="d">Fecha: {{ now()->format('d-m-Y') }}<br>Hora: {{ now()->format('H:i:s') }}</td>
    </tr></table>

    <h1>ARQUEO DIARIO DE CAJA - {{ $fechaTxt }}{{ $sucursal ? ' · '.$sucursal : '' }}{{ $cajero ? ' · '.$cajero : '' }}</h1>

    <table>
        <thead>
            <tr><th>Fecha</th><th>Cod. Doc.</th><th>Serie</th><th>Número</th><th>Descripción</th>
                @foreach ($medios as $m)<th class="d">{{ $m->nombre }}</th>@endforeach
                <th class="d">Crédito</th><th class="d">Total</th></tr>
        </thead>
        <tbody>
            <tr class="sec"><td colspan="{{ 7 + $cols }}">01. (+) VENTAS DEL DÍA</td></tr>
            @forelse ($ventas as $v)
                <tr><td>{{ \Carbon\Carbon::parse($v->ccafem)->format('d-m-Y') }}</td><td>{{ $v->tdocod }}</td><td>{{ $v->serdoc }}</td><td>{{ $v->numdoc }}</td>
                    <td>{{ $v->ccanom ?: 'Varios' }}</td>
                    @foreach ($medios as $m)<td class="d">{{ $n($v->montos[$m->id] ?? 0) }}</td>@endforeach
                    <td class="d">{{ $n($v->credito) }}</td><td class="d">{{ $n($v->ccaitv) }}</td></tr>
            @empty
                <tr><td colspan="{{ 7 + $cols }}" class="nota">No hubo ventas.</td></tr>
            @endforelse
            <tr class="tot"><td colspan="5">TOTAL VENTAS DEL DÍA</td>
                @foreach ($porMedio as $m)<td class="d">{{ $n($m->ventas) }}</td>@endforeach
                <td class="d">{{ $n($t['credito']) }}</td><td class="d">{{ $n($t['ventas']) }}</td></tr>

            <tr class="sec"><td colspan="{{ 7 + $cols }}">02. VENTAS AL CRÉDITO</td></tr>
            @foreach ($credito as $v)
                <tr><td>{{ \Carbon\Carbon::parse($v->ccafem)->format('d-m-Y') }}</td><td>{{ $v->tdocod }}</td><td>{{ $v->serdoc }}</td><td>{{ $v->numdoc }}</td>
                    <td colspan="{{ 2 + $cols }}">{{ $v->ccanom }}</td><td class="d">{{ $n($v->credito) }}</td></tr>
            @endforeach
            <tr class="tot"><td colspan="{{ 6 + $cols }}">TOTAL VENTAS AL CRÉDITO</td><td class="d">{{ $n($t['credito']) }}</td></tr>

            <tr class="sec"><td colspan="{{ 7 + $cols }}">03. (+) COBRANZAS</td></tr>
            @foreach ($cobranzas as $c)
                <tr><td>{{ \Carbon\Carbon::parse($c->fec_dep)->format('d-m-Y') }}</td><td>{{ $c->tdocod }}</td><td>{{ $c->serdoc }}</td><td>{{ $c->numdoc }}</td>
                    <td>{{ $c->ccanom }}<br><span class="nota">Recibo {{ $c->numero_recibo }}</span></td>
                    @foreach ($medios as $m)<td class="d">{{ $n($c->montos[$m->id] ?? 0) }}</td>@endforeach
                    <td></td><td class="d">{{ $n($c->abono) }}</td></tr>
            @endforeach
            <tr class="tot"><td colspan="{{ 6 + $cols }}">TOTAL COBRANZAS</td><td class="d">{{ $n($t['cobranzas']) }}</td></tr>

            <tr class="sec"><td colspan="{{ 7 + $cols }}">04. (−) PAGOS A PROVEEDORES</td></tr>
            @foreach ($pagos as $p)
                <tr><td>{{ \Carbon\Carbon::parse($p->fec_dep)->format('d-m-Y') }}</td><td>{{ $p->tdocod }}</td><td>{{ $p->com_doc_ser }}</td><td>{{ $p->com_doc_num }}</td>
                    <td colspan="{{ 2 + $cols }}">{{ $p->prov_raz }}@if ((float) $p->de_caja > 0) <span class="nota">(efectivo de caja: {{ $n($p->de_caja) }})</span>@endif</td>
                    <td class="d">{{ $n($p->abono) }}</td></tr>
            @endforeach
            <tr class="tot"><td colspan="{{ 6 + $cols }}">TOTAL PAGOS</td><td class="d">{{ $n($t['pagos']) }}</td></tr>

            <tr class="sec"><td colspan="{{ 7 + $cols }}">10. (−) GASTOS</td></tr>
            @foreach ($gastos->groupBy('tip_caj_nom') as $grupo => $filas)
                <tr class="grupo"><td colspan="{{ 7 + $cols }}">{{ $grupo }}</td></tr>
                @foreach ($filas as $g)
                    <tr><td>{{ \Carbon\Carbon::parse($g->registro)->format('d-m-Y') }}</td><td colspan="2"></td><td>{{ $g->mov_num_doc }}</td>
                        <td colspan="{{ 2 + $cols }}">{{ $g->mov_com }}</td><td class="d">{{ $n($g->importe) }}</td></tr>
                @endforeach
            @endforeach
            <tr class="tot"><td colspan="{{ 6 + $cols }}">TOTAL GASTOS</td><td class="d">{{ $n($t['gastos']) }}</td></tr>

            <tr class="sec"><td colspan="{{ 7 + $cols }}">15. (+) OTROS INGRESOS</td></tr>
            @foreach ($otros as $o)
                <tr><td>{{ \Carbon\Carbon::parse($o->registro)->format('d-m-Y') }}</td><td colspan="2"></td><td>{{ $o->mov_num_doc }}</td>
                    <td colspan="{{ 2 + $cols }}">{{ $o->tip_caj_nom }} · {{ $o->mov_com }}</td><td class="d">{{ $n($o->importe) }}</td></tr>
            @endforeach
            <tr class="tot"><td colspan="{{ 6 + $cols }}">TOTAL OTROS INGRESOS</td><td class="d">{{ $n($t['otros']) }}</td></tr>
        </tbody>
    </table>

    <h2>Resumen ingresos efectivo</h2>
    <table class="resumen">
        <tr><td>FONDO DE CAJA (APERTURA)</td><td class="d">{{ $n($t['ingresos']['fondo']) }}</td></tr>
        <tr><td>COBRANZAS DE DOCUMENTOS A CRÉDITO</td><td class="d">{{ $n($t['ingresos']['cobranzas']) }}</td></tr>
        <tr><td>TOTAL FACTURAS EFECTIVO</td><td class="d">{{ $n($t['ingresos']['facturas']) }}</td></tr>
        <tr><td>TOTAL BOLETAS EFECTIVO</td><td class="d">{{ $n($t['ingresos']['boletas']) }}</td></tr>
        <tr><td>TOTAL NOTAS DE VENTA EFECTIVO</td><td class="d">{{ $n($t['ingresos']['notas']) }}</td></tr>
        <tr><td>OTROS INGRESOS</td><td class="d">{{ $n($t['ingresos']['otros']) }}</td></tr>
        <tr class="gran"><td>TOTAL INGRESOS</td><td class="d">{{ $n($t['total_ingresos']) }}</td></tr>
    </table>

    <h2>Resumen egresos efectivo</h2>
    <table class="resumen">
        <tr><td>TOTAL GASTOS</td><td class="d">{{ $n($t['egresos']['gastos']) }}</td></tr>
        <tr><td>PAGOS A PROVEEDORES (DE CAJA)</td><td class="d">{{ $n($t['egresos']['pagos']) }}</td></tr>
        @if ($t['egresos']['dolares'] > 0)
            <tr><td>EFECTIVO EN DÓLARES (US$ {{ $n($t['dolares_usd']) }}, VA APARTE)</td><td class="d">{{ $n($t['egresos']['dolares']) }}</td></tr>
        @endif
        <tr class="gran"><td>TOTAL EGRESOS</td><td class="d">{{ $n($t['total_egresos']) }}</td></tr>
    </table>

    <table class="resumen" style="margin-top:10px">
        <tr class="gran"><td>TOTAL SALDO EN EFECTIVO ( INGRESOS − EGRESOS )</td><td class="d">S/ {{ $n($t['saldo']) }}</td></tr>
    </table>

    <h2>Resumen ventas por medios de pago del {{ $fechaTxt }}</h2>
    <table class="resumen">
        <tr><th>Item</th><th>Medio de pago</th><th class="d">Ventas</th><th class="d">Cobranzas</th><th class="d">Total</th></tr>
        @foreach ($porMedio as $i => $m)
            <tr><td>{{ $i + 1 }}</td><td>{{ $m->nombre }}</td><td class="d">{{ $n($m->ventas) }}</td><td class="d">{{ $n($m->cobranzas) }}</td>
                <td class="d">{{ $n($m->ventas + $m->cobranzas) }}</td></tr>
        @endforeach
        @if ($t['credito'] > 0)
            <tr><td>{{ $porMedio->count() + 1 }}</td><td>CRÉDITO</td><td class="d">{{ $n($t['credito']) }}</td><td class="d">—</td><td class="d">{{ $n($t['credito']) }}</td></tr>
        @endif
        <tr class="gran"><td></td><td>TOTAL</td><td class="d">{{ $n($t['ventas']) }}</td><td class="d">{{ $n($t['cobranzas']) }}</td>
            <td class="d">{{ $n($t['ventas'] + $t['cobranzas']) }}</td></tr>
    </table>
</body>
</html>
