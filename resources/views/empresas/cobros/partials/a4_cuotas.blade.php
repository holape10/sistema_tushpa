{{--
    Cuadro de cuotas de una venta al crédito (comprobante A4, PDF y navegador), como lo pide SUNAT: número, fecha de
    vencimiento, moneda y monto de cada cuota. $simple = diseño en blanco y negro (franja gris); si no, el moderno (azul).
    Solo tablas: Dompdf las dibuja bien.
--}}
@php $cuotasA4 = \App\Support\CuotasCredito::de($cab); @endphp
@if ($cuotasA4->isNotEmpty())
    @php
        $fondo = ($simple ?? false) ? '#7a7a7a' : '#1e3a8a';
        $borde = ($simple ?? false) ? '#000' : '#8f969e';
        $moneda = ($cab->moncod ?? 'PEN') === 'USD' ? 'USD' : 'PEN';
    @endphp
    <table style="width:50%; border-collapse:collapse; margin-top:8px; font-size:8.5px; border:1px solid {{ $borde }}">
        <tr><td colspan="4" style="background:{{ $fondo }}; color:#fff; text-align:center; font-weight:bold; padding:3px">CUOTAS</td></tr>
        <tr style="background:{{ $fondo }}; color:#fff; font-weight:bold; text-align:center">
            <td style="padding:3px; width:10%">#</td><td style="padding:3px">FECHA VENCIMIENTO</td><td style="padding:3px">MONEDA</td><td style="padding:3px">MONTO</td>
        </tr>
        @foreach ($cuotasA4 as $c)
            <tr style="text-align:center">
                <td style="padding:3px; border-top:1px solid #d1d5db">{{ $c->numero }}</td>
                <td style="padding:3px; border-top:1px solid #d1d5db">{{ \Carbon\Carbon::parse($c->fecha)->format('Y-m-d') }}</td>
                <td style="padding:3px; border-top:1px solid #d1d5db">{{ $moneda }}</td>
                <td style="padding:3px; border-top:1px solid #d1d5db; text-align:right">{{ number_format($c->monto, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif
