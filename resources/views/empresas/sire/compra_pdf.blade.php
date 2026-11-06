{{--
    Comprobante de compra en PDF (Dompdf), armado con lo que dice su XML. Mismo diseño que el A4 de ventas (cabecera,
    recuadro azul con RUC y número, adquiriente, detalle, SON + QR y totales), con el proveedor como emisor.
    Variables: $x (compras_xml o ventas_xml), $lineas, $numero y, o $comprador (Empresa: es una compra), o $emisor y $receptor (una venta)
--}}
@php
    $tdodes = ['01' => 'FACTURA ELECTRÓNICA', '03' => 'BOLETA DE VENTA ELECTRÓNICA', '07' => 'NOTA DE CRÉDITO ELECTRÓNICA',
        '08' => 'NOTA DE DÉBITO ELECTRÓNICA'][$x->tipo] ?? 'COMPROBANTE '.$x->tipo;
    $mon = $x->moneda === 'USD' ? '$' : 'S/';
    $gravada = round($lineas->filter(fn ($l) => (float) $l->igv != 0)->sum('base'), 2);
    $noGravada = round($lineas->filter(fn ($l) => (float) $l->igv == 0)->sum('base'), 2);
    $cant = fn ($n) => rtrim(rtrim(number_format($n, 3), '0'), '.');
    // Compra: emite el proveedor y recibe la empresa. Venta: emite la empresa y recibe su cliente
    $emisor ??= ['ruc' => (string) $x->ruc_emisor, 'nombre' => $x->razon_emisor ?: 'RUC '.$x->ruc_emisor, 'nota' => 'Proveedor · comprobante recibido'];
    $receptor ??= ['doc' => (string) $x->IdEmpresa, 'nombre' => (string) ($comprador->NomEmpresa ?? ''), 'direccion' => (string) ($comprador->DirEmpresa ?? '')];
    $tipoDocReceptor = strlen($receptor['doc']) === 11 ? 'RUC' : 'DOC.';
    $qr = \App\Support\Sunat\CodigoQr::svg((object) ['IdEmpresa' => $emisor['ruc'], 'tdocod' => $x->tipo, 'serdoc' => $x->serie, 'numdoc' => $x->numero,
        'ccaigv' => $x->igv, 'ccaitv' => $x->total, 'ccafem' => $x->fecha, 'tdicod' => strlen($receptor['doc']) === 11 ? '6' : '1', 'ccandi' => $receptor['doc']], 110);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tdodes }} {{ $numero }}</title>
    <style>
        @page { margin: 12mm 11mm; } body { margin: 0; }
        .a4 { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 9px; }
        .a4 table { width: 100%; border-collapse: collapse; }
        .a4 .r { text-align: right; } .a4 .c { text-align: center; }
        .a4 .cab { background: #eef0f3; border-bottom: 3px solid #007bff; }
        .a4 .cab td { vertical-align: middle; padding: 10px; }
        .a4 .empresa { font-size: 15px; font-weight: bold; color: #2c3e50; }
        .a4 .info { font-size: 8.5px; color: #4b5563; line-height: 1.45; margin-top: 4px; }
        .a4 .numeracion { background: #fff; border: 2px solid #007bff; border-radius: 12px; text-align: center; padding: 10px 8px; }
        .a4 .numeracion .ruc { font-size: 11px; font-weight: bold; color: #495057; }
        .a4 .numeracion .tipo { background: #007bff; color: #fff; font-size: 11px; font-weight: bold; border-radius: 6px; padding: 8px 4px; margin: 7px 0; }
        .a4 .numeracion .num { font-size: 13px; font-weight: bold; color: #007bff; }
        .a4 .cliente { background: #f8f9fa; border-left: 4px solid #007bff; padding: 9px 12px; margin-top: 12px; line-height: 1.6; }
        .a4 .lbl { font-weight: bold; color: #2c3e50; }
        .a4 .caja { border: 2px solid #8f969e; border-radius: 10px; padding: 8px; margin-top: 10px; }
        .a4 .caja th { background: #6c757d; color: #fff; font-size: 8.5px; padding: 7px 6px; }
        .a4 .caja td { text-align: center; padding: 7px 6px; font-size: 8.5px; }
        .a4 .det { margin-top: 12px; border: 2px solid #8f969e; }
        .a4 .det th { background: #495057; color: #fff; font-size: 9.5px; padding: 9px 6px; text-align: center; }
        .a4 .det td { padding: 8px 6px; font-size: 8.5px; border-right: 1px solid #aeb5bd; border-bottom: 1px solid #aeb5bd; }
        .a4 .det tr.par td { background: #f8f9fa; }
        .a4 .tot { margin-top: 14px; border: 2px solid #8f969e; }
        .a4 .letras { background: #f8f9fa; border-left: 4px solid #007bff; border-radius: 6px; padding: 7px; font-size: 8px; margin-bottom: 8px; }
        .a4 .tot td.tt { background: #f1f3f5; padding: 6px 4px; font-size: 9px; border-bottom: 1px solid #aeb5bd; vertical-align: middle; }
        .a4 .tot tr.final td.tt { border-bottom: 2px solid #007bff; font-weight: bold; color: #007bff; font-size: 10.5px; }
        .a4 .representacion { background: #6c757d; color: #fff; font-weight: bold; font-size: 8px; border-radius: 6px; padding: 7px; text-align: center; }
        .a4 .pie { background: #f1f3f5; border-radius: 6px; padding: 10px; text-align: center; font-size: 7.5px; color: #4b5563; margin-top: 14px; }
    </style>
</head>
<body>
<div class="a4">
    <table class="cab">
        <tr>
            <td style="width:64%">
                <div class="empresa">{{ $emisor['nombre'] }}</div>
                <div class="info">{{ $emisor['nota'] }}</div>
            </td>
            <td style="width:36%">
                <div class="numeracion">
                    <div class="ruc">RUC {{ $emisor['ruc'] }}</div>
                    <div class="tipo">{{ $tdodes }}</div>
                    <div class="num">{{ $numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="cliente">
        <span class="lbl">Razón Social:</span> {{ $receptor['nombre'] ?: '-' }}<br>
        <span class="lbl">{{ $tipoDocReceptor }}:</span> {{ $receptor['doc'] ?: '-' }}<br>
        <span class="lbl">Dirección:</span> {{ $receptor['direccion'] ?: '-' }}
    </div>

    <div class="caja">
        <table>
            <tr><th>Fecha Emisión</th><th>Moneda</th><th>Líneas</th></tr>
            <tr>
                <td>{{ $x->fecha ? \Carbon\Carbon::parse($x->fecha)->format('d-m-Y') : '-' }}</td>
                <td>{{ $x->moneda === 'USD' ? 'DÓLARES' : 'SOLES' }}</td>
                <td>{{ $lineas->count() }}</td>
            </tr>
        </table>
    </div>

    <table class="det">
        <thead><tr><th style="width:10%">CANT.</th><th style="width:48%">DESCRIPCIÓN</th><th style="width:10%">U.D.M</th><th style="width:16%">V. UNIT.</th><th style="width:16%">VALOR</th></tr></thead>
        <tbody>
            @foreach ($lineas as $i => $l)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td class="c">{{ $cant($l->cantidad) }}</td>
                    <td>{{ $l->descripcion }}@if ($l->codigo)<br><span style="color:#6b7280">Cód. {{ $l->codigo }}</span>@endif</td>
                    <td class="c">{{ $l->unidad }}</td>
                    <td class="r">{{ number_format($l->precio, 2) }}</td>
                    <td class="r">{{ number_format($l->base, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $filas = [];
        if ($gravada) { $filas[] = ['OP. GRAVADA', $gravada]; }
        if ($noGravada) { $filas[] = ['OP. NO GRAVADA', $noGravada]; }
        $filas[] = ['IGV', (float) $x->igv];
        $n = count($filas) + 1;
    @endphp
    <table class="tot">
        @foreach ($filas as $k => [$etiqueta, $monto])
            <tr>
                @if ($k === 0)
                    <td rowspan="{{ $n }}" style="width:28%; border-right:2px solid #8f969e; vertical-align:top; padding:10px">
                        <div class="letras">{{ \App\Support\Sunat\NumeroLetras::convertir((float) $x->total) }}</div>
                        <div class="c"><img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:85px; height:85px"></div>
                    </td>
                    <td rowspan="{{ $n }}" style="width:40%; border-right:2px solid #8f969e; vertical-align:middle; padding:10px" class="c">
                        <div class="representacion">REPRESENTACIÓN DE LA {{ $tdodes }} SEGÚN SU XML</div>
                    </td>
                @endif
                <td class="tt lbl" style="width:14%; white-space:nowrap; padding-left:10px">{{ $etiqueta }}</td>
                <td class="tt" style="width:6%">{{ $mon }}</td>
                <td class="tt r" style="width:12%; padding-right:10px">{{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach
        <tr class="final">
            <td class="tt" style="white-space:nowrap; padding-left:10px">IMPORTE TOTAL</td>
            <td class="tt">{{ $mon }}</td>
            <td class="tt r" style="padding-right:10px">{{ number_format($x->total, 2) }}</td>
        </tr>
    </table>

    <div class="pie">
        Documento generado por {{ config('marca.nombre') }} con los datos del XML que el proveedor envió a SUNAT. No reemplaza al comprobante original del emisor.<br>
        Consúltalo en SUNAT: RUC {{ $emisor['ruc'] }} · {{ $numero }}
    </div>
</div>
</body>
</html>
