{{-- Pie de la web pública y botón flotante de WhatsApp. Enlaza a las páginas de cada ciudad desde todas las páginas del sitio --}}
@php
    $enPortada ??= true;
    $whatsapp ??= 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode('Hola, quiero información sobre el sistema TUSHPA.');
    $ancla = fn (string $seccion): string => ($enPortada ? '' : route('sitio.inicio')).'#'.$seccion;
@endphp
<footer class="pie">
    <div class="kene kene-franja franja position-absolute top-0 start-0" style="color:rgba(165,180,252,.35)"></div>
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="bg-white d-inline-block rounded-3 px-3 py-2 mb-3"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="38"></div>
                <p class="small mb-0">Sistema de facturación electrónica y gestión para negocios del Perú. Hecho con orgullo en la Amazonía.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h5>Sistema</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ $ancla('rubros') }}">Rubros</a></li>
                    <li class="mb-2"><a href="{{ $ancla('funciones') }}">Funciones</a></li>
                    <li class="mb-2"><a href="{{ $ancla('planes') }}">Planes</a></li>
                    <li class="mb-2"><a href="{{ route('sitio.tutorial') }}">Tutoriales</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h5>Clientes</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ route('sitio.carta.crear') }}">Crea tu carta digital gratis</a></li>
                    <li class="mb-2"><a href="{{ config('sitio.demo.url') }}" target="_blank" rel="noopener">Ver demo (usuario y clave: {{ config('sitio.demo.usuario') }})</a></li>
                    <li class="mb-2"><a href="{{ route('sitio.ingresar') }}">Ingresar a mi sistema</a></li>
                    <li class="mb-2"><a href="{{ route('sitio.registro') }}">Prueba gratis</a></li>
                    <li class="mb-2"><a href="{{ $whatsapp }}" target="_blank" rel="noopener">Soporte por WhatsApp</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h5>Contacto</h5>
                <p class="small mb-1">+51 928 396 147</p>
                <p class="small mb-1">{{ config('soporte.correo') }}</p>
                <p class="small mb-0">Iquitos, Loreto · Perú</p>
            </div>
        </div>
        @if (config('ciudades'))
            <div class="mt-4">
                <h5>Facturación electrónica en tu ciudad</h5>
                <ul class="list-inline small mb-0">
                    @foreach (config('ciudades') as $slug => $ciudad)
                        <li class="list-inline-item me-3"><a href="{{ route('sitio.ciudad', $slug) }}">Sistema de facturación en {{ $ciudad['nombre'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
        <hr class="my-4" style="border-color:rgba(255,255,255,.12)">
        <p class="small text-center mb-0">&copy; {{ date('Y') }} TUSHPA · {{ config('soporte.nombre') }}. Todos los derechos reservados.</p>
    </div>
</footer>

<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="whatsapp-flotante" aria-label="Escríbenos por WhatsApp"><i class="fab fa-whatsapp"></i></a>
