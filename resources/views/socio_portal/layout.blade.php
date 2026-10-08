@php
    $nombreClub = $negocio->nombre_comercial ?: ($negocio->NomEmpresa ?? 'Club');
    $logoClub = collect([$negocio->logo_suc ?? null, $negocio->LogEmpresa ?? null])->first(fn($l) => $l && is_file(public_path($l)));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#065f46">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Portal del socio') · {{ $nombreClub }}</title>
    @if ($logoClub)<link rel="icon" href="{{ asset($logoClub) }}">@endif
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    <header class="bg-emerald-800 text-white">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center gap-3">
            @if ($logoClub)
                <img src="{{ asset($logoClub) }}" alt="" class="h-10 w-10 rounded-xl object-contain bg-white p-0.5">
            @else
                <span class="h-10 w-10 rounded-xl bg-white text-emerald-800 flex items-center justify-center font-black">{{ mb_substr($nombreClub, 0, 1) }}</span>
            @endif
            <div class="min-w-0">
                <p class="font-extrabold truncate leading-tight">{{ $nombreClub }}</p>
                <p class="text-xs text-emerald-200">@yield('subtitulo', 'Portal del socio')</p>
            </div>
            @yield('acciones')
        </div>
    </header>
    <main class="max-w-3xl mx-auto px-4 py-6">
        @yield('contenido')
    </main>
</body>
</html>
