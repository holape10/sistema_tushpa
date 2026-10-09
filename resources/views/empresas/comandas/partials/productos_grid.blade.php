@forelse ($productos as $p)
    @php
        $pres = $presentaciones[$p->IdProducto] ?? collect();
        $precioBase = $precios[$p->IdProducto] ?? $p->propun;
        // Sin precio propio, la presentación vale precio base x factor
        $presJson = $pres->map(fn ($x) => ['id' => $x['id'], 'nombre' => $x['nombre'], 'precio' => $x['precio'] > 0 ? $x['precio'] : round($precioBase * $x['factor'], 2)])->values()->toJson();
    @endphp
    <div class="product-item-kiosko" data-id="{{ $p->IdProducto }}" data-nombre="{{ $p->pronom }}" data-precio="{{ $precios[$p->IdProducto] ?? $p->propun }}"
         @if ($pres->isNotEmpty()) data-presentaciones="{{ $presJson }}" @endif>
        <div class="product-name-kiosko">{{ $p->pronom }}</div>
        <div class="product-price-kiosko">S/. {{ number_format($precios[$p->IdProducto] ?? $p->propun, 2) }}</div>
        @if ($pres->isNotEmpty())
            <div class="product-pres-kiosko">📦 {{ $pres->pluck('nombre')->implode(' · ') }}</div>
        @endif
        <div class="product-stock-kiosko">Stock: {{ (int) ($p->stock_disponible ?? 0) }}</div>
    </div>
@empty
    <p style="grid-column: 1 / -1; text-align: center; color: #888;">Sin productos en esta categoría</p>
@endforelse