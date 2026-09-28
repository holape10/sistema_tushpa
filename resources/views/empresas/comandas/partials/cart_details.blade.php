@forelse ($cart as $item)
    <div class="cart-item" data-id="{{ $item['id'] }}" data-cantidad="{{ $item['cantidad'] }}" data-precio="{{ $item['precio'] }}">
        <span class="cart-item-name">{{ $item['nombre'] }}</span>
        <div class="cart-item-qty-control">
            <button class="btn-qty btn-minus">−</button>
            <input type="text" value="{{ $item['cantidad'] }}" readonly>
            <button class="btn-qty btn-plus">+</button>
        </div>
        <span class="cart-item-total-price">S/ {{ number_format($item['cantidad'] * $item['precio'], 2) }}</span>
        @if (empty($item['is_old_item']))
            <button class="cart-item-remove btn-quitar">Quitar</button>
        @else
            <span class="cart-item-old">Ya comandado</span>
        @endif
    </div>
@empty
    <p style="text-align: center; color: #888; padding: 20px 0;">Carrito vacío</p>
@endforelse