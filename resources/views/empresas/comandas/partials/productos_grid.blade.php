@forelse ($productos as $p)
    <div class="product-item-kiosko" data-id="{{ $p->IdProducto }}" data-nombre="{{ $p->pronom }}" data-precio="{{ $p->propun }}">
        <div class="product-name-kiosko">{{ $p->pronom }}</div>
        <div class="product-price-kiosko">S/. {{ number_format($p->propun, 2) }}</div>
        <div class="product-stock-kiosko">Stock: {{ (int) ($p->stock_disponible ?? 0) }}</div>
    </div>
@empty
    <p style="grid-column: 1 / -1; text-align: center; color: #888;">Sin productos en esta categoría</p>
@endforelse