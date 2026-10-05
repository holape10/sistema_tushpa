@forelse ($cart as $item)
    @php
        $esViejo = !empty($item['is_old_item']);
        $agregado = $esViejo ? $item['cantidad'] - ($item['cantidad_original'] ?? $item['cantidad']) : 0;
        $facturado = (float) ($item['facturado'] ?? 0);
        $fmt = fn($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    @endphp
    <div class="cart-item {{ $esViejo ? 'cart-item-enviado' : '' }}" data-id="{{ $item['id'] }}" data-producto="{{ $item['IdProducto'] ?? $item['id'] }}"
         data-cantidad="{{ $item['cantidad'] }}" data-precio="{{ $item['precio'] }}"
         data-minima="{{ max($item['cantidad_minima'] ?? ($item['cantidad_original'] ?? 0), $facturado) }}"
         data-facturado="{{ $facturado }}" data-old="{{ $esViejo ? '1' : '0' }}">
        <span class="cart-item-name">
            {{ $item['nombre'] }}
            @if ($esViejo)<small class="badge-enviado">ENVIADO</small>@endif
            @if ($agregado > 0)<small class="badge-nuevo">+{{ $fmt($agregado) }} nuevo</small>@endif
            @if ($facturado > 0)<small class="badge-cobrado">{{ $fmt($facturado) }} cobrado</small>@endif
        </span>
        <div class="cart-item-qty-control">
            <button class="btn-qty btn-minus" title="{{ $esViejo ? 'Reducir (requiere autorización si baja de lo enviado)' : 'Restar' }}">−</button>
            <input type="text" value="{{ $fmt($item['cantidad']) }}" readonly>
            <button class="btn-qty btn-plus" title="Sumar">+</button>
        </div>
        <span class="cart-item-total-price">S/ {{ number_format($item['cantidad'] * $item['precio'], 2) }}</span>
        @if (!$esViejo)
            <button class="cart-item-remove btn-quitar">Quitar</button>
            <input type="text" class="cart-obs" maxlength="200" placeholder="Observación (ej. sin cebolla)" value="{{ $item['observaciones'] ?? '' }}">
        @else
            @if ($facturado <= 0)
                <button class="cart-item-remove btn-quitar-autorizado" style="background:#f0ad4e;">Eliminar (autoriz.)</button>
            @endif
            @if (!empty($item['observaciones']))
                <small class="cart-obs-texto">{{ $item['observaciones'] }}</small>
            @endif
        @endif
    </div>
@empty
    <p style="text-align: center; color: #888; padding: 20px 0;">Carrito vacío</p>
@endforelse
