{{-- Carta digital pública creada en "Crea tu carta" (la que ven los clientes al escanear el QR) --}}
@php
    [, $fondo, $acento, $texto, $letra] = $d;
    $wa = preg_replace('/\D/', '', (string) $carta->whatsapp);
    $wa = $wa ? (strlen($wa) === 9 ? '51'.$wa : $wa) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $fondo }}">
    <title>Carta · {{ $carta->nombre }}</title>
    <meta name="description" content="Carta de {{ $carta->nombre }}{{ $carta->direccion ? ' · '.$carta->direccion : '' }}">
    <meta property="og:title" content="Carta · {{ $carta->nombre }}">
    <meta property="og:image" content="{{ asset($carta->foto) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Poppins, system-ui, sans-serif; background: {{ $fondo }}; color: {{ $texto }}; min-height: 100vh; }
        .cab { text-align: center; padding: 28px 16px 18px; }
        .cab small { letter-spacing: .25em; text-transform: uppercase; opacity: .7; font-weight: 600; font-size: .72rem; }
        .cab h1 { font-family: {{ $letra }}; font-size: clamp(1.8rem, 6vw, 2.6rem); margin: 6px 0 10px; line-height: 1.1; }
        .linea { width: 70px; height: 4px; border-radius: 4px; background: {{ $acento }}; margin: 0 auto; }
        .datos { display: flex; flex-wrap: wrap; gap: 8px 16px; justify-content: center; margin-top: 14px; font-size: .85rem; opacity: .85; }
        .foto { max-width: 820px; margin: 0 auto; padding: 0 12px 110px; }
        .foto img { width: 100%; border-radius: 16px; box-shadow: 0 20px 50px -15px rgba(0,0,0,.45); display: block; background: #fff; cursor: zoom-in; }
        .ayuda { text-align: center; font-size: .78rem; opacity: .6; margin-top: 10px; }
        .barra { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); display: flex; gap: 10px; justify-content: center;
                 background: linear-gradient(to top, {{ $fondo }} 60%, transparent); }
        .btn { flex: 1; max-width: 360px; display: flex; align-items: center; justify-content: center; gap: 8px; height: 52px; border-radius: 16px; font-weight: 700; text-decoration: none; font-size: 1rem; }
        .btn-wa { background: #22c55e; color: #fff; }
        .btn-mapa { background: rgba(255,255,255,.15); color: {{ $texto }}; border: 1px solid rgba(127,127,127,.35); max-width: 140px; }
        .zoom { position: fixed; inset: 0; background: #000; display: none; overflow: auto; z-index: 10; }
        .zoom img { width: 200%; max-width: none; display: block; }
        .zoom .cerrar { position: fixed; top: 12px; right: 12px; width: 44px; height: 44px; border-radius: 50%; border: 0; background: #fff; font-size: 1.4rem; }
        .pie { text-align: center; font-size: .72rem; opacity: .55; padding: 0 0 100px; }
        .pie a { color: inherit; }
    </style>
</head>
<body>
    <header class="cab">
        <small>Nuestra carta</small>
        <h1>{{ $carta->nombre }}</h1>
        <div class="linea"></div>
        <div class="datos">
            @if ($carta->direccion)<span><i class="fas fa-location-dot"></i> {{ $carta->direccion }}</span>@endif
            @if ($carta->horario)<span><i class="far fa-clock"></i> {{ $carta->horario }}</span>@endif
        </div>
    </header>

    <main class="foto">
        <img src="{{ asset($carta->foto) }}" alt="Carta de {{ $carta->nombre }}" onclick="document.getElementById('zoom').style.display = 'block'">
        <p class="ayuda"><i class="fas fa-magnifying-glass-plus"></i> Toca la carta para verla más grande</p>
    </main>

    <p class="pie">Carta digital gratis hecha con <a href="{{ route('sitio.carta.crear') }}">TUSHPA</a> · crea la tuya</p>

    @if ($wa || $carta->direccion)
        <nav class="barra">
            @if ($wa)
                <a class="btn btn-wa" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hola '.$carta->nombre.', vi su carta y quiero hacer un pedido: ') }}" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp fa-lg"></i> Pedir por WhatsApp</a>
            @endif
            @if ($carta->direccion)
                <a class="btn btn-mapa" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($carta->direccion) }}" target="_blank" rel="noopener"><i class="fas fa-map-location-dot"></i> Mapa</a>
            @endif
        </nav>
    @endif

    <div class="zoom" id="zoom">
        <button class="cerrar" onclick="document.getElementById('zoom').style.display = 'none'" aria-label="Cerrar">×</button>
        <img src="{{ asset($carta->foto) }}" alt="">
    </div>
</body>
</html>
