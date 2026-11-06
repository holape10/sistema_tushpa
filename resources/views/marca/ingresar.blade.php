{{--
    Portada del dominio de una marca: presenta el sistema con su nombre y sus colores, y la empresa entra escribiendo su
    RUC o el nombre de su sistema. Todo sale de la marca (nombre, colores, WhatsApp): sirve igual para cualquier revendedor.
--}}
@extends('marca.base')
@section('titulo', 'Sistema de facturación y ventas')
@section('robots', 'index, follow')

@php
    $nombre = config('marca.nombre');
    $wa = config('soporte.whatsapp');
    $demo = $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode("Hola, quiero conocer el sistema {$nombre} para mi negocio.") : null;
    $funciones = [
        ['fa-file-invoice', 'Facturación electrónica', 'Boletas, facturas y notas enviadas a SUNAT al instante, con su PDF y XML.'],
        ['fa-cash-register', 'Ventas y caja', 'Vende en segundos desde la PC, tablet o celular, y cierra tu caja sin descuadres.'],
        ['fa-boxes-stacked', 'Inventario y kardex', 'Stock en tiempo real, compras, almacenes y alertas cuando algo se acaba.'],
        ['fa-calculator', 'Contabilidad y SIRE', 'Registro de compras y ventas, propuestas del SIRE y reportes para tu contador.'],
        ['fa-chart-line', 'Reportes claros', 'Cuánto vendes, qué se vende más y cuánto ganas, sin hojas de cálculo.'],
        ['fa-headset', 'Soporte cercano', 'Te acompañamos en la instalación, la capacitación y el día a día.'],
    ];
    $rubros = [['fa-utensils', 'Restaurantes'], ['fa-store', 'Comercios'], ['fa-pills', 'Farmacias'], ['fa-screwdriver-wrench', 'Ferreterías'],
        ['fa-gas-pump', 'Grifos'], ['fa-hotel', 'Hoteles'], ['fa-briefcase', 'Estudios contables'], ['fa-shirt', 'Lavanderías']];
@endphp

@push('cabecera')
    <style>
        .hero h1 { font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
        .chip { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .25); color: #fff;
            border-radius: 999px; padding: .35rem .8rem; font-size: .85rem; font-weight: 600; }
        .seccion { background: #f8fafc; }
        .funcion { border: 0; border-radius: 1.1rem; box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .35); height: 100%; transition: transform .15s; }
        .funcion:hover { transform: translateY(-3px); }
        .icono { width: 48px; height: 48px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem;
            background: linear-gradient(135deg, var(--marca), var(--marca2)); }
        .rubro { background: #fff; border-radius: 1rem; padding: .9rem .5rem; text-align: center; font-size: .85rem; font-weight: 600; color: #334155;
            box-shadow: 0 6px 18px -14px rgba(15, 23, 42, .4); }
        .rubro i { display: block; font-size: 1.3rem; margin-bottom: .35rem; color: var(--marca); }
        .franja { background: linear-gradient(135deg, var(--marca), var(--marca2)); border-radius: 1.5rem; }
    </style>
@endpush

@section('contenido')
    <div class="row align-items-center g-4 g-lg-5 hero py-3 py-lg-5">
        <div class="col-lg-7 text-white order-2 order-lg-1">
            <span class="chip mb-3"><i class="fas fa-circle-check"></i> Conectado con SUNAT</span>
            <h1 class="display-5 mb-3">El sistema que ordena tu negocio: factura, vende y controla todo en un solo lugar</h1>
            <p class="lead text-white-50 mb-4" style="color: rgba(255,255,255,.85) !important">Con <b>{{ $nombre }}</b> emites tus comprobantes electrónicos,
                manejas tu caja y tu inventario, y ves tus reportes desde la computadora, la tablet o el celular. Sin instalar nada.</p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="chip"><i class="fas fa-cloud"></i> 100 % en la nube</span>
                <span class="chip"><i class="fas fa-mobile-screen"></i> PC, tablet y celular</span>
                <span class="chip"><i class="fas fa-shield-halved"></i> Copias de seguridad diarias</span>
            </div>
            @if ($demo)
                <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-light btn-lg rounded-pill fw-bold px-4"><i class="fab fa-whatsapp text-success me-2"></i>Pide tu demostración</a>
            @endif
        </div>
        {{-- En el celular, primero el ingreso (quien ya tiene su sistema no tiene que bajar) --}}
        <div class="col-lg-5 order-1 order-lg-2">
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
    <section class="seccion py-5">
        <div class="container py-lg-3">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Todo lo que necesita tu negocio</h2>
                <p class="text-muted mb-0">Un solo sistema, sin complicaciones, pensado para negocios del Perú.</p>
            </div>
            <div class="row g-4">
                @foreach ($funciones as [$icono, $titulo, $texto])
                    <div class="col-sm-6 col-lg-4">
                        <div class="card funcion p-4">
                            <span class="icono mb-3"><i class="fas {{ $icono }}"></i></span>
                            <h3 class="h5 fw-bold">{{ $titulo }}</h3>
                            <p class="text-muted mb-0">{{ $texto }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-5 mb-4">
                <h2 class="h4 fw-bold">Para todo tipo de negocio</h2>
            </div>
            <div class="row g-3 justify-content-center">
                @foreach ($rubros as [$icono, $texto])
                    <div class="col-6 col-sm-4 col-md-3 col-lg"><div class="rubro"><i class="fas {{ $icono }}"></i>{{ $texto }}</div></div>
                @endforeach
            </div>

            @if ($demo)
                <div class="franja text-white text-center p-4 p-md-5 mt-5">
                    <h2 class="fw-bold mb-2">¿Listo para ordenar tu negocio?</h2>
                    <p class="mb-4" style="color: rgba(255,255,255,.85)">Escríbenos y te mostramos el sistema funcionando con tu tipo de negocio.</p>
                    <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-light btn-lg rounded-pill fw-bold px-4"><i class="fab fa-whatsapp text-success me-2"></i>{{ config('soporte.telefono') }}</a>
                </div>
            @endif
        </div>
    </section>
@endsection
