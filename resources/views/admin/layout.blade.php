<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Panel') · TUSHPA</title>
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    @auth('superadmin')
        <header class="bg-slate-900 text-white">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3">
                <img src="{{ asset('imagenes/512.png') }}" alt="" class="w-8 h-8 rounded-lg bg-white p-1">
                <a href="{{ route('admin.clientes.index') }}" class="font-bold">TUSHPA <span class="font-normal text-slate-400">· Panel de clientes</span></a>
                <span class="ml-auto text-sm text-slate-400 hidden sm:inline">{{ auth('superadmin')->user()->nombre }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="text-sm px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700">Salir</button>
                </form>
            </div>
        </header>
    @endauth

    <main class="max-w-6xl mx-auto px-4 py-6">
        @if (session('ok'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
