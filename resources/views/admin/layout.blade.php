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
        @php
            $yo = auth('superadmin')->user();
            $ticketsNuevos = \App\Http\Controllers\Admin\TicketController::sinLeer();
            $mensajesNuevos = $yo->es_dueno ? \Illuminate\Support\Facades\DB::connection('central')->table('web_contactos')->where('atendido', false)->count() : 0;
            // Menú por grupos, como el del sistema de ventas: [nombre, ruta, patrón de ruta activa, aviso, ícono (path SVG)]
            $grupos = array_filter([
                'Principal' => [
                    ['Tablero', 'admin.tablero', 'admin.tablero', 0, 'M4 13h6V4H4v9zm0 7h6v-5H4v5zm10 0h6v-9h-6v9zm0-16v5h6V4h-6z'],
                    ['Clientes', 'admin.clientes.index', 'admin.clientes.*', 0, 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['Tickets', 'admin.tickets.index', 'admin.tickets.*', $ticketsNuevos, 'M8 10h8M8 14h5m-9 6l3-3h10a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14z'],
                ],
                'Ventas' => array_filter([
                    ['Planes', 'admin.planes.index', 'admin.planes.*', 0, 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-7-3-7 3V6a2 2 0 012-2z'],
                    $yo->es_dueno ? ['Promociones', 'admin.promociones.index', 'admin.promociones.*', 0, 'M7 7h.01M3 11l8.59 8.59a2 2 0 002.82 0l6.18-6.18a2 2 0 000-2.82L12 2H3v9z'] : null,
                    $yo->es_dueno ? ['Prospectos', 'admin.prospectos.index', 'admin.prospectos.*', 0, 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'] : null,
                    $yo->es_dueno ? ['Certificados', 'admin.certificados.index', 'admin.certificados.*', 0, 'M12 15l-3 6 3-1.5L15 21l-3-6zm0 0a6 6 0 100-12 6 6 0 000 12z'] : null,
                    ['Mis cuentas y QR', 'admin.cobros.index', 'admin.cobros.*', 0, 'M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z'],
                ]),
                'Página web' => $yo->es_dueno ? [
                    ['Mensajes web', 'admin.mensajes.index', 'admin.mensajes.*', $mensajesNuevos, 'M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['Anuncios', 'admin.anuncios.index', 'admin.anuncios.*', 0, 'M11 5L6 9H2v6h4l5 4V5zm4.54 3.46a5 5 0 010 7.07'],
                    ['Tutoriales', 'admin.tutoriales.index', 'admin.tutoriales.*', 0, 'M12 6.25C10.5 5 8.5 4.5 6 4.5v13c2.5 0 4.5.5 6 1.75m0-13c1.5-1.25 3.5-1.75 6-1.75v13c-2.5 0-4.5.5-6 1.75m0-13v13'],
                    ['Visitas', 'admin.visitas.index', 'admin.visitas.*', 0, 'M15 12a3 3 0 11-6 0 3 3 0 016 0zm-3-7C7 5 3 12 3 12s4 7 9 7 9-7 9-7-4-7-9-7z'],
                    ['Logos web', 'admin.logos.index', 'admin.logos.*', 0, 'M4 16l4.59-4.59a2 2 0 012.82 0L16 16m-2-2l1.59-1.59a2 2 0 012.82 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ] : [],
                'Sistema' => array_filter([
                    $yo->es_dueno || $yo->puede_crear_usuarios ? ['Usuarios', 'admin.usuarios.index', 'admin.usuarios.*', 0, 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'] : null,
                    $yo->es_dueno ? ['Agente', 'admin.agente.index', 'admin.agente.*', 0, 'M9 17v2m6-2v2M5 7h14a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2zm7-4v4'] : null,
                    $yo->es_dueno ? ['Respaldos', 'admin.respaldos.index', 'admin.respaldos.*', 0, 'M4 7v10c0 2 3.6 3 8 3s8-1 8-3V7M4 7c0 2 3.6 3 8 3s8-1 8-3M4 7c0-2 3.6-3 8-3s8 1 8 3m-16 5c0 2 3.6 3 8 3s8-1 8-3'] : null,
                ]),
            ]);
            $grupoActual = collect($grupos)->search(fn ($items) => collect($items)->contains(fn ($i) => request()->routeIs($i[2])));
        @endphp
        {{-- Menú lateral como el del sistema: escondido detrás de ☰ (se abre con clic o pasando el mouse); en PC se puede dejar fijo --}}
        <div x-data="{
                abierto: false,
                grupo: @js($grupoActual ?: null),
                fijo: (() => { try { return localStorage.getItem('panel_menu_fijo') === '1' } catch (e) { return false } })(),
                grande: window.matchMedia('(min-width: 1024px)').matches,
                get anclado() { return this.fijo && this.grande },
                alternarFijo() { this.fijo = !this.fijo; this.abierto = false; try { localStorage.setItem('panel_menu_fijo', this.fijo ? '1' : '0') } catch (e) {} },
             }" x-init="window.matchMedia('(min-width: 1024px)').addEventListener('change', e => grande = e.matches); $watch('anclado', v => document.body.classList.toggle('menu-anclado', v)); document.body.classList.toggle('menu-anclado', anclado)"
             @keydown.escape.window="abierto = false">
            <aside :class="abierto || anclado ? 'translate-x-0' : '-translate-x-full'" @mouseleave="if (!anclado) abierto = false"
                   class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-indigo-950 text-white transition-transform duration-200 shadow-2xl flex flex-col isolate">
                <div class="kene kene-meandro absolute inset-0 -z-10 text-white/[0.04]" aria-hidden="true"></div>
                <div class="px-4 pt-4 pb-3 flex items-center gap-2 shrink-0">
                    <img src="{{ asset('imagenes/icono.png') }}" alt="" class="w-9 h-9 rounded-lg bg-white p-1">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-sm leading-tight">TUSHPA</p>
                        <p class="text-xs text-indigo-300 leading-tight truncate">Panel · {{ $yo->nombre }}</p>
                    </div>
                    <button type="button" x-show="grande" @click="alternarFijo()" class="p-1.5 rounded-lg hover:bg-indigo-800"
                            :class="fijo ? 'text-amber-300' : 'text-indigo-300'" :title="fijo ? 'Soltar el menú' : 'Dejar el menú fijo'" aria-label="Fijar menú">
                        <svg class="w-5 h-5" :fill="fijo ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                    </button>
                    <button type="button" x-show="!anclado" @click="abierto = false" class="p-1.5 rounded-lg text-indigo-300 hover:bg-indigo-800" aria-label="Cerrar menú">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="kene kene-franja h-3 mx-3 shrink-0 text-indigo-400/50" aria-hidden="true"></div>

                <nav class="flex-1 overflow-y-auto overscroll-contain px-2 py-3 text-sm space-y-0.5 [scrollbar-width:thin] [scrollbar-color:#6366f1_transparent]">
                    @foreach ($grupos as $nombreGrupo => $items)
                        @php
                            $enlace = fn ($i, $chico = false) => '<a href="'.e(route($i[1])).'" class="flex items-center gap-3 px-3 '.($chico ? 'py-1.5 text-[13px]' : 'py-2 font-medium').' rounded-lg transition '
                                .(request()->routeIs($i[2]) ? 'bg-white text-indigo-950 font-semibold' : 'text-indigo-100 hover:bg-indigo-800 hover:text-white').'">'
                                .'<svg class="w-4 h-4 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="'.$i[4].'"/></svg>'
                                .'<span class="flex-1">'.e($i[0]).'</span>'
                                .($i[3] ? '<span class="px-1.5 rounded-full bg-rose-500 text-white text-[11px] font-bold">'.$i[3].'</span>' : '').'</a>';
                            $avisos = array_sum(array_column($items, 3));
                        @endphp
                        @if ($nombreGrupo === 'Principal')
                            @foreach ($items as $i){!! $enlace($i) !!}@endforeach
                            <div class="kene kene-franja h-3 my-2 mx-1 text-indigo-400/40" aria-hidden="true"></div>
                        @else
                            <button type="button" @click="grupo = grupo === @js($nombreGrupo) ? null : @js($nombreGrupo)"
                                    class="w-full flex items-center px-3 py-2 rounded-lg font-medium transition hover:bg-indigo-800 {{ $nombreGrupo === $grupoActual ? 'text-white' : 'text-indigo-100' }}">
                                <span class="flex-1 text-left">{{ $nombreGrupo }}</span>
                                @if ($avisos)<span x-show="grupo !== @js($nombreGrupo)" class="mr-2 px-1.5 rounded-full bg-rose-500 text-[11px] font-bold">{{ $avisos }}</span>@endif
                                <span class="mr-2 text-[10px] font-semibold text-indigo-300">{{ count($items) }}</span>
                                <svg :class="grupo === @js($nombreGrupo) ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <div x-show="grupo === @js($nombreGrupo)" x-collapse @if ($nombreGrupo !== $grupoActual) x-cloak @endif class="pl-3 space-y-0.5 pb-1">
                                @foreach ($items as $i){!! $enlace($i, true) !!}@endforeach
                            </div>
                        @endif
                    @endforeach
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}" class="p-3 shrink-0 border-t border-indigo-800/70 space-y-2">
                    @csrf
                    {{-- Mi perfil: foto, nombre, celular, cumpleaños y contraseña --}}
                    <a href="{{ route('admin.perfil') }}" class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg transition {{ request()->routeIs('admin.perfil*') ? 'bg-white text-indigo-950' : 'hover:bg-indigo-800' }}">
                        @include('admin.partials.avatar', ['usuario' => $yo, 'tamano' => 'w-9 h-9 text-xs'])
                        <span class="min-w-0 flex-1 leading-tight"><span class="block text-sm font-semibold truncate">{{ $yo->nombre }}</span>
                            <span class="block text-[11px] opacity-70">Mi perfil · contraseña</span></span>
                    </a>
                    <button class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-indigo-900 hover:bg-rose-600 text-sm font-semibold transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Salir</button>
                </form>
            </aside>
            <div x-show="abierto && !anclado" x-transition.opacity x-cloak @click="abierto = false" class="fixed inset-0 bg-black/30 z-30"></div>

            {{-- Barra de arriba con el botón ☰ (se esconde cuando el menú está fijo) --}}
            <header x-show="!anclado" class="sticky top-0 z-20 bg-white shadow-sm px-3 py-2 flex items-center gap-3">
                <button type="button" @mouseenter="if (grande) abierto = true" @click="abierto = !abierto"
                        class="p-2.5 rounded-xl bg-indigo-950 text-white shadow hover:bg-indigo-800 relative" aria-label="Menú">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    @if ($ticketsNuevos + $mensajesNuevos)<span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-rose-500 ring-2 ring-white"></span>@endif
                </button>
                <p class="font-bold text-slate-800">TUSHPA <span class="font-normal text-slate-400">· @yield('title', 'Panel')</span></p>
            </header>
        </div>
        <style>@media (min-width: 1024px) { body.menu-anclado .contenido-panel { padding-left: 16rem; } }</style>
    @endauth

    <div class="contenido-panel transition-[padding] duration-200">
        <main class="@yield('ancho', 'max-w-6xl') mx-auto px-3 sm:px-4 py-4 sm:py-6">
            @if (session('ok'))
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('ok') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
@include('partials.avisos')
</body>
</html>
