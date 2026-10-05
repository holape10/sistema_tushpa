{{-- Precio de venta + precios dinámicos (no aplica a insumos: no se venden) --}}
<div x-show="tipo != '4'" x-cloak>
    <label class="block text-sm font-medium text-gray-700 mb-1">Precio de venta *</label>
    <div class="flex">
        <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">S/</span>
        <input type="number" step="0.01" min="0" name="propun" x-model="precio" :required="tipo != '4'" :disabled="tipo == '4'"
               class="flex-1 min-w-0 h-11 border-gray-300 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500">
        <button type="button" @click="modalPrecios = true" title="Precios dinámicos por día y hora"
                class="px-3.5 rounded-r-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold whitespace-nowrap">
            ⚡ <span x-text="precios.length ? precios.length : '+'"></span>
        </button>
    </div>
    <p class="text-xs mt-1" :class="precios.length ? 'text-emerald-700 font-medium' : 'text-gray-400'"
       x-text="precios.length ? precios.length + (precios.length === 1 ? ' precio dinámico configurado' : ' precios dinámicos configurados') : 'Con ⚡ defines precios especiales por día y hora (happy hour, fin de semana…).'"></p>
</div>
