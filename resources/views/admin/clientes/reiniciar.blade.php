@extends('admin.layout')
@section('title', 'Dejar en cero')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-500 hover:text-indigo-700">← Clientes</a>
        <h1 class="text-xl font-bold mt-1">Dejar en cero · {{ $cliente->razon_social }}</h1>
        <p class="text-sm text-slate-500">RUC {{ $cliente->ruc }} · base {{ $cliente->base_datos }}</p>
    </div>

    <section class="rounded-2xl bg-amber-50 border border-amber-200 p-5 text-sm text-amber-900 space-y-2">
        <p class="font-bold">Úsalo solo cuando termine la etapa de pruebas, antes de que la empresa empiece a trabajar de verdad.</p>
        <p>Se borran todos los movimientos, el stock queda en 0 y los correlativos empiezan desde 1. Antes se guarda un <b>respaldo completo</b> (en Respaldos) por si hay que volver atrás.</p>
    </section>

    <section class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-bold text-slate-700 mb-3">🗑 Se borra</h2>
        <div class="divide-y text-sm">
            @foreach ($previa['grupos'] as $grupo => $cantidad)
                <div class="flex justify-between py-2"><span>{{ $grupo }}</span><span class="font-bold {{ $cantidad ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($cantidad) }} registros</span></div>
            @endforeach
            <div class="flex justify-between py-2"><span>Stock de productos</span><span class="font-bold text-rose-600">{{ number_format($previa['stock']) }} productos con stock → 0</span></div>
            <div class="flex justify-between py-2"><span>Mesas y habitaciones</span><span class="font-bold text-slate-600">quedan libres</span></div>
            <div class="flex justify-between py-2"><span>Puntos de clientes (fidelización)</span><span class="font-bold text-slate-600">a 0</span></div>
        </div>
    </section>

    <section class="bg-white rounded-2xl shadow-sm p-5 text-sm">
        <h2 class="font-bold text-slate-700 mb-2">🔢 Correlativos</h2>
        @if ($previa['reinicia_electronicos'])
            <p class="text-emerald-700">Todos empiezan desde 1: facturas, boletas, notas de crédito y débito, guías, notas de venta y proformas.</p>
            @if (! $previa['produccion'])
                <p class="mt-1 text-slate-500">La empresa está en modo de pruebas de SUNAT (beta): lo emitido no cuenta para SUNAT.</p>
            @endif
        @else
            <p class="text-amber-800"><b>Las series electrónicas NO se reinician:</b> la empresa está en producción y ya envió {{ $previa['sunat'] }} comprobante(s) a SUNAT.
                Si se repitieran esos números, SUNAT los rechazaría. Facturas, boletas, notas y guías siguen su numeración; notas de venta y proformas empiezan desde 1.</p>
            <p class="mt-1 text-slate-500">Si de todas formas quieres que las boletas empiecen en 1, usa una serie nueva (ej. B002) en la configuración de la sucursal.</p>
        @endif
    </section>

    <section class="bg-white rounded-2xl shadow-sm p-5 text-sm">
        <h2 class="font-bold text-slate-700 mb-2">✅ No se toca</h2>
        <p class="text-slate-600">Usuarios y permisos, productos, precios, categorías, recetas, combos, presentaciones, mesas y pisos, impresoras y asignaciones, clientes, proveedores,
            motorizados, medios de pago, almacenes, la configuración de la empresa y de las sucursales.</p>
    </section>

    <form method="POST" action="{{ route('admin.clientes.reiniciar.confirmar', $cliente) }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-3"
          x-data="{ ruc: '', enviando: false }" @submit="enviando = true">
        @csrf
        <label class="block text-sm font-semibold text-slate-700">Para confirmar escribe el RUC <span class="font-mono">{{ $cliente->ruc }}</span>
            <input name="confirmacion" x-model="ruc" autocomplete="off" inputmode="numeric" class="mt-1 w-full rounded-xl border-slate-300 font-mono text-lg"></label>
        <button :disabled="ruc !== @js($cliente->ruc) || enviando"
                class="w-full h-12 rounded-xl bg-rose-600 text-white font-bold disabled:opacity-40"
                x-text="enviando ? 'Respaldando y dejando en cero… (no cierres esta página)' : 'Dejar en cero {{ $cliente->nombre_comercial ?: $cliente->razon_social }}'"></button>
    </form>
</div>
@endsection
