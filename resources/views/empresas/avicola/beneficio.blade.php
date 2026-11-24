@extends('layouts.app')
@section('title', 'Beneficio y partes')
@section('content')
    @include('empresas.partials.alert')
    @php($opciones = $productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'stock' => (float) $p->stock])->values())

    <form method="POST" action="{{ route('avicola.beneficiar') }}" x-data="{ productos: @js($opciones), vivo: {{ (int) ($productos->first(fn ($p) => str_contains($p->pronom, 'VIV'))->IdProducto ?? $productos->first()->IdProducto ?? 0) }},
            kgVivo: null, partes: [{ IdProducto: '', kg: null }],
            get salida() { return this.partes.reduce((s, p) => s + (Number(p.kg) || 0), 0) },
            get rendimiento() { return this.kgVivo > 0 ? this.salida / this.kgVivo * 100 : 0 } }"
          class="bg-white rounded-2xl shadow-sm p-5 mb-6 space-y-4">
        @csrf
        <div>
            <p class="font-bold text-gray-800"><i class="fas fa-drumstick-bite text-rose-600"></i> Beneficiar pollo (pelar y trozar)</p>
            <p class="text-xs text-gray-500">Sale el pollo vivo del stock y entra lo que salió (pollo pelado, pechuga, menudencia…), con el costo del vivo repartido por kilo.
                Lo que falta (pluma, sangre) queda como merma del beneficio.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
            <label class="text-xs font-semibold text-gray-500">Pollo vivo que entra
                <select name="IdProducto" x-model.number="vivo" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                    <template x-for="p in productos" :key="p.id"><option :value="p.id" x-text="p.nombre + ' (stock ' + p.stock.toFixed(2) + ')'"></option></template>
                </select></label>
            <label class="text-xs font-semibold text-gray-500">Aves<input type="number" min="0" name="aves" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-center"></label>
            <label class="text-xs font-semibold text-gray-500">Kilos de pollo vivo<input type="number" step="0.001" min="0" name="kg_vivo" x-model.number="kgVivo" required class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right font-bold"></label>
        </div>
        <div>
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-bold text-gray-700">Lo que salió</p>
                <button type="button" @click="partes.push({ IdProducto: '', kg: null })" class="h-8 px-3 rounded-lg bg-rose-600 text-white text-xs font-bold">+ Parte</button>
            </div>
            <template x-for="(x, i) in partes" :key="i">
                <div class="grid grid-cols-12 gap-2 mb-1.5">
                    <select :name="`partes[${i}][IdProducto]`" x-model="x.IdProducto" class="col-span-7 h-10 rounded-lg border-gray-300 text-sm">
                        <option value="">— Elige —</option>
                        <template x-for="p in productos.filter(p => p.id !== vivo)" :key="p.id"><option :value="p.id" x-text="p.nombre"></option></template>
                    </select>
                    <input type="number" step="0.001" min="0" :name="`partes[${i}][kg]`" x-model.number="x.kg" placeholder="kg" class="col-span-4 h-10 rounded-lg border-gray-300 text-right font-bold">
                    <button type="button" @click="partes.splice(i, 1)" x-show="partes.length > 1" class="col-span-1 text-gray-300 hover:text-rose-600">✕</button>
                </div>
            </template>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
            <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-xs text-gray-500">Salió</p><p class="text-xl font-extrabold" x-text="salida.toFixed(3) + ' kg'"></p></div>
            <div class="rounded-xl bg-rose-50 p-3 text-center"><p class="text-xs text-rose-700">Merma (pluma, sangre)</p><p class="text-xl font-extrabold text-rose-700" x-text="Math.max(0, (kgVivo || 0) - salida).toFixed(3) + ' kg'"></p></div>
            <div class="rounded-xl p-3 text-center" :class="rendimiento >= 70 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"><p class="text-xs">Rendimiento</p><p class="text-xl font-extrabold" x-text="rendimiento.toFixed(1) + ' %'"></p></div>
        </div>
        <input name="observacion" maxlength="150" placeholder="Observación (opcional)" class="w-full h-10 rounded-xl border-gray-300 text-sm">
        <button class="h-11 px-6 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold">Registrar beneficio</button>
    </form>

    <form class="flex flex-wrap items-end gap-2 mb-3">
        <label class="text-xs text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="h-9 px-3 rounded-lg bg-gray-800 text-white text-sm font-semibold">Ver</button>
    </form>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Pollo vivo</th><th class="px-3 py-2 text-right">Aves</th>
                <th class="px-3 py-2 text-right">Kg vivo</th><th class="px-3 py-2 text-left">Salió</th><th class="px-3 py-2 text-right">Rendimiento</th><th class="px-3 py-2 text-right">Costo</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($beneficios as $b)
                    <tr><td class="px-3 py-2 text-gray-500">{{ \Carbon\Carbon::parse($b->fecha)->format('d/m H:i') }}</td><td class="px-3 py-2">{{ $b->pronom }}</td>
                        <td class="px-3 py-2 text-right">{{ $b->aves }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $b->kg_vivo, 2) }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ ($partes[$b->id] ?? collect())->map(fn ($x) => $x->pronom.' '.number_format((float) $x->kg, 2).' kg')->implode(' · ') }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $b->rendimiento, 1) }} %</td><td class="px-3 py-2 text-right">{{ number_format((float) $b->costo_total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">Sin beneficios en estas fechas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
