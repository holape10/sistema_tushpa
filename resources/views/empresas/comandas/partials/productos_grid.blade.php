@forelse ($productos as $p)
    @php
        $pres = $presentaciones[$p->IdProducto] ?? collect();
        $precioBase = $precios[$p->IdProducto] ?? $p->propun;
        // Sin precio propio, la presentación vale precio base x factor
        $presJson = $pres->map(fn ($x) => ['id' => $x['id'], 'nombre' => $x['nombre'], 'precio' => $x['precio'] > 0 ? $x['precio'] : round($precioBase * $x['factor'], 2)])->values()->toJson();
        $q = isset($quedan[$p->IdProducto]) ? max(0, $quedan[$p->IdProducto]) : null;
    @endphp
    <button type="button"
        class="product-item-kiosko relative flex min-h-[96px] flex-col rounded-2xl bg-white p-3 text-left shadow-sm ring-1 ring-gray-200 active:scale-[.97] transition-transform select-none {{ $q === 0.0 || $q === 0 ? 'opacity-60' : '' }}"
        data-id="{{ $p->IdProducto }}" data-nombre="{{ $p->pronom }}" data-precio="{{ $precioBase }}"
        @if ($pres->isNotEmpty()) data-presentaciones="{{ $presJson }}" @endif @if (isset($conOpciones[$p->IdProducto])) data-opciones="1" @endif>
        @if (isset($fotos[$p->IdProducto]))
            {{-- Foto pequeña (miniatura liviana) al lado del nombre: el botón casi no crece --}}
            <span class="flex items-start gap-2">
                <span class="flex-1 min-w-0 text-sm font-bold leading-snug text-gray-800 line-clamp-3">{{ $p->pronom }}</span>
                <img src="{{ $fotos[$p->IdProducto] }}" alt="" loading="lazy" decoding="async" width="44" height="44" class="h-11 w-11 shrink-0 rounded-xl object-cover bg-gray-100">
            </span>
        @else
            <span class="text-sm font-bold leading-snug text-gray-800 line-clamp-3">{{ $p->pronom }}</span>
        @endif
        <span class="mt-auto pt-1.5 text-base font-black text-indigo-700">S/ {{ number_format($precioBase, 2) }}</span>
        @if (isset($conOpciones[$p->IdProducto]))
            <span class="mt-1 self-start rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">🍽️ Elige opciones</span>
        @endif
        @if ($pres->isNotEmpty())
            <span class="mt-1 self-start rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-bold text-indigo-700 line-clamp-1">📦 {{ $pres->pluck('nombre')->implode(' · ') }}</span>
        @endif
        @if ($q !== null)
            <span class="text-[11px] font-extrabold {{ $q > 0 ? ($q <= 3 ? 'text-amber-600' : 'text-emerald-600') : 'text-rose-600' }}">{{ $q > 0 ? 'Quedan '.rtrim(rtrim(number_format($q, 2), '0'), '.').' hoy' : 'AGOTADO HOY' }}</span>
        @elseif (in_array((int) $p->promocion, [0, 4], true))
            <span class="text-[11px] text-emerald-600">Stock: {{ (int) ($p->stock_disponible ?? 0) }}</span>
        @endif
        {{-- Cuántos van en el pedido (lo pone la pantalla) --}}
        <span class="en-pedido absolute -top-2 -right-2 hidden min-w-[26px] h-[26px] items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-black text-white shadow"></span>
    </button>
@empty
    <p class="col-span-full py-10 text-center text-gray-400">Sin productos aquí</p>
@endforelse
