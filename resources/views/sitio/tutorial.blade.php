@php
    // Tutoriales por rubro: se crean en admin.tushpa.app › Tutoriales
    $conTutoriales = $tutoriales->groupBy('rubro');
    $chips = collect($rubros)->filter(fn ($r, $clave) => $conTutoriales->has($clave));
    $preguntas = [
        ['fa-download', '¿Necesito instalar algo?', 'No. TUSHPA funciona en la nube: entras desde el navegador de tu PC, tablet o celular. Solo necesitas internet.'],
        ['fa-shield-halved', '¿Cómo consigo mi certificado digital?', 'Te lo vendemos como partner de LLAMA.PE, a sus mismos precios, y te ayudamos a instalarlo. Míralo en la sección "Certificado digital" de la página principal.'],
        ['fa-headset', '¿Y si me quedo atascado?', 'Escríbenos por WhatsApp al '.config('soporte.telefono').': te ayudamos paso a paso. Desde tu sistema también puedes abrir un ticket con el botón de ayuda (?).'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutoriales por rubro | TUSHPA</title>
    <meta name="description" content="Aprende a usar TUSHPA con guías paso a paso para tu rubro: restaurantes, comercios, gimnasios, hoteles y más. Facturación electrónica SUNAT, caja e inventario.">
    <link rel="canonical" href="{{ route('sitio.tutorial') }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('web/css/sitio.css') }}?v={{ filemtime(public_path('web/css/sitio.css')) }}">
    <style>
        .tut-hero { padding: 8rem 0 4.5rem; }
        .tut-buscar { position: relative; max-width: 560px; margin: 1.75rem auto 0; }
        .tut-buscar i { position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: #64748b; }
        .tut-buscar input { width: 100%; height: 58px; border: 0; border-radius: 999px; padding: 0 1.25rem 0 3.1rem; font-size: 1rem; box-shadow: 0 20px 45px -15px rgba(0, 0, 0, .45); }
        .tut-chips { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; margin-bottom: 2rem; }
        .tut-chip { border: 0; background: #fff; color: var(--tinta); border-radius: 999px; padding: .55rem 1.1rem; font-weight: 600; font-size: .92rem; box-shadow: 0 6px 16px -10px rgba(15, 23, 42, .5); }
        .tut-chip i { color: var(--selva-500); margin-right: .35rem; }
        .tut-chip.activo { background: var(--selva-600); color: #fff; }
        .tut-chip.activo i { color: var(--huito); }
        .tut-card { display: flex; flex-direction: column; height: 100%; background: #fff; border-radius: 1.25rem; overflow: hidden; text-decoration: none; color: inherit;
                    border: 1px solid rgba(30, 41, 59, .07); transition: transform .2s, box-shadow .2s; }
        .tut-card:hover { transform: translateY(-4px); box-shadow: 0 22px 45px -20px rgba(15, 23, 42, .45); color: inherit; }
        .tut-portada { aspect-ratio: 16 / 9; background: linear-gradient(135deg, var(--selva-600), var(--rio)); display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, .85); font-size: 2.6rem; position: relative; }
        .tut-portada img { width: 100%; height: 100%; object-fit: cover; }
        .tut-portada .tut-video { position: absolute; right: .75rem; bottom: .75rem; background: rgba(0, 0, 0, .55); color: #fff; font-size: .75rem; font-weight: 600; padding: .25rem .6rem; border-radius: 999px; }
        .tut-cuerpo { padding: 1.25rem 1.35rem 1.4rem; display: flex; flex-direction: column; flex: 1; }
        .tut-rubro { font-size: .75rem; font-weight: 700; text-transform: uppercase; color: var(--selva-500); }
        .tut-cuerpo h3 { font-size: 1.12rem; font-weight: 700; margin: .35rem 0 .45rem; }
        .tut-meta { margin-top: auto; padding-top: .8rem; font-size: .82rem; color: var(--gris); }
        .tut-vacio { display: none; }
    </style>
</head>
<body>
@include('sitio.partials.menu', ['enPortada' => false])

<section class="portada tut-hero text-center">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <div class="container">
        <span class="etiqueta" style="color: var(--huito)">Centro de aprendizaje</span>
        <h1 class="fw-bold mt-2">Aprende a usar <span class="resalte">TUSHPA</span> en tu rubro</h1>
        <p class="bajada mx-auto">Guías cortas, paso a paso y con imágenes: desde abrir tu caja hasta cobrar una mesa o vender un plan del gimnasio.</p>
        <div class="tut-buscar">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="tutBuscar" placeholder="¿Qué quieres aprender? (ej.: cobrar, factura, caja)" autocomplete="off">
        </div>
    </div>
</section>

<section class="seccion seccion-arena" style="padding-top: 3rem">
    <div class="container">
        @if ($tutoriales->isEmpty())
            <p class="text-center texto-gris py-5">Muy pronto publicaremos los tutoriales. Mientras tanto, <a href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener">escríbenos por WhatsApp</a>.</p>
        @else
            <div class="tut-chips" role="tablist">
                <button type="button" class="tut-chip activo" data-rubro=""><i class="fas fa-layer-group"></i>Todos</button>
                @foreach ($chips as $clave => $r)
                    <button type="button" class="tut-chip" data-rubro="{{ $clave }}"><i class="fas {{ $r['icono'] }}"></i>{{ $r['nombre'] }}</button>
                @endforeach
            </div>
            <div class="row g-4" id="tutLista">
                @foreach ($tutoriales as $t)
                    @php $portada = collect($t->pasos)->pluck('imagen')->filter()->first(); @endphp
                    <div class="col-sm-6 col-lg-4 tut-item" data-rubro="{{ $t->rubro }}"
                         data-texto="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($t->titulo.' '.$t->resumen.' '.collect($t->pasos)->pluck('titulo')->implode(' ').' '.($rubros[$t->rubro]['nombre'] ?? ''))) }}">
                        <a class="tut-card" href="{{ route('sitio.tutorial.ver', $t->slug) }}">
                            <div class="tut-portada">
                                @if ($portada)<img src="{{ asset($portada) }}" alt="" loading="lazy">@else<i class="fas {{ $rubros[$t->rubro]['icono'] ?? 'fa-book-open' }}"></i>@endif
                                @if (\App\Support\Tutoriales::youtube($t->video_url))<span class="tut-video"><i class="fas fa-play me-1"></i>Con video</span>@endif
                            </div>
                            <div class="tut-cuerpo">
                                <span class="tut-rubro"><i class="fas {{ $rubros[$t->rubro]['icono'] ?? 'fa-star' }} me-1"></i>{{ $rubros[$t->rubro]['nombre'] ?? $t->rubro }}</span>
                                <h3>{{ $t->titulo }}</h3>
                                @if ($t->resumen)<p class="texto-gris small mb-0">{{ $t->resumen }}</p>@endif
                                <div class="tut-meta"><i class="far fa-clock me-1"></i>{{ $t->minutos }} min · {{ count($t->pasos) }} pasos</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
            <p class="text-center texto-gris py-5 tut-vacio" id="tutVacio">No encontramos ese tema. <a href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener">Pregúntanos por WhatsApp</a>.</p>
        @endif
    </div>
</section>

<section class="seccion">
    <div class="container">
        <div class="seccion-titulo">
            <span class="etiqueta">Preguntas frecuentes</span>
            <h2>¿Tienes dudas rápidas?</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="preguntasTut">
                    @foreach ($preguntas as [$icono, $pregunta, $respuesta])
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pt{{ $loop->index }}">
                                    <i class="fas {{ $icono }} me-3" style="color: var(--selva-500)"></i>{{ $pregunta }}</button>
                            </h3>
                            <div id="pt{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#preguntasTut">
                                <div class="accordion-body">{{ $respuesta }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion seccion-oscura text-center">
    <div class="container">
        <h2 class="fw-bold mb-3">¿Prefieres que te enseñemos en vivo?</h2>
        <p class="mb-4" style="color: rgba(255,255,255,.75)">Agenda una capacitación con nuestro equipo por videollamada.</p>
        <a href="https://wa.me/{{ config('soporte.whatsapp') }}?text={{ rawurlencode('Hola, quiero una capacitación de TUSHPA') }}" target="_blank" rel="noopener" class="btn btn-achiote btn-lg">
            <i class="fab fa-whatsapp me-2"></i>Agendar por WhatsApp</a>
    </div>
</section>

@include('sitio.partials.pie')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
<script>
    // Filtro por rubro y búsqueda (sin recargar)
    (function () {
        const items = [...document.querySelectorAll('.tut-item')];
        if (!items.length) return;
        const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        let rubro = new URLSearchParams(location.search).get('rubro') || '';
        const buscar = document.getElementById('tutBuscar');
        function filtrar() {
            const q = norm(buscar.value.trim());
            let visibles = 0;
            items.forEach(el => {
                const ok = (!rubro || el.dataset.rubro === rubro || el.dataset.rubro === 'TODOS') && (!q || q.split(/\s+/).every(p => el.dataset.texto.includes(p)));
                el.style.display = ok ? '' : 'none';
                // Con un rubro elegido, primero los suyos y después los que sirven para todos
                el.style.order = rubro && el.dataset.rubro === 'TODOS' ? 1 : 0;
                visibles += ok;
            });
            document.getElementById('tutVacio').style.display = visibles ? 'none' : 'block';
            document.querySelectorAll('.tut-chip').forEach(c => c.classList.toggle('activo', c.dataset.rubro === rubro));
        }
        document.querySelectorAll('.tut-chip').forEach(c => c.addEventListener('click', () => { rubro = c.dataset.rubro; filtrar(); }));
        buscar.addEventListener('input', filtrar);
        filtrar();
    })();
</script>
@include('sitio.partials.extras')
</body>
</html>
