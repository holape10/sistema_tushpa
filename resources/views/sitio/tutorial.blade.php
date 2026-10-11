<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Tutoriales | TUSHPA</title>
    <link rel="icon" type="image/png" href="{{ asset('web/img/favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('web/css/style.css') }}?v={{ filemtime(public_path('web/css/style.css')) }}">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --transition: all 0.3s ease;
        }
        
        .tutorial-hero {
            position: relative;
            background: var(--gradient-primary);
            padding: 10rem 0 6rem;
            color: white;
            overflow: hidden;
        }
        .tutorial-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
        }
        .tutorial-hero .container {
            position: relative;
            z-index: 2;
        }
        .search-box {
            max-width: 700px;
            margin: 0 auto;
            position: relative;
        }
        .search-box input {
            border-radius: 50px;
            padding: 1.2rem 1.5rem 1.2rem 3.5rem;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            font-size: 1.1rem;
        }
        .search-box i {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        /* Tabs */
        .nav-tabs {
        border: none;
        margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #64748b !important;  /* Color gris oscuro legible */
            font-weight: 600;
            padding: 1rem 2.5rem;
            margin: 0 0.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        .nav-tabs .nav-link.active {
            color: white !important;  /* Texto blanco en activo */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        }
        .nav-tabs .nav-link:hover:not(.active) {
            background: #f8fafc;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
        }
        
        /* Cards */
        .tutorial-card {
            transition: var(--transition);
            border: none;
            overflow: hidden;
            height: 100%;
        }
        .tutorial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
        }
        .video-thumbnail {
            position: relative;
            height: 200px;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .video-thumbnail i {
            font-size: 4rem;
            color: rgba(255,255,255,0.3);
        }
        .play-button {
            position: absolute;
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.5rem;
            transition: var(--transition);
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .tutorial-card:hover .play-button {
            transform: scale(1.1);
            background: var(--primary-color);
            color: white;
        }
        .duration-badge {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        /* Guías Escritas - Contenido */
        .tutorial-content {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }
        .tutorial-content img {
            max-width: 100%;
            border-radius: 0.75rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            margin: 2rem 0;
            border: 2px solid #e2e8f0;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            font-weight: 700;
            margin-right: 1rem;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1.5rem;
            border-radius: 0.75rem;
            font-family: 'Courier New', monospace;
            margin: 1.5rem 0;
            border-left: 4px solid var(--primary-color);
        }
        .download-pdf {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .download-pdf:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.4);
            color: white;
        }
        
        /* FAQ */
        .faq-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .accordion-item {
            border: none;
            margin-bottom: 1rem;
            border-radius: 1rem !important;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .accordion-button {
            background: white;
            color: #1e293b;
            font-weight: 600;
            padding: 1.5rem;
            border: none;
        }
        .accordion-button:not(.collapsed) {
            background: var(--gradient-primary);
            color: white;
            box-shadow: none;
        }
        .accordion-button:not(.collapsed)::after {
            filter: brightness(0) invert(1);
        }
        .accordion-body {
            background: white;
            padding: 1.5rem;
            color: #64748b;
        }
        
        /* Video Modal */
        .video-modal .modal-dialog {
            max-width: 900px;
        }
        .video-modal .modal-content {
            border: none;
            border-radius: 1rem;
            overflow: hidden;
        }
        .video-modal .modal-header {
            background: var(--gradient-primary);
            color: white;
            border: none;
        }
        .video-modal .btn-close {
            filter: brightness(0) invert(1);
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            background: #000;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* CTA */
        .tutorial-cta {
            background: var(--gradient-primary);
            position: relative;
            overflow: hidden;
        }
        .tutorial-cta::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="{{ route('sitio.inicio') }}">
                <!--<img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="50">-->
                <img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="50" id="navLogo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.inicio') }}">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link active" href="{{ route('sitio.tutorial') }}">Tutorial</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about">Nosotros</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#services">Servicios</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contacto</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="tutorial-hero text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3" data-aos="fade-down">
                Centro de Aprendizaje <span style="color: #ffd700;">TUSHPA</span>
            </h1>
            <p class="lead mb-5 opacity-90" data-aos="fade-down" data-aos-delay="100">
                Domina nuestro sistema de facturación y gestión con guías paso a paso
            </p>
            
            <div class="search-box" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-search"></i>
                <input type="text" id="tutorialSearch" class="form-control form-control-lg" 
                       placeholder="¿Qué quieres aprender hoy? (Ej: emitir factura, configurar...)">
            </div>
        </div>
    </section>

    <!-- Tabs Navigation -->
    <section class="py-5 bg-light">
        <div class="container">
            <ul class="nav nav-tabs justify-content-center border-0" id="tutorialTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="videos-tab" data-bs-toggle="tab" 
                            data-bs-target="#videos" type="button" role="tab">
                        <i class="fas fa-video me-2"></i>Videos Tutoriales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="guias-tab" data-bs-toggle="tab" 
                            data-bs-target="#guias" type="button" role="tab">
                        <i class="fas fa-book me-2"></i>Guías con Imágenes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="faq-tab" data-bs-toggle="tab" 
                            data-bs-target="#faq" type="button" role="tab">
                        <i class="fas fa-question-circle me-2"></i>Preguntas Frecuentes
                    </button>
                </li>
            </ul>
        </div>
    </section>

    <!-- Tab Content -->
    <div class="tab-content" id="tutorialTabContent">
        
        <!-- VIDEOS -->
        <div class="tab-pane fade show active" id="videos" role="tabpanel">
            <section class="py-5">
                <div class="container">
                    <div class="text-center mb-5" data-aos="fade-up">
                        <h6 class="text-primary text-uppercase mb-2">Aprende Visualmente</h6>
                        <h2 class="display-6 fw-bold">Videos Tutoriales</h2>
                    </div>
                    
                    <div class="row g-4">
                        <!-- Video 1 -->
                        <div class="col-md-6 col-lg-4 tutorial-item" data-aos="fade-up" data-category="facturacion" 
                             data-bs-toggle="modal" data-bs-target="#videoModal1">
                            <div class="card tutorial-card h-100 bg-white rounded-3 shadow-sm">
                                <div class="video-thumbnail">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <div class="play-button"><i class="fas fa-play ms-1"></i></div>
                                    <span class="duration-badge"><i class="far fa-clock me-1"></i>5:30</span>
                                </div>
                                <div class="card-body p-4">
                                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3">Facturación</span>
                                    <h5 class="fw-bold mb-2">Cómo emitir tu primera factura</h5>
                                    <p class="text-muted small mb-3">Aprende paso a paso a crear y enviar facturas electrónicas.</p>
                                    <button class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                                        <i class="fas fa-play me-1"></i>Ver Video
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Video 2 -->
                        <div class="col-md-6 col-lg-4 tutorial-item" data-aos="fade-up" data-aos-delay="100" 
                             data-category="configuracion" data-bs-toggle="modal" data-bs-target="#videoModal2">
                            <div class="card tutorial-card h-100 bg-white rounded-3 shadow-sm">
                                <div class="video-thumbnail">
                                    <i class="fas fa-cogs"></i>
                                    <div class="play-button"><i class="fas fa-play ms-1"></i></div>
                                    <span class="duration-badge"><i class="far fa-clock me-1"></i>8:15</span>
                                </div>
                                <div class="card-body p-4">
                                    <span class="badge bg-success bg-opacity-10 text-success mb-3">Configuración</span>
                                    <h5 class="fw-bold mb-2">Configuración inicial del sistema</h5>
                                    <p class="text-muted small mb-3">Configura tu empresa, usuarios y permisos.</p>
                                    <button class="btn btn-outline-success btn-sm w-100 rounded-pill">
                                        <i class="fas fa-play me-1"></i>Ver Video
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Video 3 -->
                        <div class="col-md-6 col-lg-4 tutorial-item" data-aos="fade-up" data-aos-delay="200" 
                             data-category="reportes" data-bs-toggle="modal" data-bs-target="#videoModal3">
                            <div class="card tutorial-card h-100 bg-white rounded-3 shadow-sm">
                                <div class="video-thumbnail">
                                    <i class="fas fa-chart-bar"></i>
                                    <div class="play-button"><i class="fas fa-play ms-1"></i></div>
                                    <span class="duration-badge"><i class="far fa-clock me-1"></i>6:45</span>
                                </div>
                                <div class="card-body p-4">
                                    <span class="badge bg-info bg-opacity-10 text-info mb-3">Reportes</span>
                                    <h5 class="fw-bold mb-2">Generación de reportes de ventas</h5>
                                    <p class="text-muted small mb-3">Aprende a generar y exportar reportes.</p>
                                    <button class="btn btn-outline-info btn-sm w-100 rounded-pill">
                                        <i class="fas fa-play me-1"></i>Ver Video
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- GUÍAS ESCRITAS CON IMÁGENES -->
        <div class="tab-pane fade" id="guias" role="tabpanel">
            <section class="py-5">
                <div class="container">
                    <div class="text-center mb-5" data-aos="fade-up">
                        <h6 class="text-primary text-uppercase mb-2">Documentación Completa</h6>
                        <h2 class="display-6 fw-bold">Guías con Imágenes y PDFs</h2>
                    </div>
                    
                    <!-- GUÍA 1 -->
                    <div class="tutorial-item" data-aos="fade-up" data-category="certificado">
                        <div class="tutorial-content">
                            <div class="d-flex align-items-center mb-4">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 me-3">
                                    <i class="fas fa-file-alt me-1"></i> Guía Completa
                                </span>
                                <span class="text-muted"><i class="far fa-clock me-2"></i>15 min</span>
                                <span class="text-muted ms-3"><i class="fas fa-signal me-2"></i>Nivel: Básico</span>
                            </div>
                            
                            <h2 class="fw-bold mb-4">Cómo Configurar tu Certificado Digital</h2>
                            
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>El certificado digital es necesario para emitir facturas electrónicas válidas ante SUNAT</div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">1</span>
                                    Obtener el Certificado Digital
                                </h5>
                                <p class="text-muted">
                                    Puedes obtenerlo de estas entidades autorizadas por SUNAT:
                                </p>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded-3 text-center border">
                                            <i class="fas fa-shield-alt fa-2x text-primary mb-2"></i>
                                            <p class="mb-0 fw-bold">IZENPE</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded-3 text-center border">
                                            <i class="fas fa-shield-alt fa-2x text-primary mb-2"></i>
                                            <p class="mb-0 fw-bold">ENTRUST</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded-3 text-center border">
                                            <i class="fas fa-shield-alt fa-2x text-primary mb-2"></i>
                                            <p class="mb-0 fw-bold">ECERTPERU</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- IMAGEN 1 -->
                                <div class="bg-light p-4 rounded-3 text-center border border-dashed">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-2"><strong>Imagen: Formulario de solicitud</strong></p>
                                    <small class="text-muted">Reemplazar con: images/tutoriales/certificado-paso1.jpg</small>
                                    <!-- Cuando tengas la imagen real, usa: -->
                                    <!-- <img src="images/tutoriales/certificado-paso1.jpg" alt="Paso 1" class="img-fluid"> -->
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">2</span>
                                    Instalar en tu Navegador
                                </h5>
                                <ol class="text-muted ps-4">
                                    <li class="mb-2">Abre Google Chrome</li>
                                    <li class="mb-2">Ve a Configuración → Privacidad y seguridad</li>
                                    <li class="mb-2">Click en "Seguridad" → "Gestionar certificados"</li>
                                    <li class="mb-2">Click en "Importar" y selecciona tu archivo .pfx</li>
                                </ol>
                                <!-- IMAGEN 2 -->
                                <div class="bg-light p-4 rounded-3 text-center border border-dashed">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-2"><strong>Imagen: Instalación en Chrome</strong></p>
                                    <small class="text-muted">Reemplazar con: images/tutoriales/certificado-paso2.jpg</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">3</span>
                                    Configurar en TUSHPA
                                </h5>
                                <div class="code-block">
Menú Principal → Configuración → Certificado Digital<br>
→ Seleccionar archivo .pfx<br>
→ Ingresar contraseña<br>
→ Click en "Guardar"
                                </div>
                                <!-- IMAGEN 3 -->
                                <div class="bg-light p-4 rounded-3 text-center border border-dashed">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-2"><strong>Imagen: Configuración en TUSHPA</strong></p>
                                    <small class="text-muted">Reemplazar con: images/tutoriales/certificado-paso3.jpg</small>
                                </div>
                            </div>

                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <div><strong>¡Listo!</strong> Ya puedes emitir facturas electrónicas.</div>
                            </div>

                            <div class="text-center mt-5 pt-4 border-top">
                                <p class="text-muted mb-3">¿Descargar esta guía en PDF?</p>
                                <a href="downloads/guia-certificado-digital.pdf" class="download-pdf" download>
                                    <i class="fas fa-download"></i> Descargar PDF
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- GUÍA 2 -->
                    <div class="tutorial-item" data-aos="fade-up" data-category="reportes">
                        <div class="tutorial-content">
                            <div class="d-flex align-items-center mb-4">
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 me-3">
                                    <i class="fas fa-file-alt me-1"></i> Guía Completa
                                </span>
                                <span class="text-muted"><i class="far fa-clock me-2"></i>10 min</span>
                                <span class="text-muted ms-3"><i class="fas fa-signal me-2"></i>Nivel: Intermedio</span>
                            </div>
                            
                            <h2 class="fw-bold mb-4">Cómo Generar Reportes de Ventas</h2>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">1</span>
                                    Acceder al Módulo
                                </h5>
                                <div class="code-block">Reportes → Ventas → Reporte General</div>
                                <div class="bg-light p-4 rounded-3 text-center border border-dashed">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-2"><strong>Imagen: Menú de reportes</strong></p>
                                    <small class="text-muted">Reemplazar con: images/tutoriales/reporte-paso1.jpg</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">2</span>
                                    Filtrar por Fechas
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3">
                                            <i class="fas fa-calendar-day text-primary me-2"></i>
                                            <strong>Hoy</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3">
                                            <i class="fas fa-calendar-week text-primary me-2"></i>
                                            <strong>Esta semana</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3">
                                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                                            <strong>Este mes</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3">
                                            <i class="fas fa-calendar-check text-primary me-2"></i>
                                            <strong>Personalizado</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">
                                    <span class="step-number">3</span>
                                    Exportar
                                </h5>
                                <div class="d-flex gap-3 flex-wrap justify-content-center">
                                    <button class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-file-excel me-2"></i>Excel
                                    </button>
                                    <button class="btn btn-outline-danger btn-lg">
                                        <i class="fas fa-file-pdf me-2"></i>PDF
                                    </button>
                                </div>
                            </div>

                            <div class="text-center mt-5 pt-4 border-top">
                                <a href="downloads/guia-reportes-ventas.pdf" class="download-pdf" download>
                                    <i class="fas fa-download"></i> Descargar PDF
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- FAQ -->
        <div class="tab-pane fade" id="faq" role="tabpanel">
            <section class="faq-section py-5">
                <div class="container">
                    <div class="text-center mb-5" data-aos="fade-up">
                        <h6 class="text-primary text-uppercase mb-2">Preguntas Frecuentes</h6>
                        <h2 class="display-5 fw-bold">¿Tienes dudas rápidas?</h2>
                    </div>
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-8" data-aos="fade-up">
                            <div class="accordion" id="faqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                            <i class="fas fa-download me-3"></i>¿Necesito instalar algo?
                                        </button>
                                    </h2>
                                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            No, TUSHPA es 100% en la nube. Solo necesitas un navegador y conexión a internet.
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                            <i class="fas fa-certificate me-3"></i>¿Cómo obtengo mi certificado digital?
                                        </button>
                                    </h2>
                                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            De entidades como IZENPE, ENTRUST o ECERTPERU. Toma 24-48 horas.
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                            <i class="fas fa-headset me-3"></i>¿Soporte técnico?
                                        </button>
                                    </h2>
                                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            Disponible 24/7 por WhatsApp: +51 928 396 147 o email: holapesac@gmail.com
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- CTA -->
    <section class="tutorial-cta py-5">
        <div class="container py-4 text-center position-relative" style="z-index: 2;">
            <h2 class="display-5 fw-bold text-white mb-3">¿Necesitas ayuda personalizada?</h2>
            <p class="lead text-white-50 mb-4">Agenda una capacitación gratuita con nuestro equipo</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="index.php#contact" class="btn btn-light btn-lg px-5 rounded-pill">
                    <i class="fas fa-calendar-check me-2"></i>Agendar Capacitación
                </a>
                <a href="https://wa.me/51928396147" target="_blank" class="btn btn-success btn-lg px-5 rounded-pill">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- MODALES VIDEO -->
    <div class="modal fade video-modal" id="videoModal1" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cómo emitir tu primera factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="video-container">
                        <iframe src="https://www.youtube.com/embed/27T0zYOROmk" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade video-modal" id="videoModal2" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configuración inicial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="video-container">
                        <iframe src="https://www.youtube.com/embed/DEF456uvw" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade video-modal" id="videoModal3" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reportes de ventas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="video-container">
                        <iframe src="https://www.youtube.com/embed/GHI789rst" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="text-center">
                <p class="mb-0">&copy; {{ date('Y') }} TUSHPA. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
        
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        document.getElementById('tutorialSearch').addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.tutorial-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        document.querySelectorAll('.video-modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function () {
                const iframes = this.querySelectorAll('iframe');
                iframes.forEach(iframe => {
                    const src = iframe.src;
                    iframe.src = '';
                    setTimeout(() => { iframe.src = src; }, 100);
                });
            });
        });
    </script>
</body>
</html>