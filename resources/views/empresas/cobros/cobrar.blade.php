@php $directa = $directa ?? false; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $directa ? 'Punto de venta' : 'Cobrar' }} - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.cobros.partials.cobrar.estilos')
    @include('partials.pwa')
</head>
<body>
<div class="container-fluid">
    <div id="aviso_venta" class="alert alert-success text-center" style="display:none; margin-bottom:10px; font-size:14px;"></div>
    <iframe id="marco_impresion" style="display:none;" title="Impresión"></iframe>
    @if (session('success'))
        <div class="alert alert-success text-center" style="margin-bottom:10px;"><i class="fas fa-print"></i> {{ session('success') }}</div>
    @endif
    <div class="cobro-top">
        <a href="{{ $volver ?? route('comandas.seleccion') }}" class="top-btn">←</a>
        <h1>{{ $directa ? 'Punto de venta' : 'Cobrar' }}
            @unless ($directa)<small>{{ $mesa ? trim(($piso->pis_nom ?? '').' / '.$mesa->mes_nom, ' /') : strtoupper($pedido->ped_tip) }}</small>@endunless</h1>
        <span class="chip chip-verde">Turno N° {{ $turno->turno }} abierto</span>
        <label class="top-btn"><input type="checkbox" id="imprimir" checked> Imprimir</label>
        @if (! $directa)
            @if ($separadas)
                <a href="{{ route('cobros.cobrar', $pedido->ped_id) }}" class="top-btn">Cobrar todo junto</a>
            @else
                <a href="{{ route('cobros.separadas', $pedido->ped_id) }}" class="top-btn morado">👥 Cuentas separadas</a>
            @endif
        @endif
    </div>
    <div class="cobro-grid {{ $directa ? 'es-pv' : '' }}">
        @include('empresas.cobros.partials.cobrar.panel_cobro')
        @include('empresas.cobros.partials.cobrar.panel_comprobante')
        @include('empresas.cobros.partials.cobrar.panel_pago')
    </div>
</div>

@include('empresas.cobros.partials.cobrar.modal_cortesia')

<script>
    const SEPARADAS = {{ $separadas ? 'true' : 'false' }};
    const DIRECTA = {{ $directa ? 'true' : 'false' }};
    const TDOCOD_PRED = '{{ $negocio->tdocod_pred ?? '13' }}';
    let TOTAL = {{ $separadas ? 0 : number_format($total, 2, '.', '') }};
    const PED_ID = {{ $pedido->ped_id ?? 'null' }};
    let medios = [];
    const EXIGE_OP = {{ ($negocio->exigir_operacion ?? false) ? 'true' : 'false' }};
    // Descuentos y cortesías (se activan en la sucursal)
    const DSCTO = {{ $descuentos->descuentos ? 'true' : 'false' }};
    const CORTESIAS = {{ $descuentos->cortesias ? 'true' : 'false' }};
    const ES_ADMIN = {{ auth()->user()->esAdmin() ? 'true' : 'false' }};
    const MAX_CAJERO = {{ (float) $descuentos->max }};
    // Cupones, propina (aparte del comprobante) y pago en dólares (con el tipo de cambio de la sucursal)
    const CUPONES = {{ $descuentos->cupones ? 'true' : 'false' }};
    const PROPINA = {{ $descuentos->propina ? 'true' : 'false' }};
    const TC = {{ $descuentos->tipoCambio ?? 'null' }};
    let cupon = null;       // { codigo, tipo, valor } del cupón aplicado
    let BRUTO = TOTAL;      // lo que se cobra antes del descuento (sin las cortesías)
    let GRATIS = 0;         // valor de las cortesías
    const cortesias = {};   // cobrar mesa: { clave: { cantidad, motivo } }
    const PROMOS = @json($promos ?? []);   // promociones automáticas vigentes (2x1, 3x2...)
    let PROMOS_APLICADAS = [];              // [{ nombre, veces, ahorro }]

    const el = id => document.getElementById(id);
    const fmt = n => (Math.round(n * 100) / 100).toFixed(2);

    // Comprobante -> tipo de documento de identidad (como tu sistema viejo)
    el('tdocod').addEventListener('change', function () {
        if (this.value === '01') el('tdicod').value = '6';
        if (this.value === '03') el('tdicod').value = '1';
    });

    // Estado de pago: contado muestra medios; crédito muestra vencimiento
    el('estadopago').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        const contado = opt.dataset.tipo === 'CONTADO';
        el('bloque_medios').style.display = contado ? 'block' : 'none';
        el('div_fecVen').style.display = contado ? 'none' : 'block';

        if (!contado) {
            const dias = parseInt(opt.dataset.dias) || 1;
            const f = new Date(el('fecEmi').value + 'T00:00:00');
            f.setDate(f.getDate() + dias);
            el('fecVen').value = f.toISOString().slice(0, 10);
        }
    });

    @include('empresas.cobros.partials.cobrar.js_descuentos')
    @include('empresas.cobros.partials.cobrar.js_medios')
    @include('empresas.cobros.partials.cobrar.js_buscar_cliente')
    @include('empresas.cobros.partials.cobrar.js_cuentas_separadas')
    @include('empresas.cobros.partials.cobrar.js_cliente')
    @include('empresas.cobros.partials.cobrar.js_punto_venta')
    @include('empresas.cobros.partials.cobrar.js_registrar')
    @include('empresas.cobros.partials.cobrar.js_promociones')
</script>
@include('partials.avisos')
@include('partials.aviso_servicio')
<script src="{{ asset('js/fidelizacion-pos.js') }}?v={{ filemtime(public_path('js/fidelizacion-pos.js')) }}" data-previa="{{ route('fidelizacion.previa') }}" data-reservar="{{ route('fidelizacion.reservar') }}" data-csrf="{{ csrf_token() }}"></script>
</body>
</html>