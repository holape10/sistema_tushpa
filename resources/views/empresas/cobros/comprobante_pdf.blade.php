{{-- Comprobante A4 para PDF (Dompdf): mismo contenido que comprobante_a4 pero solo con tablas, que Dompdf sí dibuja bien --}}
@php
    $numero = $cab->serdoc . '-' . str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT);
    $electronico = in_array($cab->tdocod, ['01', '03', '07', '08'], true);
    $logo = $negocio->logo_suc ?? null ?: ($empresa->LogEmpresa ?? null);
    $logo = $logo && is_file(public_path($logo)) ? public_path($logo) : null;
    $docCliente = ['6' => 'RUC', '1' => 'DNI', '4' => 'C.E.', '7' => 'PASAPORTE', '0' => 'DOC.'][(string) $cab->tdicod] ?? 'DOC.';
    $qr = $electronico ? \App\Support\Sunat\CodigoQr::svg($cab, 110) : null;
    $cant = fn($n) => rtrim(rtrim(number_format($n, 3), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tdodes }} {{ $numero }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        .r { text-align: right; } .c { text-align: center; }
        .caja { border: 2px solid #312e81; border-radius: 6px; text-align: center; }
        .caja td { padding: 6px; }
        .tipo { background: #312e81; color: #fff; font-weight: bold; font-size: 11px; }
        .datos td { padding: 3px 4px; vertical-align: top; }
        .det th { background: #312e81; color: #fff; padding: 5px 4px; font-size: 9px; text-align: left; }
        .det td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .tot td { padding: 3px 4px; }
        .tot .total td { font-size: 12px; font-weight: bold; border-top: 2px solid #312e81; }
        .gris { color: #6b7280; }
    </style>
</head>
<body>
<table>
    <tr>
        <td style="width:62%; vertical-align:top">
            <table><tr>
                @if ($logo)<td style="width:95px; vertical-align:top"><img src="{{ $logo }}" style="max-width:90px; max-height:70px"></td>@endif
                <td style="vertical-align:top">
                    <div style="font-size:13px; font-weight:bold">{{ $empresa->NomEmpresa ?? '' }}</div>
                    @if (!empty($negocio->nombre_comercial) && $negocio->nombre_comercial !== ($empresa->NomEmpresa ?? ''))<div><b>{{ $negocio->nombre_comercial }}</b></div>@endif
                    <div class="gris">{{ $negocio->direccion ?? '' }}</div>
                    @if (!empty($negocio->departamento))<div class="gris">{{ $negocio->distrito }} - {{ $negocio->provincia }} - {{ $negocio->departamento }}</div>@endif
                    @if (!empty($negocio->telefono))<div class="gris">Tel: {{ $negocio->telefono }}</div>@endif
                    @if (!empty($negocio->correo))<div class="gris">{{ $negocio->correo }}</div>@endif
                </td>
            </tr></table>
        </td>
        <td style="vertical-align:top">
            <table class="caja">
                <tr><td style="font-weight:bold; font-size:11px">R.U.C. {{ $cab->IdEmpresa }}</td></tr>
                <tr><td class="tipo">{{ $tdodes }}</td></tr>
                <tr><td style="font-weight:bold; font-size:12px">{{ $numero }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="datos" style="margin-top:10px; background:#f9fafb">
    <tr><td style="width:55%"><b>Cliente:</b> {{ $cab->ccanom }}</td><td><b>Fecha emisión:</b> {{ \Carbon\Carbon::parse($cab->ccafem)->format('d/m/Y') }}</td></tr>
    <tr><td><b>{{ $docCliente }}:</b> {{ $cab->ccandi }}</td><td><b>Moneda:</b> SOLES</td></tr>
    <tr>
        <td>@if ($cab->direccion && $cab->direccion !== '--')<b>Dirección:</b> {{ $cab->direccion }}@endif</td>
        <td><b>Condición:</b> {{ $cab->estadopago }}@if ($cab->estadopago === 'CREDITO' && $cab->ccafve) · vence {{ \Carbon\Carbon::parse($cab->ccafve)->format('d/m/Y') }}@endif</td>
    </tr>
    @if ($cab->ccaobs)<tr><td colspan="2"><b>Observación:</b> {{ $cab->ccaobs }}</td></tr>@endif
</table>

<table class="det" style="margin-top:10px">
    <thead><tr><th class="c" style="width:25px">#</th><th>Descripción</th><th class="c" style="width:40px">Und.</th>
        <th class="r" style="width:50px">Cant.</th><th class="r" style="width:70px">P. Unit.</th><th class="r" style="width:75px">Importe</th></tr></thead>
    <tbody>
    @foreach ($detalle as $i => $d)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $d->cdedes }}</td><td class="c">{{ $d->umecod }}</td>
            <td class="r">{{ $cant($d->cdecan) }}</td><td class="r">{{ number_format($d->cdepuni, 2) }}</td><td class="r">{{ number_format($d->cdevve, 2) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top:10px">
    <tr>
        <td style="width:58%; vertical-align:top">
            <div class="gris">SON:</div>
            <div style="font-weight:bold">{{ \App\Support\Sunat\NumeroLetras::convertir((float) $cab->ccaitv) }}</div>
        </td>
        <td style="vertical-align:top">
            <table class="tot">
                @if ($cab->ccatvg > 0)<tr><td>Op. gravada</td><td class="r">S/ {{ number_format($cab->ccatvg, 2) }}</td></tr>@endif
                @if ($cab->ccatexo > 0)<tr><td>Op. exonerada</td><td class="r">S/ {{ number_format($cab->ccatexo, 2) }}</td></tr>@endif
                <tr><td>IGV</td><td class="r">S/ {{ number_format($cab->ccaigv, 2) }}</td></tr>
                <tr class="total"><td>TOTAL</td><td class="r">S/ {{ number_format($cab->ccaitv, 2) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top:14px">
    <tr>
        @if ($qr)<td style="width:120px"><img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" style="width:105px; height:105px"></td>@endif
        <td class="gris" style="vertical-align:middle">
            @if ($electronico)
                <b>Representación impresa de la {{ mb_strtolower($tdodes) }}.</b><br>
                @if ($cab->ccaqr)Código hash: {{ $cab->ccaqr }}<br>@endif
                Consulte su comprobante en www.sunat.gob.pe<br>
            @endif
            BIENES TRANSFERIDOS EN LA AMAZONÍA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONÍA.
        </td>
    </tr>
</table>
</body>
</html>
