<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tdodes }} {{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; background: #eee; margin: 0; padding: 15px; }
        .ticket { width: 300px; margin: 0 auto; background: #fff; padding: 12px; }
        .c { text-align: center; } .r { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; }
        .b1 { background: #28a745; color: #fff; } .b2 { background: #3498db; color: #fff; }
        .qr { margin: 6px 0 4px; } .qr svg { width: 130px; height: 130px; }
        body.embed { background: #fff; padding: 8px 0; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .ticket { width: 100%; } }
    </style>
</head>
<body class="{{ request('embed') ? 'embed' : '' }}">
<div class="ticket">
    <div class="c">
        <strong>{{ $empresa->NomEmpresa ?? '' }}</strong><br>
        RUC: {{ $cab->IdEmpresa }}<br>
        {{ $negocio->direccion ?? '' }}<br>
        @if (!empty($negocio->departamento)) {{ $negocio->distrito }} - {{ $negocio->provincia }} - {{ $negocio->departamento }}<br>@endif
        @if (!empty($negocio->telefono)) Tel: {{ $negocio->telefono }}<br>@endif
        <hr>
        <strong>{{ $tdodes }}</strong><br>
        <strong>{{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</strong>
    </div>
    <hr>
    Fecha: {{ \Carbon\Carbon::parse($cab->fecha_hora)->format('d/m/Y H:i') }}<br>
    Cliente: {{ $cab->ccanom }}<br>
    Doc: {{ $cab->ccandi }}<br>
    @if ($cab->direccion && $cab->direccion !== '--') Dir: {{ $cab->direccion }}<br>@endif
    @if (in_array($cab->tdocod, ['07', '08'], true))
        {{-- Nota de crédito / débito: documento que modifica y motivo --}}
        Modifica: {{ $cab->tdocod_ref === '01' ? 'FACTURA' : 'BOLETA' }} {{ $cab->serie_ref }}-{{ str_pad($cab->num_ref, 8, '0', STR_PAD_LEFT) }}<br>
        @if ($cab->ccafem_ref)Fecha doc.: {{ \Carbon\Carbon::parse($cab->ccafem_ref)->format('d/m/Y') }}<br>@endif
        Motivo: {{ $cab->tipnot }} - {{ $cab->tdocod === '07'
            ? \Illuminate\Support\Facades\DB::table('tipo_nota_credito')->where('nccod', $cab->tipnot)->value('ncdes')
            : \Illuminate\Support\Facades\DB::table('tipo_nota_debito')->where('ndcod', $cab->tipnot)->value('nddes') }}
    @else
        Condición: {{ $cab->estadopago }}
        @if ($cab->estadopago === 'CREDITO')<br>Vence: {{ \Carbon\Carbon::parse($cab->ccafve)->format('d/m/Y') }}@endif
    @endif
    @if ($cab->anulado_nc)<br><strong>ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</strong>@endif
    @if (!empty($cab->placa))<br>Placa: <strong>{{ $cab->placa }}</strong>@endif
    @if (!empty($cab->guia_remision))<br>Guía: {{ $cab->guia_remision }}@endif
    @if ($cab->ccaobs)<br><strong>{{ $cab->ccaobs }}</strong>@endif
    <hr>
    <table>
        <thead><tr><td>DESCRIPCION</td><td class="r">CANT</td><td class="r">P.U</td><td class="r">TOTAL</td></tr></thead>
        <tbody>
        @if ($cab->consumo)
            <tr><td>POR CONSUMO</td><td class="r">1</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $d)
                <tr>
                    <td>{{ $d->cdedes }}@if (!empty($d->lotes))<br><small>Lote: {{ $d->lotes }}</small>@endif</td>
                    <td class="r">{{ rtrim(rtrim(number_format($d->cdecan, 3), '0'), '.') }}</td>
                    <td class="r">{{ number_format($d->cdepuni, 2) }}</td>
                    <td class="r">{{ number_format($d->cdevve, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <hr>
    @foreach ($medios as $m)
        <div style="display:flex; justify-content:space-between;"><span>{{ $m->nom_med_pag }}</span><span>S/ {{ number_format($m->monto, 2) }}</span></div>
    @endforeach
    @if ($cab->ccatvg > 0)<div style="display:flex; justify-content:space-between;"><span>OP. GRAVADA</span><span>S/ {{ number_format($cab->ccatvg, 2) }}</span></div>@endif
    @if ($cab->ccatexo > 0)<div style="display:flex; justify-content:space-between;"><span>OP. EXONERADA</span><span>S/ {{ number_format($cab->ccatexo, 2) }}</span></div>@endif
    @if ($cab->ccaigv > 0)<div style="display:flex; justify-content:space-between;"><span>IGV</span><span>S/ {{ number_format($cab->ccaigv, 2) }}</span></div>@endif
    <div style="display:flex; justify-content:space-between; font-size:15px;"><strong>TOTAL</strong><strong>S/ {{ number_format($cab->ccaitv, 2) }}</strong></div>
    @if ($cab->paga > 0)<div style="display:flex; justify-content:space-between;"><span>PAGA CON</span><span>S/ {{ number_format($cab->paga, 2) }}</span></div>@endif
    @if ($cab->vuelto > 0)<div style="display:flex; justify-content:space-between;"><span>VUELTO</span><span>S/ {{ number_format($cab->vuelto, 2) }}</span></div>@endif
    <hr>
    @if (\App\Support\Sunat\CodigoQr::aplica($cab))
        {{-- QR SUNAT: resumen del comprobante para validarlo --}}
        <div class="c qr">{!! \App\Support\Sunat\CodigoQr::svg($cab, 130) !!}</div>
        @if ($cab->ccaqr)<div class="c" style="font-size:9px; word-break:break-all;">Hash: {{ $cab->ccaqr }}</div>@endif
    @endif
    <div class="c" style="font-size:10px;">
        @if (\App\Support\Sunat\CodigoQr::aplica($cab))REPRESENTACIÓN IMPRESA DE LA {{ $tdodes }}<br>Consulte en www.sunat.gob.pe<br>@endif
        BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA
    </div>
</div>

@unless (request('embed'))
<div class="acciones">
    <button class="b2" onclick="window.print()">IMPRIMIR</button>
    <a class="b2" style="background:#6b7280;" href="{{ request()->fullUrlWithQuery(['formato' => 'a4']) }}">VER EN A4</a>
    @if ($pedidoPendiente)
        <a class="b2" style="background:#8e44ad;" href="{{ route('cobros.separadas', $pedidoPendiente) }}">COBRAR SIGUIENTE CUENTA</a>
    @endif
    @if (in_array($cab->tdocod, ['07', '08'], true))
        <a class="b1" href="{{ route('notas.index') }}">VOLVER A NOTAS</a>
    @elseif ($cab->ped_tip === 'SOCIO')
        <a class="b1" href="{{ route('socios.index') }}">VOLVER A SOCIOS</a>
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
</body>
</html>