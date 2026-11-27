<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Pasajes') · {{ $nombre }}</title>
    @if ($logo)<link rel="icon" href="{{ asset($logo) }}">@endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    <header class="bg-indigo-700 text-white shadow">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
            <a href="{{ route('pasajes.web', $negocio->id_empresa_negocio) }}" class="flex items-center gap-2 flex-1 min-w-0">
                @if ($logo)<img src="{{ asset($logo) }}" alt="" class="h-9 w-9 rounded-lg bg-white object-contain">@endif
                <span class="font-black truncate">{{ $nombre }}</span>
            </a>
            @if ($pasajero)
                <a href="{{ route('pasajes.mis', $negocio->id_empresa_negocio) }}" class="text-sm font-bold"><i class="fas fa-ticket"></i> Mis pasajes</a>
                <form method="POST" action="{{ route('pasajes.salir', $negocio->id_empresa_negocio) }}">@csrf
                    <button class="text-sm opacity-80" title="Salir"><i class="fas fa-right-from-bracket"></i></button></form>
            @else
                <a href="{{ route('pasajes.cuenta', [$negocio->id_empresa_negocio, 'volver' => url()->current()]) }}" class="text-sm font-bold"><i class="fas fa-user"></i> Ingresar</a>
            @endif
        </div>
    </header>
    <main class="max-w-4xl mx-auto px-4 py-5 space-y-4">
        @yield('contenido')
    </main>
</body>
</html>
