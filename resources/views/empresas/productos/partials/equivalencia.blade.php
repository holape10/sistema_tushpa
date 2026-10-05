{{-- Insumos: unidad equivalente para las recetas (1 KG = 1000 GR) --}}
<div x-show="tipo == '4'" x-cloak class="sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
    <p class="text-sm font-semibold text-amber-800">Unidad equivalente <span class="font-normal text-amber-700">(opcional)</span></p>
    <p class="text-xs text-amber-700 mb-3">Compras el insumo en una unidad y lo usas en otra. Ej.: arroz por Kilogramo, en las recetas por Gramos.</p>
    <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr_auto_1fr] items-center gap-2 text-sm">
        <span class="font-semibold text-gray-700">1 <span x-text="nombreUnidad(umecod)"></span> =</span>
        <input type="number" step="any" min="0.0001" name="factor_equivalente" x-model="factorEquivalente" :required="usaEquivalente"
               :disabled="tipo != '4'" placeholder="1000" class="w-full rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
        <span class="hidden sm:inline text-gray-500">en</span>
        <select name="ume_equivalente" x-model="umeEquivalente" :disabled="tipo != '4'"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
            <option value="">— Sin equivalencia —</option>
            @foreach ($unidades as $u)
                <option value="{{ $u->umecod }}">{{ $u->umenom }}</option>
            @endforeach
        </select>
    </div>
    <p x-show="usaEquivalente && factorEquivalente > 0" class="text-xs text-amber-800 mt-2"
       x-text="'✔ 1 ' + nombreUnidad(umecod) + ' equivale a ' + factorEquivalente + ' ' + nombreUnidad(umeEquivalente)"></p>
</div>
