@php
    $nombreTienda = $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Tienda');
    $logoTienda = collect([$negocio->logo_suc ?? null, $empresa->LogEmpresa ?? null])->first(fn($l) => $l && is_file(public_path($l)));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Tienda') · {{ $nombreTienda }}</title>
    <meta name="description" content="Tienda virtual de {{ $nombreTienda }}: compra en línea.">
    @if ($logoTienda)<link rel="icon" href="{{ asset($logoTienda) }}">@endif
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
            <a href="{{ route('tienda.index') }}" class="flex items-center gap-2.5 min-w-0">
                @if ($logoTienda)
                    <img src="{{ asset($logoTienda) }}" alt="" class="h-10 w-10 rounded-xl object-contain bg-white">
                @else
                    <span class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black">{{ mb_substr($nombreTienda, 0, 1) }}</span>
                @endif
                <span class="font-extrabold text-slate-800 truncate">{{ $nombreTienda }}</span>
            </a>
            <div class="ml-auto flex items-center gap-2">
                @yield('acciones')
                @if ($cliente)
                    <a href="{{ route('tienda.cuenta') }}" class="h-10 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold flex items-center gap-1.5" title="Mi cuenta">
                        <span>👤</span><span class="hidden sm:inline max-w-[140px] truncate">{{ \Illuminate\Support\Str::of($cliente->clinom)->explode(' ')->first() }}</span>
                    </a>
                @else
                    <a href="{{ route('tienda.login') }}" class="h-10 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold flex items-center">Ingresar</a>
                @endif
            </div>
        </div>
    </header>

    @if (session('aviso'))
        <div class="max-w-6xl mx-auto w-full px-4 mt-4">
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('aviso') }}</div>
        </div>
    @endif

    <main class="flex-1">@yield('contenido')</main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 py-6 text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-2 justify-between">
            <span>{{ $empresa->NomEmpresa ?? '' }} · RUC {{ $negocio->IdEmpresa }}<br>{{ $negocio->direccion }}</span>
            <span>@if ($negocio->tienda_whatsapp)WhatsApp: {{ $negocio->tienda_whatsapp }}@endif</span>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
