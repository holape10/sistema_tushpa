{{-- Carta digital pública creada en "Crea tu carta" (la que ven los clientes al escanear el QR) --}}
@php
    [, $fondo, $acento, $texto, $letra] = $d;
    $wa = preg_replace('/\D/', '', (string) $carta->whatsapp);
    $wa = $wa ? (strlen($wa) === 9 ? '51'.$wa : $wa) : null;
    // Productos por categoría, en el orden en que los escribió (sin categoría van al final como "OTROS")
    $grupos = $productos->groupBy(fn ($p) => $p->categoria ?: 'OTROS');
    $imagen = $carta->foto ?: $productos->firstWhere('foto')?->foto;
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
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
    @if ($imagen)<meta property="og:image" content="{{ asset($imagen) }}">@endif
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
        .menu { max-width: 820px; margin: 0 auto; padding: 0 12px 24px; }
        .cats { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; overflow-x: auto; padding: 10px 12px; margin: 0 -12px 8px; background: {{ $fondo }}; scrollbar-width: none; }
        .cats::-webkit-scrollbar { display: none; }
        .cats a { flex: 0 0 auto; padding: 7px 14px; border-radius: 999px; border: 1px solid rgba(127,127,127,.35); color: inherit; text-decoration: none; font-size: .82rem; font-weight: 600; white-space: nowrap; }
        .cats a:active { background: {{ $acento }}; color: {{ $fondo }}; }
        .cat-titulo { font-family: {{ $letra }}; font-size: 1.3rem; margin: 22px 0 10px; display: flex; align-items: center; gap: 10px; scroll-margin-top: 60px; }
        .cat-titulo::after { content: ''; flex: 1; height: 2px; background: {{ $acento }}; opacity: .5; border-radius: 2px; }
        .plato { display: flex; gap: 12px; align-items: center; padding: 12px; border-radius: 16px; background: rgba(127,127,127,.08); margin-bottom: 10px; }
        .plato img { width: 84px; height: 84px; flex: 0 0 84px; object-fit: cover; border-radius: 12px; cursor: zoom-in; background: #fff; }
        .plato .info { flex: 1; min-width: 0; }
        .plato b { display: block; font-size: 1rem; line-height: 1.25; }
        .plato p { margin: 3px 0 0; font-size: .82rem; opacity: .75; line-height: 1.35; }
        .plato .precio { font-weight: 800; color: {{ $acento }}; white-space: nowrap; font-size: 1.02rem; }
        .zoom.producto { display: none; align-items: center; justify-content: center; }
        .zoom.producto img { width: auto; max-width: 100%; max-height: 100%; margin: auto; }
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

    @if ($productos->isNotEmpty())
        <main class="menu">
            @if ($grupos->count() > 1)
                <nav class="cats">@foreach ($grupos->keys() as $i => $categoria)<a href="#cat-{{ $i }}">{{ $categoria }}</a>@endforeach</nav>
            @endif
            @foreach ($grupos as $categoria => $platos)
                <h2 class="cat-titulo" id="cat-{{ $loop->index }}">{{ $categoria }}</h2>
                @foreach ($platos as $p)
                    <div class="plato">
                        @if ($p->foto)<img src="{{ asset($p->foto) }}" alt="{{ $p->nombre }}" loading="lazy" onclick="verFoto(this.src)">@endif
                        <div class="info"><b>{{ $p->nombre }}</b>@if ($p->descripcion)<p>{{ $p->descripcion }}</p>@endif</div>
                        @if ($p->precio !== null)<span class="precio">{{ $soles($p->precio) }}</span>@endif
                    </div>
                @endforeach
            @endforeach
        </main>
    @endif

    @if ($carta->foto)
        <main class="foto" @if ($productos->isNotEmpty()) style="padding-bottom: 24px" @endif>
            <img src="{{ asset($carta->foto) }}" alt="Carta de {{ $carta->nombre }}" onclick="document.getElementById('zoom').style.display = 'block'">
            <p class="ayuda"><i class="fas fa-magnifying-glass-plus"></i> Toca la carta para verla más grande</p>
        </main>
    @endif

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

    @if ($carta->foto)
        <div class="zoom" id="zoom">
            <button class="cerrar" onclick="document.getElementById('zoom').style.display = 'none'" aria-label="Cerrar">×</button>
            <img src="{{ asset($carta->foto) }}" alt="">
        </div>
    @endif
    <div class="zoom producto" id="zoom_producto" onclick="this.style.display = 'none'">
        <button class="cerrar" aria-label="Cerrar">×</button>
        <img src="" alt="">
    </div>
    <script>
        function verFoto(src) { const z = document.getElementById('zoom_producto'); z.querySelector('img').src = src; z.style.display = 'flex'; }
    </script>
</body>
</html>
