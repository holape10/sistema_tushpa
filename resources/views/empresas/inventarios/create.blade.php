@extends('layouts.app')
@section('title', 'Nuevo Inventario')
@section('content')
    @include('empresas.partials.alert')

    @php
        $lista = $productos->map(fn($p) => [
            'id' => $p->IdProducto, 'cod' => $p->procod, 'nom' => $p->pronom, 'ume' => $p->umecod, 'cat' => $p->cat_nom ?? '',
            'tipo' => (int) $p->promocion === 4 ? 'Insumo' : 'Producto', 'stock' => (float) $p->stock, 'costo' => (float) $p->costo,
            'kardex' => isset($conMovimientos[$p->IdProducto]),
        ])->values();
    @endphp

    <form method="POST" action="{{ route('inventarios.store') }}" x-data="inventario(@js($lista))" @submit="enviar($event)">
        @csrf
        <input type="hidden" name="items" :value="json">

        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <div class="grid sm:grid-cols-4 gap-3">
                <label class="text-sm">Almacén
                    <select name="id_almacen" class="block w-full rounded-lg border-gray-300 text-sm"
                            onchange="location.href='{{ route('inventarios.create') }}?almacen=' + this.value">
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id_almacen }}" @selected($a->id_almacen == $idAlmacen)>{{ $a->descripcion }}{{ $a->predeterminado ? ' (predeterminado)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">Fecha del conteo
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="block w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="text-sm sm:col-span-2">Observación
                    <input name="observaciones" value="{{ old('observaciones') }}" maxlength="255" placeholder="Ej. Inventario inicial, inventario de fin de mes..." class="block w-full rounded-lg border-gray-300 text-sm">
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                <a href="{{ route('inventarios.plantilla', ['almacen' => $idAlmacen]) }}" class="px-3 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">⬇ Descargar plantilla Excel</a>
                <label class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-semibold cursor-pointer hover:bg-emerald-100">
                    ⬆ Cargar Excel
                    <input type="file" accept=".xlsx,.csv" class="hidden" @change="cargarExcel($event)">
                </label>
                <span class="text-xs text-gray-500" x-show="cargando">Leyendo archivo...</span>
                <button type="button" @click="igualarStock()" class="px-3 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200"
                        title="Copia el stock del sistema como conteo en los productos sin contar">Copiar stock del sistema</button>
                <button type="button" @click="conteo = {}" class="px-3 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">Limpiar conteo</button>
            </div>
            <template x-if="errores.length">
                <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-xs max-h-32 overflow-y-auto">
                    <p class="font-semibold mb-1">Filas del Excel que no se cargaron:</p>
                    <template x-for="e in errores"><p x-text="e"></p></template>
                </div>
            </template>
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="flex flex-col sm:flex-row gap-3 p-4 pb-2">
                <input type="search" x-model="filtro" placeholder="Buscar por nombre, código o categoría..." class="flex-1 rounded-lg border-gray-300 text-sm">
                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="soloContados" class="rounded border-gray-300"> Solo contados</label>
                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="soloDiferencias" class="rounded border-gray-300"> Solo con diferencia</label>
            </div>
            <p class="px-4 text-xs text-gray-400">Deja vacío el conteo de los productos que no contaste: no se modifican. Si contaste 0, escribe 0.</p>

            <div class="overflow-x-auto max-h-[60vh] overflow-y-auto mt-2">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Producto</th>
                            <th class="px-3 py-2 text-right">Stock sistema</th>
                            <th class="px-3 py-2 w-32">Stock físico</th>
                            <th class="px-3 py-2 text-right">Diferencia</th>
                            <th class="px-3 py-2 w-28">Costo unit.</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="p in visibles" :key="p.id">
                            <tr :class="contado(p) ? 'bg-indigo-50/40' : ''">
                                <td class="px-3 py-1.5 text-gray-500" x-text="p.cod"></td>
                                <td class="px-3 py-1.5"><span class="font-medium text-gray-700" x-text="p.nom"></span>
                                    <span class="block text-xs text-gray-400" x-text="p.tipo + (p.cat ? ' · ' + p.cat : '') + ' · ' + p.ume"></span></td>
                                <td class="px-3 py-1.5 text-right" x-text="num(p.stock)"></td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="any" min="0" class="w-full rounded-lg border-gray-300 text-sm text-right"
                                           :value="conteo[p.id]?.cantidad ?? ''" @input="fijar(p, 'cantidad', $event.target.value)">
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold"
                                    :class="diferencia(p) > 0 ? 'text-green-600' : (diferencia(p) < 0 ? 'text-red-600' : 'text-gray-400')"
                                    x-text="contado(p) ? (diferencia(p) > 0 ? '+' : '') + num(diferencia(p)) : ''"></td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-sm text-right"
                                           :value="conteo[p.id]?.costo ?? p.costo.toFixed(2)" @input="fijar(p, 'costo', $event.target.value)">
                                </td>
                                <td class="px-3 py-1.5 text-xs">
                                    <span x-show="contado(p)" class="px-2 py-0.5 rounded-full"
                                          :class="p.kardex ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                                          x-text="p.kardex ? 'Ajuste' : 'Saldo inicial'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!visibles.length"><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin productos para mostrar. Los preparados y combos no manejan stock.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-gray-100">
                <p class="text-sm text-gray-600">
                    <strong x-text="resumen.contados"></strong> productos contados ·
                    <span class="text-green-600">+<span x-text="resumen.sobrantes"></span> con sobrante</span> ·
                    <span class="text-red-600"><span x-text="resumen.faltantes"></span> con faltante</span> ·
                    Ajuste valorizado: <strong x-text="'S/ ' + resumen.valor.toFixed(2)"></strong>
                </p>
                <div class="flex gap-2 w-full sm:w-auto">
                    <a href="{{ route('inventarios.index') }}" class="flex-1 sm:flex-none text-center px-4 py-3 rounded-xl bg-gray-200 text-gray-700 font-semibold text-sm">Cancelar</a>
                    <button class="flex-1 sm:flex-none px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700">PROCESAR INVENTARIO</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function inventario(productos) {
            return {
                productos, conteo: {}, filtro: '', soloContados: false, soloDiferencias: false, errores: [], cargando: false,
                num(n) { return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 3 }); },
                contado(p) { return this.conteo[p.id] && this.conteo[p.id].cantidad !== '' && this.conteo[p.id].cantidad != null; },
                diferencia(p) { return this.contado(p) ? Math.round((parseFloat(this.conteo[p.id].cantidad) - p.stock) * 1000) / 1000 : 0; },
                fijar(p, campo, valor) {
                    const actual = this.conteo[p.id] || { cantidad: '', costo: null };
                    this.conteo = { ...this.conteo, [p.id]: { ...actual, [campo]: valor } };
                },
                get visibles() {
                    const q = this.filtro.trim().toLowerCase();
                    return this.productos.filter(p =>
                        (!q || (p.nom + ' ' + p.cod + ' ' + p.cat).toLowerCase().includes(q))
                        && (!this.soloContados || this.contado(p))
                        && (!this.soloDiferencias || this.diferencia(p) !== 0));
                },
                get resumen() {
                    let r = { contados: 0, sobrantes: 0, faltantes: 0, valor: 0 };
                    this.productos.forEach(p => {
                        if (!this.contado(p)) return;
                        const d = this.diferencia(p), c = parseFloat(this.conteo[p.id].costo ?? p.costo) || 0;
                        r.contados++; if (d > 0) r.sobrantes++; if (d < 0) r.faltantes++; r.valor += d * c;
                    });
                    return r;
                },
                get json() {
                    return JSON.stringify(this.productos.filter(p => this.contado(p)).map(p => ({
                        IdProducto: p.id, cantidad: parseFloat(this.conteo[p.id].cantidad),
                        costo: this.conteo[p.id].costo != null && this.conteo[p.id].costo !== '' ? parseFloat(this.conteo[p.id].costo) : null,
                    })));
                },
                igualarStock() {
                    const nuevo = { ...this.conteo };
                    this.productos.forEach(p => { if (!this.contado(p)) nuevo[p.id] = { cantidad: String(p.stock), costo: null }; });
                    this.conteo = nuevo;
                },
                async cargarExcel(e) {
                    const archivo = e.target.files[0];
                    if (!archivo) return;
                    const datos = new FormData();
                    datos.append('archivo', archivo);
                    datos.append('_token', '{{ csrf_token() }}');
                    this.cargando = true; this.errores = [];
                    try {
                        const r = await fetch('{{ route('inventarios.leer_excel') }}', { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
                        const j = await r.json();
                        if (!r.ok) { alert(j.message || 'No se pudo leer el Excel.'); return; }
                        const nuevo = { ...this.conteo };
                        Object.entries(j.items).forEach(([id, v]) => { nuevo[id] = { cantidad: String(v.cantidad), costo: v.costo }; });
                        this.conteo = nuevo;
                        this.errores = j.errores;
                        this.soloContados = true;
                        alert(Object.keys(j.items).length + ' productos cargados desde el Excel. Revisa y presiona PROCESAR INVENTARIO.');
                    } catch (err) {
                        alert('No se pudo leer el Excel.');
                    } finally {
                        this.cargando = false; e.target.value = '';
                    }
                },
                enviar(e) {
                    const r = this.resumen;
                    if (!r.contados) { e.preventDefault(); alert('Ingresa el conteo de al menos un producto.'); return; }
                    if (!confirm(`Se ajustará el stock de ${r.contados} productos al conteo ingresado. ¿Continuar?`)) e.preventDefault();
                },
            };
        }
    </script>
@endsection
