{{-- Páginas públicas del dominio de una marca (ingresar, contrato): con su logo, sus colores y su contacto --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') | {{ config('marca.nombre') }}</title>
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
    <link rel="icon" href="{{ asset(config('marca.icono')) }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, {{ config('marca.color') }} 0%, {{ config('marca.color_secundario') }} 100%); min-height: 100vh; }
        .tarjeta { border-radius: 1.25rem; border: 0; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, .45); }
        .paso { width: 28px; height: 28px; border-radius: 50%; background: {{ config('marca.color') }}; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
        .form-control, .form-select { border-radius: .75rem; padding: .65rem .9rem; }
        .form-label { font-weight: 600; font-size: .9rem; color: #334155; }
        .logo-marca { max-height: 64px; max-width: 220px; background: #fff; border-radius: 14px; padding: 6px 10px; }
    </style>
    @stack('cabecera')
</head>
<body>
    <nav class="py-3">
        <div class="container d-flex align-items-center">
            <a href="/"><img src="{{ asset(config('marca.logo')) }}" alt="{{ config('marca.nombre') }}" class="logo-marca"></a>
        </div>
    </nav>
    <main class="container pb-5">
        @yield('contenido')
    </main>
    <p class="text-center text-white-50 small pb-4 mb-0">
        &copy; {{ date('Y') }} {{ config('marca.nombre') }}
        @if (config('soporte.whatsapp')) · <a class="text-white-50" href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> {{ config('soporte.telefono') }}</a>@endif
        @if (config('soporte.correo')) · <a class="text-white-50" href="mailto:{{ config('soporte.correo') }}"><i class="fas fa-envelope"></i> {{ config('soporte.correo') }}</a>@endif
    </p>
    @stack('scripts')
</body>
</html>
