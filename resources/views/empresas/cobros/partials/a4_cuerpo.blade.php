{{--
    Comprobante A4 con el diseño del sistema antiguo (logo, empresa, recuadro azul con RUC y número, cliente, fechas,
    detalle, SON + QR, totales y pie). Lo usan el PDF (Dompdf) y la vista A4 del navegador, para que salgan iguales.
    Solo tablas, colores sólidos y bordes: lo que Dompdf dibuja bien.
    Variables: $cab, $detalle, $empresa, $negocio, $tdodes, $paraPdf (true = rutas de archivo para Dompdf)
--}}
@php
    $numero = $cab->serdoc . '-' . str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT);
    $electronico = in_array($cab->tdocod, ['01', '03', '07', '08'], true);
    $esNota = in_array($cab->tdocod, ['07', '08'], true);
    $ruta = function (?string $archivo) use ($paraPdf) {
        if (!$archivo || !is_file(public_path($archivo))) {
            return null;
        }
        return $paraPdf ? public_path($archivo) : asset($archivo);
    };
    $logo = $ruta($negocio->logo_suc ?? null) ?? $ruta($empresa->LogEmpresa ?? null);
    // QR de pago (Yape/Plin) opcional por empresa: public/imagenes/qr-pago-{RUC}.png
    $qrPago = $ruta('imagenes/qr-pago-' . $cab->IdEmpresa . '.png');
    $tipoDoc = \Illuminate\Support\Facades\DB::table('tipo_documento_identidad')->where('tdicod', $cab->tdicod)->value('tdides') ?: 'DOC.';
    $motivoNota = $esNota ? ($cab->tdocod === '07'
        ? \Illuminate\Support\Facades\DB::table('tipo_nota_credito')->where('nccod', $cab->tipnot)->value('ncdes')
        : \Illuminate\Support\Facades\DB::table('tipo_nota_debito')->where('ndcod', $cab->tipnot)->value('nddes')) : null;
    $qr = $electronico ? \App\Support\Sunat\CodigoQr::svg($cab, 110) : null;
    $cant = fn($n) => rtrim(rtrim(number_format($n, 3), '0'), '.');
    $mon = ($cab->moncod ?? 'PEN') === 'USD' ? '$' : 'S/';
    $fecha = fn($f) => $f ? \Carbon\Carbon::parse($f)->format('d-m-Y') : '-';
    $soporte = config('soporte');
@endphp
<style>
    .a4 { font-family: 'DejaVu Sans', 'Segoe UI', Tahoma, sans-serif; color: #2c3e50; font-size: 9px; }
    .a4 table { width: 100%; border-collapse: collapse; }
    .a4 .r { text-align: right; } .a4 .c { text-align: center; }
    .a4 .cab { background: #f1f3f5; border-bottom: 3px solid #007bff; }
    .a4 .cab td { vertical-align: middle; padding: 10px; }
    .a4 .empresa { font-size: 17px; font-weight: bold; color: #2c3e50; }
    .a4 .razon { font-size: 10px; font-weight: bold; color: #6c757d; margin-top: 2px; }
    .a4 .info { font-size: 8.5px; color: #6c757d; line-height: 1.45; margin-top: 4px; }
    .a4 .numeracion { background: #fff; border: 2px solid #007bff; border-radius: 12px; text-align: center; padding: 10px 8px; }
    .a4 .numeracion .ruc { font-size: 11px; font-weight: bold; color: #495057; }
    .a4 .numeracion .tipo { background: #007bff; color: #fff; font-size: 11.5px; font-weight: bold; border-radius: 6px; padding: 8px 4px; margin: 7px 0; }
    .a4 .numeracion .num { font-size: 13px; font-weight: bold; color: #007bff; }
    .a4 .cliente { background: #f8f9fa; border-left: 4px solid #007bff; padding: 9px 12px; margin-top: 12px; line-height: 1.6; }
    .a4 .lbl { font-weight: bold; color: #2c3e50; }
    .a4 .caja { border: 2px solid #dee2e6; border-radius: 10px; padding: 8px; margin-top: 10px; }
    .a4 .caja th { background: #6c757d; color: #fff; font-size: 8.5px; padding: 7px 6px; }
    .a4 .caja td { text-align: center; padding: 7px 6px; font-size: 8.5px; }
    .a4 .nc { border: 2px solid #dee2e6; border-left: 5px solid #dc3545; border-radius: 10px; padding: 8px 12px; margin-top: 10px; }
    .a4 .nc .titulo { color: #dc3545; font-weight: bold; font-size: 8.5px; text-transform: uppercase; border-bottom: 1px solid #f1f3f5; padding-bottom: 4px; margin-bottom: 4px; }
    .a4 .nc .k { display: block; color: #6c757d; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
    .a4 .nc .v { font-weight: bold; font-size: 9px; }
    .a4 .det { margin-top: 12px; border: 2px solid #dee2e6; }
    .a4 .det th { background: #495057; color: #fff; font-size: 9.5px; padding: 9px 6px; text-align: center; }
    .a4 .det td { padding: 8px 6px; font-size: 8.5px; border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
    .a4 .det tr.par td { background: #f8f9fa; }
    .a4 .tot { margin-top: 14px; border: 2px solid #dee2e6; }
    .a4 .letras { background: #f8f9fa; border-left: 4px solid #007bff; border-radius: 6px; padding: 7px; font-size: 8px; margin-bottom: 8px; }
    .a4 .tot td.tt { background: #f1f3f5; padding: 6px 4px; font-size: 9px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
    .a4 .tot tr.final td.tt { border-bottom: 2px solid #007bff; font-weight: bold; color: #007bff; font-size: 10.5px; }
    .a4 .representacion { background: #6c757d; color: #fff; font-weight: bold; font-size: 8px; border-radius: 6px; padding: 7px; text-align: center; margin-top: 8px; }
    .a4 .pie { background: #f1f3f5; border-radius: 6px; padding: 10px; text-align: center; font-size: 7.5px; color: #6c757d; margin-top: 14px; }
    .a4 .pie a { color: #007bff; font-weight: bold; text-decoration: none; }
    .a4 .anulado { border: 2px solid #dc3545; color: #dc3545; font-weight: bold; text-align: center; padding: 6px; margin-top: 10px; border-radius: 6px; }
</style>

<div class="a4">
    {{-- Cabecera --}}
    <table class="cab">
        <tr>
            <td style="width:23%">@if ($logo)<img src="{{ $logo }}" style="max-width:140px; max-height:90px">@endif</td>
            <td style="width:45%" class="c">
                @if (!empty($negocio->nombre_comercial) && $negocio->nombre_comercial !== ($empresa->NomEmpresa ?? ''))
                    <div class="empresa">{{ $negocio->nombre_comercial }}</div>
                    <div class="razon">{{ $empresa->NomEmpresa ?? '' }}</div>
                @else
                    <div class="empresa">{{ $empresa->NomEmpresa ?? '' }}</div>
                @endif
                <div class="info">
                    {{ $negocio->direccion ?? '' }}<br>
                    @if (!empty($negocio->departamento)){{ $negocio->departamento }} - {{ $negocio->provincia }} - {{ $negocio->distrito }}<br>@endif
                    @if (!empty($negocio->telefono)){{ $negocio->telefono }}@endif
                    @if (!empty($negocio->correo)) &nbsp; {{ $negocio->correo }}@endif
                </div>
            </td>
            <td style="width:32%">
                <div class="numeracion">
                    <div class="ruc">RUC {{ $cab->IdEmpresa }}</div>
                    <div class="tipo">{{ $tdodes }}</div>
                    <div class="num">{{ $numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Cliente --}}
    <div class="cliente">
        <span class="lbl">Razón Social:</span> {{ $cab->ccanom }}
        @if (!empty($cab->clicorcli)) &nbsp;&nbsp;&nbsp; <span class="lbl">Correo:</span> {{ $cab->clicorcli }}@endif<br>
        <span class="lbl">{{ $tipoDoc }}:</span> {{ $cab->ccandi }}<br>
        <span class="lbl">Dirección:</span> {{ $cab->direccion && $cab->direccion !== '--' ? $cab->direccion : '-' }}
        @if (!empty($cab->placa)) &nbsp;&nbsp;&nbsp; <span class="lbl">Placa:</span> {{ $cab->placa }}@endif
    </div>

    @if ($esNota)
        <div class="nc">
            <div class="titulo">Información de la nota y comprobante afectado</div>
            <table>
                <tr>
                    <td style="width:22%"><span class="k">Fecha emisión</span><span class="v">{{ $fecha($cab->ccafem) }}</span></td>
                    <td style="width:38%"><span class="k">Motivo</span><span class="v">{{ $cab->tipnot }} - {{ $motivoNota }}</span></td>
                    <td style="width:22%"><span class="k">Comprobante afectado</span><span class="v">{{ $cab->serie_ref }}-{{ $cab->num_ref ? str_pad($cab->num_ref, 8, '0', STR_PAD_LEFT) : '' }}</span></td>
                    <td style="width:18%"><span class="k">Fecha emisión ref.</span><span class="v">{{ $fecha($cab->ccafem_ref ?? null) }}</span></td>
                </tr>
            </table>
        </div>
    @else
        <div class="caja">
            <table>
                <tr><th>Fecha Emisión</th><th>Fecha Vencimiento</th><th>Condición Pago</th><th>Número Guía</th></tr>
                <tr>
                    <td>{{ $fecha($cab->ccafem) }}</td>
                    <td>{{ $fecha($cab->ccafve ?: $cab->ccafem) }}</td>
                    <td>{{ $cab->estadopago ?: '-' }}</td>
                    <td>{{ !empty($cab->guia_remision) ? $cab->guia_remision : '-' }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if (!empty($cab->anulado_nc))
        <div class="anulado">ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</div>
    @elseif (!empty($cab->ccabaj))
        <div class="anulado">{{ $cab->ccabaj }}</div>
    @endif

    {{-- Detalle --}}
    <table class="det">
        <thead><tr><th style="width:10%">CANT.</th><th style="width:50%">DESCRIPCIÓN</th><th style="width:10%">U.D.M</th><th style="width:15%">P.U</th><th style="width:15%">TOTAL</th></tr></thead>
        <tbody>
        @if (!empty($cab->consumo))
            <tr><td class="c">1</td><td>POR CONSUMO</td><td class="c">NIU</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $i => $d)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td class="c">{{ $cant($d->cdecan) }}</td>
                    <td>{{ $d->cdedes }}@if (!empty($d->lotes))<br><span style="color:#0f766e">Lote: {{ $d->lotes }}</span>@endif</td>
                    <td class="c">{{ $d->umecod }}</td>
                    <td class="r">{{ number_format($d->cdepuni, 2) }}</td>
                    <td class="r">{{ number_format($d->cdevve, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @if ($cab->ccaobs)<p style="margin:6px 0 0"><span class="lbl">Observación:</span> {{ $cab->ccaobs }}</p>@endif

    {{-- SON + QR | QR de pago | totales (una sola tabla con rowspan: Dompdf corta las tablas anidadas) --}}
    @php
        $filas = [['SUBTOTAL', $cab->ccatexo + $cab->ccatvg]];
        if ($cab->ccatvg > 0) { $filas[] = ['OP. GRAVADA', $cab->ccatvg]; }
        if ($cab->ccatexo > 0) { $filas[] = ['OP. EXONERADA', $cab->ccatexo]; }
        $filas[] = ['IGV', $cab->ccaigv];
        $n = count($filas) + 1;
    @endphp
    <table class="tot">
        @foreach ($filas as $k => [$etiqueta, $monto])
            <tr>
                @if ($k === 0)
                    <td rowspan="{{ $n }}" style="width:28%; border-right:2px solid #dee2e6; vertical-align:top; padding:10px">
                        <div class="letras"><span class="lbl">SON:</span> {{ \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv) }}</div>
                        @if ($qr)<div class="c"><img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:85px; height:85px"></div>@endif
                    </td>
                    <td rowspan="{{ $n }}" style="width:40%; border-right:2px solid #dee2e6; vertical-align:top; padding:10px" class="c">
                        @if ($qrPago && !$esNota)<img src="{{ $qrPago }}" style="max-width:200px; max-height:130px">@endif
                        <div class="representacion">REPRESENTACIÓN IMPRESA DE LA {{ $tdodes }}</div>
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
            <td class="tt r" style="padding-right:10px">{{ number_format($cab->ccaitv, 2) }}</td>
        </tr>
    </table>

    @php $fid = \Illuminate\Support\Facades\DB::table('fid_movimientos')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('tipo', 'VENTA')->first(['puntos', 'saldo']); @endphp
    @if ($fid)
        <div style="margin-top:10px; border:2px dashed #7c3aed; color:#5b21b6; border-radius:8px; padding:7px; text-align:center; font-weight:bold; font-size:9.5px;">
            ★ Con esta compra ganaste {{ $fid->puntos }} puntos · Tus puntos acumulados: {{ $fid->saldo }} ★</div>
    @endif

    <div class="pie">
        @if ($electronico)Representación impresa del comprobante electrónico · Consúltelo en www.sunat.gob.pe<br>@endif
        <strong>"BIENES TRANSFERIDOS EN LA AMAZONÍA PARA SER CONSUMIDOS EN LA MISMA" - "SERVICIOS PRESTADOS EN LA AMAZONÍA"</strong><br>
        SISTEMA DESARROLLADO POR <a href="https://holape.app">{{ $soporte['nombre'] ?? 'HOLAPE EIRL' }}</a> - {{ $soporte['telefono'] ?? '' }}
    </div>
</div>
