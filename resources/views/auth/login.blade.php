@php($rubros = $rubros ?? null)
{{-- Varias empresas en la misma PC: el login lleva el color y el nombre de esta empresa (Mantenimiento › Apariencia) --}}
@php($nombreEmpresa = $rubros ? null : \App\Support\Apariencia::nombre())
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $nombreEmpresa ? $nombreEmpresa.' · ' : '' }}Iniciar Sesión - Sistema {{ config('marca.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset(config('marca.icono')) }}" type="image/png">
    {{-- Íconos sin frenar la pantalla: se cargan en paralelo y se aplican al llegar --}}
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {!! \App\Support\Apariencia::estilo(\App\Support\Apariencia::deLaBase()) !!}
    @include('partials.pwa')
</head>
<body class="min-h-screen bg-gray-50">

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Panel izquierdo: marca + rubros (no va cuando se elige el rubro: ahí los rubros ocupan toda la pantalla) -->
        @unless ($rubros)
        <style>
            @keyframes login-flota { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
            @keyframes login-orbe { 0%, 100% { transform: translate(0, 0) scale(1) } 50% { transform: translate(30px, -20px) scale(1.08) } }
            @keyframes login-entra { from { opacity: 0; transform: translateY(14px) } to { opacity: 1; transform: none } }
            .login-flota { animation: login-flota 6s ease-in-out infinite; }
            .login-flota-2 { animation: login-flota 7s ease-in-out infinite 1.2s; }
            .login-orbe { animation: login-orbe 14s ease-in-out infinite; }
            .login-entra { animation: login-entra .8s cubic-bezier(.2,.8,.2,1) both; }
            .login-rubro-sale { opacity: 0; transform: translateY(-8px); }
            @media (prefers-reduced-motion: reduce) { .login-flota, .login-flota-2, .login-orbe, .login-entra { animation: none; } }
        </style>
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-[#0b0a2e] via-indigo-900 to-blue-800 flex-col items-center justify-center overflow-hidden px-10 py-12 isolate"
             @unless (config('marca.es_tushpa')) style="background-image: linear-gradient(135deg, {{ config('marca.color') }}, {{ config('marca.color_secundario') }})" @endunless>
            <x-kene-adorno patron="laberinto" color="text-white/[0.05]" :franja="false" />
            <!-- Luces de fondo que se mueven despacio -->
            <div class="login-orbe absolute -top-32 -left-24 w-[28rem] h-[28rem] rounded-full bg-fuchsia-500/25 blur-3xl -z-10"></div>
            <div class="login-orbe absolute -bottom-40 -right-24 w-[32rem] h-[32rem] rounded-full bg-cyan-400/20 blur-3xl -z-10" style="animation-delay: -7s"></div>
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(5,5,30,.55))]"></div>

            <div class="relative w-full max-w-lg flex flex-col items-center text-center">
                <img src="{{ asset(config('marca.logo')) }}" alt="Sistema {{ config('marca.nombre') }}" width="320" height="128" fetchpriority="high"
                     class="login-entra w-64 h-20 object-contain drop-shadow-[0_10px_30px_rgba(99,102,241,.55)] mb-5">

                <span class="login-entra inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 backdrop-blur px-3.5 py-1 text-xs font-semibold text-indigo-100" style="animation-delay:.08s">
                    <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span></span>
                    Facturación electrónica SUNAT
                </span>

                <h1 class="login-entra mt-5 text-white text-4xl xl:text-5xl font-extrabold leading-[1.08] tracking-tight" style="animation-delay:.15s">
                    Tu negocio,<br><span class="bg-gradient-to-r from-cyan-300 via-indigo-200 to-fuchsia-300 bg-clip-text text-transparent">bajo control.</span>
                </h1>
                <p class="login-entra mt-4 text-indigo-100/80 text-base max-w-md" style="animation-delay:.22s">Vende, factura y controla tu inventario desde la PC, la tablet o el celular.</p>

                <!-- Lo que hace el sistema (sin montos ni datos: que nadie lo confunda con sus ventas) -->
                <div class="login-entra mt-8 grid grid-cols-2 gap-2.5 w-full max-w-md text-left" style="animation-delay:.3s">
                    @foreach ([
                        ['fa-file-invoice', 'Facturación SUNAT', 'Boletas, facturas y notas al instante', 'from-emerald-400/25 text-emerald-300', 'login-flota'],
                        ['fa-cash-register', 'Ventas y caja', 'Cobra rápido con efectivo, Yape o tarjeta', 'from-cyan-400/25 text-cyan-300', 'login-flota-2'],
                        ['fa-boxes-stacked', 'Inventario', 'Stock y kardex siempre al día', 'from-indigo-400/25 text-indigo-200', 'login-flota-2'],
                        ['fa-chart-line', 'Reportes', 'Sabe qué vendes y cuánto ganas', 'from-fuchsia-400/25 text-fuchsia-300', 'login-flota'],
                    ] as $i => [$icono, $titulo, $detalle, $color, $flota])
                        <div class="{{ $flota }} flex items-center gap-3 rounded-xl border border-white/15 bg-white/[0.07] backdrop-blur-xl px-3 py-2.5 shadow-[0_15px_40px_-20px_rgba(0,0,0,.6)] hover:bg-white/[0.12] hover:border-white/25 transition"
                             style="animation-delay: -{{ $i * 1.5 }}s">
                            <span class="w-9 h-9 shrink-0 rounded-lg bg-gradient-to-br {{ $color }} to-transparent border border-white/10 flex items-center justify-center">
                                <i class="fas {{ $icono }} text-sm"></i></span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold text-white leading-tight">{{ $titulo }}</span>
                                <span class="block text-[11px] text-indigo-200/75 leading-snug">{{ $detalle }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>

                <!-- Rubros: el nombre va cambiando solo -->
                <div class="login-entra mt-14" style="animation-delay:.4s"
                     x-data="{ rubros: ['Restaurantes', 'Cafeterías', 'Discotecas y bares', 'Comercios', 'Farmacias', 'Grifos', 'Ferreterías', 'Construcción', 'Talleres', 'Hoteles', 'Lavanderías', 'Salones de belleza'], i: 0, sale: false }"
                     x-init="setInterval(() => { sale = true; setTimeout(() => { i = (i + 1) % rubros.length; sale = false }, 300) }, 2200)">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-indigo-200/60">Para todo tipo de negocio</p>
                    <p class="mt-2 h-8 text-2xl font-bold text-white transition-all duration-300" :class="sale && 'login-rubro-sale'" x-text="rubros[i]">Restaurantes</p>
                </div>

                <p class="text-indigo-300/60 text-xs mt-8">Desarrollamos ideas, creamos soluciones</p>
            </div>
        </div>
        @endunless

        <!-- Panel derecho: formulario -->
        <div class="flex-1 flex items-center justify-center px-4 sm:px-6 py-10 {{ $rubros ? 'bg-gradient-to-b from-indigo-50 via-white to-gray-50' : 'bg-gray-50' }}">
            <div class="w-full {{ $rubros ? 'max-w-5xl' : 'max-w-sm' }}" x-data="{ rubro: @js(old('rubro', '')), rubros: @js($rubros ? $rubros->map(fn ($r) => ['nombre' => $r['nombre'], 'icono' => $r['icono']]) : []) }">

                @if ($rubros)
                    <!-- Logo arriba (pantalla de elegir el tipo de negocio) -->
                    <div class="flex flex-col items-center mb-6">
                        <img src="{{ asset(config('marca.logo')) }}" alt="{{ config('marca.nombre') }}" width="320" height="128" fetchpriority="high" class="w-64 sm:w-80 h-24 sm:h-28 object-contain drop-shadow">
                        <p class="text-gray-500 text-sm -mt-1">Un solo sistema para gestionar y facturar cualquier tipo de negocio</p>
                    </div>
                @else
                    <!-- Logo visible solo en móvil -->
                    <div class="flex lg:hidden flex-col items-center mb-8">
                        <img src="{{ asset(config('marca.icono')) }}" alt="Logo" class="w-16 h-16 mb-2">
                        <span class="font-bold text-gray-700 tracking-wide">{{ config('marca.nombre') }}</span>
                    </div>
                @endif

                @if ($rubros)
                    {{-- Paso 1 (demo): el tipo de negocio decide qué menú se ve al entrar --}}
                    <div x-show="! rubro" @if (old('rubro')) style="display:none" @endif>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-800 text-center">¿Qué tipo de negocio tienes?</h2>
                        <p class="text-gray-500 text-center mb-6">Elige uno y te mostramos solo lo que necesitas</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                            @foreach ($rubros as $clave => $r)
                                <button type="button" @click="rubro = @js($clave); $nextTick(() => $root.querySelector('[name=email]')?.focus())"
                                        class="group flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-white bg-white shadow-sm px-3 py-5 sm:py-6 text-center
                                               hover:border-indigo-400 hover:shadow-lg hover:-translate-y-0.5 active:scale-[0.97] transition">
                                    <span class="w-14 h-14 rounded-2xl bg-indigo-50 group-hover:bg-indigo-600 flex items-center justify-center transition">
                                        <i class="fas {{ $r['icono'] }} text-2xl text-indigo-600 group-hover:text-white transition"></i></span>
                                    <span class="text-base font-bold text-gray-800 leading-tight">{{ $r['nombre'] }}</span>
                                    <span class="text-xs text-gray-400 leading-snug">{{ $r['ejemplos'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <p class="text-center text-gray-400 text-xs mt-6">Desarrollamos ideas, creamos soluciones</p>
                    </div>
                @endif

                <div class="bg-white rounded-3xl shadow-2xl shadow-indigo-900/10 border border-gray-100 p-8 sm:p-9 {{ $rubros ? 'max-w-sm mx-auto' : '' }}"
                     @if ($rubros) x-show="rubro" @if (! old('rubro')) style="display:none" @endif @endif>
                    @if ($rubros)
                        <button type="button" @click="rubro = ''" class="mb-4 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                            <i class="fas" :class="rubros[rubro]?.icono"></i> <span x-text="rubros[rubro]?.nombre"></span>
                            <span class="text-xs font-normal text-indigo-400">· cambiar</span>
                        </button>
                    @endif
                    @if ($nombreEmpresa)
                        {{-- Para no confundirse de empresa cuando se usan varias en la misma PC --}}
                        <div class="mb-5 flex items-center gap-3 rounded-2xl bg-indigo-600 px-4 py-3 text-white">
                            <i class="fas fa-store text-xl"></i>
                            <span class="min-w-0"><span class="block text-[11px] uppercase tracking-wide text-indigo-100">Estás entrando a</span>
                                <span class="block truncate text-lg font-black">{{ $nombreEmpresa }}</span></span>
                        </div>
                    @endif
                    <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight">Iniciar sesión</h2>
                    <p class="text-gray-500 text-sm mt-1 mb-7">Bienvenido de nuevo. Ingresa con tu usuario y contraseña.</p>

                    @if (session('success'))
                        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-2 text-sm">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ ver: false, mayus: false, enviando: false }" @submit="enviando = true">
                        @csrf
                        @if ($rubros)
                            <input type="hidden" name="rubro" :value="rubro">
                        @endif
                        {{-- Nombre de la PC que guardó el agente de impresión (Seguridad › Equipos lo muestra) --}}
                        <input type="hidden" name="equipo_pc" x-init="try { $el.value = localStorage.getItem('tushpa_pc') || '' } catch (e) {}">

                        <div>
                            <label for="login_usuario" class="block text-sm font-semibold text-gray-700 mb-1.5">Usuario</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400 pointer-events-none">
                                    <i class="fas fa-user text-sm"></i>
                                </span>
                                <input id="login_usuario" type="text" name="email" value="{{ old('email') }}" placeholder="RUC o usuario"
                                    autofocus required autocomplete="username" autocapitalize="none" spellcheck="false"
                                    class="w-full h-12 pl-10 pr-3 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 text-sm transition">
                            </div>
                        </div>

                        <div>
                            <label for="login_clave" class="block text-sm font-semibold text-gray-700 mb-1.5">Contraseña</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400 pointer-events-none">
                                    <i class="fas fa-lock text-sm"></i>
                                </span>
                                <input id="login_clave" :type="ver ? 'text' : 'password'" type="password" name="password" placeholder="Tu contraseña" required
                                    autocomplete="current-password"
                                    @keyup="mayus = $event.getModifierState && $event.getModifierState('CapsLock')" @blur="mayus = false"
                                    class="w-full h-12 pl-10 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 text-sm transition">
                                {{-- Ojito: ver u ocultar la contraseña --}}
                                <button type="button" @click="ver = ! ver" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'" :title="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                        class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-gray-400 hover:text-indigo-600 focus:outline-none focus-visible:text-indigo-600">
                                    <i class="fas" :class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                            <p x-show="mayus" x-cloak class="mt-1.5 text-xs font-medium text-amber-600"><i class="fas fa-triangle-exclamation"></i> Las mayúsculas están activadas</p>
                        </div>

                        {{-- Empresa con varias sucursales: se elige dónde trabajar (cada usuario entra a la suya) --}}
                        @if (($sucursales ?? collect())->isNotEmpty())
                            <div>
                                <label for="sucursal" class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Sucursal</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-emerald-600 pointer-events-none">
                                        <i class="fas fa-house text-sm"></i>
                                    </span>
                                    <select id="sucursal" name="sucursal" required
                                        class="w-full pl-10 pr-8 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm font-semibold text-gray-700 transition">
                                        @foreach ($sucursales as $id => $nombre)
                                            <option value="{{ $id }}" @selected($id === $sucursalElegida)>{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm text-gray-600 select-none cursor-pointer w-fit">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Mantener la sesión iniciada
                        </label>

                        <button type="submit" :disabled="enviando"
                            class="w-full h-12 rounded-xl bg-gradient-to-r from-indigo-800 to-blue-600 text-white text-sm font-bold tracking-wide hover:opacity-95 hover:shadow-xl active:scale-[0.98] transition shadow-lg shadow-indigo-900/25 disabled:opacity-70 disabled:cursor-wait inline-flex items-center justify-center gap-2"
                            @unless (config('marca.es_tushpa')) style="background-image: linear-gradient(90deg, {{ config('marca.color') }}, {{ config('marca.color_secundario') }})" @endunless>
                            <i class="fas fa-spinner fa-spin" x-show="enviando" x-cloak></i>
                            <span x-text="enviando ? 'Ingresando…' : 'Ingresar'">Ingresar</span>
                            <i class="fas fa-arrow-right text-xs" x-show="! enviando"></i>
                        </button>
                        <p class="flex items-center justify-center gap-1.5 text-xs text-gray-400"><i class="fas fa-shield-halved"></i> Conexión segura y cifrada</p>
                    </form>

                    {{-- Solo los rubros con mozos o cobradores (en la demo, según el rubro elegido) --}}
                    @if ($rubros || \App\Support\Rubros::usaLoginMovil())
                        <div class="flex items-center gap-3 my-5 text-xs text-gray-400" @if ($rubros) x-show="@js(\App\Support\Rubros::LOGIN_MOVIL).includes(rubro)" x-cloak @endif><span class="h-px flex-1 bg-gray-200"></span>o<span class="h-px flex-1 bg-gray-200"></span></div>
                        <a href="{{ route('login.movil') }}" class="flex items-center justify-center gap-2 w-full h-11 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-indigo-300 transition"
                           @if ($rubros) x-show="@js(\App\Support\Rubros::LOGIN_MOVIL).includes(rubro)" x-cloak @endif>
                            <i class="fas fa-tablet-screen-button"></i> Login para tablet / celular ({{ \App\Support\Rubros::actual() === 'PRESTAMOS' ? 'cobradores' : 'mozos' }})
                        </a>
                    @endif
                    <div class="text-center mt-4">
                        @include('partials.pwa_boton', ['clase' => 'inline-flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-indigo-200 text-indigo-700 text-sm font-semibold hover:bg-indigo-50'])
                    </div>
                </div>

                <p class="text-center text-gray-400 text-xs mt-6">© {{ date('Y') }} Sistema {{ config('marca.nombre') }} · Todos los derechos reservados</p>
            </div>
        </div>
    </div>

</body>
</html>