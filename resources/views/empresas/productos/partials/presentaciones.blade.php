{{-- Presentaciones: otras formas de vender el mismo producto (KG y SACO x 50) --}}
<section class="bg-white rounded-2xl shadow-sm" x-show="tipo == '0'" x-cloak>
    <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
        <div class="min-w-0 flex-1">
            <h3 class="font-bold text-gray-800">Presentaciones</h3>
            <p class="text-xs text-gray-400">
                La unidad predeterminada es <strong class="text-gray-600" x-text="nombreUnidad(umecod)"></strong>
                (<span x-text="soles(precio)"></span>). Agrega otras formas de venderlo: por ejemplo <strong>SACO</strong> con factor <strong>50</strong> = un saco trae 50 <span x-text="nombreUnidad(umecod)"></span>.
            </p>
        </div>
        <button type="button" @click="agregarPresentacion()" class="px-4 h-10 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 whitespace-nowrap">+ Presentación</button>
    </div>

    <div class="p-5 space-y-3">
        <template x-for="(pr, i) in presentaciones" :key="pr.key">
            <div class="rounded-xl border border-gray-200 p-3 grid grid-cols-2 sm:grid-cols-12 gap-2 items-end">
                <input type="hidden" :name="`presentaciones[${i}][id]`" :value="pr.id ?? ''">
                <label class="col-span-1 sm:col-span-2 text-xs text-gray-500">Unidad
                    <select :name="`presentaciones[${i}][umecod]`" :disabled="tipo != '0'" x-model="pr.umecod" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($unidades as $u)
                            <option value="{{ $u->umecod }}">{{ $u->umenom }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="col-span-1 sm:col-span-3 text-xs text-gray-500">Nombre
                    <input type="text" :name="`presentaciones[${i}][nombre]`" x-model="pr.nombre" maxlength="60"
                           :placeholder="nombreUnidad(pr.umecod) + (pr.factor ? ' X ' + pr.factor : '')"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="col-span-1 sm:col-span-2 text-xs text-gray-500">Factor (trae)
                    <input type="number" step="any" min="0.001" :name="`presentaciones[${i}][factor]`" x-model="pr.factor" :required="tipo == '0'" :disabled="tipo != '0'" placeholder="50"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm text-right focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="col-span-1 sm:col-span-2 text-xs text-gray-500">Precio S/
                    <input type="number" step="0.01" min="0.01" :name="`presentaciones[${i}][precio]`" x-model="pr.precio" :required="tipo == '0'" :disabled="tipo != '0'" :placeholder="sugerido(pr)"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm text-right focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="col-span-2 sm:col-span-3 text-xs text-gray-500">Código de barras
                    <div class="mt-1 flex">
                        <input type="text" :name="`presentaciones[${i}][codigo_barra]`" x-model="pr.codigo_barra" maxlength="50" @keydown.enter.prevent
                               class="flex-1 min-w-0 rounded-l-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" @click="escanear(pr)" class="px-3 bg-sky-500 hover:bg-sky-600 text-white" title="Escanear con la cámara" aria-label="Escanear con la cámara">📷</button>
                        <button type="button" @click="presentaciones.splice(i, 1)" class="px-3 rounded-r-lg bg-rose-50 text-rose-600 hover:bg-rose-100" title="Quitar presentación" aria-label="Quitar presentación">✕</button>
                    </div>
                </label>
                <p class="col-span-2 sm:col-span-12 text-xs text-gray-500" x-show="pr.factor > 0">
                    1 <span x-text="pr.nombre || nombreUnidad(pr.umecod)"></span> = <strong x-text="pr.factor"></strong> <span x-text="nombreUnidad(umecod)"></span>
                    · descuenta <span x-text="pr.factor"></span> del stock por cada una vendida
                </p>
            </div>
        </template>
        <p x-show="!presentaciones.length" class="text-sm text-gray-400 py-2">Sin presentaciones: se vende solo por <span x-text="nombreUnidad(umecod)"></span>.</p>
    </div>
</section>
