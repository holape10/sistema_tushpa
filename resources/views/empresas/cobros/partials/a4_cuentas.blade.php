{{--
    Cuentas bancarias de la empresa al pie del comprobante A4 (PDF y vista del navegador), para que el cliente pague.
    Se configuran en Empresa → Cuentas bancarias. $simple = diseño en blanco y negro: centrado en un recuadro con
    franja gris; el moderno, en una tabla a todo el ancho. Solo tablas: Dompdf las dibuja bien.
--}}
@php $cuentasA4 = collect($empresa->cuentas_bancarias ?? [])->filter(fn ($c) => ! empty($c['banco'])); @endphp
@if ($cuentasA4->isNotEmpty())
    @if ($simple ?? false)
        {{-- Centrado, en un recuadro redondeado con la franja gris del título (como el recuadro del RUC) --}}
        <div style="width:78%; margin:12px auto 0; border:1px solid #000; border-radius:8px; overflow:hidden">
            <div style="background:#7a7a7a; color:#fff; text-align:center; font-weight:bold; font-size:8.5px; padding:4px">CUENTAS BANCARIAS</div>
            <table style="width:100%; border-collapse:collapse; font-size:8.5px">
                <tr style="font-weight:bold">
                    <td style="padding:4px 6px; text-align:center">BANCO</td>
                    <td style="padding:4px 6px; text-align:center">MONEDA</td>
                    <td style="padding:4px 6px; text-align:center">N° DE CUENTA</td>
                    <td style="padding:4px 6px; text-align:center">CCI</td>
                </tr>
                @foreach ($cuentasA4 as $c)
                    <tr>
                        <td style="padding:3px 6px; text-align:center; border-top:1px solid #bdbdbd; font-weight:bold">{{ $c['banco'] }}</td>
                        <td style="padding:3px 6px; text-align:center; border-top:1px solid #bdbdbd">{{ ($c['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES' : 'SOLES' }}</td>
                        <td style="padding:3px 6px; text-align:center; border-top:1px solid #bdbdbd">{{ $c['numero'] ?: '—' }}</td>
                        <td style="padding:3px 6px; text-align:center; border-top:1px solid #bdbdbd">{{ $c['cci'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @else
        <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:8.5px; border:1px solid #8f969e">
            <tr>
                <td colspan="4" style="text-align:center; padding:4px 8px; font-weight:bold; background:#eef2f7; border-bottom:1px solid #8f969e">
                    CUENTAS BANCARIAS · {{ mb_strtoupper($empresa->NomEmpresa) }}</td>
            </tr>
            <tr style="font-weight:bold">
                <td style="text-align:center; padding:3px 8px; width:28%">BANCO</td>
                <td style="text-align:center; padding:3px 8px; width:12%">MONEDA</td>
                <td style="text-align:center; padding:3px 8px; width:28%">N° DE CUENTA</td>
                <td style="text-align:center; padding:3px 8px">CCI</td>
            </tr>
            @foreach ($cuentasA4 as $c)
                <tr style="border-top:1px solid #8f969e">
                    <td style="text-align:center; padding:3px 8px">{{ $c['banco'] }}</td>
                    <td style="text-align:center; padding:3px 8px">{{ ($c['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES' : 'SOLES' }}</td>
                    <td style="text-align:center; padding:3px 8px">{{ $c['numero'] ?: '—' }}</td>
                    <td style="text-align:center; padding:3px 8px">{{ $c['cci'] ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif
