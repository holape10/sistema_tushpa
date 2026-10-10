{{-- Platos (Preparados): lleva entrada, puede ir en un trío, o es un "arma tu trío" --}}
@php
    $p = $producto ?? null;
    $trio = (int) old('trio_cantidad', $p->trio_cantidad ?? 0);
@endphp
<section class="bg-white rounded-2xl shadow-sm" x-show="tipo == '2'" x-cloak x-data="{ esTrio: @js((bool) old('es_trio', $trio > 0)) }">
    <header class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-800">🍽️ Menú y tríos <span class="text-xs font-normal text-gray-400">(opcional)</span></h3>
        <p class="text-xs text-gray-400">Marca solo lo que corresponda a este plato.</p>
    </header>
    <div class="p-5 space-y-3">
        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="lleva_entrada" value="1" @checked(old('lleva_entrada', $p->lleva_entrada ?? false)) class="mt-0.5 rounded text-indigo-600">
            <span><span class="font-bold text-gray-800">🥗 Lleva entrada</span>
                <span class="block text-xs text-gray-500">Es un plato de menú: en la comanda el mozo elige la entrada (sopa, tequeños…).
                    Las entradas se crean en <a href="{{ url('/entradas') }}" target="_blank" class="text-indigo-600 underline">Restaurante › Entradas</a>.</span></span>
        </label>
        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="en_trio" value="1" @checked(old('en_trio', $p->en_trio ?? false)) class="mt-0.5 rounded text-indigo-600">
            <span><span class="font-bold text-gray-800">🐟 Puede ir en un trío</span>
                <span class="block text-xs text-gray-500">Este plato aparece para elegir cuando piden un "Arma tu trío".</span></span>
        </label>
        <div class="p-3 rounded-xl border border-gray-200">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="es_trio" value="1" x-model="esTrio" class="mt-0.5 rounded text-indigo-600">
                <span><span class="font-bold text-gray-800">🍱 Este plato es un "Arma tu trío"</span>
                    <span class="block text-xs text-gray-500">En la comanda el mozo elige los platos que quiere el cliente (de los marcados "puede ir en un trío"). No maneja stock.</span></span>
            </label>
            <label x-show="esTrio" x-cloak class="mt-2 ml-7 flex items-center gap-2 text-sm text-gray-700">El cliente elige
                <select name="trio_cantidad" class="h-9 rounded-lg border-gray-300 text-sm font-bold">
                    @foreach ([2, 3, 4, 5] as $n)<option value="{{ $n }}" @selected(($trio ?: 3) === $n)>{{ $n }}</option>@endforeach
                </select> platos distintos</label>
        </div>
    </div>
</section>
