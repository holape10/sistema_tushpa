<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $numero }}</title>
    <style>
        @page { margin: 26px 34px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.6px; color: #1e293b; line-height: 1.42; }
        .cab { width: 100%; border-bottom: 2px solid #1d4ed8; padding-bottom: 6px; margin-bottom: 8px; }
        .cab td { vertical-align: middle; }
        .cab .marca { font-size: 16px; font-weight: bold; color: #1d4ed8; }
        .cab .dato { font-size: 8.5px; color: #475569; text-align: right; }
        .titulo { text-align: center; font-size: 14px; margin: 6px 0 0; letter-spacing: .5px; }
        .sub { text-align: center; font-size: 9px; color: #64748b; margin: 2px 0 8px; }
        h2 { font-size: 9.6px; margin: 7px 0 2px; color: #0f172a; page-break-after: avoid; }
        p { margin: 3px 0; text-align: justify; }
        ul { margin: 2px 0 2px 14px; padding: 0; }
        li { margin: 1px 0; text-align: justify; }
        .firmas { width: 100%; margin-top: 26px; }
        .firmas td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 16px; }
        .linea { border-top: 1px solid #334155; padding-top: 3px; font-size: 8.5px; }
        .firma { height: 62px; }
        .constancia { margin-top: 12px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; font-size: 7.8px; color: #475569; }
        .pie { position: fixed; bottom: -26px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="cab"><tr>
        <td style="width:60px">@if ($logo)<img src="{{ $logo }}" style="width:54px;height:54px" alt="">@endif</td>
        <td><span class="marca">{{ $proveedor['marca'] }}</span><br><span style="font-size:8.5px;color:#475569">{{ $proveedor['razon_social'] }} · RUC {{ $proveedor['ruc'] }}</span></td>
        <td class="dato">CONTRATO N° <b>{{ $numero }}</b><br>{{ $proveedor['web'] }}</td>
    </tr></table>

    @include('contratos.cuerpo')

    <table class="firmas"><tr>
        <td>
            <div class="firma">@if ($firmaProveedor)<img src="{{ $firmaProveedor }}" style="max-height:60px;max-width:220px" alt="">@endif</div>
            <div class="linea"><b>EL PROVEEDOR</b><br>{{ $proveedor['razon_social'] }}<br>{{ $proveedor['representante'] }} · DNI {{ $proveedor['dni_representante'] }}</div>
        </td>
        <td>
            <div class="firma">@if ($firma)<img src="{{ $firma }}" style="max-height:60px;max-width:220px" alt="">@endif</div>
            <div class="linea"><b>EL CLIENTE</b><br>{{ $cliente?->razon_social }}<br>{{ $contrato->firmante ?: '______________________' }} · DNI {{ $contrato->firmante_dni ?: '__________' }}</div>
        </td>
    </tr></table>

    @if ($firma && $contrato->firmado_el)
        <div class="constancia"><b>Constancia de firma electrónica.</b> Firmado en pantalla por {{ $contrato->firmante }} (DNI {{ $contrato->firmante_dni }})
            el {{ \Carbon\Carbon::parse($contrato->firmado_el)->format('d/m/Y H:i:s') }} desde la IP {{ $contrato->firmado_ip }}.
            Huella del documento (SHA-256): {{ $contrato->huella }}.@if ($contrato->estado === 'APROBADO') Documentos revisados y aprobados por {{ $proveedor['razon_social'] }}.@endif</div>
    @endif

    <div class="pie">{{ $proveedor['marca'] }} · {{ $proveedor['razon_social'] }} · Contrato {{ $numero }}</div>
</body>
</html>
