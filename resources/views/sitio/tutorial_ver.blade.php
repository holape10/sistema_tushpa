@php
    $rubro = $rubros[$t->rubro] ?? ['nombre' => $t->rubro, 'icono' => 'fa-star'];
    $video = \App\Support\Tutoriales::youtube($t->video_url);
    // Para Google: guía paso a paso (HowTo)
    $seoJson = json_encode([
        '@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => $t->titulo, 'description' => $t->resumen ?: $t->titulo,
        'totalTime' => 'PT'.$t->minutos.'M',
        'step' => collect($t->pasos)->values()->map(fn ($p, $i) => array_filter(['@type' => 'HowToStep', 'position' => $i + 1,
            'name' => $p['titulo'] ?: 'Paso '.($i + 1), 'text' => $p['texto'] ?: $p['titulo'], 'image' => $p['imagen'] ? asset($p['imagen']) : null]))->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $t->titulo }} · Tutorial | TUSHPA</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($t->resumen ?: collect($t->pasos)->pluck('texto')->implode(' '), 155) }}">
    <link rel="canonical" href="{{ route('sitio.tutorial.ver', $t->slug) }}">
    <meta property="og:title" content="{{ $t->titulo }}">
    @if ($img = collect($t->pasos)->pluck('imagen')->filter()->first())<meta property="og:image" content="{{ asset($img) }}">@endif
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('web/css/sitio.css') }}?v={{ filemtime(public_path('web/css/sitio.css')) }}">
    <script type="application/ld+json">{!! $seoJson !!}</script>
    <style>
        .tut-hero { padding: 7.5rem 0 3.5rem; }
        .tut-hero a { color: rgba(255, 255, 255, .8); text-decoration: none; }
        .tut-paso { display: grid; grid-template-columns: 44px 1fr; gap: 1rem; padding: 1.6rem 0; border-bottom: 1px dashed rgba(30, 41, 59, .15); }
        .tut-paso:last-child { border-bottom: 0; }
        .tut-num { width: 44px; height: 44px; border-radius: 50%; background: var(--selva-600); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .tut-paso h2 { font-size: 1.2rem; font-weight: 700; margin: .45rem 0 .5rem; }
        .tut-paso p { color: #334155; white-space: pre-line; margin: 0; }
        .tut-paso img { display: block; max-width: 100%; margin-top: 1rem; border-radius: 1rem; border: 1px solid rgba(30, 41, 59, .1); box-shadow: 0 18px 40px -22px rgba(15, 23, 42, .5); cursor: zoom-in; }
        .tut-video { position: relative; aspect-ratio: 16 / 9; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 25px 50px -20px rgba(15, 23, 42, .5); margin-bottom: 1.5rem; }
        .tut-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .tut-otro { display: block; padding: .8rem 1rem; border-radius: .9rem; background: #fff; text-decoration: none; color: var(--tinta); font-weight: 600; border: 1px solid rgba(30, 41, 59, .08); }
        .tut-otro:hover { border-color: var(--selva-500); color: var(--selva-600); }
    </style>
</head>
<body>
@include('sitio.partials.menu', ['enPortada' => false])

<section class="portada tut-hero">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <div class="container" style="max-width: 860px">
        <p class="small mb-2"><a href="{{ route('sitio.tutorial') }}"><i class="fas fa-arrow-left me-1"></i>Tutoriales</a> ·
            <a href="{{ route('sitio.tutorial', ['rubro' => $t->rubro]) }}"><i class="fas {{ $rubro['icono'] }} me-1"></i>{{ $rubro['nombre'] }}</a></p>
        <h1 class="fw-bold" style="font-size: clamp(1.8rem, 4vw, 2.8rem)">{{ $t->titulo }}</h1>
        @if ($t->resumen)<p class="bajada">{{ $t->resumen }}</p>@endif
        <p class="small mb-0" style="color: rgba(255,255,255,.75)"><i class="far fa-clock me-1"></i>{{ $t->minutos }} min · {{ count($t->pasos) }} pasos</p>
    </div>
</section>

<section class="seccion seccion-arena" style="padding-top: 3rem">
    <div class="container" style="max-width: 860px">
        @if ($video)
            <div class="tut-video"><iframe src="https://www.youtube-nocookie.com/embed/{{ $video }}" title="{{ $t->titulo }}" loading="lazy" allowfullscreen></iframe></div>
        @endif
        <article class="bg-white rounded-4 p-4 p-md-5" style="border: 1px solid rgba(30,41,59,.07)">
            @foreach ($t->pasos as $i => $p)
                <div class="tut-paso">
                    <span class="tut-num">{{ $i + 1 }}</span>
                    <div>
                        @if ($p['titulo'])<h2>{{ $p['titulo'] }}</h2>@endif
                        @if ($p['texto'])<p>{{ $p['texto'] }}</p>@endif
                        @if ($p['imagen'])
                            <a href="{{ asset($p['imagen']) }}" target="_blank" rel="noopener"><img src="{{ asset($p['imagen']) }}" alt="{{ $p['titulo'] ?: $t->titulo }}" loading="lazy"></a>
                        @endif
                    </div>
                </div>
            @endforeach
        </article>

        <div class="text-center mt-4">
            <p class="texto-gris mb-2">¿Te quedó alguna duda?</p>
            <a href="https://wa.me/{{ config('soporte.whatsapp') }}?text={{ rawurlencode('Hola, tengo una duda sobre el tutorial: '.$t->titulo) }}" target="_blank" rel="noopener" class="btn btn-selva">
                <i class="fab fa-whatsapp me-1"></i>Pregúntanos por WhatsApp</a>
        </div>

        @if ($otros->isNotEmpty())
            <h2 class="h5 fw-bold mt-5 mb-3">Más tutoriales de {{ $rubro['nombre'] }}</h2>
            <div class="row g-2">
                @foreach ($otros as $o)
                    <div class="col-md-6"><a class="tut-otro" href="{{ route('sitio.tutorial.ver', $o->slug) }}"><i class="fas fa-book-open me-2" style="color: var(--selva-500)"></i>{{ $o->titulo }}</a></div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@include('sitio.partials.pie')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
@include('sitio.partials.extras')
</body>
</html>
