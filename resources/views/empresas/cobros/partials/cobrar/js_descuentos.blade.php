{{-- JS: descuento, cupón, propina, vuelto y cortesías (va dentro de cobrar.blade.php) --}}
    // ---------- Descuento a la cuenta ----------
    function montoDescuento() {
        if (cupon) return Math.round((cupon.tipo === 'P' ? BRUTO * cupon.valor / 100 : cupon.valor) * 100) / 100;
        if (!DSCTO) return 0;
        const v = parseFloat(el('dscto_valor').value) || 0;
        if (v <= 0) return 0;
        return Math.round((el('dscto_tipo').value === 'P' ? BRUTO * v / 100 : v) * 100) / 100;
    }
    // TOTAL = lo que se cobra menos el descuento; los medios de pago se vuelven a armar
    function aplicarDescuento() {
        const d = montoDescuento();
        TOTAL = Math.max(0, Math.round((BRUTO - d) * 100) / 100);
        el('lbl_total').textContent = fmt(TOTAL);
        el('total_comp').value = fmt(TOTAL);
        if (DSCTO || CUPONES) {
            const pct = BRUTO > 0 ? d / BRUTO * 100 : 0;
            if (DSCTO) el('dscto_clave_box').style.display = (!cupon && !ES_ADMIN && pct > MAX_CAJERO + 0.001) ? 'block' : 'none';
            el('dscto_info').textContent = d > 0 ? (cupon ? `Cupón ${cupon.codigo}: ` : 'Descuento: ') + `-S/ ${fmt(d)} (${pct.toFixed(1)}%)` + (d >= BRUTO ? ' · es mayor que la cuenta' : '') : '';
        }
        const partes = PROMOS_APLICADAS.map(p => `🎉 ${p.nombre}${p.veces > 1 ? ' ×' + p.veces : ''}: −S/ ${fmt(p.ahorro)}`);
        if (d > 0) partes.push(`Consumo S/ ${fmt(BRUTO)} − descuento S/ ${fmt(d)}`);
        if (GRATIS > 0) partes.push(`🎁 Cortesías S/ ${fmt(GRATIS)} (no se cobran)`);
        el('lbl_desglose').textContent = partes.join(' · ');
        medios = [];
        renderMedios();
        pintarPropina();
    }
    if (DSCTO) {
        ['dscto_valor', 'dscto_tipo'].forEach(id => el(id).addEventListener('input', aplicarDescuento));
        el('dscto_tipo').addEventListener('change', aplicarDescuento);
    }

    // ---------- Cupón ----------
    async function aplicarCupon() {
        const codigo = el('cupon_codigo').value.trim().toUpperCase();
        if (!codigo) { el('cupon_codigo').focus(); return; }
        el('cupon_error').style.display = 'none';
        el('btn_cupon').disabled = true;
        const r = await fetch(@json(route('cobros.cupon')) + `?codigo=${encodeURIComponent(codigo)}&total=${BRUTO}`, { headers: { Accept: 'application/json' } })
            .then(x => x.json()).catch(() => ({ ok: false, mensaje: 'Error de conexión con el servidor.' }));
        el('btn_cupon').disabled = false;
        if (!r.ok) {
            el('cupon_error').textContent = r.mensaje || (r.errors ? Object.values(r.errors)[0][0] : 'No se pudo revisar el cupón.');
            el('cupon_error').style.display = 'block';
            return;
        }
        cupon = { codigo: r.codigo, tipo: r.tipo, valor: r.valor };
        el('cupon_texto').textContent = '✔ ' + r.mensaje;
        el('cupon_box').style.display = 'none';
        el('cupon_info').style.display = 'block';
        if (DSCTO) { el('dscto_manual').style.display = 'none'; el('dscto_valor').value = ''; el('clave_admin').value = ''; }
        aplicarDescuento();
    }
    function quitarCupon() {
        if (!CUPONES) return;
        cupon = null;
        el('cupon_codigo').value = '';
        el('cupon_error').style.display = 'none';
        el('cupon_box').style.display = '';
        el('cupon_info').style.display = 'none';
        if (DSCTO) el('dscto_manual').style.display = '';
        aplicarDescuento();
    }
    if (CUPONES) {
        el('btn_cupon').addEventListener('click', aplicarCupon);
        el('btn_cupon_quitar').addEventListener('click', quitarCupon);
        el('cupon_codigo').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); aplicarCupon(); } });
    }

    // ---------- Propina (aparte del comprobante) y vuelto ----------
    const montoPropina = () => PROPINA ? Math.max(0, Math.round((parseFloat(el('propina').value) || 0) * 100) / 100) : 0;
    function pintarPropina() {
        if (PROPINA) {
            const p = montoPropina();
            el('propina_info').textContent = p > 0 ? `El cliente paga S/ ${fmt(TOTAL + p)} (cuenta S/ ${fmt(TOTAL)} + propina S/ ${fmt(p)})` : '';
        }
        actualizarVuelto();
    }
    function limpiarPropina() {
        if (!PROPINA) return;
        el('propina').value = '';
        document.querySelectorAll('.btn-propina').forEach(b => b.classList.remove('activa'));
        pintarPropina();
    }
    if (PROPINA) {
        document.querySelectorAll('.btn-propina').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.btn-propina').forEach(x => x.classList.remove('activa'));
            b.classList.add('activa');
            el('propina').value = fmt(TOTAL * Number(b.dataset.pct) / 100);
            pintarPropina();
        }));
        el('propina_quitar').addEventListener('click', limpiarPropina);
        el('propina').addEventListener('input', () => { document.querySelectorAll('.btn-propina').forEach(x => x.classList.remove('activa')); pintarPropina(); });
    }
    // "Paga con" en dólares: el vuelto sale en soles
    const pagaEnDolares = () => TC && el('paga_usd') && el('paga_usd').checked;
    const pagaSoles = () => {
        const v = parseFloat(el('paga').value) || 0;
        return pagaEnDolares() ? Math.round(v * TC * 100) / 100 : v;
    };
    function actualizarVuelto() {
        el('vuelto').value = fmt(Math.max(0, pagaSoles() - TOTAL - montoPropina()));
        if (TC) {
            el('paga_equiv').style.display = pagaEnDolares() && pagaSoles() > 0 ? 'block' : 'none';
            el('paga_equiv').textContent = `= S/ ${fmt(pagaSoles())}`;
        }
    }
    if (TC) {
        el('paga_usd').addEventListener('change', () => { el('paga_moneda').textContent = pagaEnDolares() ? 'US$' : 'S/'; actualizarVuelto(); });
    }

    // ---------- Modal de cortesía ----------
    let alDarCortesia = null;
    function abrirCortesia(nombre, maximo, listo) {
        alDarCortesia = listo;
        el('mc_producto').textContent = nombre;
        el('mc_cant_box').style.display = maximo > 1 ? 'block' : 'none';
        el('mc_cant').value = maximo;
        el('mc_cant').max = maximo;
        el('mc_motivo').value = '';
        document.querySelectorAll('.mc-motivo').forEach(b => b.classList.remove('activo'));
        el('modal_cortesia').style.display = 'flex';
        setTimeout(() => (maximo > 1 ? el('mc_cant') : el('mc_motivo')).focus(), 50);
    }
    function cerrarCortesia() { el('modal_cortesia').style.display = 'none'; alDarCortesia = null; }
    if (CORTESIAS) {
        document.querySelectorAll('.mc-motivo').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.mc-motivo').forEach(x => x.classList.remove('activo'));
            b.classList.add('activo');
            el('mc_motivo').value = b.dataset.motivo;
        }));
        el('mc_cancelar').addEventListener('click', cerrarCortesia);
        el('modal_cortesia').addEventListener('click', e => { if (e.target === el('modal_cortesia')) cerrarCortesia(); });
        el('mc_ok').addEventListener('click', () => {
            const max = parseFloat(el('mc_cant').max) || 1;
            const cant = Math.min(max, Math.max(0, parseFloat(el('mc_cant').value) || 0));
            if (cant <= 0) { alert('Escribe cuántos van de cortesía.'); return; }
            const motivo = el('mc_motivo').value.trim().toUpperCase() || 'CORTESÍA DE LA CASA';
            const listo = alDarCortesia;
            cerrarCortesia();
            if (listo) listo(cant, motivo);
        });
    }

    // Vuelto
    el('paga').addEventListener('input', actualizarVuelto);

