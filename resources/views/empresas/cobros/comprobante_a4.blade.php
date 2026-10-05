@php
    $numero = $cab->serdoc . '-' . str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT);
    $electronico = in_array($cab->tdocod, ['01', '03', '07', '08'], true);
    $esNota = in_array($cab->tdocod, ['07', '08'], true);
    $logo = $negocio->logo_suc ?? null ?: ($empresa->LogEmpresa ?? null);
    $docCliente = ['6' => 'RUC', '1' => 'DNI', '4' => 'C.E.', '7' => 'PASAPORTE', '0' => 'DOC.'][(string) $cab->tdicod] ?? 'DOC.';
    $motivoNota = $esNota ? ($cab->tdocod === '07'
        ? \Illuminate\Support\Facades\DB::table('tipo_nota_credito')->where('nccod', $cab->tipnot)->value('ncdes')
        : \Illuminate\Support\Facades\DB::table('tipo_nota_debito')->where('ndcod', $cab->tipnot)->value('nddes')) : null;
    $qr = $electronico ? \App\Support\Sunat\CodigoQr::svg($cab, 110) : null;
    $cant = fn($n) => rtrim(rtrim(number_format($n, 3), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tdodes }} {{ $numero }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; background: #e5e7eb; margin: 0; padding: 20px; }
        .hoja { width: 210mm; min-height: 270mm; margin: 0 auto; background: #fff; padding: 14mm 12mm; box-shadow: 0 4px 20px rgba(0,0,0,.12); display: flex; flex-direction: column; }
        .cabecera { display: flex; gap: 16px; align-items: flex-start; }
        .emisor { flex: 1; display: flex; gap: 12px; }
        .emisor img { max-width: 110px; max-height: 80px; object-fit: contain; }
        .emisor h1 { font-size: 16px; margin: 0 0 4px; }
        .emisor p { margin: 1px 0; color: #4b5563; }
        .caja-doc { width: 230px; border: 2px solid #312e81; border-radius: 8px; text-align: center; overflow: hidden; }
        .caja-doc .ruc { padding: 8px; font-weight: bold; font-size: 13px; }
        .caja-doc .tipo { background: #312e81; color: #fff; padding: 8px; font-weight: bold; font-size: 13px; text-transform: uppercase; }
        .caja-doc .num { padding: 8px; font-weight: bold; font-size: 15px; letter-spacing: .5px; }
        .datos { margin-top: 14px; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; }
        .datos b { display: inline-block; min-width: 92px; color: #374151; }
        .nota { margin-top: 10px; border-left: 4px solid #e11d48; background: #fff1f2; padding: 8px 12px; border-radius: 4px; }
        .anulado { margin-top: 10px; border: 2px solid #dc2626; color: #dc2626; text-align: center; font-weight: bold; padding: 6px; border-radius: 6px; font-size: 13px; }
        table.det { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.det th { background: #312e81; color: #fff; padding: 7px 6px; font-size: 10px; text-transform: uppercase; text-align: left; }
        table.det td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.det tr:nth-child(even) td { background: #f9fafb; }
        .r { text-align: right; } .c { text-align: center; }
        .pie { display: flex; gap: 16px; margin-top: 14px; align-items: flex-start; }
        .letras { flex: 1; }
        .letras .monto { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-weight: bold; }
        .totales { width: 250px; border-collapse: collapse; }
        .totales td { padding: 4px 8px; }
        .totales .total td { background: #312e81; color: #fff; font-weight: bold; font-size: 14px; }
        .qr { display: flex; gap: 12px; align-items: center; margin-top: auto; padding-top: 16px; border-top: 1px dashed #9ca3af; color: #6b7280; font-size: 10px; }
        .qr-img svg { width: 110px; height: 110px; display: block; }
        .acciones { text-align: center; margin: 18px 0 4px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; color: #fff; background: #3498db; }
        .acciones .gris { background: #6b7280; }
        body.embed { background: #fff; padding: 0; }
        body.embed .hoja { box-shadow: none; }
        @media screen and (max-width: 820px) {
            body { padding: 0; }
            .hoja { width: 100%; min-height: auto; padding: 16px; }
            .cabecera, .pie, .emisor { flex-direction: column; }
            .caja-doc, .totales { width: 100%; }
            .datos { grid-template-columns: 1fr; }
            table.det { font-size: 10px; }
            table.det th, table.det td { padding: 5px 3px; }
        }
        @media print { body { background: #fff; padding: 0; } .hoja { box-shadow: none; width: auto; min-height: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body class="{{ request('embed') ? 'embed' : '' }}">
<div class="hoja">
    <div class="cabecera">
        <div class="emisor">
            @if ($logo)<img src="{{ asset($logo) }}" alt="Logo">@endif
            <div>
                <h1>{{ $empresa->NomEmpresa ?? '' }}</h1>
                @if (!empty($negocio->nombre_comercial) && $negocio->nombre_comercial !== ($empresa->NomEmpresa ?? ''))<p><b>{{ $negocio->nombre_comercial }}</b></p>@endif
                <p>{{ $negocio->direccion ?? '' }}</p>
                @if (!empty($negocio->departamento))<p>{{ $negocio->distrito }} - {{ $negocio->provincia }} - {{ $negocio->departamento }}</p>@endif
                @if (!empty($negocio->telefono))<p>Tel: {{ $negocio->telefono }}</p>@endif
                @if (!empty($negocio->correo))<p>{{ $negocio->correo }}</p>@endif
            </div>
        </div>
        <div class="caja-doc">
            <div class="ruc">R.U.C. {{ $cab->IdEmpresa }}</div>
            <div class="tipo">{{ $tdodes }}</div>
            <div class="num">{{ $numero }}</div>
        </div>
    </div>

    <div class="datos">
        <div><b>Cliente:</b> {{ $cab->ccanom }}</div>
        <div><b>Fecha emisión:</b> {{ \Carbon\Carbon::parse($cab->fecha_hora)->format('d/m/Y H:i') }}</div>
        <div><b>{{ $docCliente }}:</b> {{ $cab->ccandi }}</div>
        <div><b>Moneda:</b> {{ ($cab->moncod ?: 'PEN') === 'PEN' ? 'SOLES' : $cab->moncod }}</div>
        @if ($cab->direccion && $cab->direccion !== '--')<div><b>Dirección:</b> {{ $cab->direccion }}</div>@endif
        @unless ($esNota)
            <div><b>Condición:</b> {{ $cab->estadopago }}@if ($cab->estadopago === 'CREDITO' && $cab->ccafve) · vence {{ \Carbon\Carbon::parse($cab->ccafve)->format('d/m/Y') }}@endif</div>
        @endunless
        @if (!empty($cab->placa))<div><b>Placa:</b> {{ $cab->placa }}</div>@endif
        @if (!empty($cab->guia_remision))<div><b>Guía de remisión:</b> {{ $cab->guia_remision }}</div>@endif
        @if ($cab->ccaobs)<div style="grid-column: 1 / -1"><b>Observación:</b> {{ $cab->ccaobs }}</div>@endif
    </div>

    @if ($esNota)
        <div class="nota">
            <b>Documento que modifica:</b> {{ $cab->tdocod_ref === '01' ? 'FACTURA' : 'BOLETA' }} {{ $cab->serie_ref }}-{{ str_pad($cab->num_ref, 8, '0', STR_PAD_LEFT) }}
            @if ($cab->ccafem_ref) del {{ \Carbon\Carbon::parse($cab->ccafem_ref)->format('d/m/Y') }}@endif
            <br><b>Motivo:</b> {{ $cab->tipnot }} - {{ $motivoNota }}
        </div>
    @endif
    @if ($cab->anulado_nc)
        <div class="anulado">ANULADO CON NOTA DE CRÉDITO {{ $cab->anulado_nc }}</div>
    @elseif ($cab->ccabaj)
        <div class="anulado">{{ $cab->ccabaj }}</div>
    @endif

    <table class="det">
        <thead><tr><th style="width:40px" class="c">#</th><th style="width:70px">Código</th><th>Descripción</th><th style="width:50px" class="c">Und.</th>
            <th style="width:60px" class="r">Cant.</th><th style="width:80px" class="r">P. Unit.</th><th style="width:90px" class="r">Importe</th></tr></thead>
        <tbody>
        @if ($cab->consumo)
            <tr><td class="c">1</td><td></td><td>POR CONSUMO</td><td class="c">NIU</td><td class="r">1</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $i => $d)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $d->procod }}</td>
                    <td>{{ $d->cdedes }}@if (!empty($d->lotes))<br><small style="color:#0f766e">Lote: {{ $d->lotes }}</small>@endif</td>
                    <td class="c">{{ $d->umecod }}</td>
                    <td class="r">{{ $cant($d->cdecan) }}</td>
                    <td class="r">{{ number_format($d->cdepuni, 2) }}</td>
                    <td class="r">{{ number_format($d->cdevve, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>

    <div class="pie">
        <div class="letras">
            <p style="margin:0 0 4px;color:#6b7280">SON:</p>
            <div class="monto">{{ \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv) }}</div>
            @if ($medios->isNotEmpty())
                <p style="margin:10px 0 2px;color:#6b7280">FORMA DE PAGO:</p>
                @foreach ($medios as $m)<span style="margin-right:12px">{{ $m->nom_med_pag }}: S/ {{ number_format($m->monto, 2) }}</span>@endforeach
            @endif
        </div>
        <table class="totales">
            @if ($cab->ccatvg > 0)<tr><td>Op. gravada</td><td class="r">S/ {{ number_format($cab->ccatvg, 2) }}</td></tr>@endif
            @if ($cab->ccatexo > 0)<tr><td>Op. exonerada</td><td class="r">S/ {{ number_format($cab->ccatexo, 2) }}</td></tr>@endif
            @if ($cab->ccatinaf > 0)<tr><td>Op. inafecta</td><td class="r">S/ {{ number_format($cab->ccatinaf, 2) }}</td></tr>@endif
            <tr><td>IGV</td><td class="r">S/ {{ number_format($cab->ccaigv, 2) }}</td></tr>
            <tr class="total"><td>TOTAL</td><td class="r">S/ {{ number_format($cab->ccaitv, 2) }}</td></tr>
            @if ($cab->paga > 0)<tr><td>Paga con</td><td class="r">S/ {{ number_format($cab->paga, 2) }}</td></tr>@endif
            @if ($cab->vuelto > 0)<tr><td>Vuelto</td><td class="r">S/ {{ number_format($cab->vuelto, 2) }}</td></tr>@endif
        </table>
    </div>

    <div class="qr">
        @if ($qr)<div class="qr-img">{!! $qr !!}</div>@endif
        <div>
            @if ($electronico)
                <b>Representación impresa de la {{ mb_strtolower($tdodes) }}.</b><br>
                @if ($cab->ccaqr)Código hash: {{ $cab->ccaqr }}<br>@endif
                Consulte su comprobante en www.sunat.gob.pe<br>
            @endif
            BIENES TRANSFERIDOS EN LA AMAZONÍA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONÍA.
        </div>
    </div>
</div>

@unless (request('embed'))
    <div class="acciones">
        <button onclick="window.print()">IMPRIMIR / GUARDAR PDF</button>
        <a class="gris" href="{{ request()->fullUrlWithQuery(['formato' => 'ticket']) }}">VER EN TICKET</a>
    </div>
@endunless


@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 600));</script>
@endif
</body>
</html>
