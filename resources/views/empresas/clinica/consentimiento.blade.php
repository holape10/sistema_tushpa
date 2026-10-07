<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentimiento informado · {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; line-height: 1.6; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 210mm; margin: 12px auto; background: #fff; padding: 18mm; box-sizing: border-box; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 14px; }
        .cab img { max-height: 55px; max-width: 120px; object-fit: contain; }
        h1 { text-align: center; font-size: 16px; color: #0f766e; margin: 10px 0 16px; }
        .linea { border-bottom: 1px solid #111; display: inline-block; min-width: 60mm; }
        ol li { margin-bottom: 6px; }
        .firmas { display: flex; justify-content: space-between; margin-top: 60px; text-align: center; }
        .firmas div { width: 70mm; border-top: 1px solid #111; padding-top: 4px; }
        .huella { width: 25mm; height: 30mm; border: 1px solid #999; margin: 0 auto 4px; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
@php $mascota = $h->tipo === 'MASCOTA'; @endphp
<div class="acciones"><button onclick="window.print()">Imprimir</button></div>
<div class="hoja">
    <div class="cab">
        <div><b>{{ $negocio->nombre_comercial ?: $negocio->razon }}</b><br>{{ $negocio->direccion }}</div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>

    <h1>CONSENTIMIENTO INFORMADO</h1>

    <p>Yo, <b>{{ $h->clinom }}</b>, identificado(a) con DNI N° <b>{{ $h->clinum }}</b>,
        @if ($mascota) propietario(a) de la mascota <b>{{ $h->mascota }}</b> ({{ trim($h->especie . ' ' . $h->raza) }}), @else en pleno uso de mis facultades, @endif
        con historia clínica N° <b>{{ $h->his_cli_cod }}</b>, declaro que:</p>
    <ol>
        <li>El(la) profesional <b>{{ $doctor ?: '______________________________' }}</b> me ha explicado de forma clara y comprensible el diagnóstico y el tratamiento propuesto:
            <b>{{ $tratamiento ?: '__________________________________________________' }}</b>.</li>
        <li>Se me informó de los beneficios, las alternativas de tratamiento y los posibles riesgos y complicaciones{{ $riesgos ? ':' : '' }} {{ $riesgos ?: '' }}
            @unless ($riesgos)<br><span class="linea" style="min-width:100%;">&nbsp;</span><br><span class="linea" style="min-width:100%;">&nbsp;</span>@endunless</li>
        <li>Tuve la oportunidad de hacer preguntas y todas fueron respondidas satisfactoriamente.</li>
        <li>Informé con veracidad mis antecedentes{{ $h->alergias ? ' y alergias (' . $h->alergias . ')' : ', alergias' }} y los medicamentos que tomo.</li>
        <li>Sé que puedo retirar este consentimiento en cualquier momento antes del procedimiento.</li>
    </ol>
    <p>Por lo expuesto, <b>AUTORIZO</b> la realización del tratamiento indicado{{ $mascota ? ' a mi mascota' : '' }}.</p>
    <p>{{ \Illuminate\Support\Str::title(mb_strtolower($negocio->distrito ?: 'Lugar')) }}, {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>

    <div class="firmas">
        <div><div class="huella"></div>Firma y huella {{ $mascota ? 'del propietario' : 'del paciente' }}<br>DNI {{ $h->clinum }}</div>
        <div style="margin-top:34mm;">Firma y sello del profesional<br>{{ $doctor }}</div>
    </div>
</div>
</body>
</html>
