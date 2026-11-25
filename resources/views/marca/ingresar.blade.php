{{--
    Portada del dominio de una marca: presenta el sistema con su nombre y sus colores, y la empresa entra escribiendo su
    RUC o el nombre de su sistema. Todo sale de la marca (nombre, colores, WhatsApp, correo): sirve igual para cualquier
    revendedor y nunca nombra a TUSHPA.
--}}
@extends('marca.base')
@section('titulo', 'Sistema de facturación y ventas')
@section('robots', 'index, follow')

@php
    $nombre = config('marca.nombre');
    $wa = config('soporte.whatsapp');
    $demo = $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode("Hola, quiero conocer el sistema {$nombre} para mi negocio.") : null;
    $rubros = \App\Support\RubrosWeb::lista();
    $ia = [
        ['fa-microphone', 'Vende hablando', 'Dices "2 jabones y una gaseosa, paga con Yape" y la venta se arma sola.'],
        ['fa-file-invoice-dollar', 'Ingresa tus compras', 'Sube la foto, el PDF o el XML de la factura de tu proveedor y la compra se llena sola.'],
        ['fa-box-open', 'Crea tus productos', 'Díctalos o súbelos con foto: nombre, precio, stock y presentaciones en segundos.'],
        ['fa-boxes-stacked', 'Cuenta tu inventario', 'Toma una foto a las cajas o a la ruma y la IA te dice cuántas hay.'],
        ['fa-comments', 'Pregúntale lo que quieras', '"¿Cuánto vendí hoy?", "¿Qué se está acabando?", "¿Quién me debe?": te responde al instante.'],
        ['fa-chart-column', 'Reportes y gráficos', 'Pídele un reporte o un gráfico de tus ventas y te lo muestra ahí mismo.'],
    ];
    $funciones = [
        ['fa-cash-register', 'Caja y turnos', 'Apertura y cierre de caja por usuario, con lo vendido por forma de pago.'],
        ['fa-boxes-stacked', 'Inventario y kardex', 'Varios almacenes, transferencias, inventarios físicos y alertas de stock bajo.'],
        ['fa-cart-shopping', 'Compras y gastos', 'Registra lo que compras y gastas para saber cuánto ganas de verdad.'],
        ['fa-hand-holding-dollar', 'Cuentas por cobrar y pagar', 'Créditos de clientes y deudas con proveedores al día.'],
        ['fa-chart-pie', 'Reportes claros', 'Ventas por día, producto, vendedor y rentabilidad.'],
        ['fa-code-branch', 'Varias sucursales', 'Cada local con su caja, almacén y series de comprobantes.'],
        ['fa-user-lock', 'Usuarios y permisos', 'Cada trabajador ve solo las opciones que le das.'],
        ['fa-calculator', 'Contabilidad y SIRE', 'Registro de compras y ventas, SIRE, planilla y reportes para tu contador.'],
        ['fa-bag-shopping', 'Tienda virtual', 'Tu catálogo en línea para que te pidan por internet.'],
        ['fa-fingerprint', 'Asistencia del personal', 'Marcación con QR desde el celular y tareo mensual.'],
        ['fa-file-excel', 'Carga desde Excel', 'Sube tus productos de una vez y empieza a vender.'],
        ['fa-shield-halved', 'Copias de seguridad', 'Tus datos se respaldan todos los días, en una base propia para tu empresa.'],
    ];
    $venta = [['2', 'Arroz extra 5 kg', 'ARROZ EXTRA 5KG', 46.00], ['3', 'Aceite vegetal 1 L', 'ACEITE VEGETAL 1L', 28.50], ['1', 'Leche evaporada x6', 'LECHE EVAPORADA X6', 22.80], ['4', 'Gaseosa 1.5 L', 'GASEOSA 1.5L', 26.00]];
    $totalVenta = number_format(array_sum(array_column($venta, 3)), 2);
    $preguntas = [
        ['¿Tengo que instalar algo?', "No. {$nombre} funciona desde el navegador en tu PC, tablet o celular, con tu propia dirección (por ejemplo, tunegocio.".config('marca.dominio').'). Si quieres, lo instalas como app con un clic.'],
        ['¿Emite boletas y facturas electrónicas?', 'Sí. Boletas, facturas, notas de crédito y débito, notas de venta y guías de remisión electrónicas, enviadas a SUNAT al instante.'],
        ['¿Mis datos están separados de los de otras empresas?', 'Sí. Cada empresa tiene su propia base de datos y sus propios usuarios, cada uno con los permisos que tú le des.'],
        ['¿Necesito internet?', 'Sí. El sistema trabaja en línea para enviar tus comprobantes a SUNAT y tener tus datos al día en todos tus equipos.'],
        ['¿Me ayudan a empezar?', 'Sí. Te acompañamos a cargar tus productos, configurar tus impresoras y emitir tu primer comprobante.'],
    ];
@endphp

@section('enlaces')
    <a href="#ia" class="enlace d-none d-lg-inline">Asistente IA</a>
    <a href="#rubros" class="enlace d-none d-lg-inline">Rubros</a>
    <a href="#funciones" class="enlace d-none d-lg-inline">Funciones</a>
    <a href="#preguntas" class="enlace d-none d-lg-inline">Preguntas</a>
    <a href="#ingresar" class="enlace d-none d-md-inline"><i class="fas fa-right-to-bracket me-1"></i>Ingresar</a>
@endsection

@push('cabecera')
    <style>
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        h1, h2, h3, h4 { font-weight: 700; letter-spacing: -.02em; }
        .hero h1 { font-weight: 800; line-height: 1.08; font-size: clamp(2.1rem, 4.6vw, 3.4rem); }
        .resalte { background: linear-gradient(90deg, #fde68a, #fff); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .chip { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .25); color: #fff;
            border-radius: 999px; padding: .35rem .8rem; font-size: .85rem; font-weight: 600; }
        .chip i { color: #fde68a; }
        .btn-hueco { border: 2px solid rgba(255, 255, 255, .6); color: #fff; } .btn-hueco:hover { background: rgba(255, 255, 255, .12); color: #fff; }
        .bloque { padding: 5rem 0; position: relative; overflow: hidden; }
        .bloque-claro { background: #fff; } .bloque-gris { background: #f6f7fb; }
        .bloque-oscuro { background: linear-gradient(160deg, var(--oscuro), color-mix(in srgb, var(--marca2) 45%, #0b1020)); color: #fff; }
        .bloque-ia { background: linear-gradient(180deg, color-mix(in srgb, var(--marca) 8%, #fff) 0%, #fff 100%); }
        .titulo { max-width: 44rem; margin: 0 auto 3rem; text-align: center; }
        .titulo h2 { font-size: clamp(1.8rem, 3.4vw, 2.6rem); margin: .6rem 0 1rem; }
        .titulo p { color: var(--gris); font-size: 1.05rem; margin: 0; }
        .etiqueta { display: inline-block; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--marca); }
        .bloque-oscuro .etiqueta { color: #fde68a; }
        .resalte-marca { background: linear-gradient(90deg, var(--marca), var(--marca2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .icono { width: 52px; height: 52px; border-radius: 1rem; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.25rem;
            background: linear-gradient(135deg, var(--marca), var(--marca2)); margin-bottom: 1rem; }
        .caja { height: 100%; background: #fff; border-radius: 1.25rem; padding: 1.6rem; border: 1px solid rgba(30, 41, 59, .07);
            box-shadow: 0 10px 30px -22px rgba(15, 23, 42, .45); transition: transform .2s, box-shadow .2s; }
        .caja:hover { transform: translateY(-4px); box-shadow: 0 24px 50px -26px rgba(15, 23, 42, .5); }
        .caja h3 { font-size: 1.08rem; margin-bottom: .45rem; } .caja p { color: var(--gris); font-size: .92rem; margin: 0; }
        /* Confianza */
        .confianza { background: var(--oscuro); color: rgba(255, 255, 255, .85); padding: 1.1rem 0; font-size: .92rem; }
        .confianza i { color: #fde68a; margin-right: .45rem; }
        /* Maqueta del sistema */
        .maqueta { background: #fff; border-radius: 1.25rem; box-shadow: 0 40px 80px -30px rgba(15, 23, 42, .55); overflow: hidden; }
        .maqueta-barra { display: flex; align-items: center; gap: .4rem; padding: .7rem 1rem; background: #f1f5f9; font-size: .75rem; color: var(--gris); }
        .maqueta-barra span { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; }
        .maqueta-barra b { margin-left: .6rem; font-weight: 500; }
        .maqueta-cuerpo { padding: 1.2rem 1.3rem; font-size: .88rem; }
        .maqueta-linea { display: flex; justify-content: space-between; padding: .45rem 0; border-bottom: 1px dashed #e2e8f0; }
        .maqueta-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.1rem; padding-top: .8rem; }
        .maqueta-ok { margin-top: .9rem; background: #ecfdf5; color: #047857; border-radius: .7rem; padding: .55rem .8rem; font-weight: 600; font-size: .82rem; }
        .flotante { position: absolute; z-index: 2; background: #fff; border-radius: 999px; padding: .45rem .9rem .45rem .5rem; font-size: .8rem; font-weight: 600;
            box-shadow: 0 14px 30px -14px rgba(15, 23, 42, .5); display: flex; align-items: center; gap: .5rem; color: var(--tinta); }
        .flotante i { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem; }
        .flotante-1 { top: -18px; left: -14px; } .flotante-2 { bottom: -18px; right: -10px; }
        /* Rubros */
        .rubros-lista { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-bottom: 2rem; }
        .rubro-btn { border: 1px solid rgba(30, 41, 59, .12); background: #fff; border-radius: 999px; padding: .5rem 1rem; font-size: .88rem; font-weight: 600; color: var(--tinta); }
        .rubro-btn i { color: var(--marca); margin-right: .45rem; }
        .rubro-btn.activo { background: var(--marca); border-color: var(--marca); color: #fff; } .rubro-btn.activo i { color: #fff; }
        .rubro-panel { display: none; background: #fff; border-radius: 1.5rem; overflow: hidden; box-shadow: 0 30px 60px -35px rgba(15, 23, 42, .5); }
        .rubro-panel.activo { display: block; }
        .rubro-lado { height: 100%; padding: 2.2rem; color: #fff; background: linear-gradient(160deg, var(--marca), var(--marca2)); }
        .rubro-lado .icono-grande { font-size: 2.6rem; margin-bottom: 1rem; color: #fde68a; }
        .rubro-funciones { padding: 2rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1.2rem; }
        .funcion { display: flex; gap: .8rem; align-items: flex-start; }
        .funcion > i { width: 36px; height: 36px; flex: 0 0 36px; border-radius: .7rem; display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--marca) 12%, #fff); color: var(--marca); }
        .funcion b { display: block; font-size: .95rem; } .funcion span { color: var(--gris); font-size: .86rem; }
        /* Facturación */
        .lista-check { list-style: none; padding: 0; margin: 0; }
        .lista-check li { display: flex; gap: .7rem; margin-bottom: .9rem; color: rgba(255, 255, 255, .85); }
        .lista-check i { color: #4ade80; margin-top: .25rem; }
        .ticket { background: #fff; color: #111827; max-width: 330px; margin: 0 auto; padding: 1.5rem 1.4rem; font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: .78rem; border-radius: .4rem; box-shadow: 0 40px 70px -30px rgba(0, 0, 0, .7); transform: rotate(2deg); }
        .ticket .c { text-align: center; } .ticket hr { border-top: 1px dashed #6b7280; opacity: 1; margin: .7rem 0; }
        .ticket .qr { width: 78px; height: 78px; margin: .8rem auto; background:
            conic-gradient(#111 25%, #fff 0 50%, #111 0 75%, #fff 0) 0 0 / 13px 13px; border: 4px solid #111; }
        /* Pasos y preguntas */
        .paso-num { width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-weight: 800;
            font-size: 1.3rem; color: #fff; background: linear-gradient(135deg, var(--marca), var(--marca2)); box-shadow: 0 14px 30px -14px var(--marca); }
        .pregunta { background: #fff; border-radius: 1rem; border: 1px solid rgba(30, 41, 59, .08); margin-bottom: .75rem; }
        .pregunta summary { padding: 1rem 1.25rem; font-weight: 600; cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 1rem; }
        .pregunta summary::-webkit-details-marker { display: none; }
        .pregunta summary::after { content: '+'; color: var(--marca); font-size: 1.3rem; line-height: 1; }
        .pregunta[open] summary::after { content: '−'; }
        .pregunta p { padding: 0 1.25rem 1.1rem; margin: 0; color: var(--gris); }
        .llamada { border-radius: 1.75rem; padding: 3.5rem 1.5rem; text-align: center; color: #fff; background: linear-gradient(135deg, var(--marca), var(--marca2));
            box-shadow: 0 40px 80px -40px var(--marca); }
        .contacto-dato { display: flex; gap: .9rem; align-items: flex-start; margin-bottom: 1.1rem; }
        .contacto-dato > i { width: 42px; height: 42px; flex: 0 0 42px; border-radius: .8rem; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, .12); color: #fde68a; }
        .contacto-dato a { color: inherit; }
        /* Pollo (Font Awesome gratis no tiene uno): mismo tamaño y color que los demás íconos <i class="fas fa-pollo"> */
        .fas.fa-pollo::before {
            content: "";
            display: inline-block;
            width: 1.1em;
            height: 1em;
            vertical-align: -.125em;
            background-color: currentColor;
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill-rule='evenodd' d='M36 9C36 5 40 4 41 7C42 3 47 4 46 8C49 7 51 10 48 12L38 13Z M44 11C49 11 52 15 51 19L58 21L51 24C51 26 50 28 48 29C52 35 52 44 45 50C39 55 27 55 19 50C11 45 6 36 6 26C6 20 9 14 13 11C14 17 18 23 24 26C29 28 33 27 36 24C35 20 35 16 38 13C40 12 42 11 44 11Z M45.8 17A1.8 1.8 0 1 0 42.2 17A1.8 1.8 0 1 0 45.8 17Z M48 25C50 26 50 31 47 31C45 31 45 27 48 25Z M26 52h3v6h4v2.5h-7Z M36 52h3v6h4v2.5h-7Z'/%3E%3C/svg%3E") no-repeat center / contain;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill-rule='evenodd' d='M36 9C36 5 40 4 41 7C42 3 47 4 46 8C49 7 51 10 48 12L38 13Z M44 11C49 11 52 15 51 19L58 21L51 24C51 26 50 28 48 29C52 35 52 44 45 50C39 55 27 55 19 50C11 45 6 36 6 26C6 20 9 14 13 11C14 17 18 23 24 26C29 28 33 27 36 24C35 20 35 16 38 13C40 12 42 11 44 11Z M45.8 17A1.8 1.8 0 1 0 42.2 17A1.8 1.8 0 1 0 45.8 17Z M48 25C50 26 50 31 47 31C45 31 45 27 48 25Z M26 52h3v6h4v2.5h-7Z M36 52h3v6h4v2.5h-7Z'/%3E%3C/svg%3E") no-repeat center / contain;
        }
        @media (max-width: 991px) { .bloque { padding: 3.5rem 0; } .flotante { display: none; } }
    </style>
@endpush

@section('contenido')
    <div class="row align-items-center g-4 g-lg-5 hero py-3 py-lg-5">
        <div class="col-lg-7 text-white order-2 order-lg-1">
            <span class="chip mb-3"><i class="fas fa-circle-check"></i> Conectado con SUNAT</span>
            <h1 class="mb-3">Factura a SUNAT y controla tu negocio <span class="resalte">desde un solo sistema</span></h1>
            <p class="lead mb-4" style="color: rgba(255,255,255,.88)">Con <b>{{ $nombre }}</b> emites tus comprobantes electrónicos, vendes, manejas tu caja y tu inventario,
                con un <b>asistente de inteligencia artificial</b> que te ayuda a vender, comprar y crear tus productos. Desde la PC, la tablet o el celular, sin instalar nada.</p>
            <div class="d-flex flex-wrap gap-3 mb-4">
                @if ($demo)
                    <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-light btn-lg rounded-pill fw-bold px-4"><i class="fab fa-whatsapp text-success me-2"></i>Pide tu demostración</a>
                @endif
                <a href="#funciones" class="btn btn-hueco btn-lg rounded-pill fw-semibold px-4"><i class="fas fa-circle-play me-2"></i>Ver lo que hace</a>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="chip"><i class="fas fa-wand-magic-sparkles"></i> Con inteligencia artificial</span>
                <span class="chip"><i class="fas fa-cloud"></i> 100 % en la nube</span>
                <span class="chip"><i class="fas fa-mobile-screen"></i> PC, tablet y celular</span>
                <span class="chip"><i class="fas fa-shield-halved"></i> Copias de seguridad diarias</span>
            </div>
        </div>
        {{-- En el celular, primero el ingreso (quien ya tiene su sistema no tiene que bajar) --}}
        <div class="col-lg-5 order-1 order-lg-2" id="ingresar">
            <div class="card tarjeta p-4 p-sm-5">
                <h2 class="h4 fw-bold mb-1">Ingresa a tu sistema</h2>
                <p class="text-muted mb-4">Escribe el RUC de tu empresa o el nombre de tu sistema (ej. <b>miempresa</b>.{{ config('marca.dominio') }}).</p>
                @if ($error)<div class="alert alert-warning py-2 small">{{ $error }}</div>@endif
                <form method="GET" action="/">
                    <label class="form-label" for="cuenta">RUC o nombre de tu sistema</label>
                    <input id="cuenta" name="cuenta" value="{{ $cuenta }}" required maxlength="60" autocomplete="off" class="form-control mb-3" placeholder="20123456789">
                    <button class="btn btn-marca btn-lg w-100 fw-semibold rounded-pill"><i class="fas fa-right-to-bracket me-2"></i>Ingresar</button>
                </form>
                @if ($wa)
                    <p class="small text-muted text-center mt-3 mb-0">¿Aún no tienes tu sistema? <a href="{{ $demo }}" target="_blank" rel="noopener" class="fw-semibold">Escríbenos</a></p>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('completo')
    {{-- Confianza --}}
    <div class="confianza">
        <div class="container d-flex flex-wrap justify-content-center gap-4 text-center">
            <span><i class="fas fa-file-invoice"></i>Facturación electrónica SUNAT</span>
            <span><i class="fas fa-wand-magic-sparkles"></i>Asistente con IA</span>
            <span><i class="fas fa-store"></i>{{ count($rubros) }} rubros de negocio</span>
            <span><i class="fas fa-mobile-screen"></i>PC, tablet y celular</span>
            <span><i class="fas fa-database"></i>Base de datos propia por empresa</span>
        </div>
    </div>

    {{-- Así se ve --}}
    <section class="bloque bloque-claro">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="etiqueta">Punto de venta</span>
                    <h2 class="mt-2 mb-3" style="font-size:clamp(1.8rem,3.4vw,2.5rem)">Vende en segundos y tu boleta llega a SUNAT sola</h2>
                    <p class="text-muted fs-5 mb-4">Busca o escanea el producto, cobra en efectivo, Yape, tarjeta o varias formas a la vez, y entrega la boleta impresa o por WhatsApp.
                        Tu caja y tu stock se actualizan al instante.</p>
                    <div class="row g-3">
                        <div class="col-sm-6"><div class="funcion"><i class="fas fa-barcode"></i><div><b>Lector de código</b><span>Vende rápido con tu lector o la cámara.</span></div></div></div>
                        <div class="col-sm-6"><div class="funcion"><i class="fas fa-star"></i><div><b>Más vendidos</b><span>Tus productos más movidos, a un toque.</span></div></div></div>
                        <div class="col-sm-6"><div class="funcion"><i class="fas fa-money-bill-transfer"></i><div><b>Varias formas de pago</b><span>Efectivo, Yape, Plin y tarjeta en una venta.</span></div></div></div>
                        <div class="col-sm-6"><div class="funcion"><i class="fab fa-whatsapp"></i><div><b>Comprobante por WhatsApp</b><span>Tu cliente lo recibe al instante.</span></div></div></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative mx-auto" style="max-width:460px">
                        <div class="flotante flotante-1"><i class="fas fa-check" style="background:#16a34a"></i>Boleta aceptada por SUNAT</div>
                        <div class="flotante flotante-2"><i class="fab fa-whatsapp" style="background:#25d366"></i>Enviada al cliente</div>
                        <div class="maqueta">
                            <div class="maqueta-barra"><span></span><span></span><span></span><b>minegocio.{{ config('marca.dominio') }}</b></div>
                            <div class="maqueta-cuerpo">
                                <div class="small fw-bold text-uppercase text-muted mb-1">Punto de venta · Caja 01</div>
                                @foreach ($venta as [$cantidad, $producto, , $importe])
                                    <div class="maqueta-linea"><span>{{ $cantidad }} × {{ $producto }}</span><b>S/ {{ number_format($importe, 2) }}</b></div>
                                @endforeach
                                <div class="maqueta-total"><span>Total</span><span>S/ {{ $totalVenta }}</span></div>
                                <div class="maqueta-ok"><i class="fas fa-circle-check me-1"></i>Boleta B001-00318 · ACEPTADA</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Inteligencia artificial --}}
    <section id="ia" class="bloque bloque-ia">
        <div class="container">
            <div class="titulo">
                <span class="etiqueta"><i class="fas fa-wand-magic-sparkles me-1"></i>Inteligencia artificial incluida</span>
                <h2>Tu negocio con un <span class="resalte-marca">asistente IA</span> que trabaja contigo</h2>
                <p>Habla o toma una foto y la IA hace el trabajo pesado. Tú solo revisas y aceptas.</p>
            </div>
            <div class="row g-4">
                @foreach ($ia as [$icono, $titulo, $texto])
                    <div class="col-sm-6 col-lg-4">
                        <div class="caja"><div class="icono"><i class="fas {{ $icono }}"></i></div><h3>{{ $titulo }}</h3><p>{{ $texto }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Rubros --}}
    <section id="rubros" class="bloque bloque-gris">
        <div class="container">
            <div class="titulo">
                <span class="etiqueta">Un sistema para cada negocio</span>
                <h2>Elige tu rubro y mira lo que hace por ti</h2>
                <p>El sistema arranca con el menú de tu tipo de negocio. Todos incluyen facturación electrónica, caja, productos e inventario.</p>
            </div>
            <div class="rubros-lista">
                @foreach ($rubros as $clave => $r)
                    <button type="button" class="rubro-btn {{ $loop->first ? 'activo' : '' }}" data-rubro="{{ $clave }}"><i class="fas {{ $r['icono'] }}"></i>{{ $r['nombre'] }}</button>
                @endforeach
            </div>
            @foreach ($rubros as $clave => $r)
                <div class="rubro-panel {{ $loop->first ? 'activo' : '' }}" id="rubro-{{ $clave }}">
                    <div class="row g-0">
                        <div class="col-lg-4">
                            <div class="rubro-lado">
                                <div class="icono-grande"><i class="fas {{ $r['icono'] }}"></i></div>
                                <h3>{{ $r['nombre'] }}</h3>
                                <p class="small mb-3" style="color:rgba(255,255,255,.75)">{{ $r['ejemplos'] }}</p>
                                <p class="mb-4">{{ $r['texto'] }}</p>
                                @if ($demo)
                                    <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-light rounded-pill fw-semibold"><i class="fab fa-whatsapp text-success me-1"></i>Quiero verlo</a>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="rubro-funciones">
                                @foreach ($r['funciones'] as [$icono, $titulo, $detalle])
                                    <div class="funcion"><i class="fas {{ $icono }}"></i><div><b>{{ $titulo }}</b><span>{{ $detalle }}</span></div></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Facturación electrónica --}}
    <section class="bloque bloque-oscuro">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="etiqueta">Facturación electrónica</span>
                    <h2 class="mt-3 mb-4" style="font-size:clamp(1.8rem,3.4vw,2.6rem)">Tus comprobantes van a SUNAT solos, y tu cliente los recibe al instante</h2>
                    <ul class="lista-check">
                        <li><i class="fas fa-circle-check"></i><span><b>Boletas, facturas, notas de crédito y débito</b> y notas de venta.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Guías de remisión electrónicas</b> para tus traslados.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Resúmenes diarios y anulaciones</b> desde el mismo sistema.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Envía el comprobante por WhatsApp</b> o imprímelo en ticket o en A4.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Tu propia página de consulta:</b> tus clientes descargan su PDF, XML y CDR en <i>tuempresa.{{ config('marca.dominio') }}/cpe</i>.</span></li>
                        <li><i class="fas fa-circle-check"></i><span><b>Reportes listos para tu contador</b> (SIRE y registros de compras y ventas).</span></li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="ticket">
                        <div class="c"><b>TU NEGOCIO S.A.C.</b><br>RUC 20XXXXXXXXX<br>Av. Principal 123</div>
                        <hr>
                        <div class="c"><b>BOLETA DE VENTA ELECTRÓNICA</b><br>B001-00318</div>
                        <hr>
                        @foreach ($venta as [$cantidad, , $productoTicket, $importe])
                            <div class="d-flex justify-content-between"><span>{{ $cantidad }} {{ $productoTicket }}</span><span>{{ number_format($importe, 2) }}</span></div>
                        @endforeach
                        <hr>
                        <div class="d-flex justify-content-between"><b>TOTAL S/</b><b>{{ $totalVenta }}</b></div>
                        <div class="qr"></div>
                        <div class="c" style="font-size:.7rem">Consulta tu comprobante en:<br><b>tunegocio.{{ config('marca.dominio') }}/cpe</b></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Funciones --}}
    <section id="funciones" class="bloque bloque-claro">
        <div class="container">
            <div class="titulo">
                <span class="etiqueta">Todo en un solo lugar</span>
                <h2>Lo que tu negocio necesita para crecer ordenado</h2>
                <p>Sin hojas de Excel sueltas ni cuadernos: ventas, caja, stock y personal, con reportes que entiendes.</p>
            </div>
            <div class="row g-4">
                @foreach ($funciones as [$icono, $titulo, $texto])
                    <div class="col-sm-6 col-lg-3">
                        <div class="caja"><div class="icono"><i class="fas {{ $icono }}"></i></div><h3>{{ $titulo }}</h3><p>{{ $texto }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Cómo empezar --}}
    <section class="bloque bloque-gris">
        <div class="container">
            <div class="titulo">
                <span class="etiqueta">Empieza hoy</span>
                <h2>Tu sistema listo en 3 pasos</h2>
            </div>
            <div class="row g-4 text-center">
                @foreach ([
                    ['Escríbenos', 'Cuéntanos de tu negocio y te mostramos el sistema funcionando con tu rubro.'],
                    ['Carga tus productos', 'Uno por uno, desde Excel o con la ayuda de la IA. Configuramos tus impresoras contigo.'],
                    ['Empieza a vender', 'Emite tus boletas y facturas. Te acompañamos en el día a día.'],
                ] as [$titulo, $texto])
                    <div class="col-md-4">
                        <div class="paso-num">{{ $loop->iteration }}</div>
                        <h3 class="fs-5">{{ $titulo }}</h3>
                        <p class="text-muted mb-0">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Preguntas frecuentes --}}
    <section id="preguntas" class="bloque bloque-claro">
        <div class="container" style="max-width:860px">
            <div class="titulo">
                <span class="etiqueta">Preguntas frecuentes</span>
                <h2>¿Tienes dudas?</h2>
            </div>
            @foreach ($preguntas as [$pregunta, $respuesta])
                <details class="pregunta" @if ($loop->first) open @endif>
                    <summary>{{ $pregunta }}</summary>
                    <p>{{ $respuesta }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Llamada final y contacto --}}
    <section class="bloque bloque-gris pt-0">
        <div class="container">
            <div class="llamada">
                <h2 class="mb-3" style="font-size:clamp(1.7rem,3.2vw,2.5rem)">¿Listo para ordenar tu negocio?</h2>
                <p class="mb-4" style="color:rgba(255,255,255,.85)">Escríbenos y te mostramos {{ $nombre }} funcionando con tu tipo de negocio.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    @if ($demo)
                        <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-light btn-lg rounded-pill fw-bold px-4"><i class="fab fa-whatsapp text-success me-2"></i>Hablar por WhatsApp</a>
                    @endif
                    <a href="#ingresar" class="btn btn-hueco btn-lg rounded-pill fw-semibold px-4"><i class="fas fa-right-to-bracket me-2"></i>Ya tengo mi sistema</a>
                </div>
            </div>
        </div>
    </section>

    @if (config('soporte.telefono') || config('soporte.correo') || config('soporte.direccion'))
        <section class="bloque bloque-oscuro" style="padding:3.5rem 0">
            <div class="container">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5">
                        <span class="etiqueta">Contacto</span>
                        <h2 class="mt-2 mb-0" style="font-size:clamp(1.7rem,3vw,2.3rem)">Conversemos sobre tu negocio</h2>
                    </div>
                    <div class="col-lg-7">
                        <div class="row">
                            @if (config('soporte.telefono'))
                                <div class="col-md-6"><div class="contacto-dato"><i class="fab fa-whatsapp"></i><div><b>Celular / WhatsApp</b><br>
                                    @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">{{ config('soporte.telefono') }}</a>@else{{ config('soporte.telefono') }}@endif</div></div></div>
                            @endif
                            @if (config('soporte.correo'))
                                <div class="col-md-6"><div class="contacto-dato"><i class="fas fa-envelope"></i><div><b>Correo</b><br><a href="mailto:{{ config('soporte.correo') }}">{{ config('soporte.correo') }}</a></div></div></div>
                            @endif
                            @if (config('soporte.direccion'))
                                <div class="col-12"><div class="contacto-dato"><i class="fas fa-location-dot"></i><div><b>Dirección</b><br>{{ config('soporte.direccion') }}</div></div></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        // Pestañas de rubros
        document.querySelectorAll('.rubro-btn').forEach((boton) => boton.addEventListener('click', () => {
            document.querySelectorAll('.rubro-btn, .rubro-panel').forEach((el) => el.classList.remove('activo'));
            boton.classList.add('activo');
            document.getElementById('rubro-' + boton.dataset.rubro).classList.add('activo');
        }));
    </script>
@endpush
