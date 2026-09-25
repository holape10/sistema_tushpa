<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema Tushpa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-500 via-purple-500 to-blue-600 px-4">

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 pt-8 pb-10 flex justify-center">
            <div class="bg-white rounded-2xl p-3 shadow-lg">
                <img src="{{ asset('logo.png') }}" alt="Logo" class="w-16 h-16 object-contain"
                     onerror="this.style.display='none'">
            </div>
        </div>

        <div class="p-8 -mt-6 bg-white rounded-t-3xl">
            <h1 class="text-center text-lg font-bold text-gray-700 tracking-wide mb-6">INICIAR SESIÓN</h1>

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

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </span>
                    <input type="text" name="email" value="{{ old('email') }}" placeholder="Usuario"
                        autofocus required
                        class="w-full pl-10 pr-3 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.66 0 3-1.34 3-3V6a3 3 0 00-6 0v2c0 1.66 1.34 3 3 3zm6 2H6a1 1 0 00-1 1v6a1 1 0 001 1h12a1 1 0 001-1v-6a1 1 0 00-1-1z" />
                        </svg>
                    </span>
                    <input type="password" name="password" placeholder="Contraseña" required
                        class="w-full pl-10 pr-3 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>

                <label class="flex items-center gap-2 text-xs text-gray-500">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600">
                    Recordarme
                </label>

                <button type="submit"
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold tracking-wide hover:opacity-90 active:scale-[0.98] transition shadow-md">
                    INGRESAR
                </button>
            </form>
        </div>
    </div>

</body>
</html>