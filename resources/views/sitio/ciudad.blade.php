{{-- Página de SEO local de una ciudad. Los textos salen de config/ciudades.php (variable $c, ver SeoSitio::ciudad()) --}}
@php
    $enPortada = false;
    $whatsapp = 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode('Hola, quiero información sobre el sistema TUSHPA en '.$c['nombre'].'.');
    $fondos = ['fondo-selva', 'fondo-rio', 'fondo-achiote', 'fondo-huito'];

    // SEO
    $seoTitulo = $c['titulo'];
    $seoDescripcion = $c['descripcion'];
    $seoUrl = $c['url'];
    $seoJson = \App\Support\SeoSitio::json([
        \App\Support\SeoSitio::organizacion(),
        \App\Support\SeoSitio::sitioWeb(),
        \App\Support\SeoSitio::servicio($c),
        \App\Support\SeoSitio::migas([['Inicio', \App\Support\SeoSitio::url('/')], [$c['h1'], $c['url']]]),
        \App\Support\SeoSitio::preguntas($c['preguntas']),
    ]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('sitio.partials.seo')
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('web/css/sitio.css') }}?v={{ filemtime(public_path('web/css/sitio.css')) }}">
</head>
<body>
{{-- Hoja (se reutiliza en las decoraciones) --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="hoja" viewBox="0 0 200 100">
        <path d="M2 50 C40 2 150 -8 198 50 C150 108 40 98 2 50 Z" fill="currentColor"/>
        <path d="M2 50 H190 M60 50 L95 22 M60 50 L95 78 M110 50 L140 26 M110 50 L140 74 M150 50 L170 34 M150 50 L170 66" stroke="#000" stroke-opacity=".25" stroke-width="3" fill="none"/>
    </symbol>
</svg>

@include('sitio.partials.menu')

{{-- Portada de la ciudad --}}
<section id="inicio" class="portada">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <svg class="hoja hoja-1"><use href="#hoja"/></svg>
    <svg class="hoja hoja-2"><use href="#hoja"/></svg>

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <p class="small mb-3" style="color:rgba(255,255,255,.7)"><a href="{{ route('sitio.inicio') }}" class="text-white-50">Inicio</a> › Facturación electrónica en {{ $c['nombre'] }}</p>
                <span class="etiqueta">{{ $c['nombre'] }}, {{ $c['region'] }}</span>
                <h1 class="mt-3 mb-4">{{ $c['h1'] }}</h1>
                <p class="bajada mb-4">{{ $c['bajada'] }}</p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis {{ config('sitio.dias_prueba') }} días</a>
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-hueco btn-lg"><i class="fab fa-whatsapp me-2"></i>Escríbenos por WhatsApp</a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="chip-portada"><i class="fas fa-circle-check" style="color:#ffd700"></i>Sin tarjeta</span>
                    <span class="chip-portada"><i class="fas fa-bolt" style="color:#ffd700"></i>Listo en segundos</span>
                    <span class="chip-portada"><i class="fas fa-headset" style="color:#ffd700"></i>Soporte por WhatsApp</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="tarjeta p-4" style="height:auto">
                    <h2 class="h5 fw-bold mb-3">Qué incluye tu sistema</h2>
                    <ul class="lista-check mb-0">
                        <li><i class="fas fa-circle-check"></i><span><b>Boletas, facturas, notas de crédito y débito</b> y notas de venta.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Guías de remisión electrónicas</b> para tus traslados.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Resúmenes diarios y anulaciones</b> desde el mismo sistema.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Envío por WhatsApp</b> e impresión en ticket o en A4.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Inventario, caja y reportes</b> listos para tu contador.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="rio-borde">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none"><path d="M0 40 C240 70 420 10 720 34 C1000 56 1180 14 1440 36 V70 H0 Z" fill="#0f1544"/></svg>
    </div>
</section>

{{-- Confianza --}}
<div class="confianza">
    <div class="container d-flex flex-wrap justify-content-center gap-4 text-center">
        <span><i class="fas fa-file-invoice"></i>Facturación electrónica SUNAT</span>
        <span><i class="fas fa-mobile-screen"></i>PC, tablet y celular</span>
        <span><i class="fas fa-gift"></i>{{ config('sitio.dias_prueba') }} días gratis, sin tarjeta</span>
        <span><i class="fas fa-location-dot"></i>{{ $c['sede'] ? 'Oficina en '.$c['nombre'] : 'Atención remota desde Iquitos' }}</span>
    </div>
</div>

{{-- Para los negocios de la ciudad --}}
<section id="negocios" class="seccion">
    <div class="container">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">{{ $c['nombre'] }}</span>
            <h2>Un sistema para los negocios de {{ $c['nombre'] }}</h2>
        </div>
        <div class="mx-auto mb-5 revelar" style="max-width:860px">
            @foreach ($c['parrafos'] as $parrafo)
                <p class="texto-gris fs-5 {{ $loop->last ? 'mb-0' : 'mb-3' }}">{{ $parrafo }}</p>
            @endforeach
        </div>
        <div class="row g-4">
            @foreach ($c['rubros'] as [$icono, $nombre, $texto])
                <div class="col-md-6 revelar">
                    <div class="tarjeta">
                        <div class="icono {{ $fondos[$loop->index % count($fondos)] }}"><i class="fas {{ $icono }}"></i></div>
                        <h3 class="fs-5 fw-bold mb-2">{{ $nombre }}</h3>
                        <p>{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-center texto-gris mt-4 mb-0 revelar">
            Mira todos los rubros y funciones en la <a href="{{ route('sitio.inicio') }}#rubros">portada de TUSHPA</a>.
        </p>
    </div>
</section>

{{-- Cómo te atendemos --}}
<section id="atencion" class="seccion seccion-oscura">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7 revelar">
                <span class="etiqueta">Atención</span>
                <h2 class="mt-3 mb-3" style="font-size:clamp(1.8rem,3.2vw,2.5rem)">{{ $c['atencion']['titulo'] }}</h2>
                <p class="mb-0" style="color:rgba(255,255,255,.8)">{{ $c['atencion']['texto'] }}</p>
            </div>
            <div class="col-lg-5 revelar">
                <div class="d-flex flex-wrap gap-3 justify-content-lg-end">
                    <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis</a>
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-hueco btn-lg"><i class="fab fa-whatsapp me-2"></i>WhatsApp</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Preguntas frecuentes de la ciudad --}}
<section class="seccion seccion-arena">
    <div class="container" style="max-width:860px">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">Preguntas frecuentes</span>
            <h2>Facturación electrónica en {{ $c['nombre'] }}: tus dudas</h2>
        </div>
        <div class="accordion revelar" id="preguntas">
            @foreach ($c['preguntas'] as [$pregunta, $respuesta])
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pregunta{{ $loop->index }}">{{ $pregunta }}</button>
                    </h3>
                    <div id="pregunta{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#preguntas">
                        <div class="accordion-body">{{ $respuesta }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Otras ciudades --}}
@if (count(config('ciudades')) > 1)
    <section class="seccion pb-0 seccion-arena">
        <div class="container text-center">
            <p class="texto-gris mb-3 revelar">También atendemos negocios en:</p>
            <div class="d-flex flex-wrap justify-content-center gap-2 revelar">
                @foreach (config('ciudades') as $slug => $otra)
                    @continue($slug === $c['slug'])
                    <a class="chip-portada" style="background:#fff;color:var(--tinta);border-color:rgba(30,41,59,.1)" href="{{ route('sitio.ciudad', $slug) }}"><i class="fas fa-location-dot" style="color:var(--selva-500)"></i>Sistema de facturación en {{ $otra['nombre'] }}</a>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Llamada final --}}
<section class="seccion pt-5 seccion-arena">
    <div class="container">
        <div class="llamada revelar">
            <div class="kene kene-laberinto"></div>
            <svg class="hoja" style="width:240px;right:-70px;bottom:-60px;transform:rotate(-30deg);opacity:.2"><use href="#hoja"/></svg>
            <h2 class="mb-3" style="font-size:clamp(1.7rem,3.2vw,2.5rem)">¿Listo para facturar desde {{ $c['nombre'] }}?</h2>
            <p class="mb-4" style="color:rgba(255,255,255,.8)">Crea tu sistema ahora y úsalo gratis {{ config('sitio.dias_prueba') }} días. Sin tarjeta.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis</a>
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-hueco btn-lg"><i class="fab fa-whatsapp me-2"></i>Hablar por WhatsApp</a>
            </div>
        </div>
    </div>
</section>

{{-- Pie y botón de WhatsApp --}}
@include('sitio.partials.pie')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
</body>
</html>
