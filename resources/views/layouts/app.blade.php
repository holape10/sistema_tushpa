<!DOCTYPE html>
<html lang="es">
<head>

    <style>[x-cloak]{display:none!important}</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <title>@yield('title', 'Sistema Tushpa')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

    <!-- Zona que controla mostrar/ocultar: engloba hamburguesa + sidebar -->
    <div @mouseleave="sidebarOpen = false">

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 w-64 bg-indigo-900 text-white transform transition-transform duration-200 overflow-y-auto shadow-2xl">
            <div class="px-5 py-5 border-b border-indigo-800 flex items-center gap-3">
                <img src="{{ asset('imagenes/512.png') }}" alt="Logo" class="w-9 h-9 rounded-lg object-contain bg-white p-1">
                <div>
                    <p class="font-bold text-sm leading-tight">{{ auth()->user()->IdEmpresa }}</p>
                    <p class="text-xs text-indigo-300 leading-tight">{{ auth()->user()->apeusu }}</p>
                </div>
            </div>

            <nav class="px-2 py-4 space-y-1 text-sm">
                @foreach ($menu ?? [] as $grupo => $modulos)
                    @if ($grupo === 'Principal')
                        @foreach ($modulos as $mod)
                            <a href="{{ $mod->mod_url }}"
                               class="flex items-center px-3 py-2 rounded-lg text-indigo-100 hover:bg-indigo-800 transition font-medium">
                                {{ $mod->mod_nom }}
                            </a>
                        @endforeach
                    @else
                        <div x-data="{ show: false }" @mouseenter="show = true" @mouseleave="show = false" class="relative">
                            <div class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-indigo-100 cursor-default">
                                <span class="font-medium">{{ $grupo }}</span>
                                <svg :class="show ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                            <div x-show="show" x-collapse class="pl-3 space-y-0.5">
                                @foreach ($modulos as $mod)
                                    <a href="{{ $mod->mod_url }}"
                                       class="block px-3 py-1.5 rounded-lg text-indigo-200 hover:bg-indigo-800 hover:text-white transition text-[13px]">
                                        {{ $mod->mod_nom }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
        </aside>

        <!-- Botón hamburguesa: fijo, siempre visible -->
        <button @mouseenter="sidebarOpen = true" @click="sidebarOpen = !sidebarOpen"
            class="fixed top-3 left-3 z-50 bg-indigo-900 text-white p-2.5 rounded-xl shadow-lg hover:bg-indigo-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Overlay oscuro cuando está abierto -->
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
             class="fixed inset-0 bg-black/30 z-30"></div>
    </div>

    <!-- Contenido -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm px-4 py-3 flex items-center justify-between pl-16">
            <h1 class="text-gray-700 font-semibold text-sm sm:text-base">@yield('title', 'Panel')</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-xs sm:text-sm text-red-600 hover:underline">Cerrar sesión</button>
            </form>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</div>

    
@stack('scripts')
</body>
</html>