{{-- JS: punto de venta (venta directa): carrito y productos (va dentro de cobrar.blade.php) --}}
    // ---------- Punto de venta (venta directa) ----------
    let carrito = [];

    function pintarCarrito() {
        const tb = el('carrito');
        if (!tb) return;
        if (!carrito.length) {
            tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Toca un producto para agregarlo.</td></tr>';
        } else {
            tb.innerHTML = '';
            carrito.forEach((it, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td><span class="nom"></span><input class="cart-obs" maxlength="100" placeholder="nota para cocina"></td>
                    <td><div class="cart-ctrl"><button type="button" class="c-menos">−</button><input type="number" class="c-cant" min="1" step="1"><button type="button" class="c-mas">+</button></div></td>
                    <td class="text-right"></td><td class="text-right"><strong></strong></td>
                    <td class="text-center" style="white-space:nowrap;">${CORTESIAS ? '<button type="button" class="btn-cortesia c-cort" title="Cortesía (gratis)">🎁</button> ' : ''}<button type="button" class="btn btn-danger btn-xs c-quitar">&times;</button></td>`;
                tr.querySelector('.nom').textContent = it.nombre + (it.cortesia ? '  🎁 CORTESÍA · ' + it.motivo : '');
                tr.classList.toggle('es-cortesia', !!it.cortesia);
                tr.querySelector('.c-cort')?.classList.toggle('activa', !!it.cortesia);
                tr.querySelector('.cart-obs').value = it.observacion;
                tr.querySelector('.c-cant').value = it.cantidad;
                tr.children[2].textContent = fmt(it.precio);
                tr.querySelector('strong').textContent = fmt(it.cortesia ? 0 : it.cantidad * it.precio);
                tr.querySelector('.c-menos').onclick = () => { if (it.cantidad > 1) { it.cantidad--; pintarCarrito(); } };
                tr.querySelector('.c-mas').onclick = () => { it.cantidad++; pintarCarrito(); };
                tr.querySelector('.c-cant').onchange = e => { it.cantidad = Math.max(1, parseFloat(e.target.value) || 1); pintarCarrito(); };
                tr.querySelector('.cart-obs').onchange = e => { it.observacion = e.target.value; };
                tr.querySelector('.c-quitar').onclick = () => { carrito.splice(i, 1); pintarCarrito(); };
                const cort = tr.querySelector('.c-cort');
                if (cort) cort.onclick = () => {
                    if (it.cortesia) { it.cortesia = false; it.motivo = ''; pintarCarrito(); return; }
                    abrirCortesia(it.nombre, 1, (n, motivo) => { it.cortesia = true; it.motivo = motivo; pintarCarrito(); });
                };
                tb.appendChild(tr);
            });
        }
        BRUTO = Math.round(carrito.reduce((a, it) => a + (it.cortesia ? 0 : it.cantidad * it.precio), 0) * 100) / 100;
        GRATIS = Math.round(carrito.reduce((a, it) => a + (it.cortesia ? it.cantidad * it.precio : 0), 0) * 100) / 100;
        aplicarDescuento();
    }

    function cargarProductos(catId = null, texto = '') {
        const params = new URLSearchParams({ vista: 'pv' });
        if (texto) params.set('search_text', texto); else if (catId) params.set('category_id', catId);
        fetch(`{{ route('comandas.search_products') }}?${params}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json()).then(d => el('productos_grid').innerHTML = d.vista);
    }

