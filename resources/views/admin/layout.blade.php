<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Panel') · TUSHPA</title>
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
    <style>
        [x-cloak]{display:none!important}
        /* Tablas del panel en celular: cada fila es una tarjeta y cada dato lleva su título (data-label) */
        @media (max-width: 767px) {
            .tabla-movil thead { display: none; }
            .tabla-movil tr { display: block; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
            .tabla-movil td { display: block; padding: 4px 16px !important; }
            .tabla-movil td[data-label]::before { content: attr(data-label); display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #94a3b8; }
            .tabla-movil td.celda-acciones { text-align: left; white-space: normal; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    @auth('superadmin')
        {{-- En celular el menú se abre con el botón ☰ (en PC va en una sola fila) --}}
        <header class="bg-slate-900 text-white" x-data="{ menu: false }">
            <div class="@yield('ancho', 'max-w-6xl') mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
                <img src="{{ asset('imagenes/icono.png') }}" alt="" class="w-8 h-8 rounded-lg bg-white p-1">
                <a href="{{ route('admin.clientes.index') }}" class="font-bold">TUSHPA <span class="font-normal text-slate-400 hidden sm:inline">· Panel de clientes</span></a>
                <button type="button" @click="menu = !menu" class="lg:hidden ml-auto px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700" aria-label="Menú">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <nav :class="menu ? 'flex' : 'hidden'" class="order-last lg:order-none w-full lg:w-auto lg:ml-4 lg:flex flex-col lg:flex-row lg:flex-wrap gap-1 text-sm">
                    <a href="{{ route('admin.clientes.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.clientes.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Clientes</a>
                    @php $ticketsNuevos = \App\Http\Controllers\Admin\TicketController::sinLeer(); @endphp
                    <a href="{{ route('admin.tickets.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.tickets.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">
                        Tickets @if ($ticketsNuevos)<span class="ml-1 px-1.5 rounded-full bg-rose-500 text-[11px] font-bold">{{ $ticketsNuevos }}</span>@endif
                    </a>
                    <a href="{{ route('admin.planes.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.planes.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Planes</a>
                    @unless (auth('superadmin')->user()->es_dueno)
                        <a href="{{ route('admin.cobros.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.cobros.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Mis datos de cobro</a>
                    @endunless
                    @if (auth('superadmin')->user()->es_dueno || auth('superadmin')->user()->puede_crear_usuarios)
                        <a href="{{ route('admin.usuarios.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.usuarios.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Usuarios</a>
                    @endif
                    @if (auth('superadmin')->user()->es_dueno)
                        @php $mensajesNuevos = \Illuminate\Support\Facades\DB::connection('central')->table('web_contactos')->where('atendido', false)->count(); @endphp
                        <a href="{{ route('admin.mensajes.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.mensajes.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">
                            Mensajes web @if ($mensajesNuevos)<span class="ml-1 px-1.5 rounded-full bg-rose-500 text-[11px] font-bold">{{ $mensajesNuevos }}</span>@endif
                        </a>
                        <a href="{{ route('admin.anuncios.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.anuncios.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Anuncios</a>
                        <a href="{{ route('admin.promociones.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.promociones.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Promociones</a>
                        <a href="{{ route('admin.tutoriales.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.tutoriales.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Tutoriales</a>
                        <a href="{{ route('admin.certificados.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.certificados.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Certificados</a>
                        <a href="{{ route('admin.prospectos.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.prospectos.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Prospectos</a>
                        <a href="{{ route('admin.visitas.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.visitas.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Visitas</a>
                        <a href="{{ route('admin.logos.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.logos.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Logos web</a>
                        <a href="{{ route('admin.agente.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.agente.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Agente</a>
                        <a href="{{ route('admin.respaldos.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.respaldos.*') ? 'bg-slate-700' : 'hover:bg-slate-800' }}">Respaldos</a>
                    @endif
                </nav>
                <span class="lg:ml-auto text-sm text-slate-400 hidden lg:inline">{{ auth('superadmin')->user()->nombre }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="text-sm px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700">Salir</button>
                </form>
            </div>
        </header>
    @endauth

    <main class="@yield('ancho', 'max-w-6xl') mx-auto px-3 sm:px-4 py-4 sm:py-6">
        @if (session('ok'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
@include('partials.avisos')
</body>
</html>
