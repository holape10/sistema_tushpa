@extends('layouts.app')
@section('title', 'Pedidos a proveedores')

@section('content')
{{-- Pedidos sugeridos: lo que se acaba, por el proveedor que lo vende más barato, listo para pedirlo por WhatsApp --}}
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') ?: '0';
@endphp
<div class="max-w-5xl mx-auto space-y-4" x-data="{ copiado: null, async copiar(id, texto) { try { await navigator.clipboard.writeText(texto); this.copiado = id; setTimeout(() => this.copiado = null, 2000); } catch (e) {} } }">
    <section class="rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">🛒 Pedidos a tus proveedores</h1>
        <p class="mt-1 text-white/90">Lo que se te está acabando (según lo que vendes cada día y tu stock mínimo), agrupado por el proveedor que te lo vende <b>más barato</b>.
            Revisa y mándale el pedido por WhatsApp con un toque.</p>
    </section>

    <form class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold text-gray-600">Pedir para</span>
        @foreach (\App\Support\PedidosProveedor::DIAS as $d)
            <button name="dias" value="{{ $d }}" class="h-10 px-4 rounded-xl text-sm font-bold {{ $dias === $d ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $d }} días</button>
        @endforeach
        <a href="{{ route('compras.comparar') }}" class="ml-auto text-sm font-bold text-indigo-700 underline">Ver precios por proveedor</a>
    </form>

    @forelse ($grupos as $i => $g)
        @php $texto = $mensaje($g); @endphp
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100 {{ $g->id ? '' : 'bg-amber-50' }}">
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-extrabold text-gray-800">{{ $g->id ? '🏭 '.$g->proveedor : '❔ '.$g->proveedor }}</p>
                    <p class="text-xs text-gray-500">{{ $g->items->count() }} producto(s) · aprox. {{ $soles($g->total) }}{{ $g->telefono ? '' : ($g->id ? ' · sin celular registrado' : ' · nunca se compraron: elige a quién pedírselos') }}</p>
                </div>
                <a href="https://wa.me/{{ $g->telefono }}?text={{ rawurlencode($texto) }}" target="_blank" rel="noopener"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">💬 Pedir por WhatsApp</a>
                <button type="button" @click="copiar({{ $i }}, @js($texto))" class="h-10 px-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-bold"
                        x-text="copiado === {{ $i }} ? '✓ Copiado' : '📋 Copiar'"></button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Producto</th><th class="px-2 py-2 text-right">Stock</th><th class="px-2 py-2 text-right">Vendes por día</th>
                        <th class="px-2 py-2 text-right">Pedir</th><th class="px-4 py-2 text-right">Costo aprox.</th></tr>
                </thead>
                <tbody>
                    @foreach ($g->items as $it)
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-2 font-semibold text-gray-800">{{ $it->nombre }}</td>
                            <td class="px-2 py-2 text-right {{ $it->stock <= 0 ? 'text-rose-600 font-bold' : '' }}">{{ $num($it->stock) }}</td>
                            <td class="px-2 py-2 text-right">{{ $num($it->por_dia) }}</td>
                            <td class="px-2 py-2 text-right font-black text-emerald-700">{{ $num($it->pedir) }}</td>
                            <td class="px-4 py-2 text-right">{{ $it->subtotal > 0 ? $soles($it->subtotal) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">✓ No se te está acabando nada para los próximos {{ $dias }} días.</p>
    @endforelse

    <p class="text-xs text-gray-500">Cuando llegue la mercadería, regístrala en <a href="{{ route('compras.create') }}" class="font-bold text-indigo-700 underline">Nueva compra</a>: el stock sube y el sistema aprende el precio de cada proveedor.</p>
</div>
@endsection
