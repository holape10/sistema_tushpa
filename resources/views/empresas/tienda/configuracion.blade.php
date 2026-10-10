@extends('layouts.app')
@section('title', 'Tienda Virtual')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    @include('empresas.partials.alert')

    @unless ($permitida)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 p-5">
            <p class="font-bold">Tu plan actual no incluye tienda virtual.</p>
            <p class="text-sm mt-1">Comunícate con soporte para cambiarte a un plan que la incluya.</p>
        </div>
    @endunless

    <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white p-5 sm:p-6 shadow-sm relative isolate overflow-hidden">
        <x-kene-adorno />
        <div class="flex flex-wrap items-center gap-4">
            <span class="text-4xl">🛍️</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs uppercase tracking-wider text-indigo-200">Tu tienda en internet</p>
                <a href="{{ $url }}" target="_blank" class="text-lg sm:text-xl font-extrabold break-all hover:underline">{{ $url }}</a>
                <p class="text-sm text-indigo-100 mt-1">Comparte este enlace con tus clientes (WhatsApp, Facebook, Instagram).</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $negocio->tienda_activa ? 'bg-emerald-400 text-emerald-950' : 'bg-white/20' }}">{{ $negocio->tienda_activa ? 'PUBLICADA' : 'NO PUBLICADA' }}</span>
        </div>
    </section>

    <div class="grid sm:grid-cols-3 gap-3">
        <a href="{{ route('proformas.index', ['estado' => 'PENDIENTE']) }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
            <p class="text-xs font-semibold text-gray-500 uppercase">Pedidos web por atender</p>
            <p class="text-3xl font-extrabold text-indigo-700">{{ $pedidos }}</p>
            <p class="text-xs text-gray-400">Llegan como proformas "Tienda virtual": cóbralas como cualquier proforma.</p>
        </a>
        <a href="{{ route('productos.index') }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
            <p class="text-xs font-semibold text-gray-500 uppercase">Productos con imagen</p>
            <p class="text-3xl font-extrabold text-gray-800">{{ $conImagen }} <span class="text-base text-gray-400">/ {{ $totalProductos }}</span></p>
            <p class="text-xs text-gray-400">Las fotos hacen que vendas más. Súbelas en Productos.</p>
        </a>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Acceso de tus clientes</p>
            <p class="text-sm text-gray-600 mt-1">Usuario: su <strong>DNI o RUC</strong>. Si ya te compró, su primera contraseña es su mismo DNI/RUC y luego la cambia. Los nuevos crean su cuenta.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('tienda.config.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
        @csrf
        <label class="flex items-start gap-3 p-4 rounded-xl border border-indigo-200 bg-indigo-50 cursor-pointer">
            <input type="checkbox" name="tienda_activa" value="1" @checked($negocio->tienda_activa) @disabled(!$permitida) class="mt-0.5 rounded text-indigo-600">
            <span><span class="font-bold text-indigo-900">Publicar la tienda virtual</span>
                <span class="block text-sm text-indigo-700">Muestra los productos activos de esta sucursal, con su precio vigente (incluye precios dinámicos) y su stock.</span></span>
        </label>
        <label class="flex items-center gap-3 text-sm">
            <input type="checkbox" name="tienda_mostrar_agotados" value="1" @checked($negocio->tienda_mostrar_agotados) class="rounded">
            Mostrar también los productos agotados (no se pueden pedir)
        </label>
        <label class="block text-sm font-medium text-gray-700">WhatsApp de la tienda
            <input name="tienda_whatsapp" value="{{ old('tienda_whatsapp', $negocio->tienda_whatsapp) }}" maxlength="20" placeholder="987654321"
                   class="mt-1 w-full sm:w-72 h-11 rounded-xl border-gray-300 text-sm">
            <span class="block text-xs text-gray-400">Al enviar su pedido, el cliente puede avisarte por WhatsApp con un clic.</span>
        </label>
        <label class="block text-sm font-medium text-gray-700">Mensaje para tus clientes
            <textarea name="tienda_mensaje" rows="4" maxlength="2000" placeholder="Ej.: Yape/Plin al 987654321 a nombre de ... · Delivery gratis desde S/ 50 · Atendemos de 8am a 8pm"
                      class="mt-1 w-full rounded-xl border-gray-300 text-sm">{{ old('tienda_mensaje', $negocio->tienda_mensaje) }}</textarea>
            <span class="block text-xs text-gray-400">Se muestra al cliente antes de enviar su pedido (formas de pago, delivery, horarios).</span>
        </label>
        <div class="flex justify-end">
            <button @disabled(!$permitida) class="px-6 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50">Guardar</button>
        </div>
    </form>
</div>
@endsection
