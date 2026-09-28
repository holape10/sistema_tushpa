<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda - Sistema Tushpa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .header-kiosko {
            background-color: #3498db; color: white; padding: 10px; text-align: center;
            font-size: 1.1em; font-weight: bold; margin-bottom: 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .header-kiosko .back-button { background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; padding: 6px; }
        .main-content { display: flex; flex-wrap: wrap; }
        .menu-section { flex: 2; padding: 10px; border-right: 1px solid #eee; min-width: 300px; }
        .cart-section { flex: 1; padding: 10px; background-color: #e9ecef; border-radius: 8px; min-width: 280px; }

        .category-buttons-container-kiosko { display: flex; flex-wrap: wrap; gap: 8px; padding: 2px; margin-bottom: 10px; }
        .btn-category-kiosko {
            height: 40px; padding: 0 14px; font-size: 1.0em; font-weight: bold; color: white;
            border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none;
            display: flex; align-items: center; justify-content: center; white-space: nowrap;
        }
        .btn-category-kiosko.active { border: 3px solid #f0ad4e; }

        .product-search-box-kiosko { padding: 10px; background-color: #6c757d; color: white; font-weight: bold; text-align: center; border-radius: 5px; margin-bottom: 15px; }
        .product-search-box-kiosko input { border-radius: 5px; padding: 10px; font-size: 1.1em; border: none; width: 100%; }

        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; padding: 5px; max-height: calc(100vh - 300px); overflow-y: auto; }
        .product-item-kiosko {
            border: 1px solid #ddd; border-radius: 8px; padding: 8px; background-color: #fff;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer;
            transition: transform 0.1s ease; height: 110px;
        }
        .product-item-kiosko:hover { transform: translateY(-3px); box-shadow: 0 5px 10px rgba(0,0,0,0.15); }
        .product-item-kiosko .product-name-kiosko { font-size: 0.9em; font-weight: bold; color: #333; line-height: 1.2; }
        .product-item-kiosko .product-price-kiosko { font-size: 1.05em; font-weight: bold; color: #007bff; margin-top: 4px; }
        .product-item-kiosko .product-stock-kiosko { font-size: 0.75em; color: #28a745; }

        .cart-header { font-size: 1.4em; font-weight: bold; color: #333; margin-bottom: 15px; text-align: center; }
        .cart-items-container { max-height: calc(100vh - 400px); overflow-y: auto; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 10px; }
        .cart-item { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 10px; background: #fff; padding: 10px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .cart-item-name { flex: 1; font-size: 1.0em; font-weight: bold; color: #333; min-width: 130px; }
        .cart-item-qty-control { display: flex; align-items: center; }
        .cart-item-qty-control input { width: 45px; text-align: center; font-size: 1em; margin: 0 5px; border: 1px solid #ccc; border-radius: 4px; padding: 4px 0; }
        .btn-qty { padding: 3px 9px; font-size: 1.1em; border-radius: 4px; background-color: #007bff; color: white; border: none; }
        .cart-item-total-price { font-weight: bold; margin-left: 10px; color: #007bff; }
        .cart-item-remove { background-color: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 5px; margin-left: auto; font-size: 0.85em; }
        .cart-item-old { background-color: #f2dede; color: #843534; font-size: 0.8em; padding: 4px 8px; border-radius: 5px; margin-left: auto; }

        .cart-total { font-size: 1.6em; font-weight: bold; text-align: right; margin-top: 10px; color: #28a745; }
        .cart-actions { text-align: center; margin-top: 15px; }
        .cart-actions .btn { font-size: 1.2em; padding: 15px; margin: 5px 0; border-radius: 8px; width: 100%; }
        .btn-send-order { background-color: #28a745; color: white; }
        .btn-clear-cart { background-color: #dc3545; color: white; }

        @media (max-width: 991px) {
            .menu-section, .cart-section { flex-basis: 100%; border-right: none; margin-bottom: 20px; }
            .menu-section { order: 2; }
            .cart-section { order: 1; }
        }
    </style>
</head>
<body>
    <div class="header-kiosko">
        <button class="back-button" onclick="window.location.href='{{ route('comandas.seleccion') }}'">
            <i class="fas fa-arrow-left"></i>
        </button>
        <span>
            TU PEDIDO -
            @if ($order_type == 'salon')
                MESA: {{ $mesa_info['nombre'] ?? '' }}
            @else
                {{ strtoupper($order_type) }}
            @endif
        </span>
        <span></span>
    </div>

    <div class="container-fluid main-content">
        <div class="menu-section">
            <div class="category-buttons-container-kiosko">
                @foreach ($categorias as $cat)
                    <button type="button" class="btn-category-kiosko {{ $cat->cat_id == $cat_default_id ? 'active' : '' }}"
                        data-cat-id="{{ $cat->cat_id }}" style="background-color: {{ $cat->color ?? '#3f4aee' }};">
                        {{ $cat->cat_nom }}
                    </button>
                @endforeach
            </div>

            <div class="product-search-box-kiosko">
                PRODUCTOS
                <input type="text" id="buscar_producto" placeholder="BUSCAR PRODUCTO">
            </div>

            <div id="productos_grid" class="products-grid"></div>
        </div>

        <div class="cart-section">
            <div class="cart-header">Tu Carrito</div>
            <div id="cart_items" class="cart-items-container">
                @include('empresas.comandas.partials.cart_details', ['cart' => $cart])
            </div>
            <div class="cart-total" id="cart_total">
                Total: S/ {{ number_format(collect($cart)->sum(fn($i) => $i['cantidad'] * $i['precio']), 2) }}
            </div>
            <div class="cart-actions">
                <button id="btn_enviar" class="btn btn-send-order"><strong>ENVIAR COMANDA</strong></button>
                <button id="btn_vaciar" class="btn btn-clear-cart"><strong>VACIAR PEDIDO</strong></button>
            </div>
        </div>
    </div>

    <script>
        function cargarProductos(catId = null, texto = '') {
            const params = new URLSearchParams();
            if (texto) params.set('search_text', texto); else if (catId) params.set('category_id', catId);
            fetch(`{{ route('comandas.search_products') }}?${params}`)
                .then(r => r.json())
                .then(data => document.getElementById('productos_grid').innerHTML = data.vista);
        }
        cargarProductos({{ $cat_default_id ?? 'null' }});

        document.querySelectorAll('.btn-category-kiosko').forEach(b => b.addEventListener('click', function () {
            document.querySelectorAll('.btn-category-kiosko').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('buscar_producto').value = '';
            cargarProductos(this.dataset.catId);
        }));

        let timer;
        document.getElementById('buscar_producto').addEventListener('keyup', function () {
            clearTimeout(timer);
            const val = this.value;
            timer = setTimeout(() => cargarProductos(null, val), 400);
        });

        function recargarCarrito() {
            fetch("{{ route('comandas.get_cart_details') }}")
                .then(r => r.json())
                .then(data => {
                    document.getElementById('cart_items').innerHTML = data.vista;
                    actualizarTotal();
                });
        }

        function actualizarTotal() {
            let total = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                total += parseFloat(item.dataset.cantidad) * parseFloat(item.dataset.precio);
            });
            document.getElementById('cart_total').textContent = 'Total: S/ ' + total.toFixed(2);
        }

        document.addEventListener('click', function (e) {
            const card = e.target.closest('.product-item-kiosko');
            if (card) {
                fetch("{{ route('comandas.add_to_cart') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ id: card.dataset.id, producto: card.dataset.nombre, precio: card.dataset.precio })
                }).then(recargarCarrito);
            }

            const plus = e.target.closest('.btn-plus');
            if (plus) {
                const item = plus.closest('.cart-item');
                actualizarItem(item.dataset.id, parseInt(item.dataset.cantidad) + 1, item.querySelector('.cart-obs')?.value || '');
            }

            const minus = e.target.closest('.btn-minus');
            if (minus) {
                const item = minus.closest('.cart-item');
                const nueva = parseInt(item.dataset.cantidad) - 1;
                if (nueva >= 1) actualizarItem(item.dataset.id, nueva, item.querySelector('.cart-obs')?.value || '');
            }

            const del = e.target.closest('.btn-quitar');
            if (del) {
                const item = del.closest('.cart-item');
                fetch("{{ route('comandas.remove_cart_item') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ id: item.dataset.id })
                }).then(r => r.json()).then(res => {
                    if (!res.success) alert(res.message);
                    recargarCarrito();
                });
            }
        });

        function actualizarItem(id, cantidad, obs) {
            fetch("{{ route('comandas.update_cart_item') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ id, cantidad, observaciones: obs })
            }).then(recargarCarrito);
        }

        document.getElementById('btn_enviar').addEventListener('click', function () {
            this.disabled = true;
            this.textContent = 'ENVIANDO...';
            fetch("{{ route('comandas.enviar') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({})
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    alert('Comanda enviada correctamente.');
                    window.location.href = "{{ route('comandas.seleccion') }}";
                } else {
                    alert(res.message);
                    this.disabled = false;
                    this.textContent = 'ENVIAR COMANDA';
                }
            });
        });

        document.getElementById('btn_vaciar').addEventListener('click', function () {
            if (!confirm('¿Vaciar los productos nuevos del pedido?')) return;
            fetch("{{ route('comandas.clear_cart') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(recargarCarrito);
        });
    </script>
</body>
</html>