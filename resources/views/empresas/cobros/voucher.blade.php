@php
    // Diseño como el ticket del sistema antiguo: logo, recuadro con RUC y número, datos con etiqueta y "SON:"
    $numero = $cab->serdoc . '-' . (int) $cab->numdoc;
    $logo = collect([$negocio->logo_suc ?? null, $empresa->LogEmpresa ?? null])->first(fn($l) => $l && is_file(public_path($l)));
    $nombre = ($negocio->nombre_comercial ?? null) ?: ($empresa->NomEmpresa ?? '');
    $ubicacion = !empty($negocio->distrito) && !str_contains(mb_strtoupper((string) $negocio->direccion), mb_strtoupper($negocio->distrito))
        ? trim($negocio->departamento . ' - ' . $negocio->provincia . ' - ' . $negocio->distrito, ' -') : null;
    $vendedor = \Illuminate\Support\Facades\DB::table('users')->where('IdUsuario', $cab->IdUsuario_ven ?: $cab->IdUsuario)->first(['name', 'apeusu']);
    $unidades = \Illuminate\Support\Facades\DB::table('unidad_medida')->pluck('umenom', 'umecod');
    $medioPago = $medios->pluck('nom_med_pag')->filter()->unique()->implode(' / ');
    $esNota = in_array($cab->tdocod, ['07', '08'], true);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tdodes }} {{ $numero }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; background: #eee; margin: 0; padding: 15px; }
        .ticket { width: 290px; margin: 0 auto; background: #fff; padding: 10px 12px; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        .logo { max-width: 150px; max-height: 110px; object-fit: contain; display: block; margin: 0 auto 4px; }
        .empresa { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .caja { border: 1px solid #000; margin: 8px 0; padding: 6px 4px; text-align: center; font-weight: bold; font-size: 12px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 1px 0; vertical-align: top; }
        .datos td:first-child { font-weight: bold; white-space: nowrap; padding-right: 6px; width: 1%; }
        .items { margin-top: 8px; }
        .items th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 1px; font-size: 10.5px; text-align: left; }
        .items td { padding: 3px 1px; vertical-align: top; font-size: 10.5px; }
        .items .um { color: #b91c1c; font-size: 9px; }
        .items .r { padding-left: 5px; white-space: nowrap; }
        .items tbody tr:last-child td { border-bottom: 1px solid #000; }
        .totales td { padding: 2px 0; }
        .totales td:first-child { text-align: right; font-weight: bold; padding-right: 8px; }
        .totales td:last-child { text-align: right; width: 70px; }
        .total td { font-size: 13px; font-weight: bold; }
        .son { margin: 6px 0; font-size: 10px; }
        .qr { margin: 6px 0 4px; } .qr svg { width: 120px; height: 120px; }
        .nota { font-size: 9.5px; }
        .medios td { font-size: 13px; font-weight: bold; padding-top: 4px; }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; }
        .b1 { background: #28a745; color: #fff; } .b2 { background: #3498db; color: #fff; }
        body.embed { background: #fff; padding: 8px 0; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .ticket { width: 100%; padding: 0; } }
    </style>
</head>
<body class="{{ request('embed') ? 'embed' : '' }}">
<div class="ticket">
    <div class="c">
        @if ($logo)<img src="{{ asset($logo) }}" alt="" class="logo">@endif
        <div class="empresa">{{ $nombre }}</div>
        @if ($nombre !== ($empresa->NomEmpresa ?? $nombre))<div>{{ $empresa->NomEmpresa }}</div>@endif
        <div>{{ $negocio->direccion ?? '' }}</div>
        @if ($ubicacion)<div>{{ $ubicacion }}</div>@endif
        @if (!empty($negocio->telefono))<div>Tel: {{ $negocio->telefono }}</div>@endif
    </div>

    <div class="caja">
        R.U.C.: {{ $cab->IdEmpresa }}<br>
        {{ mb_strtoupper($tdodes) }}<br>
        {{ $numero }}
    </div>

    <table class="datos">
        <tr><td>Fecha:</td><td>{{ \Carbon\Carbon::parse($cab->fecha_hora)->format('d-m-Y H:i:s') }}</td></tr>
        <tr><td>Cliente:</td><td>{{ $cab->ccanom }}</td></tr>
        <tr><td>{{ $cab->tdicod === '6' ? 'RUC:' : 'DNI/RUC:' }}</td><td>{{ $cab->ccandi }}</td></tr>
        <tr><td>Dirección:</td><td>{{ $cab->direccion && $cab->direccion !== '--' ? $cab->direccion : '-' }}</td></tr>
        @if ($esNota)
            {{-- Nota de crédito / débito: documento que modifica y motivo --}}
            <tr><td>Modifica:</td><td>{{ $cab->tdocod_ref === '01' ? 'FACTURA' : 'BOLETA' }} {{ $cab->serie_ref }}-{{ (int) $cab->num_ref }}</td></tr>
            @if ($cab->ccafem_ref)<tr><td>Fecha doc.:</td><td>{{ \Carbon\Carbon::parse($cab->ccafem_ref)->format('d-m-Y') }}</td></tr>@endif
            <tr><td>Motivo:</td><td>{{ $cab->tipnot }} - {{ $cab->tdocod === '07'
                ? \Illuminate\Support\Facades\DB::table('tipo_nota_credito')->where('nccod', $cab->tipnot)->value('ncdes')
                : \Illuminate\Support\Facades\DB::table('tipo_nota_debito')->where('ndcod', $cab->tipnot)->value('nddes') }}</td></tr>
        @else
            <tr><td>M. Pago:</td><td>{{ $cab->estadopago }}@if ($cab->estadopago === 'CREDITO') · vence {{ \Carbon\Carbon::parse($cab->ccafve)->format('d-m-Y') }}@endif</td></tr>
        @endif
        @if ($vendedor)<tr><td>Vendedor:</td><td>{{ trim($vendedor->name . ' ' . $vendedor->apeusu) }}</td></tr>@endif
        @if (!empty($cab->placa))<tr><td>Placa:</td><td class="b">{{ $cab->placa }}</td></tr>@endif
        @if (!empty($cab->guia_remision))<tr><td>Guía:</td><td>{{ $cab->guia_remision }}</td></tr>@endif
        @if ($cab->ccaobs)<tr><td>Obs.:</td><td class="b">{{ $cab->ccaobs }}</td></tr>@endif
    </table>
    @if ($cab->anulado_nc)<div class="c b" style="margin-top:4px;">ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</div>@endif

    <table class="items">
        <thead><tr><th>Descrip.</th><th>U.M.</th><th class="r">Cant.</th><th class="r">P.U.</th><th class="r">Total</th></tr></thead>
        <tbody>
        @if ($cab->consumo)
            <tr><td>POR CONSUMO</td><td class="um">UNIDAD</td><td class="r">1.00</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $d)
                <tr>
                    <td>{{ $d->cdedes }}@if (!empty($d->lotes))<br><small>Lote: {{ $d->lotes }}</small>@endif</td>
                    <td class="um">{{ mb_strtoupper($unidades[$d->umecod] ?? $d->umecod) }}</td>
                    <td class="r">{{ number_format($d->cdecan, 2) }}</td>
                    <td class="r">{{ number_format($d->cdepuni, 2) }}</td>
                    <td class="r">{{ number_format($d->cdevve, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>

    <table class="totales" style="margin-top:4px;">
        @if ($cab->ccatvg > 0)<tr><td>OP. GRAVADA S/</td><td>{{ number_format($cab->ccatvg, 2) }}</td></tr>@endif
        @if ($cab->ccatexo > 0)<tr><td>OP. EXONE. S/</td><td>{{ number_format($cab->ccatexo, 2) }}</td></tr>@endif
        <tr><td>IGV S/</td><td>{{ number_format($cab->ccaigv, 2) }}</td></tr>
        <tr class="total"><td>TOTAL S/</td><td>{{ number_format($cab->ccaitv, 2) }}</td></tr>
    </table>
    <div class="son">{{ str_replace(['SON ', ' CON '], ['SON: ', ' Y '], \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv)) }}</div>

    @if (\App\Support\Sunat\CodigoQr::aplica($cab))
        <div class="c nota b">Representación impresa de la {{ mb_strtoupper($tdodes) }}</div>
        {{-- QR SUNAT: resumen del comprobante para validarlo --}}
        <div class="c qr">{!! \App\Support\Sunat\CodigoQr::svg($cab, 120) !!}</div>
        @if ($cab->ccaqr)<div class="c" style="font-size:8.5px; word-break:break-all;">Hash: {{ $cab->ccaqr }}</div>@endif
        <div class="c nota">Consulte en www.sunat.gob.pe</div>
    @endif
    <div class="c nota b" style="margin-top:6px;">"BIENES Y/O SERVICIOS TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA"</div>

    <table class="medios" style="margin-top:6px;">
        @foreach ($medios as $m)
            <tr><td>{{ mb_strtoupper($m->nom_med_pag ?? 'PAGO') }} S/</td><td class="r">{{ number_format($m->monto, 2) }}</td></tr>
        @endforeach
        @if ($cab->paga > 0 && $cab->vuelto > 0)
            <tr><td style="font-size:11px;">PAGA CON S/</td><td class="r" style="font-size:11px;">{{ number_format($cab->paga, 2) }}</td></tr>
            <tr><td style="font-size:11px;">VUELTO S/</td><td class="r" style="font-size:11px;">{{ number_format($cab->vuelto, 2) }}</td></tr>
        @endif
    </table>
    {{-- Fidelización: los puntos que ganó con esta compra y su saldo en ese momento --}}
    @php $fid = \Illuminate\Support\Facades\DB::table('fid_movimientos')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('tipo', 'VENTA')->first(['puntos', 'saldo']); @endphp
    @if ($fid)
        <div class="c b" style="margin-top:6px; border:1px dashed #000; padding:4px;">★ GANASTE {{ $fid->puntos }} PUNTOS ★<br><span style="font-weight:normal">Tus puntos acumulados: {{ $fid->saldo }}</span></div>
    @endif
</div>

@unless (request('embed'))
<div class="acciones">
    <button class="b2" onclick="window.print()">IMPRIMIR</button>
    <a class="b2" style="background:#6b7280;" href="{{ request()->fullUrlWithQuery(['formato' => 'a4']) }}">VER EN A4</a>
    @if ($pedidoPendiente)
        <a class="b2" style="background:#8e44ad;" href="{{ route('cobros.separadas', $pedidoPendiente) }}">COBRAR SIGUIENTE CUENTA</a>
    @endif
    @if ($esNota)
        <a class="b1" href="{{ route('notas.index') }}">VOLVER A NOTAS</a>
    @elseif ($cab->ped_tip === 'SOCIO')
        <a class="b1" href="{{ route('socios.index') }}">VOLVER A SOCIOS</a>
    @elseif ($cab->ped_tip === 'Clinica')
        <a class="b1" href="{{ route('clinica.agenda') }}">VOLVER A LA AGENDA</a>
    @elseif ($cab->ped_tip === 'Hotel')
        <a class="b1" href="{{ route('hotel.index') }}">VOLVER A HABITACIONES</a>
    @elseif ($cab->ped_tip === 'PVCOMANDA')
        <a class="b1" href="{{ route('cobros.directa') }}">NUEVA VENTA</a>
    @elseif (in_array($cab->ped_tip, ['POS', 'PV', 'TACTIL', 'FARMACIA', 'GRIFO'], true))
        <a class="b1" href="{{ route(['PV' => 'pv.index', 'TACTIL' => 'pv.tactil', 'POS' => 'pos.movil', 'FARMACIA' => 'pv.farmacia', 'GRIFO' => 'pv.grifo'][$cab->ped_tip]) }}">NUEVA VENTA</a>
    @else
        <a class="b1" href="{{ route('comandas.seleccion') }}">VOLVER A MESAS</a>
    @endif
</div>
@endunless

@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
@unless (request('embed'))@include('partials.avisos')@endunless
</body>
</html>
