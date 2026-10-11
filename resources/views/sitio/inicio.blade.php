<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TUSHPA - Sistema de Facturación Electrónica y Desarrollo de Software. Soluciones tecnológicas para tu negocio.">
    <meta name="keywords" content="facturación electrónica, software, desarrollo, Perú, SUNAT, sistema de gestión">
    <meta name="author" content="TUSHPA">
    
    <title>TUSHPA - Sistema de Facturación Electrónica | Desarrollo de Software</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('web/img/favicon.ico') }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('web/css/style.css') }}?v={{ filemtime(public_path('web/css/style.css')) }}">
    <style>
        /* El menú tiene más opciones (Planes, Ingresar, Prueba gratis): que entre en una línea */
        .navbar-nav .nav-link { white-space: nowrap; }
        @media (min-width: 992px) and (max-width: 1399px) { .navbar-nav .nav-link { margin-left: .35rem !important; padding-left: .45rem !important; padding-right: .45rem !important; font-size: .95rem; } }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <!--<img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="50" id="navLogo">-->
                <img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="50" id="navLogo">
                <!--<span class="brand-text">TUSHPA</span>-->
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Inicio</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('sitio.tutorial') }}" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-graduation-cap me-1"></i>Tutorial
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="#about">Nosotros</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Servicios</a></li>
                    <li class="nav-item"><a class="nav-link" href="#products">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="#clients">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#planes">Planes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contacto</a></li>
                    <li class="nav-item ms-lg-2"><a class="nav-link" href="{{ route('sitio.ingresar') }}"><i class="fas fa-sign-in-alt me-1"></i>Ingresar</a></li>
                    <li class="nav-item ms-lg-2 d-flex align-items-center">
                        <a class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold" href="{{ route('sitio.registro') }}">Prueba gratis</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="hero-title">
                        Soluciones Tecnológicas <span class="text-gradient">Inteligentes</span>
                    </h1>
                    <p class="hero-subtitle">
                        Sistema de Facturación Electrónica y Desarrollo de Software a medida para impulsar tu negocio al siguiente nivel
                    </p>
                    <div class="hero-buttons">
                        <a href="{{ route('sitio.registro') }}" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-rocket me-2"></i>Prueba gratis {{ config('sitio.dias_prueba') }} días
                        </a>
                        <a href="#services" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-play-circle me-2"></i>Conocer Más
                        </a>
                    </div>
                    <div class="hero-stats mt-5" hidden="hidden">
                        <div class="row">
                            <div class="col-4">
                                <h3 class="stat-number">+100</h3>
                                <p class="stat-label">Clientes Felices</p>
                            </div>
                            <div class="col-4">
                                <h3 class="stat-number">99.9%</h3>
                                <p class="stat-label">Uptime</p>
                            </div>
                            <div class="col-4">
                                <h3 class="stat-number">24/7</h3>
                                <p class="stat-label">Soporte</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block" data-aos="fade-left">
                    <img src="{{ asset('web/img/logo_tushpa.webp') }}" alt="TUSHPA Platform" class="img-fluid hero-image">
                </div>
            </div>
        </div>
        <div class="scroll-indicator">
            <a href="#about"><i class="fas fa-chevron-down"></i></a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <img src="{{ asset('web/img/logo_tushpa.webp') }}" alt="About TUSHPA" class="img-fluid rounded-3 shadow-lg">
                </div>
                <div class="col-lg-6 mt-4 mt-lg-0" data-aos="fade-left">
                    <h6 class="text-primary text-uppercase mb-2">Sobre Nosotros</h6>
                    <h2 class="display-5 fw-bold mb-4">Transformamos tu Negocio con Tecnología de Punta</h2>
                    <p class="lead text-muted mb-4">
                        En TUSHPA somos expertos en desarrollo de software y facturación electrónica. 
                        Ayudamos a empresas de todos los tamaños a digitalizar sus procesos y cumplir 
                        con la normativa SUNAT.
                    </p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-primary fa-2x me-3"></i>
                                <div>
                                    <h5 class="fw-bold">Facturación Electrónica</h5>
                                    <p class="text-muted mb-0">Cumple con SUNAT fácilmente</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-primary fa-2x me-3"></i>
                                <div>
                                    <h5 class="fw-bold">Software a Medida</h5>
                                    <p class="text-muted mb-0">Adaptado a tus necesidades</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-primary fa-2x me-3"></i>
                                <div>
                                    <h5 class="fw-bold">Soporte Técnico</h5>
                                    <p class="text-muted mb-0">Disponibles 24/7 para ti</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-primary fa-2x me-3"></i>
                                <div>
                                    <h5 class="fw-bold">Actualizaciones</h5>
                                    <p class="text-muted mb-0">Siempre a la vanguardia</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-primary text-uppercase mb-2">Nuestros Servicios</h6>
                <h2 class="display-5 fw-bold">Soluciones Completas para tu Empresa</h2>
                <p class="lead text-muted mt-3">Ofrecemos un portafolio completo de servicios tecnológicos</p>
            </div>
            
            <div class="row g-4">
                <!-- Service 1 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Facturación Electrónica</h4>
                        <p class="text-muted mb-4">
                            Emite boletas, facturas, notas de crédito y débito electrónicas. 
                            Totalmente compatible con SUNAT.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Boletas y Facturas</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Notas de Crédito/Débito</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Resumen Diario</li>
                            <li><i class="fas fa-check text-success me-2"></i>Comunicación de Baja</li>
                        </ul>
                    </div>
                </div>

                <!-- Service 2 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-code fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Desarrollo de Software</h4>
                        <p class="text-muted mb-4">
                            Creamos aplicaciones web y móviles a medida para optimizar 
                            los procesos de tu negocio.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Aplicaciones Web</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Apps Móviles</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Sistemas de Gestión</li>
                            <li><i class="fas fa-check text-success me-2"></i>APIs e Integraciones</li>
                        </ul>
                    </div>
                </div>

                <!-- Service 3 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-cloud fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Soluciones en la Nube</h4>
                        <p class="text-muted mb-4">
                            Migra tus sistemas a la nube y accede desde cualquier lugar 
                            del mundo de forma segura.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Hosting Empresarial</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Cloud Computing</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Backup Automático</li>
                            <li><i class="fas fa-check text-success me-2"></i>Alta Disponibilidad</li>
                        </ul>
                    </div>
                </div>

                <!-- Service 4 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-shield-alt fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Ciberseguridad</h4>
                        <p class="text-muted mb-4">
                            Protege tu información y la de tus clientes con nuestras 
                            soluciones de seguridad informática.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Auditorías de Seguridad</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Firewall y Antivirus</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Encriptación de Datos</li>
                            <li><i class="fas fa-check text-success me-2"></i>Monitoreo 24/7</li>
                        </ul>
                    </div>
                </div>

                <!-- Service 5 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-headset fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Soporte Técnico</h4>
                        <p class="text-muted mb-4">
                            Equipo de expertos disponibles las 24 horas para resolver 
                            cualquier incidencia técnica.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Soporte 24/7</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Atención Remota</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mantenimiento</li>
                            <li><i class="fas fa-check text-success me-2"></i>Capacitación</li>
                        </ul>
                    </div>
                </div>

                <!-- Service 6 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="service-card h-100 p-4 bg-white rounded-3 shadow-sm">
                        <div class="service-icon mb-4">
                            <i class="fas fa-chart-line fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Consultoría IT</h4>
                        <p class="text-muted mb-4">
                            Asesoramiento especializado para optimizar tu infraestructura 
                            tecnológica y maximizar ROI.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Auditoría de Sistemas</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Transformación Digital</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Optimización de Procesos</li>
                            <li><i class="fas fa-check text-success me-2"></i>Business Intelligence</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="products-section py-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-primary text-uppercase mb-2">Nuestros Productos</h6>
                <h2 class="display-5 fw-bold">Sistemas que Impulsan tu Negocio</h2>
                <p class="lead text-muted mt-3">Software especializado para diferentes rubros</p>
            </div>

            <div class="row g-4">
                <!-- Product 1 -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="product-card h-100 bg-white rounded-3 shadow-sm overflow-hidden">
                        <div class="product-header bg-primary text-white p-4">
                            <i class="fas fa-utensils fa-3x mb-3"></i>
                            <h4 class="fw-bold mb-0">TUSHPA Restaurant</h4>
                        </div>
                        <div class="product-body p-4">
                            <p class="text-muted mb-4">
                                Sistema de gestión completo para restaurantes, bares y cafeterías. 
                                Control de mesas, comandas y facturación.
                            </p>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Control de Mesas</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Comandas Digitales</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Inventario en Tiempo Real</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Facturación Electrónica</li>
                                <li><i class="fas fa-check text-success me-2"></i>Reportes y Estadísticas</li>
                            </ul>
                            <a href="{{ route('sitio.registro') }}" class="btn btn-outline-primary w-100">Probar gratis</a>
                        </div>
                    </div>
                </div>

                <!-- Product 2 -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="product-card h-100 bg-white rounded-3 shadow-sm overflow-hidden">
                        <div class="product-header bg-success text-white p-4">
                            <i class="fas fa-clinic-medical fa-3x mb-3"></i>
                            <h4 class="fw-bold mb-0">TUSHPA Salud</h4>
                        </div>
                        <div class="product-body p-4">
                            <p class="text-muted mb-4">
                                Software de gestión para clínicas, consultorios y centros de salud. 
                                Historias clínicas electrónicas y citas.
                            </p>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Historia Clínica Digital</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Agenda de Citas</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Recetas Electrónicas</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Facturación Médica</li>
                                <li><i class="fas fa-check text-success me-2"></i>Telemedicina</li>
                            </ul>
                            <a href="{{ route('sitio.registro') }}" class="btn btn-outline-success w-100">Probar gratis</a>
                        </div>
                    </div>
                </div>

                <!-- Product 3 -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="300">
                    <div class="product-card h-100 bg-white rounded-3 shadow-sm overflow-hidden">
                        <div class="product-header bg-info text-white p-4">
                            <i class="fas fa-store fa-3x mb-3"></i>
                            <h4 class="fw-bold mb-0">TUSHPA Retail</h4>
                        </div>
                        <div class="product-body p-4">
                            <p class="text-muted mb-4">
                                Sistema POS y gestión de inventarios para tiendas, bodegas y 
                                comercios. Control total de tu negocio.
                            </p>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Punto de Venta (POS)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Control de Stock</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Múltiples Almacenes</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Código de Barras</li>
                                <li><i class="fas fa-check text-success me-2"></i>Reportes de Ventas</li>
                            </ul>
                            <a href="{{ route('sitio.registro') }}" class="btn btn-outline-info w-100">Probar gratis</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Clients Section -->
        <section id="clients" class="clients-section py-5">
            <div class="container-fluid py-5">
                <div class="text-center mb-5" data-aos="fade-up">
                    <h6 class="text-primary text-uppercase mb-2">Nuestros Clientes</h6>
                    <h2 class="display-5 fw-bold">Empresas que Confían en Nosotros</h2>
                    <p class="lead text-muted mt-3">Más de 50 empresas nos respaldan</p>
                </div>
                
                <!-- Fila 1: Clientes 1-10 (izquierda a derecha) -->
                <div class="clients-row" data-aos="fade-up">
                    <div class="clients-marquee">
                        <div class="clients-track track-left">
                            @for ($i = 1; $i <= 10; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                        <div class="clients-track track-left">
                            @for ($i = 1; $i <= 10; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                
                <!-- Fila 2: Clientes 11-20 (derecha a izquierda) -->
                <div class="clients-row" data-aos="fade-up" data-aos-delay="100">
                    <div class="clients-marquee">
                        <div class="clients-track track-right">
                            @for ($i = 11; $i <= 20; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                        <div class="clients-track track-right">
                            @for ($i = 11; $i <= 20; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                
                <!-- Fila 3: Clientes 21-30 (izquierda a derecha) -->
                <div class="clients-row" data-aos="fade-up" data-aos-delay="200">
                    <div class="clients-marquee">
                        <div class="clients-track track-left">
                            @for ($i = 21; $i <= 30; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                        <div class="clients-track track-left">
                            @for ($i = 21; $i <= 30; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                
                <!-- Fila 4: Clientes 31-40 (derecha a izquierda) -->
                <div class="clients-row" data-aos="fade-up" data-aos-delay="300">
                    <div class="clients-marquee">
                        <div class="clients-track track-right">
                            @for ($i = 31; $i <= 40; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                        <div class="clients-track track-right">
                            @for ($i = 31; $i <= 40; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                
                <!-- Fila 5: Clientes 41-50 (izquierda a derecha) -->
                <div class="clients-row" data-aos="fade-up" data-aos-delay="400">
                    <div class="clients-marquee">
                        <div class="clients-track track-left">
                            @for ($i = 41; $i <= 50; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                        <div class="clients-track track-left">
                            @for ($i = 41; $i <= 50; $i++)
                            <div class="client-marquee-item">
                                <div class="client-card p-3 bg-white rounded-3 text-center">
                                    <img src="{{ asset('web/img/clientes/cliente'.$i.'.webp') }}" loading="lazy" 
                                         alt="Cliente {{ $i }}" 
                                         class="img-fluid client-logo"
                                         onerror="this.src='https://placehold.co/150x80/e2e8f0/64748b?text=Cliente+{{ $i }}'">
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                
            </div>
        </section>

    @include('sitio.partials.planes')

    <!-- CTA Section -->
    <section class="cta-section py-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center" data-aos="zoom-in">
                    <h2 class="display-4 fw-bold text-white mb-4">
                        ¿Listo para Digitalizar tu Negocio?
                    </h2>
                    <p class="lead text-white-50 mb-5">
                        Únete a más de 500 empresas que ya confían en TUSHPA. 
                        Comienza hoy mismo y transforma la manera de gestionar tu negocio.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="{{ route('sitio.registro') }}" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-rocket me-2"></i>Crear mi cuenta gratis
                        </a>
                        <a href="https://wa.me/51928396147" target="_blank" class="btn btn-success btn-lg px-5">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-primary text-uppercase mb-2">Contacto</h6>
                <h2 class="display-5 fw-bold">Estamos Aquí para Ayudarte</h2>
                <p class="lead text-muted mt-3">Contáctanos y te responderemos en menos de 24 horas</p>
            </div>

            <div class="row g-4">
                <!-- Contact Info -->
                <div class="col-lg-4" data-aos="fade-right">
                    <div class="contact-info-card bg-white p-4 rounded-3 shadow-sm h-100">
                        <div class="mb-4">
                            <i class="fas fa-map-marker-alt fa-2x text-primary mb-3"></i>
                            <h5 class="fw-bold">Dirección</h5>
                            <p class="text-muted mb-0">JR. RAUL  PILLCO PEREZ 285, iquitos, Perú</p>
                            <p class="text-muted mb-0" hidden="hidden">Av. Principal 123, Lima, Perú</p>
                        </div>
                        <div class="mb-4">
                            <i class="fas fa-phone fa-2x text-primary mb-3"></i>
                            <h5 class="fw-bold">Celular</h5>
                            <p class="text-muted mb-0">+51 928 396 147</p>
                            <p class="text-muted mb-0">+51 964 382 212</p>
                        </div>
                        <div class="mb-4">
                            <i class="fas fa-envelope fa-2x text-primary mb-3"></i>
                            <h5 class="fw-bold">Email</h5>
                            <p class="text-muted mb-0">{{ config('soporte.correo') }}</p>
                        </div>
                        <div class="mb-4">
                            <i class="fas fa-clock fa-2x text-primary mb-3"></i>
                            <h5 class="fw-bold">Horario de Atención</h5>
                            <p class="text-muted mb-0">Lun - Vie: 9:00 AM - 6:00 PM</p>
                        </div>
                        
                        <div class="social-links mt-4">
                            <a href="#" class="btn btn-outline-primary btn-sm me-2"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm me-2"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm me-2"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="col-lg-8" data-aos="fade-left">
                    <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm">
                        <form id="contactForm" action="{{ route('sitio.contacto') }}" method="POST">
                            @csrf
                            <input type="text" name="web" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Correo Electrónico *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <input type="text" class="form-control" id="empresa" name="empresa">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="servicio" class="form-label">Servicio de Interés</label>
                                <select class="form-select" id="servicio" name="servicio">
                                    <option value="">Selecciona un servicio...</option>
                                    <option value="facturacion">Facturación Electrónica</option>
                                    <option value="software">Desarrollo de Software</option>
                                    <option value="cloud">Soluciones en la Nube</option>
                                    <option value="soporte">Soporte Técnico</option>
                                    <option value="consultoria">Consultoría IT</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="mensaje" class="form-label">Mensaje *</label>
                                <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Mensaje
                                </button>
                            </div>
                            
                            <div id="formMessage" class="mt-3"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <h4 class="fw-bold mb-4">TUSHPA</h4>
                    <p class="text-white-50">
                        Soluciones tecnológicas innovadoras para impulsar tu negocio. 
                        Especialistas en facturación electrónica y desarrollo de software.
                    </p>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="fw-bold mb-4">Enlaces</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-white-50 text-decoration-none">Inicio</a></li>
                        <li class="mb-2"><a href="#about" class="text-white-50 text-decoration-none">Nosotros</a></li>
                        <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Servicios</a></li>
                        <li class="mb-2"><a href="#products" class="text-white-50 text-decoration-none">Productos</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold mb-4">Servicios</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Facturación Electrónica</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Desarrollo de Software</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Soporte Técnico</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Consultoría IT</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold mb-4">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Términos y Condiciones</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Política de Privacidad</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Libro de Reclamaciones</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-white-50">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-white-50">&copy; {{ date('Y') }} TUSHPA. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-white-50">Desarrollado con <i class="fas fa-heart text-danger"></i> en Perú</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <!-- Custom JS -->
    <script src="{{ asset('web/js/main.js') }}?v={{ filemtime(public_path('web/js/main.js')) }}"></script>
</body>
</html>