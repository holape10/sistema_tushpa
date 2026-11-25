@extends('layouts.app')
@section('title', 'Precios por costo')

@section('content')
{{-- Lo que subió de costo en las compras y ahora gana menos: el precio sugerido mantiene la ganancia que tenía --}}
<div class="max-w-5xl mx-auto space-y-4">
    @include('empresas.partials.alert')
    <section class="rounded-3xl bg-gradient-to-br from-rose-500 to-orange-500 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">📈 Te subieron el costo: revisa tus precios</h1>
        <p class="mt-1 text-white/90">{{ $compra ? 'En esta compra' : 'En los últimos '.$dias.' días' }} estos productos te costaron más y, con el mismo precio, ahora ganas menos.
            El precio sugerido mantiene la ganancia que tenías (redondeado a 10 céntimos). Cámbialo si quieres y toca <b>Subir precios</b>.</p>
    </section>

    @if ($sugerencias->isEmpty())
        <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">✓ Todo bien: ningún producto gana menos por el costo nuevo.
            @if ($compra)<a href="{{ route('productos.precios_costo') }}" class="font-bold text-indigo-700 underline">Ver los últimos {{ $dias }} días</a>@endif</p>
    @else
        <form method="POST" action="{{ route('productos.precios_costo.guardar') }}" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr><th class="px-3 py-2 w-8"></th><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Costo</th>
                            <th class="px-3 py-2 text-right">Precio hoy</th><th class="px-3 py-2 text-right">Ganancia</th><th class="px-3 py-2 w-36">Precio nuevo</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sugerencias as $s)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2"><input type="checkbox" name="elegidos[]" value="{{ $s->id }}" checked class="rounded text-emerald-600"></td>
                                <td class="px-3 py-2 font-semibold text-gray-800">{{ $s->nombre }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap"><span class="text-gray-400">S/ {{ number_format($s->antes, 2) }} →</span> <b class="text-rose-600">S/ {{ number_format($s->ahora, 2) }}</b></td>
                                <td class="px-3 py-2 text-right">S/ {{ number_format($s->precio, 2) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap"><span class="text-gray-400">{{ $s->margen_antes }}% →</span>
                                    <b class="{{ $s->margen_ahora < 10 ? 'text-rose-600' : 'text-amber-600' }}">{{ $s->margen_ahora }}%</b></td>
                                <td class="px-3 py-2"><input type="number" step="0.01" min="0.01" name="precios[{{ $s->id }}]" value="{{ number_format($s->sugerido, 2, '.', '') }}"
                                           class="w-full h-9 rounded-lg border-emerald-300 bg-emerald-50 text-right font-bold text-emerald-800"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 border-t border-gray-100">
                <p class="text-xs text-gray-500">Si un producto no tenía ganancia registrada, se sugiere ganar {{ (int) (\App\Support\PreciosCompra::MARGEN_DEFECTO * 100) }}%.</p>
                <button class="ml-auto h-11 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black">✓ Subir precios marcados</button>
            </div>
        </form>
    @endif
</div>
@endsection
