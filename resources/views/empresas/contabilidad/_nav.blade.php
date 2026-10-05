<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@php
    $menuConta = [
        ['contabilidad.plan', [], 'Plan contable', 'fa-sitemap'],
        ['contabilidad.centralizar', ['tipo' => 'ventas'], 'Centralizar ventas', 'fa-cash-register'],
        ['contabilidad.centralizar', ['tipo' => 'compras'], 'Centralizar compras', 'fa-cart-shopping'],
        ['contabilidad.diario', [], 'Libro diario', 'fa-book'],
        ['contabilidad.mayor', [], 'Libro mayor', 'fa-book-open'],
        ['contabilidad.balance', [], 'Balance de comprobación', 'fa-scale-balanced'],
        ['contabilidad.estados', [], 'Estados financieros', 'fa-chart-pie'],
    ];
@endphp
<nav class="flex gap-1 overflow-x-auto bg-white rounded-2xl shadow-sm p-1 mb-4 print:hidden">
    @foreach ($menuConta as [$ruta, $params, $nombre, $icono])
        @php $activo = request()->routeIs($ruta) && (!isset($params['tipo']) || request()->route('tipo') === $params['tipo']); @endphp
        <a href="{{ route($ruta, $params) }}"
           class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold whitespace-nowrap {{ $activo ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-indigo-50 hover:text-indigo-700' }}">
            <i class="fas {{ $icono }}"></i>{{ $nombre }}</a>
    @endforeach
</nav>
@include('empresas.partials.alert')
