{{-- Menú de la web pública. En la portada los enlaces bajan a su sección; en las demás páginas ($enPortada = false) vuelven a la portada --}}
@php
    $enPortada ??= true;
    $ancla = fn (string $seccion): string => ($enPortada ? '' : route('sitio.inicio')).'#'.$seccion;
@endphp
<style>
    .menu-sub { border: 0; border-radius: 1rem; padding: .5rem; box-shadow: 0 20px 45px -15px rgba(15, 23, 42, .35); min-width: 230px; }
    .menu-sub .dropdown-item { border-radius: .65rem; padding: .55rem .8rem; font-weight: 500; color: #1e293b; }
    .menu-sub .dropdown-item i { width: 1.4rem; color: #2563eb; }
    .menu-sub .dropdown-item:hover { background: #eff6ff; color: #1d4ed8; }
    /* En la PC el submenú se abre al pasar el mouse */
    @media (min-width: 992px) {
        .menu .dropdown:hover > .menu-sub { display: block; margin-top: 0; }
        .menu .dropdown > .menu-sub::before { content: ''; position: absolute; top: -12px; left: 0; right: 0; height: 12px; }
    }
</style>
<header class="menu">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="{{ $ancla('inicio') }}"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="44"></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    {{-- Submenús para que todo entre en una línea --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">El sistema <i class="fas fa-chevron-down small ms-1"></i></a>
                        <ul class="dropdown-menu menu-sub">
                            <li><a class="dropdown-item" href="{{ $ancla('rubros') }}"><i class="fas fa-store"></i>Rubros</a></li>
                            <li><a class="dropdown-item" href="{{ $ancla('facturacion') }}"><i class="fas fa-file-invoice"></i>Facturación SUNAT</a></li>
                            <li><a class="dropdown-item" href="{{ $ancla('funciones') }}"><i class="fas fa-puzzle-piece"></i>Funciones</a></li>
                            <li><a class="dropdown-item" href="{{ $ancla('clientes') }}"><i class="fas fa-users"></i>Clientes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="{{ $ancla('planes') }}">Planes</a></li>
                    @if (\App\Support\Certificados::activos()->isNotEmpty())
                        <li class="nav-item"><a class="nav-link" href="{{ $ancla('certificados') }}">Certificado digital</a></li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Recursos <i class="fas fa-chevron-down small ms-1"></i></a>
                        <ul class="dropdown-menu menu-sub">
                            <li><a class="dropdown-item" href="{{ route('sitio.carta.crear') }}"><i class="fas fa-qrcode"></i>Crea tu carta gratis</a></li>
                            <li><a class="dropdown-item" href="{{ route('sitio.tutorial') }}"><i class="fas fa-graduation-cap"></i>Tutoriales</a></li>
                        <li><a class="dropdown-item" href="{{ route('sitio.reseller') }}"><i class="fas fa-handshake"></i>Sé reseller y gana</a></li>
                            <li><a class="dropdown-item" href="{{ $ancla('contacto') }}"><i class="fas fa-envelope"></i>Contacto</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalDemo"><i class="fas fa-circle-play me-1"></i>Ver demo</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.ingresar') }}"><i class="fas fa-right-to-bracket me-1"></i>Ingresar</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-selva" href="{{ route('sitio.registro') }}">Prueba gratis</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>
@include('sitio.partials.demo')
