@php
    // $ciudad: la portada para una ciudad con partner (SitioController::ciudad), con sus textos y su contacto
    $ciudad ??= null;
    // Pedido de la maqueta y del ticket: platos de la zona
    $platos = $ciudad && $ciudad['slug'] === 'piura'
        ? [['2', 'Seco de chabelo', 'SECO DE CHABELO', 50.00], ['1', 'Ceviche de mero', 'CEVICHE DE MERO', 35.00], ['1', 'Majarisco', 'MAJARISCO', 38.00], ['1', 'Jarra de clarito', 'JARRA DE CLARITO', 14.00]]
        : [['2', 'Ceviche de doncella', 'CEVICHE DE DONCELLA', 56.00], ['1', 'Juane de gallina', 'JUANE DE GALLINA', 22.00], ['2', 'Tacacho con cecina', 'TACACHO C/CECINA', 44.00], ['1', 'Jarra de aguaje', 'JARRA DE AGUAJE', 15.00]];
    $totalPlatos = number_format(array_sum(array_column($platos, 3)), 2);
    // Lo que hace el sistema en cada rubro (también lo usa la portada de las marcas)
    $rubros = \App\Support\RubrosWeb::lista();
    $whatsapp = 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode('Hola, quiero información sobre el sistema TUSHPA'.($ciudad ? ' en '.$ciudad['nombre'] : '').'.');

    // Preguntas frecuentes: se muestran abajo y también salen en los datos estructurados (mismo texto en ambos)
    $preguntas = [
        ['¿Tengo que instalar algo?', 'No. TUSHPA funciona desde el navegador en tu PC, tablet o celular, con tu propia dirección (por ejemplo, tunegocio.'.config('tenancy.dominio').'). Si quieres, lo instalas como app con un clic.'],
        ['¿Qué pasa al terminar los '.config('sitio.dias_prueba').' días gratis?', 'Te avisamos antes de que termine. Para seguir usándolo activas tu plan; tus datos se mantienen. Si no lo activas, el sistema se suspende.'],
        ['¿Puedo emitir boletas y facturas reales durante la prueba?', 'Durante la prueba los comprobantes van a SUNAT en modo de prueba, para que aprendas sin riesgo. Al activar tu plan y subir tu certificado digital pasas a producción.'],
        ['¿Mis datos están separados de los de otras empresas?', 'Sí. Cada empresa tiene su propia base de datos y sus propios usuarios, cada uno con los permisos que tú le des.'],
        ['¿Necesito internet?', 'Sí, el sistema trabaja en línea para enviar tus comprobantes a SUNAT y tener tus datos al día en todos tus equipos.'],
        ['¿Me ayudan a configurarlo?', 'Sí. Te acompañamos por WhatsApp para cargar tus productos, configurar tus impresoras y empezar a facturar.'],
    ];

    // SEO: título (hasta ~60 caracteres), descripción (hasta ~155), dirección canónica y datos estructurados
    $seoTitulo = 'Sistema de Facturación Electrónica SUNAT | TUSHPA';
    $seoDescripcion = 'Facturación electrónica SUNAT con asistente IA, punto de venta, inventario y caja para restaurantes, hoteles y comercios. Hecho en Iquitos. Prueba gratis '.config('sitio.dias_prueba').' días.';
    $seoUrl = \App\Support\SeoSitio::url('/');
    if ($ciudad) {
        // Las preguntas de la ciudad primero (su texto propio es lo que Google posiciona)
        $preguntas = array_merge($ciudad['preguntas'], array_slice($preguntas, 0, 3));
        $seoTitulo = $ciudad['titulo'];
        $seoDescripcion = $ciudad['descripcion'];
        $seoUrl = $ciudad['url'];
    }
    $seoJson = \App\Support\SeoSitio::json(array_merge([
        \App\Support\SeoSitio::organizacion(),
        \App\Support\SeoSitio::sitioWeb(),
        \App\Support\SeoSitio::aplicacion($planes, $seoDescripcion),
    ], $ciudad ? [\App\Support\SeoSitio::servicio($ciudad), \App\Support\SeoSitio::migas([['Inicio', \App\Support\SeoSitio::url('/')], [$ciudad['h1'], $ciudad['url']]])] : [], [
        \App\Support\SeoSitio::preguntas($preguntas),
    ]));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('sitio.partials.seo')
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Íconos: se cargan sin frenar el primer dibujo de la página --}}
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
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
@include('sitio.partials.menu')

{{-- Portada --}}
<section id="inicio" class="portada">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <svg class="hoja hoja-1"><use href="#hoja"/></svg>
    <svg class="hoja hoja-2"><use href="#hoja"/></svg>
    <svg class="hoja hoja-3"><use href="#hoja"/></svg>

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                @if ($ciudad)
                    <span class="etiqueta"><i class="fas fa-location-dot me-1"></i>{{ $ciudad['nombre'] }} · te atendemos en persona y por WhatsApp</span>
                    <h1 class="mt-3 mb-4">{{ \Illuminate\Support\Str::beforeLast($ciudad['h1'], ' en ') }} <span class="resalte">en {{ $ciudad['nombre'] }}</span></h1>
                    <p class="bajada mb-4">
                        {{ $ciudad['bajada'] }}
                        Con <b>asistente de inteligencia artificial</b> que te ayuda a vender, comprar y crear tus productos.
                    </p>
                @else
                    <span class="etiqueta">Hecho en la Amazonía · para todo el Perú</span>
                    <h1 class="mt-3 mb-4">Factura a SUNAT y controla tu negocio <span class="resalte">desde un solo sistema</span></h1>
                    <p class="bajada mb-4">
                        Comandas, punto de venta, hotel, inventario, caja y boletas electrónicas.
                        Con <b>asistente de inteligencia artificial</b> que te ayuda a vender, comprar y crear tus productos.
                        Funciona en tu PC, tablet y celular, sin instalar nada.
                    </p>
                @endif
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-rocket me-2"></i>Prueba gratis {{ config('sitio.dias_prueba') }} días</a>
                    <button type="button" class="btn btn-hueco btn-lg" data-bs-toggle="modal" data-bs-target="#modalDemo"><i class="fas fa-circle-play me-2"></i>Ver demo</button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="chip-portada"><i class="fas fa-circle-check" style="color:#ffd700"></i>Sin tarjeta</span>
                    <span class="chip-portada"><i class="fas fa-bolt" style="color:#ffd700"></i>Listo en segundos</span>
                    <span class="chip-portada"><i class="fas fa-wand-magic-sparkles" style="color:#ffd700"></i>Con inteligencia artificial</span>
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
                            @foreach ($platos as [$cantidad, $plato, , $importe])
                                <div class="maqueta-linea"><span>{{ $cantidad }} × {{ $plato }}</span><b>S/ {{ number_format($importe, 2) }}</b></div>
                            @endforeach
                            <div class="maqueta-total"><span>Total</span><span>S/ {{ $totalPlatos }}</span></div>
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
        <span><i class="fas fa-store"></i>{{ count($rubros) }} rubros de negocio</span>
        <span><i class="fas fa-wand-magic-sparkles"></i>Asistente con IA</span>
        <span><i class="fas fa-mobile-screen"></i>PC, tablet y celular</span>
        <span><i class="fas fa-database"></i>Base de datos propia por empresa</span>
        <span><i class="fas fa-location-dot"></i>{{ $ciudad ? 'Atención en '.$ciudad['nombre'] : 'Desde Iquitos, Loreto' }}</span>
    </div>
</div>

@if ($ciudad)
    {{-- Para los negocios de la ciudad: sus textos y quién atiende --}}
    <section id="negocios" class="seccion">
        <div class="container">
            <div class="seccion-titulo revelar">
                <span class="etiqueta"><i class="fas fa-location-dot me-1"></i>{{ $ciudad['nombre'] }}, {{ $ciudad['region'] }}</span>
                <h2>Un sistema para los negocios de {{ $ciudad['nombre'] }}</h2>
            </div>
            <div class="row g-5 align-items-center">
                <div class="col-lg-7 revelar">
                    @foreach ($ciudad['parrafos'] as $parrafo)
                        <p class="texto-gris fs-5 {{ $loop->last ? 'mb-4' : 'mb-3' }}">{{ $parrafo }}</p>
                    @endforeach
                    <div class="row g-3">
                        @foreach ($ciudad['rubros'] as [$icono, $nombre, $texto])
                            <div class="col-sm-6">
                                <div class="funcion"><i class="fas {{ $icono }}"></i><div><b>{{ $nombre }}</b><span>{{ $texto }}</span></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-5 revelar">
                    <div class="tarjeta p-4" style="height:auto">
                        <div class="icono fondo-selva"><i class="fas fa-user-tie"></i></div>
                        <h3 class="fs-4 fw-bold mb-2">{{ $ciudad['atencion']['titulo'] }}</h3>
                        <p class="mb-3">Te atiende <b>{{ $ciudad['contacto']['nombre'] }}</b>. Te acompaña a cargar tus productos, configurar tu impresora y emitir tu primer comprobante.</p>
                        <ul class="list-unstyled mb-4 small">
                            <li class="mb-2"><i class="fas fa-location-dot me-2" style="color:var(--selva-500)"></i>{{ $ciudad['contacto']['direccion'] }}</li>
                            <li class="mb-2"><i class="fab fa-whatsapp me-2" style="color:#25d366"></i>{{ collect($ciudad['contacto']['telefonos'])->implode(' · ') }}</li>
                            @foreach ($ciudad['contacto']['correos'] ?? [] as $correo)
                                <li class="mb-2"><i class="fas fa-envelope me-2" style="color:var(--selva-500)"></i><a href="mailto:{{ $correo }}" class="text-reset">{{ $correo }}</a></li>
                            @endforeach
                        </ul>
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-selva w-100"><i class="fab fa-whatsapp me-2"></i>Escribir a {{ \Illuminate\Support\Str::before($ciudad['contacto']['nombre'], ' ') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- Inteligencia artificial: lo que la IA hace dentro del sistema --}}
<section id="ia" class="seccion seccion-ia">
    <div class="container">
        <div class="seccion-titulo revelar">
            <span class="etiqueta"><i class="fas fa-wand-magic-sparkles me-1"></i>Inteligencia artificial incluida</span>
            <h2>Tu negocio con un <span class="resalte-ia">asistente IA</span> que trabaja contigo</h2>
            <p>Habla o toma una foto y la IA hace el trabajo pesado. Tú solo revisas y aceptas.</p>
        </div>
        @php
            $funcionesIa = [
                ['fa-microphone', 'Vende hablando', 'En el Punto Venta IA dices "2 jabones y una Inca Kola, paga con Yape" y la venta se arma sola.'],
                ['fa-file-invoice-dollar', 'Ingresa tus compras', 'Sube la foto, el PDF o el XML de la factura de tu proveedor y la compra se llena sola.'],
                ['fa-box-open', 'Crea tus productos', 'Díctalos o súbelos con foto: nombre, precio, stock y presentaciones en segundos.'],
                ['fa-boxes-stacked', 'Cuenta tu inventario', 'Toma una foto a las cajas o a la ruma y la IA te dice cuántas hay.'],
                ['fa-comments', 'Pregúntale lo que quieras', '"¿Cuánto vendí hoy?", "¿Qué se está acabando?", "¿Quién me debe?": te responde al instante.'],
                ['fa-chart-column', 'Reportes y gráficos', 'Pídele un reporte o un gráfico de tus ventas y te lo muestra ahí mismo.'],
            ];
        @endphp
        <div class="row g-4">
            @foreach ($funcionesIa as [$icono, $titulo, $texto])
                <div class="col-sm-6 col-lg-4 revelar">
                    <div class="tarjeta tarjeta-ia">
                        <div class="icono"><i class="fas {{ $icono }}"></i></div>
                        <h4>{{ $titulo }}</h4>
                        <p>{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-5 revelar">
            <a href="{{ route('sitio.registro') }}" class="btn btn-achiote btn-lg"><i class="fas fa-wand-magic-sparkles me-2"></i>Pruébalo gratis con IA</a>
        </div>
    </div>
</section>

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
                    <div class="c"><b>TU NEGOCIO S.A.C.</b><br>RUC 20XXXXXXXXX<br>{{ $ciudad ? 'Av. Grau 123 · '.$ciudad['nombre'] : 'Jr. Próspero 123 · Iquitos' }}</div>
                    <hr>
                    <div class="c"><b>BOLETA DE VENTA ELECTRÓNICA</b><br>B001-00245</div>
                    <hr>
                    @foreach ($platos as [$cantidad, , $platoTicket, $importe])
                        <div class="d-flex justify-content-between"><span>{{ $cantidad }} {{ $platoTicket }}</span><span>{{ number_format($importe, 2) }}</span></div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between"><b>TOTAL S/</b><b>{{ $totalPlatos }}</b></div>
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
@include('sitio.partials.certificados')

{{-- Programa reseller: invita a vender TUSHPA (la página explica los beneficios; los precios, por WhatsApp). No va en la página de un partner --}}
@unless ($ciudad)
<section class="seccion pt-0">
    <div class="container">
        <div class="llamada revelar text-start">
            <div class="kene kene-laberinto"></div>
            <div class="row align-items-center g-4 position-relative">
                <div class="col-lg-8">
                    <span class="etiqueta" style="color: var(--huito)">Programa reseller</span>
                    <h2 class="mt-2 mb-2" style="font-size:clamp(1.6rem,3vw,2.3rem)">¿Quieres ganar vendiendo sistemas de facturación?</h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.8)">Te damos tu propio panel para crear a tus clientes. Nosotros ponemos el sistema, los servidores y el soporte: tú solo dedícate a vender.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('sitio.reseller') }}" class="btn btn-achiote btn-lg"><i class="fas fa-handshake me-2"></i>Quiero ser reseller</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endunless

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
            @foreach ($preguntas as [$pregunta, $respuesta])
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
                @if (! empty($contactoPartner))
                    {{-- Visita desde la región de un partner (ej. Piura): sus datos --}}
                    <div class="contacto-dato"><i class="fas fa-user-tie"></i><div><b>Te atiende</b><br>{{ $contactoPartner['nombre'] }}</div></div>
                    <div class="contacto-dato"><i class="fas fa-location-dot"></i><div><b>Dirección</b><br>{{ $contactoPartner['direccion'] }}</div></div>
                    <div class="contacto-dato"><i class="fab fa-whatsapp"></i><div><b>Celular / WhatsApp</b><br>{{ collect($contactoPartner['telefonos'])->map(fn ($t) => '+51 '.$t)->implode(' · ') }}</div></div>
                @else
                    <div class="contacto-dato"><i class="fas fa-location-dot"></i><div><b>Dirección</b><br>Jr. Raúl Pillco Pérez 285, Iquitos, Perú</div></div>
                    <div class="contacto-dato"><i class="fab fa-whatsapp"></i><div><b>Celular / WhatsApp</b><br>+51 928 396 147 · +51 964 382 212</div></div>
                @endif
                <div class="contacto-dato"><i class="fas fa-envelope"></i><div><b>Correo</b><br>@foreach (config('soporte.correos_web') as $correo)<a href="mailto:{{ $correo }}" class="text-reset">{{ $correo }}</a>@if (! $loop->last)<br>@endif @endforeach</div></div>
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

{{-- Pie y botón de WhatsApp (incluye los enlaces a las páginas por ciudad) --}}
@include('sitio.partials.pie')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
@include('sitio.partials.extras')
</body>
</html>
