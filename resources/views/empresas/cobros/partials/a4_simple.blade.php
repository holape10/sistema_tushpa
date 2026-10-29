{{--
    Comprobante A4 simple, en blanco y negro (como el del sistema antiguo): sin fondos ni colores, solo texto y líneas negras,
    para gastar poca tinta. Lo incluye a4_cuerpo cuando la sucursal eligió el diseño SIMPLE y usa sus mismas variables
    ($numero, $logo, $tipoDoc, $qr, $cant, $mon, $fecha, $esNota, $motivoNota, $electronico…).
--}}
<style>
    .a4s, .a4s * { color: #000; }
    .a4s { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; }
    .a4s table { width: 100%; border-collapse: collapse; }
    .a4s .r { text-align: right; } .a4s .c { text-align: center; }
    .a4s .cab td { vertical-align: top; }
    .a4s .empresa { font-size: 13px; font-weight: bold; }
    .a4s .lineas { font-size: 8px; font-style: italic; line-height: 1.4; margin-top: 3px; }
    .a4s .numeracion { border: 1px solid #000; border-radius: 10px; text-align: center; padding: 10px 6px; font-size: 11px; font-weight: bold; line-height: 1.9; }
    .a4s .cliente { margin-top: 14px; font-size: 9.5px; line-height: 1.6; }
    .a4s .caja { border: 1px solid #000; border-radius: 8px; padding: 4px; margin-top: 6px; }
    .a4s .caja th { font-size: 8.5px; padding: 3px; }
    .a4s .caja td { text-align: center; font-size: 9px; padding: 3px; }
    .a4s .det { margin-top: 10px; border: 1px solid #000; }
    .a4s .det th { font-size: 8.5px; padding: 5px 4px; border-bottom: 1px solid #000; }
    .a4s .det td { font-size: 8.5px; padding: 4px; vertical-align: top; }
    .a4s .tot { margin-top: 10px; border: 1px solid #000; }
    .a4s .tot td.tt { font-size: 8.5px; padding: 3px 4px; }
    .a4s .anulado { border: 1px solid #000; font-weight: bold; text-align: center; padding: 5px; margin-top: 8px; }
    .a4s .pie { text-align: center; font-size: 8px; font-weight: bold; margin-top: 10px; }
</style>

<div class="a4s">
    {{-- Cabecera: logo | nombre y datos | RUC, tipo y número en un recuadro --}}
    <table class="cab">
        <tr>
            <td style="width:18%">@if ($logo)<img src="{{ $logo }}" style="max-width:110px; max-height:90px">@endif</td>
            <td style="width:52%" class="c">
                <div class="empresa">{{ $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? '') }}</div>
                <div class="lineas">
                    @if (!empty($negocio->nombre_comercial) && $negocio->nombre_comercial !== ($empresa->NomEmpresa ?? ''))DE: {{ $empresa->NomEmpresa ?? '' }}<br>@endif
                    @if (!empty($negocio->cabecera_a4)){!! nl2br(e($negocio->cabecera_a4)) !!}<br>@endif
                    {{ $negocio->direccion ?? '' }}<br>
                    @if (!empty($negocio->departamento)){{ $negocio->departamento }} - {{ $negocio->provincia }} - {{ $negocio->distrito }}<br>@endif
                    @if (!empty($negocio->telefono))<strong>CELULAR: {{ $negocio->telefono }}</strong>@endif
                    @if (!empty($negocio->correo)) &nbsp; {{ $negocio->correo }}@endif
                </div>
            </td>
            <td style="width:30%">
                <div class="numeracion">RUC {{ $cab->IdEmpresa }}<br>{{ $tdodes }}<br>{{ $numero }}</div>
            </td>
        </tr>
    </table>

    {{-- Cliente --}}
    <div class="cliente">
        <strong>Señor(es):</strong> {{ $cab->ccanom }}
        @if (!empty($cab->clicorcli)) &nbsp;&nbsp;&nbsp; <strong>Correo:</strong> {{ $cab->clicorcli }}@endif<br>
        <strong>{{ $tipoDoc }}:</strong> {{ $cab->ccandi }}<br>
        <strong>Dirección:</strong> {{ $cab->direccion && $cab->direccion !== '--' ? $cab->direccion : '-' }}
        @if (!empty($cab->placa)) &nbsp;&nbsp;&nbsp; <strong>Placa:</strong> {{ $cab->placa }}@endif
    </div>

    <div class="caja">
        <table>
            @if ($esNota)
                <tr><th>Fecha Emisión</th><th>Motivo</th><th>Comprobante afectado</th><th>Fecha emisión ref.</th></tr>
                <tr>
                    <td>{{ $fecha($cab->ccafem) }}</td>
                    <td>{{ $cab->tipnot }} - {{ $motivoNota }}</td>
                    <td>{{ $cab->serie_ref }}-{{ $cab->num_ref ? str_pad($cab->num_ref, 8, '0', STR_PAD_LEFT) : '' }}</td>
                    <td>{{ $fecha($cab->ccafem_ref ?? null) }}</td>
                </tr>
            @else
                <tr><th>Fecha Emisión</th><th>Fecha Vencimiento</th><th>Condición Pago</th><th>Número Guía</th></tr>
                <tr>
                    <td>{{ $fecha($cab->ccafem) }}</td>
                    <td>{{ $fecha($cab->ccafve ?: $cab->ccafem) }}</td>
                    <td>{{ $cab->estadopago ?: '-' }}</td>
                    <td>{{ !empty($cab->guia_remision) ? $cab->guia_remision : '-' }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if (!empty($cab->anulado_nc))
        <div class="anulado">ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</div>
    @elseif (!empty($cab->ccabaj))
        <div class="anulado">{{ $cab->ccabaj }}</div>
    @endif

    {{-- Detalle --}}
    <table class="det">
        <thead><tr><th style="width:10%">CANT.</th><th style="width:52%">DESCRIPCIÓN</th><th style="width:8%">U.M.</th><th style="width:15%">P.U</th><th style="width:15%">TOTAL</th></tr></thead>
        <tbody>
        @if (!empty($cab->consumo))
            <tr><td class="c">1</td><td>POR CONSUMO</td><td class="c">NIU</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $d)
                @php $linea = \App\Support\Descuentos::linea($d); @endphp
                <tr>
                    <td class="c">{{ $cant($d->cdecan) }}</td>
                    <td>{{ $d->cdedes }}@if ($linea->cortesia)<br><strong>CORTESÍA (operación gratuita)</strong>@endif @if (!empty($d->lotes))<br>Lote: {{ $d->lotes }}@endif @if (!empty($d->series))<br>S/N: {{ $d->series }}@endif</td>
                    <td class="c">{{ $d->umecod }}</td>
                    <td class="r">{{ number_format($linea->precio, 2) }}</td>
                    <td class="r">{{ number_format($linea->total, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @php $atencionA4 = \App\Support\Impresion\Impresion::atencion($cab); @endphp
    @if ($atencionA4)<p style="margin:5px 0 0">@foreach ($atencionA4 as [$etiqueta, $nombreAtencion])<strong>{{ $etiqueta }}:</strong> {{ $nombreAtencion }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach</p>@endif
    @if ($cab->ccaobs)<p style="margin:5px 0 0"><strong>Observación:</strong> {{ $cab->ccaobs }}</p>@endif

    {{-- SON + QR | representación impresa | totales (una sola tabla con rowspan: Dompdf corta las tablas anidadas) --}}
    @php
        $filas = [];
        if ((float) ($cab->descuento ?? 0) > 0) { $filas[] = ['CONSUMO', $cab->ccaitv + $cab->descuento]; $filas[] = ['DESCUENTO', -$cab->descuento]; }
        $filas[] = ['SUBTOTAL', $cab->ccatexo + $cab->ccatvg];
        if ((float) ($cab->ccagra ?? 0) > 0) { $filas[] = ['OP. GRATUITAS', $cab->ccagra]; }
        if ($cab->ccatvg > 0) { $filas[] = ['OP. GRAVADA', $cab->ccatvg]; }
        if ($cab->ccatexo > 0) { $filas[] = ['OP. EXONERADA', $cab->ccatexo]; }
        if ((float) $cab->ccaigv > 0) { $filas[] = ['IGV', $cab->ccaigv]; }
        $n = count($filas) + 1;
    @endphp
    <table class="tot">
        @foreach ($filas as $k => [$etiqueta, $monto])
            <tr>
                @if ($k === 0)
                    <td rowspan="{{ $n }}" style="width:34%; vertical-align:top; padding:6px; font-size:8px">
                        {{ \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv) }}
                        @if ($qr)<div style="margin-top:4px"><img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:70px; height:70px"></div>@endif
                    </td>
                    <td rowspan="{{ $n }}" style="width:34%; vertical-align:bottom; padding:6px; font-size:8px" class="c">
                        @if ($qrPago && !$esNota)<img src="{{ $qrPago }}" style="max-width:150px; max-height:100px"><br>@endif
                        REPRESENTACIÓN IMPRESA DE LA {{ $tdodes }}
                    </td>
                @endif
                <td class="tt" style="width:16%; white-space:nowrap"><strong>{{ $etiqueta }}</strong></td>
                <td class="tt" style="width:4%">{{ $mon }}</td>
                <td class="tt r" style="width:12%">{{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="tt" style="white-space:nowrap; border-top:1px solid #000"><strong>IMPORTE TOTAL</strong></td>
            <td class="tt" style="border-top:1px solid #000">{{ $mon }}</td>
            <td class="tt r" style="border-top:1px solid #000"><strong>{{ number_format($cab->ccaitv, 2) }}</strong></td>
        </tr>
    </table>

    @php $fid = \App\Support\Fidelizacion::delComprobante((int) $cab->IdCpe_cabecera); @endphp
    @if ($fid)
        <div style="margin-top:8px; border:1px dashed #000; padding:5px; text-align:center; font-weight:bold; font-size:9px;">
            Tus puntos: tenías {{ $fid['antes'] }}@if ($fid['ganados']) · ganaste +{{ $fid['ganados'] }}@endif @if ($fid['premio']) · canjeaste −{{ $fid['canjeados'] }}@endif · te quedan {{ $fid['saldo'] }}
            @if ($fid['premio'])<br>PREMIO ENTREGADO: {{ $fid['cantidad'] ? rtrim(rtrim(number_format($fid['cantidad'], 3), '0'), '.').' × ' : '' }}{{ $fid['premio'] }}@endif
        </div>
    @endif

    <div class="pie">
        @if ($electronico)<span style="font-weight:normal">Consulte su comprobante en {{ \App\Support\ConsultaCpe::direccion() }}</span><br>@endif
        "BIENES TRANSFERIDOS EN LA AMAZONÍA PARA SER CONSUMIDOS EN LA MISMA" - "SERVICIOS PRESTADOS EN LA AMAZONÍA"<br>
        <span style="font-weight:normal; font-size:7.5px">{{ config('soporte.firma_comprobante') }}</span>
    </div>
</div>
