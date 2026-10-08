<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
</head>
<body class="min-h-screen bg-gray-50">

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Panel izquierdo: marca + rubros -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-indigo-950 via-indigo-800 to-blue-700 flex-col items-center justify-center overflow-hidden px-10 py-12">
            <!-- Decoración de fondo -->
            <div class="absolute -top-24 -left-24 w-80 h-80 bg-white/5 rounded-full"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-white/5 rounded-full"></div>
            <div class="absolute top-1/3 right-10 w-40 h-40 bg-blue-400/10 rounded-full blur-xl"></div>

            <div class="relative text-center max-w-md">
                <img src="{{ asset('imagenes/logo.png') }}" alt="Sistema Tushpa" class="w-80 h-28 mx-auto drop-shadow-2xl mb-4">
                <!--<h1 class="text-white text-3xl font-bold tracking-wide">TUSHPA</h1>
                <p class="text-indigo-200 text-sm mt-1 mb-2 tracking-wider uppercase">Facturación Electrónica</p>-->
                <p class="text-indigo-100/80 text-sm mb-8">Un solo sistema para gestionar y facturar cualquier tipo de negocio</p>

                <!-- Rubros -->
                <div class="grid grid-cols-4 gap-4">
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
                        <div class="flex flex-col items-center gap-1.5 bg-white/5 hover:bg-white/10 transition rounded-xl py-3 px-1 border border-white/10">
                            <i class="fas {{ $icon }} text-indigo-200 text-lg"></i>
                            <span class="text-[10px] text-indigo-100 leading-tight">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="text-indigo-300/70 text-xs mt-8">Desarrollamos ideas, creamos soluciones</p>
            </div>
        </div>

        <!-- Panel derecho: formulario -->
        <div class="flex-1 flex items-center justify-center px-6 py-12 bg-gray-50">
            <div class="w-full max-w-sm">

                <!-- Logo visible solo en móvil -->
                <div class="flex lg:hidden flex-col items-center mb-8">
                    <img src="{{ asset('imagenes/512.png') }}" alt="Logo" class="w-16 h-16 mb-2">
                    <span class="font-bold text-gray-700 tracking-wide">TUSHPA</span>
                </div>

                <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/60 border border-gray-100 p-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-1">Bienvenido de nuevo</h2>
                    <p class="text-gray-400 text-sm mb-6">Ingresa tus credenciales para continuar</p>

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

                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Usuario</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400">
                                    <i class="fas fa-user text-sm"></i>
                                </span>
                                <input type="text" name="email" value="{{ old('email') }}" placeholder="RUC o usuario"
                                    autofocus required
                                    class="w-full pl-10 pr-3 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Contraseña</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400">
                                    <i class="fas fa-lock text-sm"></i>
                                </span>
                                <input type="password" name="password" placeholder="••••••••" required
                                    class="w-full pl-10 pr-3 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm transition">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-500 select-none">
                            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Recordarme
                        </label>

                        <button type="submit"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-800 to-blue-600 text-white text-sm font-semibold tracking-wide hover:opacity-90 active:scale-[0.98] transition shadow-lg shadow-indigo-900/20">
                            INGRESAR
                        </button>
                    </form>

                    <a href="{{ route('login.movil') }}" class="block text-center text-sm text-indigo-600 hover:underline mt-5">
                        <i class="fas fa-tablet-screen-button"></i> Login para tablet / celular (mozos)
                    </a>
                    <div class="text-center mt-4">
                        @include('partials.pwa_boton', ['clase' => 'inline-flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-indigo-200 text-indigo-700 text-sm font-semibold hover:bg-indigo-50'])
                    </div>
                </div>

                <p class="text-center text-gray-400 text-xs mt-6">© {{ date('Y') }} Sistema Tushpa · Todos los derechos reservados</p>
            </div>
        </div>
    </div>

</body>
</html>