<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Verificación de socio</title>
    @php
        $colores = ['green' => ['#059669', '#ecfdf5'], 'amber' => ['#d97706', '#fffbeb'], 'red' => ['#dc2626', '#fef2f2'], 'gray' => ['#4b5563', '#f3f4f6']];
        [$fuerte, $suave] = $colores[$color] ?? $colores['gray'];
        $logo = $negocio->logo_suc ?: $negocio->LogEmpresa;
    @endphp
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; min-height: 100vh; background: {{ $suave }}; color: #111827;
               display: flex; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
        .tarjeta { background: #fff; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,.12); width: 100%; max-width: 380px; overflow: hidden; text-align: center; }
        .cab { padding: 16px; border-bottom: 1px solid #e5e7eb; }
        .cab img { max-height: 54px; max-width: 160px; object-fit: contain; }
        .cab p { margin: 6px 0 0; font-weight: bold; color: #374151; }
        .estado { background: {{ $fuerte }}; color: #fff; font-size: 30px; font-weight: 900; padding: 22px 10px; letter-spacing: 1px; }
        .datos { padding: 18px; }
        .nombre { font-size: 20px; font-weight: bold; margin: 0 0 6px; }
        .linea { color: #4b5563; margin: 4px 0; }
        .aviso { margin: 12px 18px 18px; padding: 10px; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-weight: bold; font-size: 14px; }
        .hora { color: #9ca3af; font-size: 12px; padding-bottom: 14px; }
    </style>
</head>
<body>
    <div class="tarjeta">
        <div class="cab">
            @if ($logo)<img src="{{ asset($logo) }}" alt="">@endif
            <p>{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</p>
        </div>
        <div class="estado">{{ $texto }}</div>
        <div class="datos">
            <p class="nombre">{{ $nombre }}</p>
            <p class="linea">{{ $tipo }}</p>
            <p class="linea">Socio N° <b>{{ $codigo }}</b></p>
        </div>
        @if ($aviso)<div class="aviso">{{ $aviso }}</div>@endif
        <div class="hora">Verificado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
