{{-- Páginas públicas del dominio de una marca (portada, contrato): con su logo, sus colores y su contacto --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') | {{ config('marca.nombre') }}</title>
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
    <meta name="description" content="@yield('descripcion', 'Sistema '.config('marca.nombre').': facturación electrónica, ventas, caja e inventario en la nube.')">
    <meta name="theme-color" content="{{ config('marca.color') }}">
    <link rel="icon" href="{{ asset(config('marca.icono')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --marca: {{ config('marca.color') }}; --marca2: {{ config('marca.color_secundario') }};
            --oscuro: color-mix(in srgb, var(--marca) 30%, #0b1020); --tinta: #1e293b; --gris: #64748b; }
        body { font-family: 'Poppins', system-ui, sans-serif; color: var(--tinta); background: linear-gradient(135deg, var(--marca) 0%, var(--marca2) 100%); min-height: 100vh; }
        .tarjeta { border-radius: 1.25rem; border: 0; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, .45); }
        .paso { width: 28px; height: 28px; border-radius: 50%; background: var(--marca); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
        .form-control, .form-select { border-radius: .75rem; padding: .65rem .9rem; }
        .form-label { font-weight: 600; font-size: .9rem; color: #334155; }
        .logo-marca { height: 56px; max-width: 220px; object-fit: contain; background: #fff; border-radius: 14px; padding: 5px 10px; box-shadow: 0 8px 24px -10px rgba(15, 23, 42, .5); }
        .btn-marca { background: var(--marca); color: #fff; } .btn-marca:hover { background: var(--marca); color: #fff; filter: brightness(1.1); }
        .barra { position: sticky; top: 0; z-index: 30; transition: background .2s, box-shadow .2s; }
        .barra.fija { background: color-mix(in srgb, var(--marca) 88%, #000); box-shadow: 0 10px 30px -18px rgba(0, 0, 0, .6); }
        .barra .enlace { color: rgba(255, 255, 255, .85); text-decoration: none; font-weight: 500; font-size: .92rem; }
        .barra .enlace:hover { color: #fff; }
    </style>
    @stack('cabecera')
</head>
<body>
    <nav class="py-2 barra" id="barra">
        <div class="container d-flex align-items-center justify-content-between gap-3">
            <a href="/"><img src="{{ asset(config('marca.logo')) }}" alt="{{ config('marca.nombre') }}" class="logo-marca"></a>
            <div class="d-flex align-items-center gap-3 gap-lg-4">
                @yield('enlaces')
                @if (config('soporte.whatsapp'))
                    <a href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener" class="btn btn-light rounded-pill fw-semibold px-3">
                        <i class="fab fa-whatsapp text-success me-1"></i><span class="d-none d-sm-inline">Escríbenos</span></a>
                @endif
            </div>
        </div>
    </nav>
    <main class="container pb-5">
        @yield('contenido')
    </main>
    @yield('completo')
    <p class="text-center text-white-50 small py-4 mb-0">
        &copy; {{ date('Y') }} {{ config('marca.nombre') }}
        @if (config('soporte.whatsapp')) · <a class="text-white-50" href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> {{ config('soporte.telefono') }}</a>@endif
        @if (config('soporte.correo')) · <a class="text-white-50" href="mailto:{{ config('soporte.correo') }}"><i class="fas fa-envelope"></i> {{ config('soporte.correo') }}</a>@endif
    </p>
    <script>
        (() => {
            const barra = document.getElementById('barra');
            const revisar = () => barra.classList.toggle('fija', window.scrollY > 20);
            window.addEventListener('scroll', revisar, { passive: true });
            revisar();
        })();
    </script>
    @stack('scripts')
</body>
</html>
