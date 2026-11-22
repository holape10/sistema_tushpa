@php($rubros = $rubros ?? null)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema {{ config('marca.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset(config('marca.icono')) }}" type="image/png">
    {{-- Íconos sin frenar la pantalla: se cargan en paralelo y se aplican al llegar --}}
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
</head>
<body class="min-h-screen bg-gray-50">

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Panel izquierdo: marca + rubros (no va cuando se elige el rubro: ahí los rubros ocupan toda la pantalla) -->
        @unless ($rubros)
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-indigo-950 via-indigo-800 to-blue-700 flex-col items-center justify-center overflow-hidden px-10 py-12 isolate"
             @unless (config('marca.es_tushpa')) style="background-image: linear-gradient(135deg, {{ config('marca.color') }}, {{ config('marca.color_secundario') }})" @endunless>
            <x-kene-adorno patron="laberinto" color="text-white/[0.07]" :franja="false" />
            <!-- Decoración de fondo -->
            <div class="absolute -top-24 -left-24 w-80 h-80 bg-white/5 rounded-full"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-white/5 rounded-full"></div>
            <div class="absolute top-1/3 right-10 w-40 h-40 bg-blue-400/10 rounded-full blur-xl"></div>

            <div class="relative max-w-md w-full">
                <img src="{{ asset(config('marca.logo')) }}" alt="Sistema {{ config('marca.nombre') }}" width="320" height="128" fetchpriority="high" class="w-72 h-24 object-contain drop-shadow-2xl -ml-3 mb-6">
                <h1 class="text-white text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight">Factura, vende y controla<br>tu negocio en un solo lugar.</h1>
                <p class="text-indigo-100/80 text-base mt-3 mb-7">Un solo sistema para gestionar y facturar cualquier tipo de negocio.</p>

                <ul class="space-y-3 mb-9">
                    @foreach ([
                        ['fa-file-invoice', 'Facturación electrónica SUNAT', 'Boletas, facturas y notas al instante.'],
                        ['fa-cash-register', 'Ventas y caja en tiempo real', 'Desde la PC, la tablet o el celular.'],
                        ['fa-boxes-stacked', 'Inventario y reportes', 'Stock, kardex y lo que más se vende.'],
                    ] as [$icono, $titulo, $detalle])
                        <li class="flex items-start gap-3">
                            <span class="w-9 h-9 shrink-0 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center"><i class="fas {{ $icono }} text-sm text-white"></i></span>
                            <span><span class="block text-sm font-semibold text-white">{{ $titulo }}</span><span class="block text-xs text-indigo-200/80">{{ $detalle }}</span></span>
                        </li>
                    @endforeach
                </ul>

                <!-- Rubros -->
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-200/70 mb-3">Para todo tipo de negocio</p>
                <div class="grid grid-cols-6 gap-2">
                    @foreach ([
                        ['fa-utensils', 'Restaurante'],
                        ['fa-mug-hot', 'Cafetería'],
                        ['fa-martini-glass-citrus', 'Discoteca / Bar'],
                        ['fa-cart-shopping', 'Comercio'],
                        ['fa-briefcase-medical', 'Farmacia'],
                        ['fa-gas-pump', 'Grifo'],
                        ['fa-screwdriver-wrench', 'Ferretería'],
                        ['fa-hard-hat', 'Construcción'],
                        ['fa-car', 'Automotriz'],
                        ['fa-bed', 'Hotelería'],
                        ['fa-shirt', 'Lavandería'],
                        ['fa-spa', 'Belleza'],
                    ] as [$icon, $label])
                        <div class="flex flex-col items-center gap-1 bg-white/5 hover:bg-white/10 transition rounded-lg py-2 px-1 border border-white/10" title="{{ $label }}">
                            <i class="fas {{ $icon }} text-indigo-200 text-sm"></i>
                            <span class="text-[9px] text-indigo-100/90 leading-tight text-center truncate w-full">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="text-indigo-300/70 text-xs mt-9">Desarrollamos ideas, creamos soluciones</p>
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