{{--
    Guía de remisión A4 con el mismo diseño del comprobante A4 (cabecera gris con logo, recuadro azul con RUC y número,
    cajas con borde y detalle). Lo usan la vista del navegador y el PDF (Dompdf): solo tablas, colores sólidos y bordes.
    Lleva QR siempre: el de SUNAT cuando ya fue aceptada; mientras tanto, el de la página pública de verificación.
    Variables: $g, $detalle, $empresa, $negocio, $motivos, $paraPdf
--}}
@php
    $numero = $g->serie.'-'.str_pad($g->numero, 8, '0', STR_PAD_LEFT);
    $ruta = function (?string $archivo) use ($paraPdf) {
        if (! $archivo || ! is_file(public_path($archivo))) {
            return null;
        }

        return $paraPdf ? public_path($archivo) : asset($archivo);
    };
    $logo = $ruta($negocio->logo_suc ?? null) ?? $ruta($empresa->LogEmpresa ?? null);
    $enlaceQr = $g->qr ?: ($g->token ? route('guias.verificar', $g->token) : null);
    $qr = $enlaceQr ? (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(160, 1), new \BaconQrCode\Renderer\Image\SvgImageBackEnd)))->writeString($enlaceQr) : null;
    $docs = ['1' => 'DNI', '4' => 'CARNÉ EXT.', '6' => 'RUC', '7' => 'PASAPORTE', '0' => 'DOC.'];
    $relacionados = ['01' => 'FACTURA', '03' => 'BOLETA', '09' => 'GUÍA', '12' => 'TICKET'];
    $fecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d-m-Y') : '-';
    $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $ubigeo = fn ($c) => \App\Support\Ubigeo::nombre($c);
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
    .a4 .numeracion .tipo { background: #007bff; color: #fff; font-size: 10.5px; font-weight: bold; border-radius: 6px; padding: 7px 4px; margin: 7px 0; }
    .a4 .numeracion .num { font-size: 13px; font-weight: bold; color: #007bff; }
    .a4 .bloque { background: #f8f9fa; border-left: 4px solid #007bff; padding: 8px 12px; margin-top: 10px; line-height: 1.6; }
    .a4 .titulo { color: #007bff; font-weight: bold; font-size: 8.5px; text-transform: uppercase; margin-bottom: 2px; }
    .a4 .lbl { font-weight: bold; color: #2c3e50; }
    .a4 .caja { border: 2px solid #dee2e6; border-radius: 10px; padding: 8px; margin-top: 10px; }
    .a4 .caja th { background: #6c757d; color: #fff; font-size: 8.5px; padding: 7px 6px; }
    .a4 .caja td { text-align: center; padding: 7px 6px; font-size: 8.5px; }
    .a4 .dos td { vertical-align: top; width: 50%; }
    .a4 .det { margin-top: 12px; border: 2px solid #dee2e6; }
    .a4 .det th { background: #495057; color: #fff; font-size: 9.5px; padding: 9px 6px; text-align: center; }
    .a4 .det td { padding: 8px 6px; font-size: 8.5px; border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
    .a4 .det tr.par td { background: #f8f9fa; }
    .a4 .final { margin-top: 14px; border: 2px solid #dee2e6; }
    .a4 .final td { vertical-align: middle; padding: 10px; }
    .a4 .representacion { background: #6c757d; color: #fff; font-weight: bold; font-size: 8px; border-radius: 6px; padding: 7px; text-align: center; margin-top: 8px; }
    .a4 .estado { border: 2px solid #f59e0b; color: #b45309; font-weight: bold; text-align: center; padding: 5px; border-radius: 6px; margin-top: 8px; font-size: 8.5px; }
    .a4 .pie { background: #f1f3f5; border-radius: 6px; padding: 10px; text-align: center; font-size: 7.5px; color: #6c757d; margin-top: 14px; }
    .a4 .pie a { color: #007bff; font-weight: bold; text-decoration: none; }
</style>

<div class="a4">
    <table class="cab">
        <tr>
            <td style="width:23%">@if ($logo)<img src="{{ $logo }}" style="max-width:140px; max-height:90px">@endif</td>
            <td style="width:45%" class="c">
                @if (! empty($negocio->nombre_comercial) && $negocio->nombre_comercial !== ($empresa->NomEmpresa ?? ''))
                    <div class="empresa">{{ $negocio->nombre_comercial }}</div>
                    <div class="razon">{{ $empresa->NomEmpresa ?? '' }}</div>
                @else
                    <div class="empresa">{{ $empresa->NomEmpresa ?? '' }}</div>
                @endif
                <div class="info">
                    {{ $negocio->direccion ?? '' }}<br>
                    @if (! empty($negocio->telefono)){{ $negocio->telefono }}@endif
                    @if (! empty($negocio->correo)) &nbsp; {{ $negocio->correo }}@endif
                </div>
            </td>
            <td style="width:32%">
                <div class="numeracion">
                    <div class="ruc">RUC {{ $empresa->IdEmpresa }}</div>
                    <div class="tipo">GUÍA DE REMISIÓN ELECTRÓNICA REMITENTE</div>
                    <div class="num">{{ $numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="bloque">
        <div class="titulo">Destinatario</div>
        <span class="lbl">Razón social:</span> {{ $g->dest_nom }}<br>
        <span class="lbl">{{ $docs[$g->dest_tdicod] ?? 'DOC.' }}:</span> {{ $g->dest_num }}
    </div>

    <div class="caja">
        <table>
            <tr><th>Fecha emisión</th><th>Inicio de traslado</th><th>Motivo de traslado</th><th>Modalidad</th><th>Peso bruto</th><th>Comprobante</th></tr>
            <tr>
                <td>{{ $fecha($g->fecha_emision) }} {{ substr((string) $g->hora_emision, 0, 5) }}</td>
                <td>{{ $fecha($g->fecha_traslado) }}</td>
                <td>{{ $g->motivo === '13' && $g->motivo_desc ? $g->motivo_desc : ($motivos[$g->motivo] ?? $g->motivo) }}</td>
                <td>{{ $g->modalidad === '01' ? 'PÚBLICO' : 'PRIVADO' }}</td>
                <td>{{ $cant($g->peso) }} {{ $g->unidad_peso === 'TNE' ? 'TN' : 'KG' }}{{ $g->bultos ? ' · '.$g->bultos.' bultos' : '' }}</td>
                <td>{{ $g->doc_numero ? ($relacionados[$g->doc_tdocod] ?? '').' '.$g->doc_numero : '-' }}</td>
            </tr>
        </table>
    </div>

    <table class="dos" style="margin-top:10px">
        <tr>
            <td style="padding-right:5px">
                <div class="bloque" style="margin-top:0">
                    <div class="titulo">Punto de partida</div>
                    {{ $g->partida_direccion }}<br>
                    <span class="lbl">{{ $ubigeo($g->partida_ubigeo) ?? '' }}</span> ({{ $g->partida_ubigeo }})
                </div>
            </td>
            <td style="padding-left:5px">
                <div class="bloque" style="margin-top:0">
                    <div class="titulo">Punto de llegada</div>
                    {{ $g->llegada_direccion }}<br>
                    <span class="lbl">{{ $ubigeo($g->llegada_ubigeo) ?? '' }}</span> ({{ $g->llegada_ubigeo }})
                </div>
            </td>
        </tr>
    </table>

    <div class="bloque">
        <div class="titulo">Datos del transporte</div>
        @if ($g->modalidad === '01')
            <span class="lbl">Transportista:</span> {{ $g->transp_nom }} &nbsp; <span class="lbl">RUC:</span> {{ $g->transp_ruc }}
            @if ($g->transp_mtc) &nbsp; <span class="lbl">Registro MTC:</span> {{ $g->transp_mtc }}@endif
        @elseif ($g->vehiculo_m1l)
            Traslado en vehículo de categoría M1 o L.
        @else
            <span class="lbl">Conductor:</span> {{ $g->cond_nombres }} {{ $g->cond_apellidos }}
            &nbsp; <span class="lbl">{{ $docs[$g->cond_tdicod] ?? 'DOC.' }}:</span> {{ $g->cond_num }}
            &nbsp; <span class="lbl">Licencia:</span> {{ $g->cond_licencia }}<br>
            <span class="lbl">Placa del vehículo:</span> {{ $g->placa }}
        @endif
    </div>

    <table class="det">
        <thead><tr><th style="width:6%">N°</th><th style="width:16%">CÓDIGO</th><th style="width:56%">DESCRIPCIÓN</th><th style="width:10%">U.D.M</th><th style="width:12%">CANTIDAD</th></tr></thead>
        <tbody>
            @foreach ($detalle as $i => $d)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $d->codigo ?: '-' }}</td>
                    <td>{{ $d->descripcion }}</td>
                    <td class="c">{{ $d->umecod }}</td>
                    <td class="r">{{ $cant($d->cantidad) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($g->observacion)<p style="margin:6px 0 0"><span class="lbl">Observación:</span> {{ $g->observacion }}</p>@endif

    <table class="final">
        <tr>
            <td style="width:22%; border-right:2px solid #dee2e6" class="c">
                @if ($qr)<img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:105px; height:105px">@endif
            </td>
            <td>
                <div class="representacion">REPRESENTACIÓN IMPRESA DE LA GUÍA DE REMISIÓN ELECTRÓNICA REMITENTE</div>
                <p style="margin:8px 0 0; line-height:1.5">
                    Escanee el código QR para verificar esta guía{{ $g->qr ? ' en SUNAT' : '' }}.
                    @if ($g->hash)<br><span class="lbl">Código hash:</span> {{ $g->hash }}@endif
                </p>
                @if ($g->est_sunat !== 'ACEPTADO')
                    <div class="estado">ESTADO EN SUNAT: {{ $g->est_sunat }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="pie">
        Representación impresa de la guía de remisión electrónica · Consúltela en www.sunat.gob.pe<br>
        SISTEMA DESARROLLADO POR <a href="https://holape.app">{{ $soporte['nombre'] ?? 'HOLAPE EIRL' }}</a> - {{ $soporte['telefono'] ?? '' }}
    </div>
</div>
