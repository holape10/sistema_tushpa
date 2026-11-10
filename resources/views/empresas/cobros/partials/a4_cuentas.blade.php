{{--
    Cuentas bancarias de la empresa al pie del comprobante A4 (PDF y vista del navegador), para que el cliente pague.
    Se configuran en Empresa → Cuentas bancarias. $simple = diseño en blanco y negro. Solo tablas: Dompdf las dibuja bien.
--}}
@php $cuentasA4 = collect($empresa->cuentas_bancarias ?? [])->filter(fn ($c) => ! empty($c['banco'])); @endphp
@if ($cuentasA4->isNotEmpty())
    @php
        $borde = ($simple ?? false) ? '#000' : '#8f969e';
        $fondo = ($simple ?? false) ? '#fff' : '#eef2f7';
    @endphp
    <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:8.5px; border:1px solid {{ $borde }}">
        <tr>
            <td colspan="4" style="padding:4px 8px; font-weight:bold; background:{{ $fondo }}; border-bottom:1px solid {{ $borde }}">
                CUENTAS BANCARIAS · {{ mb_strtoupper($empresa->NomEmpresa) }}</td>
        </tr>
        <tr style="font-weight:bold">
            <td style="padding:3px 8px; width:28%">BANCO</td>
            <td style="padding:3px 8px; width:12%">MONEDA</td>
            <td style="padding:3px 8px; width:28%">N° DE CUENTA</td>
            <td style="padding:3px 8px">CCI</td>
        </tr>
        @foreach ($cuentasA4 as $c)
            <tr style="border-top:1px solid {{ $borde }}">
                <td style="padding:3px 8px">{{ $c['banco'] }}</td>
                <td style="padding:3px 8px">{{ ($c['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES' : 'SOLES' }}</td>
                <td style="padding:3px 8px; font-family:'DejaVu Sans Mono', monospace">{{ $c['numero'] ?: '—' }}</td>
                <td style="padding:3px 8px; font-family:'DejaVu Sans Mono', monospace">{{ $c['cci'] ?: '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif
