{{--
    Registro de compras o ventas para imprimir (A4 horizontal): la cabecera de cada comprobante que se lleva al .dbf,
    con sus montos y cuentas, y los totales. Variables: $libro, $periodo, $comprobantes, $excluidos, $totales, $ruc, $empresa
--}}
@php
    $compras = $libro === 'compras';
    $n = fn ($v) => (float) $v != 0 ? number_format((float) $v, 2) : '';
    $meses = ['01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO', '04' => 'ABRIL', '05' => 'MAYO', '06' => 'JUNIO', '07' => 'JULIO',
        '08' => 'AGOSTO', '09' => 'SETIEMBRE', '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE'];
    $tipos = ['01' => 'FAC', '02' => 'R/H', '03' => 'BOL', '07' => 'N/C', '08' => 'N/D', '12' => 'TCK', '14' => 'RSP'];
    // Columnas de importes: en compras por destino del crédito fiscal (como el RCE); en ventas, como el RVIE
    $columnas = $compras
        ? ['B.I. grav.' => ['base_dg'], 'IGV 1' => ['igv_dg'], 'B.I. grav./no' => ['base_dgng'], 'IGV 2' => ['igv_dgng'],
            'B.I. no grav.' => ['base_dng'], 'IGV 3' => ['igv_dng'], 'No gravado' => ['no_gravado'], 'Otros' => ['isc', 'otros'], 'Total' => ['total']]
        : ['Exporta' => ['exporta'], 'Gravada' => ['gravada'], 'IGV' => ['igv'], 'Exonerada' => ['exonerada'], 'Inafecta' => ['inafecta'],
            'Otros' => ['isc', 'icbper', 'otros'], 'Total' => ['total']];
    $monto = fn (array $c, array $claves) => array_sum(array_map(fn ($k) => (float) ($c['montos'][$k] ?? 0), $claves));
    $suma = fn (array $claves) => round(array_sum(array_map(fn ($c) => $monto($c, $claves), $comprobantes)), 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 22px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; color: #111; }
        .empresa { font-size: 10px; font-weight: bold; }
        h1 { font-size: 11.5px; text-align: center; margin: 4px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 6.8px; text-transform: uppercase; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 3px 3px; text-align: left; }
        td { padding: 2px 3px; vertical-align: top; border-bottom: 0.5px solid #e5e5e5; }
        .d { text-align: right; white-space: nowrap; }
        .c { text-align: center; white-space: nowrap; }
        .nw { white-space: nowrap; }
        .tot td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: 0; font-size: 8px; }
        .nota { color: #555; font-size: 7px; margin-top: 8px; }
        .usd { color: #555; font-size: 6.5px; }
    </style>
</head>
<body>
    <div class="empresa">{{ $empresa }}</div>
    <div>RUC {{ $ruc }}</div>
    <h1>REGISTRO DE {{ $compras ? 'COMPRAS' : 'VENTAS' }} · {{ $meses[substr($periodo, 4, 2)] ?? '' }} {{ substr($periodo, 0, 4) }}</h1>

    <table>
        <thead>
            <tr>
                <th>N°</th><th>Fecha</th><th>Doc.</th><th>Serie-Número</th><th>{{ $compras ? 'RUC' : 'Doc. cliente' }}</th><th>{{ $compras ? 'Proveedor' : 'Cliente' }}</th>
                @foreach ($columnas as $titulo => $claves)<th class="d">{{ $titulo }}</th>@endforeach
                <th class="c">Cta. cargo</th><th class="c">Cta. abono</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comprobantes as $i => $c)
                <tr>
                    <td class="nw">{{ $i + 1 }}</td>
                    <td class="nw">{{ $c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '' }}</td>
                    <td class="nw">{{ $tipos[$c['tipo']] ?? $c['tipo'] }}</td>
                    <td class="nw">{{ $c['serie'] }}-{{ $c['numero'] }}
                        @if (! empty($c['ref']))<br><span class="usd">ref. {{ $c['ref']['serie'] }}-{{ $c['ref']['numero'] }}</span>@endif</td>
                    <td class="nw">{{ $c['ruc'] }}</td>
                    <td>{{ mb_strimwidth((string) $c['nombre'], 0, 38, '…') }}
                        @if (($c['moneda'] ?? 'PEN') === 'USD')<br><span class="usd">US$ · T.C. {{ $c['tc'] }}</span>@endif</td>
                    @foreach ($columnas as $titulo => $claves)<td class="d">{{ $n($monto($c, $claves)) }}</td>@endforeach
                    <td class="c">{{ $c['ctacargo'] }}</td>
                    <td class="c">{{ $c['ctaabono'] }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="6">TOTAL · {{ count($comprobantes) }} comprobante(s)</td>
                @foreach ($columnas as $titulo => $claves)<td class="d">{{ number_format($suma($claves), 2) }}</td>@endforeach
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <p class="nota">
        Solo los comprobantes que se llevan al {{ $compras ? 'almaingr' : 'tienfact' }}.dbf.
        @if ($excluidos) {{ $excluidos }} comprobante(s) marcados como «no se llevan» no figuran (suman S/ {{ number_format($totales['no_llevan']['total'] ?? 0, 2) }}). @endif
        Los comprobantes en dólares están marcados con «US$» y su tipo de cambio.
        Impreso el {{ now()->format('d/m/Y H:i') }}.
    </p>
</body>
</html>
