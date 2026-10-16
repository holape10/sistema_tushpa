@php
    $nombreTienda = $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Tienda');
    $logoTienda = collect([$negocio->logo_suc ?? null, $empresa->LogEmpresa ?? null])->first(fn($l) => $l && is_file(public_path($l)));
    $colorMarca = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $negocio->tienda_color) ? $negocio->tienda_color : '#4f46e5';
    $waTienda = preg_replace('/\D/', '', (string) $negocio->tienda_whatsapp);
    $waTienda = $waTienda ? (strlen($waTienda) === 9 ? '51'.$waTienda : $waTienda) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $colorMarca }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Tienda') · {{ $nombreTienda }}</title>
    <meta name="description" content="{{ $negocio->tienda_eslogan ?: 'Tienda virtual de '.$nombreTienda.': compra en línea.' }}">
    <meta property="og:title" content="{{ $nombreTienda }} · Tienda virtual">
    <meta property="og:description" content="{{ $negocio->tienda_eslogan ?: 'Compra en línea y recibe en tu casa o recoge en tienda.' }}">
    @if ($negocio->tienda_banner || $logoTienda)<meta property="og:image" content="{{ asset($negocio->tienda_banner ?: $logoTienda) }}">@endif
    @if ($logoTienda)<link rel="icon" href="{{ asset($logoTienda) }}">@endif
    <style>
        [x-cloak]{display:none!important}
        :root { --marca: {{ $colorMarca }}; }
        .bg-marca { background-color: var(--marca); }
        .text-marca { color: var(--marca); }
        .ring-marca { --tw-ring-color: var(--marca); }
        .border-marca { border-color: var(--marca); }
        .bg-marca-suave { background-color: color-mix(in srgb, var(--marca) 10%, white); }
        .scroll-oculto::-webkit-scrollbar { display: none; } .scroll-oculto { scrollbar-width: none; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
            <a href="{{ route('tienda.index') }}" class="flex items-center gap-2.5 min-w-0">
                @if ($logoTienda)
                    <img src="{{ asset($logoTienda) }}" alt="" class="h-10 w-10 rounded-xl object-contain bg-white ring-1 ring-slate-200">
                @else
                    <span class="h-10 w-10 rounded-xl bg-marca text-white flex items-center justify-center font-black">{{ mb_substr($nombreTienda, 0, 1) }}</span>
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

    <footer class="mt-10 bg-slate-900 text-slate-300">
        <div class="max-w-6xl mx-auto px-4 py-8 grid sm:grid-cols-3 gap-6 text-sm">
            <div>
                <p class="font-extrabold text-white text-base">{{ $nombreTienda }}</p>
                @if ($negocio->tienda_eslogan)<p class="mt-1 text-slate-400">{{ $negocio->tienda_eslogan }}</p>@endif
                <p class="mt-2 text-xs text-slate-500">{{ $empresa->NomEmpresa ?? '' }} · RUC {{ $negocio->IdEmpresa }}</p>
            </div>
            <div>
                <p class="font-bold text-white mb-1">Visítanos</p>
                <p class="text-slate-400">{{ $negocio->direccion }}</p>
                @if ($negocio->direccion)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($negocio->direccion.' '.$negocio->distrito) }}" target="_blank" rel="noopener" class="inline-block mt-1 text-xs underline">Ver en el mapa</a>
                @endif
            </div>
            <div>
                <p class="font-bold text-white mb-1">Atención</p>
                @if ($waTienda)<a href="https://wa.me/{{ $waTienda }}" target="_blank" rel="noopener" class="text-emerald-400 font-semibold">WhatsApp {{ $negocio->tienda_whatsapp }}</a>@endif
                <p class="mt-1 text-xs text-slate-500">Pagos con Yape, Plin, transferencia o efectivo. Comprobante electrónico SUNAT.</p>
            </div>
        </div>
    </footer>

    @if ($waTienda)
        <a href="https://wa.me/{{ $waTienda }}?text={{ rawurlencode('Hola '.$nombreTienda.', tengo una consulta.') }}" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp"
           class="fixed z-30 right-4 bottom-20 sm:bottom-6 w-14 h-14 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow-xl flex items-center justify-center">
            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35zM12.04 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.21-3.58.94.96-3.49-.23-.36a9.43 9.43 0 01-1.45-5.03c0-5.22 4.25-9.46 9.48-9.46 2.53 0 4.9.99 6.69 2.78a9.4 9.4 0 012.77 6.69c0 5.22-4.25 9.46-9.46 9.46z"/></svg>
        </a>
    @endif
    @stack('scripts')
</body>
</html>
