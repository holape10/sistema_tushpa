@extends('layouts.app')
@section('title', 'Configuración avícola')
@section('content')
    @include('empresas.partials.alert')
    <div class="grid lg:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('avicola.configuracion.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
            @csrf
            <p class="font-bold text-gray-800">Jabas</p>
            <label class="block text-sm font-semibold text-gray-700">Peso de una jaba vacía (tara), en kg
                <input type="number" step="0.001" min="0" name="tara_jaba" value="{{ (float) $cfg->tara_jaba }}" class="mt-1 block w-40 h-10 rounded-xl border-gray-300 text-right font-bold">
                <span class="block text-xs font-normal text-gray-500">Pesa una jaba vacía en tu balanza. Se resta por cada jaba en la venta y en el ingreso.</span></label>
            <label class="block text-sm font-semibold text-gray-700">Aves que entran en una jaba
                <input type="number" min="1" name="aves_jaba" value="{{ (int) $cfg->aves_jaba }}" class="mt-1 block w-40 h-10 rounded-xl border-gray-300 text-center font-bold">
                <span class="block text-xs font-normal text-gray-500">Para sugerir las aves al poner las jabas (se puede cambiar en cada pesada).</span></label>
            <button class="h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar</button>
        </form>

        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="font-bold text-gray-800">Productos avícolas</p>
            <p class="text-sm text-gray-500 mb-3">Son los de la categoría <b>{{ \App\Support\Avicola::CATEGORIA }}</b>. Puedes crear los típicos con un clic y luego agregar o cambiar los tuyos en Productos.</p>
            <form method="POST" action="{{ route('avicola.configuracion.productos') }}" class="mb-4">@csrf
                <button class="h-10 px-5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold">Crear productos típicos</button>
                <span class="text-xs text-gray-500 ml-2">{{ collect(\App\Support\Avicola::PRODUCTOS_TIPICOS)->pluck(0)->implode(', ') }}</span>
            </form>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($productos as $p)
                    <li class="py-2 flex justify-between"><span>{{ $p->pronom }}</span><span class="text-gray-500">S/ {{ number_format((float) $p->propun, 2) }} · stock {{ number_format((float) $p->stock, 2) }} {{ $p->umecod === 'KGM' ? 'kg' : 'und' }}</span></li>
                @empty
                    <li class="py-3 text-gray-400">Aún no hay productos avícolas.</li>
                @endforelse
            </ul>
            <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900">
                <b>IGV:</b> por lo general el <b>pollo vivo</b> está exonerado del IGV y el <b>pollo beneficiado y sus partes</b> pagan IGV. El sistema emite con la afectación de
                tu sucursal (Mantenimiento › Sucursales). Si vendes de los dos tipos, conviene una sucursal (o serie) para cada uno. Confírmalo con tu contador.
            </div>
        </div>
    </div>
@endsection
