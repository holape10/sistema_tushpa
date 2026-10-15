@forelse ($cart as $item)
    @php
        $esViejo = !empty($item['is_old_item']);
        $agregado = $esViejo ? $item['cantidad'] - ($item['cantidad_original'] ?? $item['cantidad']) : 0;
        $facturado = (float) ($item['facturado'] ?? 0);
        $fmt = fn($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    @endphp
    <div class="cart-item rounded-2xl bg-white p-3 shadow-sm ring-1 {{ $esViejo ? 'ring-amber-200 border-l-4 border-amber-400' : 'ring-gray-200' }}"
         data-id="{{ $item['id'] }}" data-producto="{{ $item['IdProducto'] ?? $item['id'] }}"
         data-cantidad="{{ $item['cantidad'] }}" data-precio="{{ $item['precio'] }}"
         data-minima="{{ max($item['cantidad_minima'] ?? ($item['cantidad_original'] ?? 0), $facturado) }}"
         data-facturado="{{ $facturado }}" data-old="{{ $esViejo ? '1' : '0' }}">
        <div class="flex items-start gap-2">
            <p class="cart-item-name flex-1 min-w-0 text-sm font-bold leading-snug text-gray-800">
                {{ $item['nombre'] }}
                @if ($esViejo)<span class="ml-1 rounded bg-amber-400 px-1.5 py-0.5 text-[10px] font-black text-white">ENVIADO</span>@endif
                @if ($agregado > 0)<span class="ml-1 rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-black text-white">+{{ $fmt($agregado) }} nuevo</span>@endif
                @if ($facturado > 0)<span class="ml-1 rounded bg-gray-500 px-1.5 py-0.5 text-[10px] font-black text-white">{{ $fmt($facturado) }} cobrado</span>@endif
                @if (!empty($item['opciones_texto']))<span class="mt-1 block rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">{{ $item['opciones_texto'] }}</span>@endif
            </p>
            <span class="shrink-0 text-sm font-black text-indigo-700">S/ {{ number_format($item['cantidad'] * $item['precio'], 2) }}</span>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <button type="button" class="btn-minus h-10 w-10 rounded-xl bg-gray-100 text-xl font-black text-gray-700 active:bg-gray-200"
                    aria-label="Restar" title="{{ $esViejo ? 'Reducir (requiere autorización si baja de lo enviado)' : 'Restar' }}">−</button>
            <span class="w-10 text-center text-base font-black text-gray-900">{{ $fmt($item['cantidad']) }}</span>
            <button type="button" class="btn-plus h-10 w-10 rounded-xl bg-indigo-600 text-xl font-black text-white active:bg-indigo-700" aria-label="Sumar">+</button>
            @if (!$esViejo)
                <button type="button" class="btn-quitar ml-auto h-10 rounded-xl px-3 text-sm font-bold text-rose-600 active:bg-rose-50">Quitar</button>
            @elseif ($facturado <= 0)
                <button type="button" class="btn-quitar-autorizado ml-auto h-10 rounded-xl bg-amber-50 px-3 text-xs font-bold text-amber-700 active:bg-amber-100">Eliminar (autoriz.)</button>
            @endif
        </div>
        @if (!$esViejo)
            <input type="text" class="cart-obs mt-2 w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-base text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                   maxlength="200" placeholder="Observación (ej. sin cebolla)" value="{{ $item['observaciones'] ?? '' }}" autocomplete="off">
        @elseif (!empty($item['observaciones']))
            <p class="mt-1.5 text-xs italic text-gray-500">» {{ $item['observaciones'] }}</p>
        @endif
    </div>
@empty
    <div class="py-10 text-center text-gray-400">
        <p class="text-3xl">🧾</p>
        <p class="mt-1 text-sm">Toca un producto para agregarlo</p>
    </div>
@endforelse
