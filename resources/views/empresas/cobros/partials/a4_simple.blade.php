{{--
    Comprobante A4 simple, en blanco y negro (el modelo de los comprobantes antiguos): razón social con sus direcciones,
    celulares y giro; recuadro del RUC con la franja gris del tipo de documento; detalle con cabecera gris oscura; recuadro
    con el SON, el QR, CANCELADO y la firma, y los totales; leyenda y cuentas bancarias al pie. Grises y negro: gasta poca
    tinta y se lee igual en una impresora en blanco y negro. Lo incluye a4_cuerpo cuando la sucursal eligió el diseño
    SIMPLE y usa sus mismas variables ($numero, $logo, $tipoDoc, $qr, $cant, $mon, $fecha, $esNota, $motivoNota…).
--}}
@php
    $razon = $empresa->NomEmpresa ?? '';
    $comercial = trim((string) ($negocio->nombre_comercial ?? ''));
    $extra = trim((string) ($negocio->cabecera_a4 ?? ''));
    // Si las líneas extra ya traen la dirección de la sucursal, no se repite
    $direccion = trim((string) ($negocio->direccion ?? ''));
    $repiteDireccion = $direccion !== '' && $extra !== '' && str_contains(mb_strtoupper($extra), mb_strtoupper($direccion));
    $lugar = $negocio->distrito ? mb_convert_case(mb_strtolower($negocio->distrito), MB_CASE_TITLE) : '';
    $contado = mb_strtoupper((string) ($cab->estadopago ?? 'CONTADO')) !== 'CREDITO' && ! $esNota;
@endphp
<style>
    .a4s, .a4s * { color: #000; }
    .a4s { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; }
    .a4s table { width: 100%; border-collapse: collapse; }
    .a4s .r { text-align: right; } .a4s .c { text-align: center; }
    .a4s .cab td { vertical-align: top; }
    .a4s .razon { font-family: 'DejaVu Serif', 'Times New Roman', serif; font-size: 13px; font-weight: bold; }
    .a4s .comercial { font-size: 10px; font-weight: bold; margin-top: 1px; }
    .a4s .datos { font-family: 'DejaVu Serif', 'Times New Roman', serif; font-size: 8.5px; line-height: 1.5; margin-top: 2px; }
    .a4s .giro { font-family: 'DejaVu Serif', 'Times New Roman', serif; font-size: 8px; font-style: italic; margin-top: 3px; line-height: 1.35; }
    .a4s .numeracion { border: 1.5px solid #000; border-radius: 10px; text-align: center; font-weight: bold; }
    .a4s .numeracion .ruc { font-size: 12px; padding: 12px 6px 10px; }
    .a4s .numeracion .tipo { background: #7a7a7a; color: #fff; font-size: 10.5px; padding: 9px 6px; }
    .a4s .numeracion .num { font-size: 12px; padding: 10px 6px 12px; }
    .a4s .cliente { margin-top: 12px; font-size: 9.5px; line-height: 1.5; }
    .a4s .caja { border: 1px solid #000; border-radius: 10px; padding: 6px 4px; margin-top: 8px; }
    .a4s .caja th { font-size: 9.5px; padding: 3px; }
    .a4s .caja td { text-align: center; font-size: 9.5px; padding: 2px 3px 3px; }
    .a4s .det { margin-top: 10px; }
    .a4s .det th { background: #6b6b6b; color: #fff; font-size: 8.5px; padding: 4px; border: 1px solid #fff; }
    .a4s .det td { font-size: 8.5px; padding: 3px 4px; vertical-align: top; border-bottom: 1px solid #bdbdbd; }
    .a4s .marco { border: 1px solid #000; border-radius: 10px; padding: 6px 8px; margin-top: 10px; }
    .a4s .marco td { font-size: 8.5px; vertical-align: top; }
    .a4s .tt { padding: 2px 3px; white-space: nowrap; }
    .a4s .anulado { border: 1px solid #000; font-weight: bold; text-align: center; padding: 5px; margin-top: 8px; }
    .a4s .leyenda { font-size: 7.5px; font-weight: bold; margin-top: 8px; line-height: 1.4; }
    .a4s .consulta { font-size: 7.5px; margin-top: 6px; }
</style>

<div class="a4s">
    {{-- Cabecera: logo | razón social, direcciones, celulares y giro | RUC, tipo y número --}}
    <table class="cab">
        <tr>
            @if ($logo)<td style="width:16%"><img src="{{ $logo }}" style="max-width:100px; max-height:85px"></td>@endif
            <td class="c" style="padding-right:8px">
                <div class="razon">{{ $razon }}</div>
                @if ($comercial !== '' && mb_strtoupper($comercial) !== mb_strtoupper($razon))<div class="comercial">{{ $comercial }}</div>@endif
                <div class="datos">
                    @if ($extra !== ''){!! nl2br(e($extra)) !!}<br>@endif
                    @if (! $repiteDireccion && $direccion !== ''){{ $direccion }}<br>@endif
                    @if (!empty($negocio->departamento)){{ mb_convert_case(mb_strtolower($negocio->departamento), MB_CASE_TITLE) }} - {{ mb_convert_case(mb_strtolower((string) $negocio->provincia), MB_CASE_TITLE) }} - {{ $lugar }}<br>@endif
                    @if (!empty($negocio->telefono))CELULAR: {{ $negocio->telefono }}<br>@endif
                    @if (!empty($negocio->correo) || !empty($negocio->web)){{ $negocio->correo }}{{ !empty($negocio->correo) && !empty($negocio->web) ? ' · ' : '' }}{{ $negocio->web }}@endif
                </div>
                @if (!empty($negocio->giro_a4))<div class="giro">{!! nl2br(e($negocio->giro_a4)) !!}</div>@endif
            </td>
            <td style="width:30%">
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
                    <td>{{ !empty($cab->guia_remision) ? $cab->guia_remision : '' }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if (!empty($cab->anulado_nc))
        <div class="anulado">ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</div>
    @elseif (!empty($cab->ccabaj))
        <div class="anulado">{{ $cab->ccabaj }}</div>
    @endif

    {{-- Detalle con cabecera gris oscura --}}
    <table class="det">
        <thead><tr>
            <th style="width:6%">ITEM</th><th style="width:9%">CANT.</th><th>DESCRIPCIÓN</th><th style="width:8%">U.D.M</th>
            <th style="width:10%">P.U</th><th style="width:8%">IGV</th><th style="width:11%">P.VENTA</th>
        </tr></thead>
        <tbody>
        @if (!empty($cab->consumo))
            <tr><td class="c">1</td><td class="c">1</td><td>POR CONSUMO</td><td class="c">NIU</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td>
                <td class="r">{{ number_format($cab->ccaigv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $i => $d)
                @php $linea = \App\Support\Descuentos::linea($d); @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $cant($d->cdecan) }}</td>
                    <td>{!! nl2br(e(($d->descripcion_impresa ?? null) ?: $d->cdedes)) !!}@if ($linea->cortesia)<br><strong>CORTESÍA (operación gratuita)</strong>@endif @if (!empty($d->lotes))<br>Lote: {{ $d->lotes }}@endif @if (!empty($d->series))<br>S/N: {{ $d->series }}@endif</td>
                    <td class="c">{{ $d->umecod }}</td>
                    <td class="r">{{ number_format($linea->precio, 2) }}</td>
                    <td class="r">{{ number_format((float) ($d->cdeigv ?? 0), 2) }}</td>
                    <td class="r">{{ number_format($linea->total, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @php $atencionA4 = \App\Support\Impresion\Impresion::atencion($cab); @endphp
    @if ($atencionA4)<p style="margin:5px 0 0">@foreach ($atencionA4 as [$etiqueta, $nombreAtencion])<strong>{{ $etiqueta }}:</strong> {{ $nombreAtencion }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach</p>@endif
    @if ($cab->ccaobs)<p style="margin:5px 0 0"><strong>Observación:</strong> {{ $cab->ccaobs }}</p>@endif

    {{-- Recuadro: SON + QR | CANCELADO, firma y representación impresa | totales (una tabla con rowspan: Dompdf corta las anidadas) --}}
    @php
        $filas = [];
        if ((float) ($cab->descuento ?? 0) > 0) { $filas[] = ['CONSUMO', $cab->ccaitv + $cab->descuento]; $filas[] = ['DESCUENTO', -$cab->descuento]; }
        $filas[] = ['SUBTOTAL', $cab->ccatexo + $cab->ccatvg + ($cab->ccatinaf ?? 0)];
        if ((float) ($cab->ccagra ?? 0) > 0) { $filas[] = ['OP. GRATUITAS', $cab->ccagra]; }
        $filas[] = ['OP. GRAVADA', $cab->ccatvg];
        $filas[] = ['OP. EXONERADA', $cab->ccatexo];
        $filas[] = ['OP. INAFECTA', $cab->ccatinaf ?? 0];
        $filas[] = ['IGV', $cab->ccaigv];
        $n = count($filas) + 1;
    @endphp
    <div class="marco">
        <table>
            @foreach ($filas as $k => [$etiqueta, $monto])
                <tr>
                    @if ($k === 0)
                        <td rowspan="{{ $n }}" style="width:30%">
                            {{ \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv) }}
                            @if ($qr)<div style="margin-top:5px"><img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:78px; height:78px"></div>@endif
                        </td>
                        <td rowspan="{{ $n }}" style="width:40%" class="c">
                            @if ($contado)<div style="font-size:10px; margin-top:6px">CANCELADO</div>@endif
                            @if ($qrPago && !$esNota)<div style="margin-top:4px"><img src="{{ $qrPago }}" style="max-width:140px; max-height:80px"></div>@endif
                            <div style="margin-top:12px">{{ $lugar ?: '' }}____de_________________del 20___</div>
                            <div style="margin-top:18px">______________________________</div>
                            <div style="margin-top:10px">REPRESENTACIÓN IMPRESA DE LA {{ $tdodes }}</div>
                        </td>
                    @endif
                    <td class="tt" style="width:15%"><strong>{{ $etiqueta }}</strong></td>
                    <td class="tt" style="width:4%">{{ $mon }}</td>
                    <td class="tt r" style="width:11%">{{ number_format($monto, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="tt"><strong>IMPORTE TOTAL</strong></td>
                <td class="tt">{{ $mon }}</td>
                <td class="tt r"><strong>{{ number_format($cab->ccaitv, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    @php $fid = \App\Support\Fidelizacion::delComprobante((int) $cab->IdCpe_cabecera); @endphp
    @if ($fid)
        <div style="margin-top:8px; border:1px dashed #000; padding:5px; text-align:center; font-weight:bold; font-size:9px;">
            Tus puntos: tenías {{ $fid['antes'] }}@if ($fid['ganados']) · ganaste +{{ $fid['ganados'] }}@endif @if ($fid['premio']) · canjeaste −{{ $fid['canjeados'] }}@endif · te quedan {{ $fid['saldo'] }}
            @if ($fid['premio'])<br>PREMIO ENTREGADO: {{ $fid['cantidad'] ? rtrim(rtrim(number_format($fid['cantidad'], 3), '0'), '.').' × ' : '' }}{{ $fid['premio'] }}@endif
        </div>
    @endif

    {{-- Solo en sucursales exoneradas (afectación 20) --}}
    @if (\App\Support\Impresion\Impresion::leyendaAmazonia($cab))
        <div class="leyenda c">
            "BIENES TRANSFERIDOS EN LA AMAZONÍA PARA SER CONSUMIDOS EN LA MISMA."<br>
            "SERVICIOS PRESTADOS EN LA AMAZONÍA"
        </div>
    @endif

    @include('empresas.cobros.partials.a4_cuentas', ['simple' => true])

    <div class="consulta c">
        @if ($electronico)Consulte su comprobante en {{ \App\Support\ConsultaCpe::direccion() }}<br>@endif
        <span style="font-size:7px">{{ config('soporte.firma_comprobante') }}</span>
    </div>
</div>
