@extends('layouts.app')
@section('title', 'Nueva Transferencia')
@section('content')
    @include('empresas.partials.alert')

    @php
        $lista = $productos->map(fn($p) => ['id' => $p->IdProducto, 'cod' => $p->procod, 'nom' => $p->pronom, 'ume' => $p->umecod])->values();
        $origenInicial = old('part_alm', $almacenes->first()->id_almacen);
        $destinoInicial = old('des_alm', $almacenes->firstWhere('id_almacen', '!=', $origenInicial)?->id_almacen);
    @endphp

    <form method="POST" action="{{ route('transferencias.store') }}" class="bg-white rounded-2xl shadow-sm p-4"
          x-data="transferencia(@js($lista), @js($stocks), {{ (int) $origenInicial }}, {{ (int) $destinoInicial }})" @submit="enviar($event)">
        @csrf
        <div class="grid sm:grid-cols-4 gap-3 mb-4">
            <label class="text-sm">Almacén de origen (sale)
                <select name="part_alm" x-model.number="origen" class="block w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Almacén de destino (entra)
                <select name="des_alm" x-model.number="destino" class="block w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Fecha<input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Observación<input name="observaciones" value="{{ old('observaciones') }}" maxlength="255" class="block w-full rounded-lg border-gray-300 text-sm"></label>
        </div>
        <p x-show="origen === destino" class="mb-3 text-sm text-red-600">El origen y el destino deben ser almacenes distintos.</p>

        <div class="flex gap-2 mb-3">
            <input list="lista_productos" x-model="buscar" @keydown.enter.prevent="agregar()" placeholder="Buscar producto o insumo y presiona Enter..." autocomplete="off" class="flex-1 rounded-lg border-gray-300 text-sm">
            <datalist id="lista_productos">
                <template x-for="p in productos" :key="p.id"><option :value="p.nom" x-text="p.cod + ' · disp. ' + num(stock(origen, p.id))"></option></template>
            </datalist>
            <button type="button" @click="agregar()" class="px-4 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Agregar</button>
            <button type="button" @click="agregarTodo()" class="px-3 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200"
                    title="Agrega todos los productos con stock en el origen">Todo el stock</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm mb-4">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Disponible origen</th><th class="px-3 py-2 text-right">Stock destino</th>
                        <th class="px-3 py-2 w-32">Cantidad</th><th class="w-10"></th></tr>
                </thead>
                <tbody>
                    <template x-for="(it, i) in items" :key="it.id">
                        <tr class="border-t border-gray-100" :class="it.cantidad > stock(origen, it.id) ? 'bg-red-50' : ''">
                            <td class="px-3 py-2"><span class="font-medium text-gray-700" x-text="it.nom"></span> <span class="text-xs text-gray-400" x-text="it.ume"></span>
                                <input type="hidden" :name="`items[${i}][IdProducto]`" :value="it.id"></td>
                            <td class="px-3 py-2 text-right" :class="it.cantidad > stock(origen, it.id) ? 'text-red-600 font-semibold' : ''" x-text="num(stock(origen, it.id))"></td>
                            <td class="px-3 py-2 text-right text-gray-500" x-text="num(stock(destino, it.id))"></td>
                            <td class="px-3 py-2"><input type="number" step="any" min="0.01" required :name="`items[${i}][cantidad]`" x-model.number="it.cantidad" class="w-full rounded-lg border-gray-300 text-sm text-right"></td>
                            <td class="px-3 py-2 text-center"><button type="button" @click="items.splice(i, 1)" class="text-red-600 font-bold">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p x-show="!items.length" class="text-center text-gray-400 text-sm mb-4">Agrega al menos un producto.</p>

        <div class="flex gap-2">
            <a href="{{ route('transferencias.index') }}" class="px-4 py-3 rounded-xl bg-gray-200 text-gray-700 font-semibold text-sm">Cancelar</a>
            <button class="flex-1 py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700">REGISTRAR TRANSFERENCIA</button>
        </div>
    </form>

    <script>
        function transferencia(productos, stocks, origen, destino) {
            return {
                productos, stocks, origen, destino, items: [], buscar: '',
                num(n) { return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 3 }); },
                stock(alm, id) { return parseFloat((this.stocks[alm] || {})[id] || 0); },
                agregar() {
                    const p = this.productos.find(x => x.nom === this.buscar);
                    if (!p) { alert('Elige un producto de la lista.'); return; }
                    const ya = this.items.find(x => x.id === p.id);
                    if (ya) ya.cantidad = (parseFloat(ya.cantidad) || 0) + 1;
                    else this.items.push({ ...p, cantidad: 1 });
                    this.buscar = '';
                },
                agregarTodo() {
                    this.productos.forEach(p => {
                        const s = this.stock(this.origen, p.id);
                        if (s > 0 && !this.items.some(x => x.id === p.id)) this.items.push({ ...p, cantidad: s });
                    });
                    if (!this.items.length) alert('El almacén de origen no tiene productos con stock.');
                },
                enviar(e) {
                    if (this.origen === this.destino) { e.preventDefault(); alert('El origen y el destino deben ser almacenes distintos.'); return; }
                    if (!this.items.length) { e.preventDefault(); alert('Agrega al menos un producto.'); return; }
                    const falta = this.items.find(x => x.cantidad > this.stock(this.origen, x.id));
                    if (falta) { e.preventDefault(); alert('Stock insuficiente en el origen para: ' + falta.nom); }
                },
            };
        }
    </script>
@endsection
