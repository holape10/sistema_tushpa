@extends('layouts.app')
@section('title', 'Etiquetas de productos')

@section('content')
<div class="max-w-6xl mx-auto space-y-4" x-data="etiquetasProductos()">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('productos.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Productos</a>
        <div>
            <h1 class="text-xl font-bold text-gray-800">🏷️ Imprimir etiquetas con código de barras</h1>
            <p class="text-sm text-gray-500">Elige los productos y cuántas etiquetas de cada uno. Salen con el código de barras que tienen registrado.</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start">
        {{-- Buscar y agregar --}}
        <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
            <input type="search" x-model="q" @input.debounce.300ms="buscar()" placeholder="🔍 Buscar producto por nombre o código"
                   class="w-full h-11 rounded-xl border-gray-300 text-sm">
            <ul class="max-h-[60vh] overflow-y-auto divide-y divide-gray-100">
                <template x-for="p in resultados" :key="p.id">
                    <li class="flex items-center gap-2 py-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate" x-text="p.nombre"></p>
                            <p class="text-xs" :class="p.codigo_barra ? 'text-gray-500' : 'text-amber-600'"
                               x-text="p.codigo_barra ? '▮▯▮ ' + p.codigo_barra : 'Sin código de barras (cód. interno ' + p.procod + ')'"></p>
                        </div>
                        <span class="text-sm font-semibold text-gray-600" x-text="'S/ ' + Number(p.precio).toFixed(2)"></span>
                        <button type="button" @click="agregar(p)" class="h-8 px-3 rounded-lg bg-indigo-600 text-white text-xs font-bold">+ Agregar</button>
                    </li>
                </template>
                <li x-show="!resultados.length" class="py-6 text-center text-sm text-gray-400">Escribe para buscar productos.</li>
            </ul>
        </section>

        {{-- Lista a imprimir y opciones --}}
        <section class="bg-white rounded-2xl shadow-sm p-4 space-y-4">
            <div>
                <p class="text-sm font-bold text-gray-700 mb-2">Para imprimir <span class="font-normal text-gray-400" x-text="'(' + totalEtiquetas() + ' etiquetas)'"></span></p>
                <ul class="divide-y divide-gray-100">
                    <template x-for="(it, i) in lista" :key="it.id">
                        <li class="flex items-center gap-2 py-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold truncate" x-text="it.nombre"></p>
                                <p class="text-xs text-gray-500" x-text="it.codigo_barra || ('Cód. interno: ' + it.procod)"></p>
                            </div>
                            <input type="number" min="1" max="500" x-model.number="it.cantidad" class="w-20 h-9 rounded-lg border-gray-300 text-sm text-right" title="Cantidad de etiquetas">
                            <button type="button" @click="lista.splice(i, 1)" class="text-gray-300 hover:text-rose-600 px-1">✕</button>
                        </li>
                    </template>
                    <li x-show="!lista.length" class="py-6 text-center text-sm text-gray-400">Agrega productos de la izquierda.</li>
                </ul>
            </div>

            <div class="grid sm:grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                <label class="text-sm font-semibold text-gray-600">Imprimir en
                    <select x-model="destino" class="mt-1 block w-full rounded-xl border-gray-300 text-sm">
                        @foreach ($impresoras as $imp)
                            <option value="{{ $imp->Id }}">{{ $imp->descripcion }} · {{ $imp->tip_conex_imp === 'WINDOWS' ? 'impresora de etiquetas / Windows' : 'ticketera '.($imp->columnas == 32 ? '58' : '80').' mm' }}</option>
                        @endforeach
                        <option value="pdf">Ver en PDF (imprimir desde el navegador)</option>
                    </select></label>
                <label class="text-sm font-semibold text-gray-600" x-show="esPdf()">Tamaño de la etiqueta
                    <select x-model="tamano" class="mt-1 block w-full rounded-xl border-gray-300 text-sm">
                        @foreach ($tamanos as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach
                    </select></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="nombre" class="rounded"> Mostrar el nombre</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="precio" class="rounded"> Mostrar el precio</label>
                <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" x-model="usarInterno" class="rounded"> Si no tiene código de barras, usar su código interno</label>
            </div>
            <p class="text-xs text-gray-400" x-show="esPdf()">Para una impresora de etiquetas, agrégala en Impresoras como «Impresora de Windows (A4)» con el nombre que tiene en Windows y elige aquí el tamaño de tu etiqueta.</p>

            <button type="button" @click="imprimir()" :disabled="enviando || !lista.length"
                    class="w-full h-11 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50"
                    x-text="enviando ? 'Enviando…' : (destino === 'pdf' ? '📄 Ver etiquetas en PDF' : '🖨 Imprimir etiquetas')"></button>
        </section>
    </div>
</div>

<script>
    function etiquetasProductos() {
        const tipos = @js($impresoras->mapWithKeys(fn ($i) => [(string) $i->Id => $i->tip_conex_imp])->all());
        return {
            q: '', resultados: [], lista: [], destino: @js($impresoras->first() ? (string) $impresoras->first()->Id : 'pdf'), tamano: '50x25',
            nombre: true, precio: true, usarInterno: false, enviando: false,
            esPdf() { return this.destino === 'pdf' || tipos[this.destino] === 'WINDOWS'; },
            totalEtiquetas() { return this.lista.reduce((s, it) => s + (Number(it.cantidad) || 0), 0); },
            async buscar() {
                const r = await fetch(@js(route('productos.etiquetas.buscar')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                this.resultados = r.ok ? await r.json() : [];
            },
            agregar(p) {
                const ya = this.lista.find(x => x.id === p.id);
                if (ya) { ya.cantidad++; return; }
                this.lista.push({ ...p, cantidad: 1 });
            },
            async imprimir() {
                const datos = { items: this.lista.map(it => ({ id: it.id, cantidad: Number(it.cantidad) || 1 })), destino: this.destino, tamano: this.tamano,
                    nombre: this.nombre, precio: this.precio, usar_interno: this.usarInterno };
                this.enviando = true;
                try {
                    const r = await fetch(@js(route('productos.etiquetas.imprimir')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), Accept: this.destino === 'pdf' ? 'application/pdf' : 'application/json' },
                        body: JSON.stringify(datos),
                    });
                    if (this.destino === 'pdf' && r.ok && (r.headers.get('Content-Type') || '').includes('pdf')) {
                        window.open(URL.createObjectURL(await r.blob()), '_blank');
                        return;
                    }
                    const d = await r.json();
                    const mensaje = d.mensaje || d.message || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo imprimir.');
                    tushpaAviso(mensaje, r.ok && d.ok ? 'ok' : 'error');
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    this.enviando = false;
                }
            },
        };
    }
</script>
@endsection
