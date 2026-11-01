{{-- JS: registrar el cobro (sin cobros dobles) e imprimir (va dentro de cobrar.blade.php) --}}
    // Clave única de cada cobro: si el mismo cobro llega dos veces al servidor (doble clic o doble toque), el segundo se descarta
    const nuevaClave = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    let claveCobro = nuevaClave();
    let cobrando = false;

    async function registrarDirecta(body, contado, restaurar) {
        const datos = Object.assign({}, body, {
            items: carrito.map(it => ({ id: it.id, cantidad: it.cantidad, precio: it.precio, observacion: it.observacion || null,
                ...(it.cortesia ? { cortesia: 1, motivo_cortesia: it.motivo } : {}) })),
            enviar_cocina: el('enviar_cocina').checked ? 1 : 0,
        });
        delete datos.ped_id;
        delete datos.separadas;
        try {
            const res = await fetch("{{ route('cobros.directa.registrar') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(datos),
            });
            const d = await res.json();
            if (res.status === 422) { alert(Object.values(d.errors)[0][0]); restaurar(); return; }
            if (d.estado === 'duplicado') { alert(d.mensaje + ' Revisa en Ventas si ya salió el comprobante.'); restaurar(); return; }
            if (d.estado !== 'success') { alert(d.mensaje || 'No se pudo registrar la venta.'); restaurar(); return; }
            claveCobro = nuevaClave();

            // Sin agente de impresión: el ticket se imprime con el navegador sin salir de la pantalla
            if (el('imprimir').checked && !d.impreso) el('marco_impresion').src = d.ticket + '&imprimir=1&t=' + Date.now();
            el('aviso_venta').innerHTML = `✔ <b>${d.numero}</b> registrada por S/ ${fmt(d.total)}` + (d.vuelto > 0 ? ` · Vuelto <b>S/ ${fmt(d.vuelto)}</b>` : '')
                + (d.impreso ? ' · 🖨 enviada a la impresora' : '');
            el('aviso_venta').style.display = 'block';

            // Lista para la siguiente venta
            carrito = [];
            pintarCarrito();
            el('paga').value = '0.00';
            el('observaciones').value = '';
            if (DSCTO) { el('dscto_valor').value = ''; el('dscto_motivo').value = ''; el('clave_admin').value = ''; }
            if (PROPINA) { el('propina').value = ''; document.querySelectorAll('.btn-propina').forEach(b => b.classList.remove('activa')); }
            if (TC) { el('paga_usd').checked = false; el('paga_moneda').textContent = 'S/'; el('med_usd').checked = false; }
            if (CUPONES) quitarCupon(); else aplicarDescuento();
            llenarCliente({ num: '00000000', nom: 'VENTA AL PORTADOR', dir: '--', tdicod: '1' });
            el('msg_cliente').textContent = '';
            restaurar();
        } catch (e) {
            alert('Error de conexión con el servidor.');
            restaurar();
        }
    }

    if (DIRECTA) {
        document.addEventListener('click', e => {
            const card = e.target.closest('.product-item-kiosko');
            if (card) {
                const id = Number(card.dataset.id);
                const ya = carrito.find(it => it.id === id && !it.cortesia);
                if (ya) ya.cantidad++; else carrito.push({ id, nombre: card.dataset.nombre, precio: parseFloat(card.dataset.precio), cantidad: 1, observacion: '' });
                pintarCarrito();
                return;
            }
            const cat = e.target.closest('.cat-btn');
            if (cat) {
                document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
                cat.classList.add('active');
                el('buscar_producto').value = '';
                cargarProductos(cat.dataset.catId);
            }
        });
        let tBusca;
        el('buscar_producto').addEventListener('input', function () {
            clearTimeout(tBusca);
            const v = this.value.trim();
            tBusca = setTimeout(() => cargarProductos(null, v), 300);
        });
        const primera = document.querySelector('.cat-btn');
        if (primera) { primera.classList.add('active'); cargarProductos(primera.dataset.catId); } else { cargarProductos(); }
        pintarCarrito();
    }

    if (!DIRECTA) recalcular();

    // Registrar
    el('btnRegistrar').addEventListener('click', async function () {
        const btn = this;
        if (cobrando || btn.disabled) return;
        const contado = el('estadopago').selectedOptions[0].dataset.tipo === 'CONTADO';

        if (contado && medios.length) {
            const suma = medios.reduce((a, m) => a + m.monto, 0);
            if (Math.abs(suma - TOTAL) > 0.01) {
                alert(`¡REVISAR MEDIO DE PAGO!\n\nTotal a cobrar: S/ ${fmt(TOTAL)}\nIngresado: S/ ${fmt(suma)}\nDiferencia: S/ ${fmt(TOTAL - suma)}`);
                return;
            }
        }

        if (SEPARADAS && TOTAL <= 0) { alert('Elige qué productos se cobran en esta cuenta.'); return; }
        if (EXIGE_OP && contado && !medios.length && !esEfectivo(el('med_pag').selectedOptions[0]) && !el('op_med_pag').value.trim()) {
            alert('Escribe el número de operación del pago con ' + el('med_pag').selectedOptions[0].dataset.nom + '.');
            el('op_med_pag').focus();
            return;
        }
        if (DIRECTA && !carrito.length) { alert('Agrega al menos un producto.'); return; }
        if (CUPONES && !cupon && el('cupon_codigo').value.trim()) { alert('Escribiste un cupón pero no lo aplicaste: toca "Aplicar" o bórralo.'); el('cupon_codigo').focus(); return; }
        if (!cupon && DSCTO && montoDescuento() > 0) {
            if (montoDescuento() >= BRUTO) { alert('El descuento no puede ser igual o mayor que la cuenta.'); el('dscto_valor').focus(); return; }
            if (el('dscto_clave_box').style.display !== 'none' && !el('clave_admin').value) { alert('Este descuento necesita la clave de un administrador.'); el('clave_admin').focus(); return; }
        }
        cobrando = true;
        btn.disabled = true;
        btn.textContent = 'PROCESANDO...';
        const restaurar = () => { cobrando = false; btn.disabled = false; btn.textContent = DIRECTA ? 'REGISTRAR VENTA' : (SEPARADAS ? 'COBRAR ESTA CUENTA' : 'REGISTRAR Y COBRAR'); };

        const body = {
            ped_id: PED_ID,
            clave_cobro: claveCobro,
            ...(el('promotor_id') ? { promotor_id: el('promotor_id').value || null } : {}),
            ...(el('mozo_id') ? { mozo_id: el('mozo_id').value || null } : {}),
            tdocod: el('tdocod').value,
            estadopago: el('estadopago').value,
            fecEmi: el('fecEmi').value,
            fecVen: el('fecVen').value,
            consumo: el('consumo').value,
            tdicod: el('tdicod').value,
            clinum: el('clinum').value.trim(),
            clinom: el('clinom').value.trim(),
            clidir: el('clidir').value,
            clicor: el('clicor').value,
            telefono: el('telefono').value,
            observaciones: el('observaciones').value,
            paga: pagaSoles(),
            imprimir: el('imprimir').checked ? 1 : 0,
            // Si no agregó medios, se cobra todo con el medio que está seleccionado en la lista (no con el predeterminado)
            id_med_pag: contado ? (medios.length ? medios.map(m => m.id) : [el('med_pag').value]) : [],
            mon_med_pag: contado ? (medios.length ? medios.map(m => m.monto) : [TOTAL]) : [],
            op_med_pag: contado ? (medios.length ? medios.map(m => m.op || '') : [EXIGE_OP ? el('op_med_pag').value.trim() : '']) : [],
            usd_med_pag: contado && TC ? (medios.length ? medios.map(m => m.usd ? 1 : 0) : [el('med_usd').checked ? 1 : 0]) : [],
            ...(montoPropina() > 0 ? { propina: montoPropina(), propina_medio: el('propina_medio').value } : {}),
            // Cuentas separadas: { clave: cantidad } de lo que paga esta persona (null = todo lo pendiente)
            separadas: SEPARADAS ? seleccionSeparada() : null,
            // Cortesías de la mesa y descuento a la cuenta (el servidor revisa permisos y la clave del administrador)
            cortesias: cortesias,
            ...(cupon ? { cupon: cupon.codigo } : (DSCTO && montoDescuento() > 0 ? {
                descuento_tipo: el('dscto_tipo').value, descuento_valor: parseFloat(el('dscto_valor').value) || 0,
                descuento_motivo: el('dscto_motivo').value.trim(), clave_admin: el('clave_admin').value || null,
            } : {})),
        };

        if (DIRECTA) {
            await registrarDirecta(body, contado, restaurar);
            return;
        }

        try {
            const res = await fetch("{{ route('cobros.registrar') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(body)
            });
            const data = await res.json();

            if (res.status === 422) { alert(Object.values(data.errors)[0][0]); restaurar(); return; }
            if (data.estado === 'success') { window.location.href = data.redirect; return; }
            if (DSCTO && /clave/i.test(data.mensaje || '')) { el('clave_admin').value = ''; el('dscto_clave_box').style.display = 'block'; }
            if (data.estado === 'duplicado') { alert(data.mensaje + ' Actualiza la pantalla para ver si ya quedó cobrado.'); restaurar(); return; }
            alert(data.mensaje || 'No se pudo registrar el cobro.');
            restaurar();
        } catch (e) {
            alert('Error de conexión con el servidor.');
            restaurar();
        }
    });
