{{-- Menú de la web pública. En la portada los enlaces bajan a su sección; en las demás páginas ($enPortada = false) vuelven a la portada --}}
@php
    $enPortada ??= true;
    $ancla = fn (string $seccion): string => ($enPortada ? '' : route('sitio.inicio')).'#'.$seccion;
@endphp
<header class="menu">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="{{ $ancla('inicio') }}"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="44"></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('rubros') }}">Rubros</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('facturacion') }}">Facturación</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('funciones') }}">Funciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('planes') }}">Planes</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('clientes') }}">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('contacto') }}">Contacto</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.tutorial') }}"><i class="fas fa-graduation-cap me-1"></i>Tutoriales</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.carta.crear') }}"><i class="fas fa-qrcode me-1"></i>Crea tu carta</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalDemo"><i class="fas fa-circle-play me-1"></i>Ver demo</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.ingresar') }}"><i class="fas fa-right-to-bracket me-1"></i>Ingresar</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-selva" href="{{ route('sitio.registro') }}">Prueba gratis</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>
@include('sitio.partials.demo')
