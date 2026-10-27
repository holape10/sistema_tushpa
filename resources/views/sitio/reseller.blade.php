@php
    // Programa de revendedores: se vende con el panel admin.tushpa.app (usuarios del panel que crean sus propias empresas).
    // Los precios no se publican: todo lleva al WhatsApp.
    $wa = 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode('Hola, quiero ser reseller de TUSHPA. ¿Me cuentan los precios y cómo empiezo?');
    $pasos = [
        ['fa-handshake', 'Te unes al equipo', 'Conversamos por WhatsApp, te contamos los precios para reseller y te damos tu acceso al panel de administrador en tushpa.app.'],
        ['fa-store', 'Vendes y creas a tus clientes', 'Desde tu panel creas la empresa de tu cliente en minutos: su RUC, su rubro y su propia dirección web, lista para facturar.'],
        ['fa-sack-dollar', 'Cobras y creces', 'Tus clientes te pagan a ti. Mientras más vendes, más ganas cada mes, sin preocuparte por la parte técnica.'],
    ];
    $beneficios = [
        ['fa-user-shield', 'Tu propio panel de administrador', 'Ves solo a tus clientes: cuándo vencen, cuántos comprobantes emiten y si están al día con SUNAT.'],
        ['fa-bolt', 'Clientes listos en minutos', 'Cada empresa sale con su dirección propia (ej. tunegocio.tushpa.app) con https y el menú de su rubro ya armado.'],
        ['fa-qrcode', 'Cobras directo', 'Pones tu QR y tu cuenta en el panel. Si un cliente no paga, le envías un aviso o lo suspendes con un clic.'],
        ['fa-code', 'Cero programación', 'Servidores, actualizaciones, cambios de SUNAT y mejoras van por nuestra cuenta. Tú no tocas una sola línea de código.'],
        ['fa-file-invoice', 'Facturación electrónica completa', 'Facturas, boletas, notas de crédito y débito, guías de remisión y resumen diario, conectados con SUNAT.'],
        ['fa-layer-group', 'Para cualquier negocio', 'Restaurantes, comercios, ferreterías, farmacias, grifos, hoteles, gimnasios, clubes y más: un solo sistema para todos.'],
        ['fa-database', 'Copias de seguridad diarias', 'La información de tus clientes se respalda sola cada noche. Puedes venderles tranquilidad.'],
        ['fa-headset', 'Soporte y capacitación', 'Te enseñamos a usar y vender el sistema. Tutoriales por rubro y soporte técnico para ti y tus clientes.'],
    ];
    // Marca blanca: a pedido, para resellers con volumen (se implementa con el caso real de cada reseller)
    $marcaBlanca = [
        ['fa-image', 'Tu logo y tu nombre', 'En el ingreso al sistema, en el menú y en los comprobantes de tus clientes.'],
        ['fa-globe', 'Tu propio dominio', 'Tus clientes entran por cliente.tumarca.pe, con certificado https.'],
        ['fa-brands fa-whatsapp', 'Tu WhatsApp y tus datos', 'La ayuda, los avisos de pago y el pie de los comprobantes llevan tu contacto.'],
        ['fa-server', 'Mismo sistema, cero mantenimiento', 'Sigue funcionando en nuestros servidores: cada mejora le llega también a tu marca.'],
    ];
    $condiciones = [
        'Disponible para resellers con un mínimo de clientes activos (lo conversamos según tu caso).',
        'Un pago único de configuración y un monto mensual por la marca.',
        'El dominio lo compras y renuevas tú; te guiamos para apuntarlo a nuestros servidores.',
        'Se firma un acuerdo simple: precios, soporte y qué pasa con tus clientes si un día dejas de trabajar con nosotros.',
        'Tus clientes siguen facturando con su propio RUC y certificado digital: para SUNAT no cambia nada.',
    ];
    $perfiles = [['fa-calculator', 'Contadores'], ['fa-laptop-code', 'Técnicos en computación'], ['fa-bullhorn', 'Vendedores y asesores'], ['fa-building', 'Agencias y emprendedores']];
    $preguntas = [
        ['¿Necesito saber programar?', 'No. El sistema ya está hecho y lo mantenemos nosotros. Tú vendes, creas a tus clientes desde tu panel y los acompañas.'],
        ['¿Cuánto cuesta y cuánto gano?', 'Los precios para reseller los conversamos directamente según cuántos clientes piensas vender. Escríbenos por WhatsApp al '.config('soporte.telefono').'.'],
        ['¿Quién da el soporte técnico?', 'Nosotros: el sistema, los servidores y SUNAT. Tú atiendes a tu cliente en lo comercial y, si algo técnico se complica, nos lo pasas.'],
        ['¿Puedo vender en cualquier ciudad?', 'Sí. TUSHPA funciona en la nube: tus clientes entran desde la PC, la tablet o el celular, estén donde estén.'],
        ['¿Mis clientes necesitan instalar algo?', 'No. Solo internet. Para imprimir en ticketeras hay un agente que se instala con un clic.'],
        ['¿Puedo vender con mi propia marca?', 'Sí, con la marca blanca: tu logo, tu dominio y tu contacto, a pedido para resellers con volumen. Se cobra aparte (configuración y un monto mensual); consúltalo por WhatsApp.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller TUSHPA: vende sistemas de facturación sin programar</title>
    <meta name="description" content="Gana vendiendo el sistema de facturación electrónica TUSHPA. Te damos tu propio panel para crear a tus clientes; nosotros ponemos el sistema, los servidores y el soporte.">
    <link rel="canonical" href="{{ route('sitio.reseller') }}">
    <meta property="og:title" content="Sé reseller de TUSHPA">
    <meta property="og:description" content="Vende sistemas de facturación electrónica con tu propio panel, sin tocar una línea de código.">
    <meta property="og:image" content="{{ asset('web/img/logo_tushpa.webp') }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" href="{{ asset('web/img/favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('web/css/sitio.css') }}?v={{ filemtime(public_path('web/css/sitio.css')) }}">
    <style>
        .rs-hero { padding: 8rem 0 5rem; }
        .rs-hero .lead-rs { font-size: 1.15rem; max-width: 640px; color: rgba(255, 255, 255, .85); }
        .rs-chip { display: inline-flex; align-items: center; gap: .45rem; background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .2); color: #fff; border-radius: 999px; padding: .45rem 1rem; font-weight: 600; font-size: .9rem; }
        .rs-paso { background: #fff; border-radius: 1.25rem; padding: 1.75rem 1.5rem; height: 100%; position: relative; box-shadow: 0 18px 40px -28px rgba(15, 23, 42, .5); }
        .rs-paso .num { position: absolute; top: -18px; left: 1.5rem; width: 40px; height: 40px; border-radius: 50%; background: var(--selva-600); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 18px -8px var(--selva-600); }
        .rs-ico { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; background: rgba(37, 99, 235, .1); color: var(--selva-600); margin-bottom: 1rem; }
        .rs-beneficio { background: #fff; border-radius: 1.25rem; padding: 1.5rem; height: 100%; border: 1px solid rgba(30, 41, 59, .07); transition: transform .2s, box-shadow .2s; }
        .rs-beneficio:hover { transform: translateY(-4px); box-shadow: 0 22px 45px -24px rgba(15, 23, 42, .45); }
        .rs-beneficio h3 { font-size: 1.08rem; font-weight: 700; margin-bottom: .4rem; }
        .rs-lado { border-radius: 1.5rem; padding: 2rem; height: 100%; }
        .rs-lado li { margin-bottom: .65rem; display: flex; gap: .6rem; }
        .rs-lado li i { margin-top: .25rem; }
        .rs-perfil { background: #fff; border-radius: 999px; padding: .7rem 1.3rem; font-weight: 600; display: inline-flex; align-items: center; gap: .55rem; box-shadow: 0 10px 24px -18px rgba(15, 23, 42, .6); }
        .rs-perfil i { color: var(--selva-500); }
    </style>
</head>
<body>
@include('sitio.partials.menu', ['enPortada' => false])

<section class="portada rs-hero">
    <div class="kene kene-laberinto kene-fondo" style="--k:1.4"></div>
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="etiqueta" style="color: var(--huito)">Programa reseller</span>
                <h1 class="fw-bold mt-2 mb-3">Vende sistemas de facturación y <span class="resalte">gana cada mes</span></h1>
                <p class="lead-rs mb-4">Sin tocar una sola línea de código. Déjanos el sistema, los servidores y SUNAT a nosotros: <b>tú solo dedícate a vender</b>.
                    Te damos tu propio panel de administrador en tushpa.app para que crees y gestiones a tus clientes.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-achiote btn-lg"><i class="fab fa-whatsapp me-2"></i>Quiero ser reseller</a>
                    <a href="#como-funciona" class="btn btn-lg" style="background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.3)">¿Cómo funciona?</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-wrap gap-2">
                    <span class="rs-chip"><i class="fas fa-user-shield"></i>Tu propio panel</span>
                    <span class="rs-chip"><i class="fas fa-code"></i>Cero programación</span>
                    <span class="rs-chip"><i class="fas fa-file-invoice"></i>Facturación SUNAT</span>
                    <span class="rs-chip"><i class="fas fa-qrcode"></i>Cobras directo</span>
                    <span class="rs-chip"><i class="fas fa-cloud"></i>100% en la nube</span>
                    <span class="rs-chip"><i class="fas fa-headset"></i>Soporte técnico incluido</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion seccion-arena" id="como-funciona">
    <div class="container">
        <div class="seccion-titulo">
            <span class="etiqueta">Cómo funciona</span>
            <h2>Empieza a vender en 3 pasos</h2>
        </div>
        <div class="row g-4 pt-3">
            @foreach ($pasos as [$icono, $titulo, $texto])
                <div class="col-md-4">
                    <div class="rs-paso">
                        <span class="num">{{ $loop->iteration }}</span>
                        <div class="rs-ico mt-2"><i class="fas {{ $icono }}"></i></div>
                        <h3 class="h5 fw-bold">{{ $titulo }}</h3>
                        <p class="texto-gris mb-0">{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="seccion">
    <div class="container">
        <div class="seccion-titulo">
            <span class="etiqueta">Beneficios</span>
            <h2>Todo lo que necesitas para vender, ya hecho</h2>
        </div>
        <div class="row g-4">
            @foreach ($beneficios as [$icono, $titulo, $texto])
                <div class="col-sm-6 col-lg-3">
                    <div class="rs-beneficio">
                        <div class="rs-ico"><i class="fas {{ $icono }}"></i></div>
                        <h3>{{ $titulo }}</h3>
                        <p class="texto-gris small mb-0">{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="seccion seccion-arena">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="rs-lado h-100" style="background: linear-gradient(135deg, var(--selva-600), var(--rio)); color: #fff">
                    <h2 class="h3 fw-bold mb-3"><i class="fas fa-shield-halved me-2"></i>Déjanos todo a nosotros</h2>
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-check"></i>El sistema completo y sus actualizaciones.</li>
                        <li><i class="fas fa-check"></i>Servidores, seguridad y copias de seguridad diarias.</li>
                        <li><i class="fas fa-check"></i>La conexión con SUNAT y sus cambios.</li>
                        <li><i class="fas fa-check"></i>El soporte técnico del sistema.</li>
                        <li><i class="fas fa-check"></i>Tutoriales y capacitación para ti y tus clientes.</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="rs-lado h-100 bg-white" style="border: 2px dashed rgba(37, 99, 235, .35)">
                    <h2 class="h3 fw-bold mb-3"><i class="fas fa-rocket me-2" style="color: var(--achiote, #ea580c)"></i>Tú solo dedícate a vender</h2>
                    <ul class="list-unstyled mb-0 texto-gris">
                        <li><i class="fas fa-circle-check" style="color: var(--selva-500)"></i>Consigue a tus clientes en tu ciudad o en todo el Perú.</li>
                        <li><i class="fas fa-circle-check" style="color: var(--selva-500)"></i>Crea su empresa desde tu panel en minutos.</li>
                        <li><i class="fas fa-circle-check" style="color: var(--selva-500)"></i>Cobra cada mes con tu propio QR o cuenta.</li>
                        <li><i class="fas fa-circle-check" style="color: var(--selva-500)"></i>Atiende la relación comercial con tu cliente.</li>
                        <li><i class="fas fa-circle-check" style="color: var(--selva-500)"></i>Haz crecer tu cartera mes a mes.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="fw-semibold mb-3">Ideal para:</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                @foreach ($perfiles as [$icono, $nombre])<span class="rs-perfil"><i class="fas {{ $icono }}"></i>{{ $nombre }}</span>@endforeach
            </div>
        </div>
    </div>
</section>

<section class="seccion" id="marca-blanca">
    <div class="container">
        <div class="seccion-titulo">
            <span class="etiqueta">Marca blanca</span>
            <h2>¿Vendes mucho? Véndelo con <span style="color: var(--selva-500)">tu propia marca</span></h2>
            <p class="texto-gris mx-auto" style="max-width: 640px">Para resellers con una cartera grande armamos el sistema con tu identidad: tus clientes verán tu marca, no la nuestra.</p>
        </div>
        <div class="row g-4 mb-4">
            @foreach ($marcaBlanca as [$icono, $titulo, $texto])
                <div class="col-sm-6 col-lg-3">
                    <div class="rs-beneficio">
                        <div class="rs-ico" style="background: rgba(118, 75, 162, .12); color: var(--rio)"><i class="{{ str_starts_with($icono, 'fa-brands') ? $icono : 'fas '.$icono }}"></i></div>
                        <h3>{{ $titulo }}</h3>
                        <p class="texto-gris small mb-0">{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="rs-lado" style="background: var(--arena-2); border: 1px solid rgba(118, 75, 162, .2)">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h3 class="h5 fw-bold mb-3"><i class="fas fa-file-signature me-2" style="color: var(--rio)"></i>Condiciones de la marca blanca</h3>
                    <ul class="list-unstyled mb-0 texto-gris">
                        @foreach ($condiciones as $c)<li><i class="fas fa-circle-check" style="color: var(--rio)"></i>{{ $c }}</li>@endforeach
                    </ul>
                </div>
                <div class="col-lg-4 text-lg-center">
                    <p class="small texto-gris mb-2">Se implementa a pedido, con tu logo y tu dominio.</p>
                    <a href="https://wa.me/{{ config('soporte.whatsapp') }}?text={{ rawurlencode('Hola, soy reseller (o quiero serlo) y me interesa la marca blanca de TUSHPA. ¿Me cuentan las condiciones?') }}"
                       target="_blank" rel="noopener" class="btn btn-lg text-white" style="background: var(--rio)"><i class="fab fa-whatsapp me-2"></i>Consultar marca blanca</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion seccion-arena">
    <div class="container">
        <div class="seccion-titulo">
            <span class="etiqueta">Preguntas frecuentes</span>
            <h2>Lo que nos suelen preguntar</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="preguntasReseller">
                    @foreach ($preguntas as [$pregunta, $respuesta])
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pr{{ $loop->index }}">{{ $pregunta }}</button>
                            </h3>
                            <div id="pr{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#preguntasReseller">
                                <div class="accordion-body">{{ $respuesta }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion seccion-oscura text-center">
    <div class="container">
        <h2 class="fw-bold mb-3">¿Listo para empezar a vender?</h2>
        <p class="mb-4" style="color: rgba(255,255,255,.75)">Escríbenos y te contamos los precios para reseller y cómo empezar hoy mismo.</p>
        <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-achiote btn-lg"><i class="fab fa-whatsapp me-2"></i>Consultar precios al {{ config('soporte.telefono') }}</a>
    </div>
</section>

@include('sitio.partials.pie')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('web/js/sitio.js') }}?v={{ filemtime(public_path('web/js/sitio.js')) }}"></script>
@include('sitio.partials.extras')
</body>
</html>
