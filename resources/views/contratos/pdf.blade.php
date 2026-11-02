<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $numero }}</title>
    {{-- Una sola hoja A4: letra chica y márgenes cortos, como los contratos de certificados digitales --}}
    <style>
        @page { margin: 22px 30px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.1px; color: #1e293b; line-height: 1.4; }
        .cab { width: 100%; border-bottom: 2px solid #1d4ed8; padding-bottom: 4px; margin-bottom: 4px; }
        .cab td { vertical-align: middle; }
        .cab .marca { font-size: 15px; font-weight: bold; color: #1d4ed8; }
        .cab .dato { font-size: 8px; color: #475569; text-align: right; }
        .titulo { text-align: center; font-size: 12.5px; margin: 4px 0 0; letter-spacing: .4px; }
        .sub { text-align: center; font-size: 8px; color: #64748b; margin: 1px 0 5px; }
        p { margin: 0 0 5px; text-align: justify; }
        .firma-cliente { width: 300px; margin: 14px auto 0; text-align: center; }
        .firma-cliente .espacio { height: 58px; }
        .firma-cliente .linea { border-top: 1px solid #334155; padding-top: 2px; font-size: 7.8px; line-height: 1.35; }
        .constancia { margin-top: 8px; border: 1px solid #cbd5e1; border-radius: 5px; padding: 4px 7px; font-size: 7px; color: #475569; }
        .notas { margin-top: 6px; font-size: 6.6px; color: #64748b; }
        .pie { text-align: center; font-size: 8px; color: #1d4ed8; font-weight: bold; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="cab"><tr>
        <td style="width:52px">@if ($logo)<img src="{{ $logo }}" style="width:46px;height:46px" alt="">@endif</td>
        <td><span class="marca">{{ $proveedor['marca'] }}</span><br><span style="font-size:8px;color:#475569">{{ $proveedor['razon_social'] }} · RUC {{ $proveedor['ruc'] }}</span></td>
        <td class="dato">CONTRATO N° <b>{{ $numero }}</b><br>{{ $proveedor['web'] }}</td>
    </tr></table>

    @include('contratos.cuerpo')

    {{-- Solo firma EL CLIENTE --}}
    <div class="firma-cliente">
        <div class="espacio">@if ($firma)<img src="{{ $firma }}" style="max-height:56px;max-width:230px" alt="">@endif</div>
        <div class="linea"><b>FIRMA DE EL CLIENTE</b><br>{{ $contrato->firmante ?: ($cliente?->representante ?: '______________________') }}
            · DNI {{ $contrato->firmante_dni ?: ($cliente?->representante_dni ?: '__________') }}<br>{{ $cliente?->razon_social }} · RUC {{ $cliente?->ruc }}</div>
    </div>

    @if ($firma && $contrato->firmado_el)
        <div class="constancia"><b>Constancia de firma electrónica.</b> Firmado en pantalla por {{ $contrato->firmante }} (DNI {{ $contrato->firmante_dni }})
            el {{ \Carbon\Carbon::parse($contrato->firmado_el)->format('d/m/Y H:i:s') }} desde la IP {{ $contrato->firmado_ip }}.
            Huella del documento (SHA-256): {{ $contrato->huella }}.@if ($contrato->estado === 'APROBADO') Documentos revisados y aprobados por {{ $proveedor['razon_social'] }}.@endif</div>
    @endif

    <div class="notas">*Ley N° 27269, Ley de Firmas y Certificados Digitales · Ley N° 29733, Ley de Protección de Datos Personales.
        **La información consignada tiene carácter de declaración jurada: quien firma responde por la veracidad de los datos y documentos que presenta.</div>
    <div class="pie">{{ $proveedor['web'] }}</div>
</body>
</html>
