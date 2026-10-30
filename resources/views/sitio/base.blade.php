{{-- Páginas simples de la web pública (registro, ingresar, listo): mismo estilo que la portada --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') | TUSHPA</title>
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Íconos: se cargan sin frenar el primer dibujo de la página --}}
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('web/css/style.css') }}?v={{ filemtime(public_path('web/css/style.css')) }}">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .tarjeta { border-radius: 1.25rem; border: 0; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, .45); }
        .paso { width: 28px; height: 28px; border-radius: 50%; background: #2563eb; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
        .form-control, .form-select { border-radius: .75rem; padding: .65rem .9rem; }
        .form-label { font-weight: 600; font-size: .9rem; color: #334155; }
    </style>
    @stack('cabecera')
</head>
<body>
    <nav class="py-3">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ route('sitio.inicio') }}"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="46"></a>
            <div class="d-flex gap-2">
                <a href="{{ route('sitio.inicio') }}#planes" class="btn btn-sm btn-outline-light rounded-pill px-3">Planes</a>
                <a href="{{ route('sitio.ingresar') }}" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold">Ingresar</a>
            </div>
        </div>
    </nav>
    <main class="container pb-5">
        @yield('contenido')
    </main>
    <p class="text-center text-white-50 small pb-4 mb-0">
        &copy; {{ date('Y') }} TUSHPA · <a class="text-white-50" href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> {{ config('soporte.telefono') }}</a> · <a class="text-white-50" href="mailto:{{ config('soporte.correo') }}"><i class="fas fa-envelope"></i> {{ config('soporte.correo') }}</a>
    </p>
    @stack('scripts')
    @include('sitio.partials.extras')
</body>
</html>
