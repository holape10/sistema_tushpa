@extends('layouts.app')
@section('title', 'Carta Digital QR')

@section('content')
@php
    $platos = collect($categorias)->sum(fn ($c) => count($c['productos']));
    $conFoto = collect($categorias)->flatMap(fn ($c) => $c['productos'])->whereNotNull('img')->count();
    $conDesc = collect($categorias)->flatMap(fn ($c) => $c['productos'])->filter(fn ($p) => $p['descripcion'])->count();
    $colores = ['#4f46e5' => 'Índigo TUSHPA', '#7c3aed' => 'Violeta', '#059669' => 'Verde selva', '#b45309' => 'Café', '#dc2626' => 'Rojo', '#0f766e' => 'Turquesa', '#be185d' => 'Fucsia', '#1e293b' => 'Negro'];
@endphp
<div class="max-w-5xl mx-auto space-y-5">
    @include('empresas.partials.alert')

    <section class="rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 text-white p-5 sm:p-7 shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center gap-6">
            <div class="bg-white rounded-2xl p-3 shadow-xl self-center shrink-0 [&_svg]:w-44 [&_svg]:h-44">{!! $qr !!}</div>
            <div class="flex-1 min-w-0">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $negocio->carta_activa ? 'bg-emerald-400 text-emerald-950' : 'bg-white/20' }}">
                    {{ $negocio->carta_activa ? '● PUBLICADA' : 'NO PUBLICADA' }}</span>
                <h1 class="text-2xl font-black mt-2">Carta digital con QR</h1>
                <p class="text-indigo-100 text-sm mt-1">Tus clientes escanean el QR con la cámara de su celular y ven la carta con precios siempre actualizados. Si cambias un precio en Productos, la carta cambia sola.</p>
                <a href="{{ $url }}" target="_blank" class="inline-block mt-3 font-mono text-sm bg-white/15 hover:bg-white/25 rounded-lg px-3 py-1.5 break-all">{{ $url }}</a>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a href="{{ $url }}" target="_blank" class="h-10 px-4 rounded-xl bg-white text-indigo-700 font-bold text-sm flex items-center gap-2">👁️ Ver la carta</a>
                    <a href="{{ route('carta.imprimir') }}" target="_blank" class="h-10 px-4 rounded-xl bg-white/15 hover:bg-white/25 font-bold text-sm flex items-center gap-2">🖨️ Imprimir afiche QR</a>
                    <button type="button" onclick="navigator.clipboard.writeText(@js($url)); window.tushpaAviso ? tushpaAviso('Enlace copiado: pégalo en WhatsApp, Facebook o Instagram.', 'ok') : alert('Enlace copiado')"
                            class="h-10 px-4 rounded-xl bg-white/15 hover:bg-white/25 font-bold text-sm flex items-center gap-2">🔗 Copiar enlace</button>
                </div>
            </div>
        </div>
    </section>

    @if (! $negocio->carta_activa)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 p-4 text-sm">
            La carta aún <b>no está publicada</b>: si alguien escanea el QR verá "Carta no disponible". Márcala abajo y guarda.
        </div>
    @endif

    <div class="grid sm:grid-cols-3 gap-3">
        <a href="{{ route('productos.index') }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
            <p class="text-xs font-semibold text-gray-500 uppercase">En la carta</p>
            <p class="text-3xl font-extrabold text-indigo-700">{{ $platos }} <span class="text-base text-gray-400">en {{ count($categorias) }} categorías</span></p>
            <p class="text-xs text-gray-400">Productos activos con precio (los insumos no salen).</p>
        </a>
        <a href="{{ route('productos.index') }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
            <p class="text-xs font-semibold text-gray-500 uppercase">Con foto</p>
            <p class="text-3xl font-extrabold text-gray-800">{{ $conFoto }} <span class="text-base text-gray-400">/ {{ $platos }}</span></p>
            <p class="text-xs text-gray-400">Al tocar el plato se ve la foto en grande.</p>
        </a>
        <a href="{{ route('productos.index') }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
            <p class="text-xs font-semibold text-gray-500 uppercase">Con descripción</p>
            <p class="text-3xl font-extrabold text-gray-800">{{ $conDesc }} <span class="text-base text-gray-400">/ {{ $platos }}</span></p>
            <p class="text-xs text-gray-400">Ej.: "Ron, menta, limón, soda". Se escribe en cada producto.</p>
        </a>
    </div>
    @if ($sinCategoria)
        <p class="text-sm text-amber-700">⚠ {{ $sinCategoria }} producto(s) no tienen categoría y no salen en la carta.</p>
    @endif

    <div class="grid lg:grid-cols-2 gap-5">
        <form method="POST" action="{{ route('carta.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" x-data="{ color: @js($color) }">
            @csrf
            <h2 class="font-bold text-gray-800">1. Configuración</h2>
            <label class="flex items-start gap-3 p-4 rounded-xl border border-indigo-200 bg-indigo-50 cursor-pointer">
                <input type="checkbox" name="carta_activa" value="1" @checked($negocio->carta_activa) class="mt-0.5 rounded text-indigo-600">
                <span><span class="font-bold text-indigo-900">Publicar la carta digital</span>
                    <span class="block text-sm text-indigo-700">Muestra las categorías visibles con sus productos y precios vigentes. Para ocultar una categoría, desmárcala como visible en Categorías.</span></span>
            </label>
            <label class="block text-sm font-medium text-gray-700">Mensaje en la portada (opcional)
                <textarea name="carta_mensaje" rows="3" maxlength="300" placeholder="Atendemos de 7:00 a.m. a 11:00 p.m. · WiFi: ABBYCAFE / clave 12345"
                          class="mt-1 w-full rounded-xl border-gray-300 text-sm">{{ old('carta_mensaje', $negocio->carta_mensaje) }}</textarea>
            </label>
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Color de la carta</p>
                <input type="hidden" name="carta_color" :value="color">
                <div class="flex flex-wrap gap-2">
                    @foreach ($colores as $hex => $nom)
                        <button type="button" title="{{ $nom }}" @click="color = '{{ $hex }}'" style="background: {{ $hex }}"
                                :class="color === '{{ $hex }}' ? 'ring-4 ring-offset-2 ring-gray-300 scale-110' : ''"
                                class="w-9 h-9 rounded-full shadow transition"></button>
                    @endforeach
                    <label class="w-9 h-9 rounded-full shadow overflow-hidden cursor-pointer ring-1 ring-gray-200" title="Otro color">
                        <input type="color" x-model="color" class="w-12 h-12 -m-1.5 cursor-pointer">
                    </label>
                </div>
            </div>
            <button class="h-11 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Guardar</button>
        </form>

        <form method="GET" action="{{ route('carta.imprimir') }}" target="_blank" class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4"
              x-data="{ todas: true }">
            <input type="hidden" name="tipo" value="mesas">
            <h2 class="font-bold text-gray-800">2. QR por mesa</h2>
            <p class="text-sm text-gray-500">Imprime una tarjeta por mesa: al escanearla, la carta muestra el nombre de la mesa. Recórtalas y ponlas en un portacarta o pégalas en la mesa.</p>
            @if ($mesas->isEmpty())
                <p class="text-sm text-amber-700">Aún no tienes mesas. Créalas en <a href="{{ url('/mesas') }}" class="underline font-semibold">Mesas</a>, o imprime el afiche general.</p>
            @else
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" x-model="todas" class="rounded"> Todas las mesas ({{ $mesas->count() }})</label>
                <div x-show="!todas" x-cloak class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-56 overflow-y-auto p-1">
                    @foreach ($mesas as $m)
                        <label class="flex items-center gap-2 text-sm rounded-lg bg-gray-50 px-2 py-1.5">
                            <input type="checkbox" name="mesas[]" value="{{ $m->mes_id }}" :disabled="todas" class="rounded"> {{ $m->mes_nom }}
                            @if ($m->pis_nom)<span class="text-[10px] text-gray-400 truncate">{{ $m->pis_nom }}</span>@endif
                        </label>
                    @endforeach
                </div>
                <button class="h-11 px-6 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold">🖨️ Imprimir QR de mesas</button>
            @endif
        </form>
    </div>
</div>
@endsection
