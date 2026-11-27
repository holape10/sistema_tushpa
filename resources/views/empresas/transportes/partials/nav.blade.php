{{-- Accesos de transportes: todas sus pantallas a un toque --}}
@php
    $secciones = [];
    if (Auth::user()->puedeCobrar()) {
        $secciones[] = ['transportes.index', 'Vender pasajes', 'fa-ticket'];
    }
    $secciones[] = ['transportes.embarque', 'Embarque', 'fa-qrcode'];
    if (Auth::user()->esAdmin()) {
        array_push($secciones, ['transportes.salidas', 'Salidas', 'fa-calendar-days'], ['transportes.unidades', 'Unidades y planos', 'fa-bus'],
            ['transportes.rutas', 'Rutas', 'fa-route'], ['transportes.reporte', 'Reporte', 'fa-chart-column'], ['transportes.configuracion', 'Configuración', 'fa-gear']);
    }
@endphp
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<nav class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
    @foreach ($secciones as [$ruta, $texto, $icono])
        <a href="{{ route($ruta) }}" class="shrink-0 px-3 py-2 rounded-xl text-sm font-bold {{ request()->routeIs($ruta) ? 'bg-indigo-600 text-white shadow' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-indigo-50' }}">
            <i class="fas {{ $icono }}"></i> {{ $texto }}</a>
    @endforeach
</nav>
