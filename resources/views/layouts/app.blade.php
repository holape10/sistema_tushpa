<!DOCTYPE html>
<html lang="es">
<head>

    <style>[x-cloak]{display:none!important}</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
    <title>@yield('title', 'Sistema Tushpa')</title>
    @php
        // Menú: solo módulos que ya tienen pantalla (los '#' aún no existen) y el grupo de la página actual abierto
        $esActual = function ($mod) {
            $url = parse_url($mod->mod_url);
            $ruta = '/' . trim($url['path'] ?? '', '/');
            $actual = '/' . trim(request()->path(), '/');
            $coincide = $actual === $ruta || ($ruta !== '/' && str_starts_with($actual . '/', $ruta . '/'));
            if (!$coincide) return false;
            parse_str($url['query'] ?? '', $q);
            foreach ($q as $k => $v) {
                if ((string) request()->query($k) !== (string) $v) return false;
            }
            return true;
        };
        // Los módulos con URL '#' aún no tienen pantalla: se muestran apagados como guía de lo que falta
        $listo = fn ($mod) => $mod->mod_url && $mod->mod_url !== '#';
        $gruposMenu = collect($menu ?? []);
        $grupoActual = $gruposMenu->search(fn ($mods) => $mods->filter($listo)->contains($esActual));
        $modulosMenu = $gruposMenu->flatMap(fn ($mods, $grupo) => $mods->map(fn ($m) => [
            'nombre' => $m->mod_nom, 'grupo' => $grupo, 'url' => $listo($m) ? url($m->mod_url) : null,
        ]))->values();
    @endphp
    <script>
        // Menú lateral: se abre con clic (o pasando sobre el botón) y NO se cierra al mover el mouse,
        // así se puede bajar arrastrando la barra o con el teclado. En pantallas grandes se puede dejar fijo.
        function menuLateral(modulos, grupoActual) {
            const leer = k => { try { return localStorage.getItem(k); } catch (e) { return null; } };
            const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
            const sinTildes = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            return {
                abierto: false,
                fijo: leer('menu_fijo') === '1',
                grande: window.matchMedia('(min-width: 1024px)').matches,
                grupo: grupoActual || leer('menu_grupo') || null,
                filtro: '',
                activo: 0,
                modulos,
                init() {
                    window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { this.grande = e.matches; });
                },
                get resultados() {
                    const q = sinTildes(this.filtro.trim());
                    return q ? this.modulos.filter(m => sinTildes(m.nombre + ' ' + m.grupo).includes(q)) : [];
                },
                // Fijo solo aplica en pantallas grandes; en celular siempre es un panel que se abre y cierra
                get anclado() { return this.fijo && this.grande; },
                get visible() { return this.abierto || this.anclado; },
                abrir() { this.abierto = true; },
                cerrar() { this.abierto = false; this.filtro = ''; },
                alternarGrupo(g) { this.grupo = this.grupo === g ? null : g; guardar('menu_grupo', this.grupo || ''); },
                alternarFijo() { this.fijo = !this.fijo; guardar('menu_fijo', this.fijo ? '1' : '0'); this.abierto = false; },
                ir(m) { if (m && m.url) window.location.href = m.url; },
                mover(p) { this.activo = Math.min(Math.max(this.activo + p, 0), Math.max(this.resultados.length - 1, 0)); },
            };
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
</head>

<body class="bg-gray-100" x-data="menuLateral(@js($modulosMenu), @js($grupoActual ?: null))"
      @keydown.escape.window="cerrar()" @keydown.window.alt.m.prevent="abierto ? cerrar() : (abrir(), $nextTick(() => $refs.filtroMenu.focus()))">

<div class="flex h-screen overflow-hidden">

    <!-- Menú lateral -->
    <aside :class="visible ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-indigo-900 text-white transform transition-transform duration-200 shadow-2xl flex flex-col isolate">
        {{-- Textura kené de fondo: muy tenue para no competir con el texto --}}
        <div class="kene kene-meandro absolute inset-0 -z-10 text-white/[0.04]" aria-hidden="true"></div>

        <div class="px-4 pt-4 pb-3 shrink-0">
            <div class="flex items-center gap-2">
                <img src="{{ asset('imagenes/icono.png') }}" alt="Logo" class="w-9 h-9 rounded-lg object-contain bg-white p-1">
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-sm leading-tight truncate">{{ auth()->user()->IdEmpresa }}</p>
                    <p class="text-xs text-indigo-300 leading-tight truncate">{{ auth()->user()->apeusu }}</p>
                </div>
                <button type="button" x-show="grande" @click="alternarFijo()" class="p-1.5 rounded-lg hover:bg-indigo-800"
                        :class="fijo ? 'text-amber-300' : 'text-indigo-300'" :title="fijo ? 'Soltar el menú' : 'Dejar el menú fijo'" aria-label="Fijar menú">
                    <svg class="w-5 h-5" :fill="fijo ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                </button>
                <button type="button" x-show="!anclado" @click="cerrar()" class="p-1.5 rounded-lg text-indigo-300 hover:bg-indigo-800" aria-label="Cerrar menú">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Buscador de módulos: llegar a cualquier pantalla sin bajar por el menú --}}
            <div class="relative mt-3">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-indigo-300 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input x-ref="filtroMenu" type="search" x-model="filtro" @input="activo = 0" placeholder="Buscar módulo…  (Alt+M)" autocomplete="off"
                       @keydown.arrow-down.prevent="mover(1)" @keydown.arrow-up.prevent="mover(-1)" @keydown.enter.prevent="ir(resultados[activo])"
                       class="w-full h-9 pl-9 pr-3 rounded-lg border-0 bg-indigo-800/70 text-sm text-white placeholder:text-indigo-300 focus:ring-2 focus:ring-indigo-400">
            </div>
        </div>
        <div class="kene kene-franja h-3 mx-3 shrink-0 text-indigo-400/50" aria-hidden="true"></div>

        <nav class="flex-1 overflow-y-auto overscroll-contain px-2 py-3 text-sm [scrollbar-width:thin] [scrollbar-color:#6366f1_transparent]">
            {{-- Resultados del buscador --}}
            <div x-show="filtro.trim()" x-cloak class="space-y-0.5">
                <template x-for="(m, i) in resultados" :key="m.grupo + m.nombre">
                    <a :href="m.url || null" @mouseenter="activo = i" :aria-disabled="!m.url"
                       :class="!m.url ? 'opacity-50 cursor-default' : (i === activo ? 'bg-indigo-700 text-white' : 'text-indigo-100')"
                       class="block px-3 py-2 rounded-lg">
                        <span class="font-medium" x-text="m.nombre"></span>
                        <span x-show="!m.url" class="ml-1 text-[10px] font-semibold uppercase bg-indigo-800 text-indigo-300 px-1.5 py-0.5 rounded">Pronto</span>
                        <span class="block text-[11px] text-indigo-300" x-text="m.grupo"></span>
                    </a>
                </template>
                <p x-show="!resultados.length" class="px-3 py-4 text-center text-indigo-300 text-xs">Sin resultados</p>
            </div>

            <div x-show="!filtro.trim()" class="space-y-0.5">
                @foreach ($gruposMenu as $grupo => $modulos)
                    @if ($grupo === 'Principal')
                        @foreach ($modulos as $mod)
                            @if ($listo($mod))
                                <a href="{{ url($mod->mod_url) }}"
                                   class="flex items-center justify-between px-3 py-2 rounded-lg font-medium transition {{ $esActual($mod) ? 'bg-white text-indigo-900' : 'text-indigo-100 hover:bg-indigo-800' }}">
                                    {{ $mod->mod_nom }}
                                    @if ($esActual($mod))
                                        <span class="w-2 h-2 rotate-45 bg-indigo-500 shrink-0" aria-hidden="true"></span>
                                    @endif
                                </a>
                            @else
                                <span class="flex items-center justify-between px-3 py-2 rounded-lg font-medium text-indigo-100/50 cursor-default" title="Aún en desarrollo">
                                    {{ $mod->mod_nom }}
                                    <span class="text-[10px] font-semibold uppercase bg-indigo-800 text-indigo-300 px-1.5 py-0.5 rounded">Pronto</span>
                                </span>
                            @endif
                        @endforeach
                        <div class="kene kene-franja h-3 my-2 mx-1 text-indigo-400/40" aria-hidden="true"></div>
                    @else
                        <div>
                            <button type="button" @click="alternarGrupo(@js($grupo))" :aria-expanded="grupo === @js($grupo)"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg font-medium transition hover:bg-indigo-800 {{ $grupo === $grupoActual ? 'text-white' : 'text-indigo-100' }}">
                                <span>{{ $grupo }}</span>
                                @php $listos = $modulos->filter($listo)->count(); @endphp
                                <span class="ml-auto mr-2 text-[10px] font-semibold {{ $listos ? 'text-indigo-300' : 'text-indigo-400/60' }}" title="{{ $listos }} de {{ $modulos->count() }} listos">{{ $listos }}/{{ $modulos->count() }}</span>
                                <svg :class="grupo === @js($grupo) ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            @php
                                // Para que el menú no sea largo: los reportes y lo que aún no está listo van en submenús plegables
                                $esReporte = fn ($m) => str_starts_with($m->mod_nom, 'Reporte:') || str_starts_with($m->mod_nom, 'Reporte ');
                                $principales = $modulos->filter(fn ($m) => !$esReporte($m) && $listo($m));
                                $subgrupos = [
                                    ['Reportes', 'M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z', $modulos->filter($esReporte)],
                                    ['Próximamente', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', $modulos->filter(fn ($m) => !$esReporte($m) && !$listo($m))],
                                ];
                                $enlace = fn ($m, $nombre) => $listo($m)
                                    ? '<a href="' . e(url($m->mod_url)) . '" class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-lg text-[13px] transition ' . ($esActual($m) ? 'bg-white text-indigo-900 font-semibold' : 'text-indigo-200 hover:bg-indigo-800 hover:text-white') . '">' . e($nombre)
                                        . ($esActual($m) ? '<span class="w-1.5 h-1.5 rotate-45 bg-indigo-500 shrink-0" aria-hidden="true"></span>' : '') . '</a>'
                                    : '<span class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-lg text-[13px] text-indigo-200/45 cursor-default" title="Aún en desarrollo">' . e($nombre)
                                        . '<span class="shrink-0 text-[9px] font-semibold uppercase bg-indigo-800 text-indigo-300/80 px-1.5 py-0.5 rounded">Pronto</span></span>';
                            @endphp
                            <div x-show="grupo === @js($grupo)" x-collapse class="pl-3 space-y-0.5 pb-1">
                                @foreach ($principales as $mod)
                                    {!! $enlace($mod, $mod->mod_nom) !!}
                                @endforeach
                                @foreach ($subgrupos as [$nombreSub, $icono, $items])
                                    @continue($items->isEmpty())
                                    @php $abiertoSub = $items->contains(fn ($m) => $listo($m) && $esActual($m)); @endphp
                                    <div x-data="{ sub: {{ $abiertoSub ? 'true' : 'false' }} }">
                                        <button type="button" @click="sub = !sub"
                                                class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-[13px] transition {{ $nombreSub === 'Reportes' ? 'text-indigo-100 hover:bg-indigo-800' : 'text-indigo-300/70 hover:bg-indigo-800/60' }}">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}"/></svg>
                                            <span class="flex-1 text-left font-medium">{{ $nombreSub }}</span>
                                            <span class="text-[10px] text-indigo-300/80">{{ $items->count() }}</span>
                                            <svg :class="sub ? 'rotate-90' : ''" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                        <div x-show="sub" x-collapse class="ml-3 pl-2 border-l border-indigo-700/70 space-y-0.5">
                                            @foreach ($items as $mod)
                                                {!! $enlace($mod, trim(preg_replace('/^Reporte:?\s*/', '', $mod->mod_nom))) !!}
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </nav>
    </aside>

    <!-- Botón del menú: se abre con clic o pasando el mouse; se cierra con clic fuera, Esc o la X -->
    <button x-show="!anclado && !abierto" @mouseenter="abrir()" @click="abierto ? cerrar() : abrir()"
            class="fixed top-3 left-3 z-50 bg-indigo-900 text-white p-2.5 rounded-xl shadow-lg hover:bg-indigo-800 transition" aria-label="Menú">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <!-- Fondo oscuro con el menú abierto (clic para cerrar) -->
    <div x-show="abierto && !anclado" x-transition.opacity x-cloak @click="cerrar()" class="fixed inset-0 bg-black/30 z-30"></div>

    <!-- Contenido -->
    <div class="flex-1 flex flex-col overflow-hidden transition-[padding] duration-200" :class="anclado ? 'pl-64' : ''">
        <header class="relative z-20 bg-white shadow-sm px-4 py-3 flex items-center justify-between" :class="anclado ? 'pl-6' : 'pl-16'">
            <h1 class="text-gray-700 font-semibold text-sm sm:text-base">@yield('title', 'Panel')</h1>
            <div class="flex items-center gap-4">
                @include('partials.pwa_boton')
                {{-- Multisucursal: en qué sucursal trabaja (el administrador puede cambiarla) --}}
                @if (!empty($sucursalActual))
                    @if ($usuario->esAdmin())
                        <a href="{{ route('sucursales.index') }}" title="Cambiar de sucursal" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>{{ $sucursalActual }}</a>
                    @else
                        <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold">{{ $sucursalActual }}</span>
                    @endif
                @endif
                {{-- Ayuda: soporte del sistema desde cualquier pantalla --}}
                <a href="{{ route('soporte') }}" title="Soporte" aria-label="Soporte"
                   class="p-2 rounded-full hover:bg-gray-100 text-gray-600 {{ request()->routeIs('soporte') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </a>

                {{-- Campanita: comprobantes pendientes de SUNAT (se refresca sola al enviar) --}}
                @include('layouts._campana_sunat')

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs sm:text-sm text-red-600 hover:underline">Cerrar sesión</button>
                </form>
            </div>
        </header>
        <div class="kene kene-franja h-3 shrink-0 text-indigo-300/60" aria-hidden="true"></div>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</div>

    
{{-- Aviso flotante y campanita de SUNAT al día: cada envío a SUNAT (desde cualquier pantalla) muestra el resultado y refresca la campanita --}}
@include('partials.avisos')
<script>
    (function () {
        const URL_CAMPANA = @json(route('sunat.campana'));

        let refrescando = null;
        window.tushpaRefrescarCampana = function () {
            clearTimeout(refrescando);
            refrescando = setTimeout(async () => {
                const actual = document.getElementById('campana-sunat');
                if (!actual) return;
                try {
                    const r = await fetchOriginal(URL_CAMPANA, { headers: { 'Accept': 'text/html' } });
                    if (!r.ok || r.redirected) return;   // sesión vencida: no se pega la página de login
                    const html = (await r.text()).trim();
                    if (html && !html.includes('id="campana-sunat"')) return;
                    const abierta = actual.querySelector('[x-show]')?.style.display !== 'none';
                    if (!html) { actual.remove(); return; }
                    actual.outerHTML = html;
                    if (abierta) document.querySelector('#campana-sunat button')?.click();
                } catch (e) { /* sin conexión: se intenta en el siguiente ciclo */ }
            }, 400);
        };

        // Todo envío a SUNAT pasa por /sunat/enviar/{id}: se intercepta para avisar y refrescar la campanita
        const fetchOriginal = window.fetch.bind(window);
        window.fetch = async function (recurso, opciones) {
            const respuesta = await fetchOriginal(recurso, opciones);
            // Fallas que la pantalla no siempre explica: se avisan grande para que nadie se quede esperando
            if (respuesta.status === 419) tushpaAviso('Tu sesión venció. Recarga la página (F5) e ingresa de nuevo.', 'aviso', 'Sesión vencida');
            else if (respuesta.status >= 500) tushpaAviso('El servidor tuvo un problema y no se completó la acción. Intenta otra vez; si sigue, avisa a soporte.', 'error');
            else if (respuesta.status === 403) tushpaAviso('No tienes permiso para esta acción. Pide acceso al administrador.', 'aviso', 'Sin permiso');
            const url = typeof recurso === 'string' ? recurso : (recurso?.url || '');
            if (/\/sunat\/enviar\/\d+/.test(url)) {
                respuesta.clone().json().then(d => {
                    const ok = !!d.success;
                    tushpaAviso(ok ? `Comprobante ${d.estado} por SUNAT.` : (d.mensaje || 'No se pudo enviar a SUNAT.'), ok, ok ? 'Enviado a SUNAT' : 'SUNAT no lo aceptó');
                    tushpaRefrescarCampana();
                }).catch(() => {});
            }
            return respuesta;
        };

        // Los envíos automáticos (al terminar la venta) se reflejan solos: cada minuto y al volver a la pestaña
        setInterval(tushpaRefrescarCampana, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) tushpaRefrescarCampana(); });
    })();
</script>

@stack('scripts')
@include('partials.aviso_servicio')
</body>
</html>