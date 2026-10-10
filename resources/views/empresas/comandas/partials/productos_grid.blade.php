@forelse ($productos as $p)
    @php
        $pres = $presentaciones[$p->IdProducto] ?? collect();
        $precioBase = $precios[$p->IdProducto] ?? $p->propun;
        // Sin precio propio, la presentación vale precio base x factor
        $presJson = $pres->map(fn ($x) => ['id' => $x['id'], 'nombre' => $x['nombre'], 'precio' => $x['precio'] > 0 ? $x['precio'] : round($precioBase * $x['factor'], 2)])->values()->toJson();
    @endphp
    <div class="product-item-kiosko" data-id="{{ $p->IdProducto }}" data-nombre="{{ $p->pronom }}" data-precio="{{ $precios[$p->IdProducto] ?? $p->propun }}"
         @if ($pres->isNotEmpty()) data-presentaciones="{{ $presJson }}" @endif @if (isset($conOpciones[$p->IdProducto])) data-opciones="1" @endif>
        <div class="product-name-kiosko">{{ $p->pronom }}</div>
        <div class="product-price-kiosko">S/. {{ number_format($precios[$p->IdProducto] ?? $p->propun, 2) }}</div>
        @if (isset($conOpciones[$p->IdProducto]))
            <div class="product-pres-kiosko" style="background:#fef3c7;color:#92400e">🍽️ Elige opciones</div>
        @endif
        @if ($pres->isNotEmpty())
            <div class="product-pres-kiosko">📦 {{ $pres->pluck('nombre')->implode(' · ') }}</div>
        @endif
        @if (isset($quedan[$p->IdProducto]))
            @php $q = max(0, $quedan[$p->IdProducto]); @endphp
            <div class="product-stock-kiosko" style="font-weight:800;color:{{ $q > 0 ? ($q <= 3 ? '#d97706' : '#16a34a') : '#dc2626' }}">{{ $q > 0 ? 'Quedan '.rtrim(rtrim(number_format($q, 2), '0'), '.').' hoy' : 'AGOTADO HOY' }}</div>
        @elseif (in_array((int) $p->promocion, [0, 4], true))
            <div class="product-stock-kiosko">Stock: {{ (int) ($p->stock_disponible ?? 0) }}</div>
        @endif
    </div>
@empty
    <p style="grid-column: 1 / -1; text-align: center; color: #888;">Sin productos en esta categoría</p>
@endforelse