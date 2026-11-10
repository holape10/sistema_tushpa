{{--
    Cuentas bancarias de la empresa al pie del comprobante A4 (PDF y vista del navegador), para que el cliente pague.
    Se configuran en Empresa → Cuentas bancarias. $simple = diseño en blanco y negro: líneas sueltas como en los
    comprobantes antiguos ("BCP SOLES : …", "BCP CCI SOLES : …"); el moderno, en una tabla. Solo tablas: Dompdf las dibuja bien.
--}}
@php $cuentasA4 = collect($empresa->cuentas_bancarias ?? [])->filter(fn ($c) => ! empty($c['banco'])); @endphp
@if ($cuentasA4->isNotEmpty())
    @if ($simple ?? false)
        <div style="margin-top:10px; font-size:8.5px; line-height:1.7">
            @foreach ($cuentasA4 as $c)
                @php $moneda = ($c['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES' : 'SOLES'; @endphp
                @if (!empty($c['numero'])){{ $c['banco'] }} {{ $moneda }} : {{ $c['numero'] }}<br>@endif
                @if (!empty($c['cci'])){{ $c['banco'] }} CCI {{ $moneda }} : {{ $c['cci'] }}<br>@endif
            @endforeach
        </div>
    @else
        <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:8.5px; border:1px solid #8f969e">
            <tr>
                <td colspan="4" style="padding:4px 8px; font-weight:bold; background:#eef2f7; border-bottom:1px solid #8f969e">
                    CUENTAS BANCARIAS · {{ mb_strtoupper($empresa->NomEmpresa) }}</td>
            </tr>
            <tr style="font-weight:bold">
                <td style="padding:3px 8px; width:28%">BANCO</td>
                <td style="padding:3px 8px; width:12%">MONEDA</td>
                <td style="padding:3px 8px; width:28%">N° DE CUENTA</td>
                <td style="padding:3px 8px">CCI</td>
            </tr>
            @foreach ($cuentasA4 as $c)
                <tr style="border-top:1px solid #8f969e">
                    <td style="padding:3px 8px">{{ $c['banco'] }}</td>
                    <td style="padding:3px 8px">{{ ($c['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES' : 'SOLES' }}</td>
                    <td style="padding:3px 8px">{{ $c['numero'] ?: '—' }}</td>
                    <td style="padding:3px 8px">{{ $c['cci'] ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif
