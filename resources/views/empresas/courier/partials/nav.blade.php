{{-- Accesos del courier: todas sus pantallas a un toque --}}
@php
    $secciones = [['courier.index', 'Envíos', 'fa-box'], ['courier.manifiestos.index', 'Manifiestos', 'fa-clipboard-list'], ['courier.reparto', 'Reparto', 'fa-motorcycle'],
        ['courier.creditos.index', 'Crédito', 'fa-handshake'], ['courier.reclamos', 'Reclamos', 'fa-comment-dots'], ['courier.reportes', 'Reportes', 'fa-chart-column']];
    if (Auth::user()->esAdmin()) {
        $secciones[] = ['courier.tarifas', 'Tarifas', 'fa-route'];
    }
@endphp
<nav class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
    @foreach ($secciones as [$ruta, $texto, $icono])
        <a href="{{ route($ruta) }}" class="shrink-0 px-3 py-2 rounded-xl text-sm font-bold {{ request()->routeIs($ruta.'*') ? 'bg-orange-600 text-white shadow' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-orange-50' }}">
            <i class="fas {{ $icono }}"></i> {{ $texto }}</a>
    @endforeach
</nav>
