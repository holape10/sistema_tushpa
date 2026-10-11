@php
    // Lo que hace el sistema en cada rubro (solo funciones que existen en TUSHPA)
    $rubros = [
        'restaurante' => ['icono' => 'fa-utensils', 'nombre' => 'Restaurantes', 'ejemplos' => 'Restaurantes, cevicherías, cafeterías, bares y discotecas',
            'texto' => 'Del pedido en la mesa a la boleta, sin papelitos: el mozo toma la comanda en el celular y la cocina la ve al instante.',
            'funciones' => [
                ['fa-mobile-screen', 'Comandas en celular o tablet', 'El mozo envía el pedido y la cocina o el bar lo imprime o lo ve en pantalla.'],
                ['fa-table-cells', 'Mesas por piso o zona', 'Pasar a otra mesa, juntar mesas para grupos y precuenta.'],
                ['fa-fire-burner', 'Pantalla de cocina', 'Pedidos por preparar y aviso al mozo cuando el plato está listo.'],
                ['fa-qrcode', 'Carta digital con QR', 'Tus clientes ven la carta con fotos y precios desde su celular.'],
                ['fa-scale-balanced', 'Recetas y food cost', 'Cuánto te cuesta cada plato, insumos que descuenta y mermas.'],
                ['fa-motorcycle', 'Delivery y para llevar', 'Pedidos con motorizado, reservas y cuentas separadas.'],
            ]],
        'hotel' => ['icono' => 'fa-bed', 'nombre' => 'Hoteles', 'ejemplos' => 'Hoteles, hospedajes y hostales (con o sin restaurante)',
            'texto' => 'Controla cada habitación con su tiempo, sus consumos y su cobro, y recibe alertas antes de que venza la estadía.',
            'funciones' => [
                ['fa-door-open', 'Habitaciones en tiempo real', 'Libre, ocupada o en limpieza, con el tiempo que le queda a cada una.'],
                ['fa-calendar-check', 'Reservas por fecha', 'El sistema avisa si una reserva choca con una estadía.'],
                ['fa-bell-concierge', 'Consumos a la habitación', 'Agrega productos o servicios y cobra todo junto al salir.'],
                ['fa-right-left', 'Cambio de habitación', 'Mueve al huésped con todos sus consumos.'],
                ['fa-chart-line', 'Reporte con gráficos', 'Ocupación, ventas por habitación y bitácora de cambios.'],
                ['fa-user-shield', 'Control del administrador', 'Quitar consumos o cambiar precios pide autorización.'],
            ]],
        'comercio' => ['icono' => 'fa-store', 'nombre' => 'Comercios', 'ejemplos' => 'Bodegas, minimarkets, ferreterías, distribuidoras y tiendas',
            'texto' => 'Vende rápido desde la caja o desde el celular, con tu stock siempre al día en todos tus almacenes.',
            'funciones' => [
                ['fa-cash-register', 'Punto de venta táctil', 'Vende con clics o con lector de código de barras.'],
                ['fa-mobile', 'Vende desde el celular', 'Punto de venta móvil para vender en ruta o en el mostrador.'],
                ['fa-boxes-stacked', 'Inventario y kardex', 'Varios almacenes, transferencias, inventarios físicos y stock mínimo.'],
                ['fa-file-signature', 'Proformas', 'Cotiza y conviértela en venta con un clic.'],
                ['fa-truck', 'Guías de remisión', 'Guías electrónicas para tus traslados y envíos.'],
                ['fa-hand-holding-dollar', 'Créditos y cobranzas', 'Ventas al crédito con cuentas por cobrar y por pagar.'],
            ]],
        'farmacia' => ['icono' => 'fa-prescription-bottle-medical', 'nombre' => 'Farmacias', 'ejemplos' => 'Farmacias y boticas',
            'texto' => 'Vende por lote y controla los vencimientos para no perder mercadería.',
            'funciones' => [
                ['fa-pills', 'Punto de venta de farmacia', 'Pensado para atender rápido en mostrador.'],
                ['fa-calendar-xmark', 'Lotes y vencimientos', 'Cada lote con su fecha y alertas de lo que está por vencer.'],
                ['fa-boxes-stacked', 'Kardex por producto', 'Entradas, salidas y stock de cada medicamento.'],
                ['fa-file-excel', 'Carga desde Excel', 'Sube tu catálogo de productos de una sola vez.'],
            ]],
        'grifo' => ['icono' => 'fa-gas-pump', 'nombre' => 'Grifos', 'ejemplos' => 'Grifos y estaciones de servicio',
            'texto' => 'Un punto de venta hecho para la isla: rápido, con créditos para tus clientes frecuentes.',
            'funciones' => [
                ['fa-gas-pump', 'Punto de venta de grifo', 'Vende combustible y productos en segundos.'],
                ['fa-users', 'Clientes al crédito', 'Cuentas por cobrar y reporte por cliente.'],
                ['fa-truck', 'Guías de remisión', 'Para tus despachos.'],
                ['fa-chart-column', 'Reportes de ventas', 'Por turno, producto y forma de pago.'],
            ]],
        'gimnasio' => ['icono' => 'fa-dumbbell', 'nombre' => 'Gimnasios', 'ejemplos' => 'Gimnasios y academias',
            'texto' => 'Membresías, cobros y control de ingreso para que nadie entre con el plan vencido.',
            'funciones' => [
                ['fa-id-card', 'Membresías y planes', 'Mensuales, trimestrales o como los vendas.'],
                ['fa-door-closed', 'Control de ingreso', 'Valida en la puerta si el socio está al día.'],
                ['fa-person-running', 'Pantalla del entrenador', 'Sus alumnos y sus rutinas.'],
                ['fa-cash-register', 'Venta de productos', 'Suplementos y bebidas con su boleta.'],
            ]],
        'estacionamiento' => ['icono' => 'fa-square-parking', 'nombre' => 'Estacionamientos', 'ejemplos' => 'Playas de estacionamiento, cocheras y valet parking',
            'texto' => 'Registra entradas y salidas, cobra por tiempo y administra a tus abonados.',
            'funciones' => [
                ['fa-ticket', 'Tickets de entrada y salida', 'Cobro automático según el tiempo.'],
                ['fa-users', 'Abonados mensuales', 'Clientes fijos con su vencimiento.'],
                ['fa-qrcode', 'Valet parking con QR', 'El cliente pide su vehículo desde su celular.'],
                ['fa-chart-line', 'Reporte de ingresos', 'Por día, por turno y por tipo de vehículo.'],
            ]],
        'club' => ['icono' => 'fa-people-group', 'nombre' => 'Clubes', 'ejemplos' => 'Clubes, asociaciones y colegios profesionales',
            'texto' => 'Socios, cuotas y carnet digital, con un portal donde cada socio ve su estado de cuenta.',
            'funciones' => [
                ['fa-address-card', 'Padrón de socios', 'Datos, familiares y estado de cada socio.'],
                ['fa-coins', 'Cuotas y pagos', 'Cobros con su comprobante y deudas al día.'],
                ['fa-qrcode', 'Carnet con QR', 'En la portería se verifica si está al día.'],
                ['fa-user-check', 'Portal del socio', 'Cada socio ve sus pagos y su carnet.'],
            ]],
        'clinica' => ['icono' => 'fa-stethoscope', 'nombre' => 'Clínicas', 'ejemplos' => 'Clínicas, consultorios y odontología',
            'texto' => 'La historia del paciente, sus citas y su cobro en un solo lugar.',
            'funciones' => [
                ['fa-notes-medical', 'Historia clínica', 'Atenciones de cada paciente, lista para imprimir.'],
                ['fa-calendar-days', 'Agenda de citas', 'Organiza los turnos de cada profesional.'],
                ['fa-prescription', 'Recetas y consentimientos', 'Se generan e imprimen desde la atención.'],
                ['fa-cash-register', 'Cobro con comprobante', 'Boleta o factura de cada atención.'],
            ]],
    ];
    $whatsapp = 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode('Hola, quiero información sobre el sistema TUSHPA.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TUSHPA · Sistema de facturación electrónica y gestión para tu negocio | Hecho en la Amazonía</title>
    <meta name="description" content="Sistema de facturación electrónica SUNAT y gestión para restaurantes, hoteles, comercios, farmacias, grifos, gimnasios, estacionamientos, clubes y clínicas. Pruébalo gratis {{ config('sitio.dias_prueba') }} días.">
    <meta property="og:title" content="TUSHPA · Facturación electrónica y gestión para tu negocio">
    <meta property="og:description" content="Comandas, punto de venta, hotel, inventario y boletas SUNAT en un solo sistema. Prueba gratis {{ config('sitio.dias_prueba') }} días.">
    <meta property="og:image" content="{{ asset('web/img/logo_tushpa.webp') }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('web/css/sitio.css') }}?v={{ filemtime(public_path('web/css/sitio.css')) }}">
</head>
<body>
{{-- Hoja (se reutiliza en las decoraciones) --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="hoja" viewBox="0 0 200 100">
        <path d="M2 50 C40 2 150 -8 198 50 C150 108 40 98 2 50 Z" fill="currentColor"/>
        <path d="M2 50 H190 M60 50 L95 22 M60 50 L95 78 M110 50 L140 26 M110 50 L140 74 M150 50 L170 34 M150 50 L170 66" stroke="#000" stroke-opacity=".25" stroke-width="3" fill="none"/>
    </symbol>
</svg>

{{-- Menú --}}
<header class="menu">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#inicio"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="44"></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="#rubros">Rubros</a></li>
                    <li class="nav-item"><a class="nav-link" href="#facturacion">Facturación</a></li>
                    <li class="nav-item"><a class="nav-link" href="#funciones">Funciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="#planes">Planes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#clientes">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contacto">Contacto</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.tutorial') }}"><i class="fas fa-graduation-cap me-1"></i>Tutoriales</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sitio.ingresar') }}"><i class="fas fa-right-to-bracket me-1"></i>Ingresar</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-selva" href="{{ route('sitio.registro') }}">Prueba gratis</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

{{-- Portada --}}
<section id="inicio" class="portada">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <svg class="hoja hoja-1"><use href="#hoja"/></svg>
    <svg class="hoja hoja-2"><use href="#hoja"/></svg>
    <svg class="hoja hoja-3"><use href="#hoja"/></svg>

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="etiqueta">Hecho en la Amazonía · para todo el Perú</span>
                <h1 class="mt-3 mb-4">Factura a SUNAT y controla tu negocio <span class="resalte">desde un solo sistema</span></h1>
                <p class="bajada mb-4">
                    Comandas, punto de venta, hotel, inventario, caja y boletas electrónicas.
                    Funciona en tu PC, tablet y celular, sin instalar nada.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis {{ config('sitio.dias_prueba') }} días</a>
                    <a href="#rubros" class="btn btn-hueco btn-lg">Ver qué hace</a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="chip-portada"><i class="fas fa-circle-check" style="color:#ffd700"></i>Sin tarjeta</span>
                    <span class="chip-portada"><i class="fas fa-bolt" style="color:#ffd700"></i>Listo en segundos</span>
                    <span class="chip-portada"><i class="fas fa-headset" style="color:#ffd700"></i>Soporte por WhatsApp</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="position-relative mx-auto" style="max-width:470px">
                    <div class="flotante flotante-1"><i class="fas fa-check" style="background:#16a34a"></i>Boleta aceptada por SUNAT</div>
                    <div class="flotante flotante-2"><i class="fab fa-whatsapp" style="background:#25d366"></i>Enviada al cliente</div>
                    <div class="maqueta">
                        <div class="maqueta-barra"><span></span><span></span><span></span><b>mirestaurante.{{ config('tenancy.dominio') }}</b></div>
                        <div class="maqueta-cuerpo">
                            <div class="maqueta-mesas">
                                <div>MESA 01</div><div class="ocupada">MESA 02</div><div class="junta">MESA 03</div><div>MESA 04</div>
                            </div>
                            <div class="small fw-bold text-uppercase texto-gris mb-1">Mesa 02 · Piso 01</div>
                            <div class="maqueta-linea"><span>2 × Ceviche de doncella</span><b>S/ 56.00</b></div>
                            <div class="maqueta-linea"><span>1 × Juane de gallina</span><b>S/ 22.00</b></div>
                            <div class="maqueta-linea"><span>2 × Tacacho con cecina</span><b>S/ 44.00</b></div>
                            <div class="maqueta-linea"><span>1 × Jarra de aguaje</span><b>S/ 15.00</b></div>
                            <div class="maqueta-total"><span>Total</span><span>S/ 137.00</span></div>
                            <div class="maqueta-sunat"><i class="fas fa-circle-check"></i>Boleta B001-00245 · ACEPTADA</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="rio-borde">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none"><path d="M0 40 C240 70 420 10 720 34 C1000 56 1180 14 1440 36 V70 H0 Z" fill="#0f1544"/></svg>
    </div>
</section>

{{-- Confianza --}}
<div class="confianza">
    <div class="container d-flex flex-wrap justify-content-center gap-4 text-center">
        <span><i class="fas fa-file-invoice"></i>Facturación electrónica SUNAT</span>
        <span><i class="fas fa-store"></i>9 rubros de negocio</span>
        <span><i class="fas fa-mobile-screen"></i>PC, tablet y celular</span>
        <span><i class="fas fa-database"></i>Base de datos propia por empresa</span>
        <span><i class="fas fa-location-dot"></i>Desde Iquitos, Loreto</span>
    </div>
</div>

{{-- Rubros --}}
<section id="rubros" class="seccion">
    <div class="container">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">Un sistema para cada negocio</span>
            <h2>Elige tu rubro y mira lo que hace por ti</h2>
            <p>Al crear tu cuenta, el sistema arranca con el menú de tu tipo de negocio. Todos incluyen facturación electrónica, caja, productos e inventario.</p>
        </div>
        <div class="rubros-lista revelar">
            @foreach ($rubros as $clave => $r)
                <button type="button" class="rubro-btn {{ $loop->first ? 'activo' : '' }}" data-rubro="{{ $clave }}"><i class="fas {{ $r['icono'] }}"></i>{{ $r['nombre'] }}</button>
            @endforeach
        </div>
        @foreach ($rubros as $clave => $r)
            <div class="rubro-panel {{ $loop->first ? 'activo' : '' }}" id="rubro-{{ $clave }}">
                <div class="row g-0">
                    <div class="col-lg-4">
                        <div class="rubro-lado">
                            <div class="kene kene-rombos"></div>
                            <div class="icono-grande"><i class="fas {{ $r['icono'] }}"></i></div>
                            <h3>{{ $r['nombre'] }}</h3>
                            <p class="small mb-3">{{ $r['ejemplos'] }}</p>
                            <p class="mb-4">{{ $r['texto'] }}</p>
                            <a href="{{ route('sitio.registro') }}" class="btn btn-achiote">Probar gratis</a>
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
<section id="facturacion" class="seccion seccion-oscura">
    <div class="kene kene-cruces position-absolute top-0 end-0 h-100 w-50" style="color:rgba(255,255,255,.05);z-index:-1"></div>
    <svg class="hoja" style="width:300px;left:-120px;top:40px;transform:rotate(-20deg)"><use href="#hoja"/></svg>
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 revelar">
                <span class="etiqueta">Facturación electrónica</span>
                <h2 class="mt-3 mb-4" style="font-size:clamp(1.8rem,3.4vw,2.6rem)">Tus comprobantes van a SUNAT solos, y tu cliente los recibe al instante</h2>
                <ul class="lista-check">
                    <li><i class="fas fa-circle-check"></i><span><b>Boletas, facturas, notas de crédito y débito</b> y notas de venta.</span></li>
                    <li><i class="fas fa-circle-check"></i><span><b>Guías de remisión electrónicas</b> para tus traslados.</span></li>
                    <li><i class="fas fa-circle-check"></i><span><b>Resúmenes diarios y anulaciones</b> desde el mismo sistema.</span></li>
                    <li><i class="fas fa-circle-check"></i><span><b>Envía el comprobante por WhatsApp</b> o imprímelo en ticket o en A4.</span></li>
                    <li><i class="fas fa-circle-check"></i><span><b>Tu propia página de consulta:</b> tus clientes descargan su PDF, XML y CDR en <i>tuempresa.{{ config('tenancy.dominio') }}/cpe</i>.</span></li>
                    <li><i class="fas fa-circle-check"></i><span><b>Emisión masiva</b> de comprobantes y reportes listos para tu contador (SIRE y CONCAR).</span></li>
                </ul>
            </div>
            <div class="col-lg-6 revelar">
                <div class="ticket">
                    <div class="c"><b>TU NEGOCIO S.A.C.</b><br>RUC 20XXXXXXXXX<br>Jr. Próspero 123 · Iquitos</div>
                    <hr>
                    <div class="c"><b>BOLETA DE VENTA ELECTRÓNICA</b><br>B001-00245</div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>2 CEVICHE DE DONCELLA</span><span>56.00</span></div>
                    <div class="d-flex justify-content-between"><span>1 JUANE DE GALLINA</span><span>22.00</span></div>
                    <div class="d-flex justify-content-between"><span>2 TACACHO C/CECINA</span><span>44.00</span></div>
                    <div class="d-flex justify-content-between"><span>1 JARRA DE AGUAJE</span><span>15.00</span></div>
                    <hr>
                    <div class="d-flex justify-content-between"><b>TOTAL S/</b><b>137.00</b></div>
                    <div class="qr"></div>
                    <div class="c" style="font-size:.7rem">Consulta tu comprobante en:<br><b>tunegocio.{{ config('tenancy.dominio') }}/cpe</b></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Funciones generales --}}
<section id="funciones" class="seccion seccion-arena">
    <div class="container">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">Todo en un solo lugar</span>
            <h2>Lo que tu negocio necesita para crecer ordenado</h2>
            <p>Sin hojas de Excel sueltas ni cuadernos: ventas, caja, stock y personal, con reportes que entiendes.</p>
        </div>
        @php
            $funciones = [
                ['fa-cash-register', 'fondo-selva', 'Caja y turnos', 'Apertura y cierre de caja por usuario, con lo vendido por forma de pago.'],
                ['fa-boxes-stacked', 'fondo-rio', 'Inventario y kardex', 'Varios almacenes, transferencias, inventarios físicos y alertas de stock bajo.'],
                ['fa-cart-shopping', 'fondo-achiote', 'Compras y gastos', 'Registra lo que compras y gastas para saber cuánto ganas de verdad.'],
                ['fa-hand-holding-dollar', 'fondo-huito', 'Cuentas por cobrar y pagar', 'Créditos de clientes y deudas con proveedores al día.'],
                ['fa-gift', 'fondo-morado', 'Fidelización', 'Puntos y premios para que tus clientes vuelvan.'],
                ['fa-bag-shopping', 'fondo-marca', 'Tienda virtual', 'Tu catálogo en línea para que te pidan por internet.'],
                ['fa-chart-pie', 'fondo-selva', 'Reportes claros', 'Ventas por día, producto, vendedor y rentabilidad.'],
                ['fa-code-branch', 'fondo-rio', 'Varias sucursales', 'Cada local con su caja, almacén y series de comprobantes.'],
                ['fa-user-lock', 'fondo-achiote', 'Usuarios y permisos', 'Cada trabajador ve solo las opciones que le das.'],
                ['fa-calculator', 'fondo-huito', 'Contabilidad y planilla', 'Libros, SIRE, CONCAR, planilla y boletas de pago.'],
                ['fa-fingerprint', 'fondo-morado', 'Asistencia del personal', 'Marcación con QR desde el celular y tareo mensual.'],
                ['fa-file-excel', 'fondo-marca', 'Carga desde Excel', 'Sube tus productos de una vez y empieza a vender.'],
            ];
        @endphp
        <div class="row g-4">
            @foreach ($funciones as [$icono, $color, $titulo, $texto])
                <div class="col-sm-6 col-lg-3 revelar">
                    <div class="tarjeta">
                        <div class="icono {{ $color }}"><i class="fas {{ $icono }}"></i></div>
                        <h4>{{ $titulo }}</h4>
                        <p>{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-5 revelar">
            <span class="chip-portada" style="background:#fff;color:var(--tinta);border-color:rgba(30,41,59,.1)">
                <i class="fas fa-mobile-screen-button" style="color:var(--selva-500)"></i>Instálalo como app en tu celular o PC con un clic
            </span>
        </div>
    </div>
</section>

{{-- Cómo empezar --}}
<section class="seccion">
    <div class="container">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">Empieza hoy</span>
            <h2>Tu sistema listo en 3 pasos</h2>
        </div>
        <div class="row g-4 text-center">
            @foreach ([
                ['Regístrate con tu RUC', 'Traemos tus datos de SUNAT y eliges tu rubro y la dirección de tu sistema.'],
                ['Carga tus productos', 'Uno por uno o todos desde Excel. Configura tus mesas, habitaciones o almacenes.'],
                ['Empieza a vender', 'Emite tus boletas y facturas. Te acompañamos por WhatsApp en todo momento.'],
            ] as [$titulo, $texto])
                <div class="col-md-4 revelar">
                    <div class="paso-num">{{ $loop->iteration }}</div>
                    <h4 class="fs-5">{{ $titulo }}</h4>
                    <p class="texto-gris mb-0">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-5 revelar">
            <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Crear mi sistema gratis</a>
        </div>
    </div>
    <div class="kene kene-franja franja position-absolute bottom-0 start-0" style="color:rgba(37,99,235,.22)"></div>
</section>

@include('sitio.partials.planes')

{{-- Clientes --}}
<section id="clientes" class="seccion overflow-hidden">
    <div class="seccion-titulo revelar">
        <span class="etiqueta">Nuestros clientes</span>
        <h2>Empresas que confían en nosotros</h2>
        <p>Más de 50 empresas nos respaldan</p>
    </div>
    @include('sitio.partials.clientes')
</section>

{{-- Preguntas frecuentes --}}
<section class="seccion seccion-arena">
    <div class="container" style="max-width:860px">
        <div class="seccion-titulo revelar">
            <span class="etiqueta">Preguntas frecuentes</span>
            <h2>¿Tienes dudas?</h2>
        </div>
        <div class="accordion revelar" id="preguntas">
            @foreach ([
                ['¿Tengo que instalar algo?', 'No. TUSHPA funciona desde el navegador en tu PC, tablet o celular, con tu propia dirección (por ejemplo, tunegocio.'.config('tenancy.dominio').'). Si quieres, lo instalas como app con un clic.'],
                ['¿Qué pasa al terminar los '.config('sitio.dias_prueba').' días gratis?', 'Te avisamos antes de que termine. Para seguir usándolo activas tu plan; tus datos se mantienen. Si no lo activas, el sistema se suspende.'],
                ['¿Puedo emitir boletas y facturas reales durante la prueba?', 'Durante la prueba los comprobantes van a SUNAT en modo de prueba, para que aprendas sin riesgo. Al activar tu plan y subir tu certificado digital pasas a producción.'],
                ['¿Mis datos están separados de los de otras empresas?', 'Sí. Cada empresa tiene su propia base de datos y sus propios usuarios, cada uno con los permisos que tú le des.'],
                ['¿Necesito internet?', 'Sí, el sistema trabaja en línea para enviar tus comprobantes a SUNAT y tener tus datos al día en todos tus equipos.'],
                ['¿Me ayudan a configurarlo?', 'Sí. Te acompañamos por WhatsApp para cargar tus productos, configurar tus impresoras y empezar a facturar.'],
            ] as [$pregunta, $respuesta])
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pregunta{{ $loop->index }}">{{ $pregunta }}</button>
                    </h3>
                    <div id="pregunta{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#preguntas">
                        <div class="accordion-body">{{ $respuesta }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Llamada final --}}
<section class="seccion pt-0 seccion-arena">
    <div class="container">
        <div class="llamada revelar">
            <div class="kene kene-laberinto"></div>
            <svg class="hoja" style="width:240px;right:-70px;bottom:-60px;transform:rotate(-30deg);opacity:.2"><use href="#hoja"/></svg>
            <h2 class="mb-3" style="font-size:clamp(1.7rem,3.2vw,2.5rem)">¿Listo para ordenar tu negocio?</h2>
            <p class="mb-4" style="color:rgba(255,255,255,.8)">Crea tu sistema ahora y úsalo gratis {{ config('sitio.dias_prueba') }} días. Sin tarjeta.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis</a>
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-hueco btn-lg"><i class="fab fa-whatsapp me-2"></i>Hablar por WhatsApp</a>
            </div>
        </div>
    </div>
</section>

{{-- Contacto --}}
<section id="contacto" class="seccion seccion-oscura">
    <div class="kene kene-rombos position-absolute top-0 start-0 h-100 w-50" style="color:rgba(255,255,255,.04);z-index:-1"></div>
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 revelar">
                <span class="etiqueta">Contacto</span>
                <h2 class="mt-3 mb-3" style="font-size:clamp(1.8rem,3.2vw,2.5rem)">Conversemos sobre tu negocio</h2>
                <p class="mb-4" style="color:rgba(255,255,255,.75)">Te respondemos en menos de 24 horas. También hacemos <b>desarrollo de software a medida</b>.</p>
                <div class="contacto-dato"><i class="fas fa-location-dot"></i><div><b>Dirección</b><br>Jr. Raúl Pillco Pérez 285, Iquitos, Perú</div></div>
                <div class="contacto-dato"><i class="fab fa-whatsapp"></i><div><b>Celular / WhatsApp</b><br>+51 928 396 147 · +51 964 382 212</div></div>
                <div class="contacto-dato"><i class="fas fa-envelope"></i><div><b>Correo</b><br>{{ config('soporte.correo') }}</div></div>
                <div class="contacto-dato"><i class="fas fa-clock"></i><div><b>Atención</b><br>Lunes a viernes, 9:00 a. m. a 6:00 p. m.</div></div>
            </div>
            <div class="col-lg-7 revelar">
                <form id="contactForm" action="{{ route('sitio.contacto') }}" method="POST" class="formulario">
                    @csrf
                    <input type="text" name="web" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" required maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label" for="email">Correo *</label><input class="form-control" id="email" name="email" type="email" required maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label" for="telefono">Celular</label><input class="form-control" id="telefono" name="telefono" maxlength="30"></div>
                        <div class="col-md-6"><label class="form-label" for="empresa">Negocio</label><input class="form-control" id="empresa" name="empresa" maxlength="150"></div>
                        <div class="col-12">
                            <label class="form-label" for="servicio">Me interesa</label>
                            <select class="form-select" id="servicio" name="servicio">
                                <option value="">Elige una opción…</option>
                                @foreach ($rubros as $r)<option value="{{ $r['nombre'] }}">Sistema para {{ mb_strtolower($r['nombre']) }}</option>@endforeach
                                <option value="Facturación electrónica">Solo facturación electrónica</option>
                                <option value="Software a medida">Desarrollo de software a medida</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label" for="mensaje">Mensaje *</label><textarea class="form-control" id="mensaje" name="mensaje" rows="4" required maxlength="3000"></textarea></div>
                        <div class="col-12 d-grid"><button type="submit" class="btn btn-selva btn-lg"><i class="fas fa-paper-plane me-2"></i>Enviar mensaje</button></div>
                        <div class="col-12" id="formMessage"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- Pie --}}
<footer class="pie">
    <div class="kene kene-franja franja position-absolute top-0 start-0" style="color:rgba(165,180,252,.35)"></div>
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="bg-white d-inline-block rounded-3 px-3 py-2 mb-3"><img src="{{ asset('web/img/logo.webp') }}" alt="TUSHPA" height="38"></div>
                <p class="small mb-0">Sistema de facturación electrónica y gestión para negocios del Perú. Hecho con orgullo en la Amazonía.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h5>Sistema</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#rubros">Rubros</a></li>
                    <li class="mb-2"><a href="#funciones">Funciones</a></li>
                    <li class="mb-2"><a href="#planes">Planes</a></li>
                    <li class="mb-2"><a href="{{ route('sitio.tutorial') }}">Tutoriales</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h5>Clientes</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ route('sitio.ingresar') }}">Ingresar a mi sistema</a></li>
                    <li class="mb-2"><a href="{{ route('sitio.registro') }}">Prueba gratis</a></li>
                    <li class="mb-2"><a href="{{ $whatsapp }}" target="_blank" rel="noopener">Soporte por WhatsApp</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h5>Contacto</h5>
                <p class="small mb-1">+51 928 396 147</p>
                <p class="small mb-1">{{ config('soporte.correo') }}</p>
                <p class="small mb-0">Iquitos, Loreto · Perú</p>
            </div>
        </div>
        <hr class="my-4" style="border-color:rgba(255,255,255,.12)">
        <p class="small text-center mb-0">&copy; {{ date('Y') }} TUSHPA · {{ config('soporte.nombre') }}. Todos los derechos reservados.</p>
    </div>
</footer>

<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="whatsapp-flotante" aria-label="Escríbenos por WhatsApp"><i class="fab fa-whatsapp"></i></a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
</body>
</html>
