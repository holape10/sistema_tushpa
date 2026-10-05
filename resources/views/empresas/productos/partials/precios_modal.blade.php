{{-- Modal: precios dinámicos por día y hora. Se guardan junto con el producto. --}}
<div x-show="modalPrecios" x-cloak data-modal-precios class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
     @click.self="modalPrecios = false" @keydown.escape.window="modalPrecios = false">
    <div class="bg-white w-full sm:max-w-4xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 bg-slate-800 text-white rounded-t-2xl">
            <h3 class="font-bold text-lg">Gestionar precios dinámicos</h3>
            <button type="button" @click="modalPrecios = false" class="text-2xl leading-none px-2" aria-label="Cerrar">×</button>
        </div>

        <div class="flex-1 overflow-y-auto p-5 space-y-3">
            <p class="text-sm text-gray-600">
                Define precios especiales por día y hora. En ese horario <strong>todos los puntos de venta</strong> y las comandas cobran el precio especial;
                fuera de él vuelve el precio normal (<span x-text="soles(precio)"></span>). Si dos reglas coinciden, gana la más específica.
            </p>
            <p class="text-xs text-sky-700 bg-sky-50 rounded-lg px-3 py-2">
                <strong>Cruce de medianoche:</strong> para un precio del <em>Jueves 18:00 al Viernes 16:00</em> crea una sola regla con
                Día: Jueves, Hora inicio: 18:00 y Hora fin: 16:00. Si la hora fin es menor o igual a la de inicio, la regla sigue hasta el día siguiente.
            </p>

            <div class="hidden sm:grid grid-cols-12 gap-2 px-1 text-xs font-semibold text-gray-500 uppercase">
                <span class="col-span-3">Día de la semana</span><span class="col-span-2">Hora inicio</span><span class="col-span-2">Hora fin</span>
                <span class="col-span-2">Precio especial</span><span class="col-span-2 text-center">Activo</span><span></span>
            </div>
            <template x-for="(r, i) in precios" :key="r.key">
                <div class="grid grid-cols-2 sm:grid-cols-12 gap-2 items-center rounded-xl border border-gray-200 sm:border-0 p-3 sm:p-1">
                    <select :name="`precios[${i}][dia]`" :disabled="tipo == '4'" x-model="r.dia" class="col-span-2 sm:col-span-3 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" aria-label="Día">
                        @foreach (\App\Support\Precios::DIAS as $num => $dia)
                            <option value="{{ $num }}">{{ $dia }}</option>
                        @endforeach
                    </select>
                    <input type="time" :name="`precios[${i}][hora_inicio]`" x-model="r.hora_inicio" :required="tipo != '4'" :disabled="tipo == '4'" aria-label="Hora inicio"
                           class="sm:col-span-2 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="time" :name="`precios[${i}][hora_fin]`" x-model="r.hora_fin" :required="tipo != '4'" :disabled="tipo == '4'" aria-label="Hora fin"
                           class="sm:col-span-2 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="number" step="0.01" min="0.01" :name="`precios[${i}][precio]`" x-model="r.precio" :required="tipo != '4'" :disabled="tipo == '4'" placeholder="S/ 0.00" aria-label="Precio especial"
                           class="sm:col-span-2 rounded-lg border-gray-300 text-sm text-right focus:border-indigo-500 focus:ring-indigo-500">
                    <label class="sm:col-span-2 flex items-center sm:justify-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" x-model="r.activo" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <input type="hidden" :name="`precios[${i}][activo]`" :disabled="tipo == '4'" :value="r.activo ? 1 : 0">
                        <span class="sm:hidden">Activo</span>
                    </label>
                    <button type="button" @click="precios.splice(i, 1)" class="justify-self-end px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50" aria-label="Quitar regla">✕</button>
                    <p x-show="cruzaMedianoche(r)" class="col-span-2 sm:col-span-12 text-xs text-sky-700 sm:pl-1">↪ Cruza la medianoche: termina al día siguiente a las <span x-text="r.hora_fin"></span>.</p>
                </div>
            </template>
            <p x-show="!precios.length" class="text-sm text-gray-400 text-center py-4">Sin precios dinámicos: siempre se cobra el precio normal.</p>
        </div>

        <div class="flex flex-wrap justify-between gap-2 px-5 py-4 border-t border-gray-100">
            <button type="button" @click="agregarPrecio()" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">+ Agregar regla</button>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">Se guardan al guardar el producto.</span>
                <button type="button" @click="modalPrecios = false" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Listo</button>
            </div>
        </div>
    </div>
</div>
