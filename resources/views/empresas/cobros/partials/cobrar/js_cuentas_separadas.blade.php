{{-- JS: cuentas separadas, total a cobrar y cortesías por fila (va dentro de cobrar.blade.php) --}}
    // ---------- Cuentas separadas ----------
    function seleccionSeparada() {
        const sel = {};
        document.querySelectorAll('tr[data-clave]').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.sep-cant')?.value) || 0;
            if (cant > 0) sel[tr.dataset.clave] = cant;
        });
        return sel;
    }

    /** Cantidad que se cobra de una fila: todo lo pendiente, o lo elegido en cuentas separadas */
    function cantidadDe(tr) {
        const inp = tr.querySelector('.sep-cant');
        const max = parseFloat(tr.dataset.max);
        if (!inp) return max;
        const cant = Math.min(Math.max(parseFloat(inp.value) || 0, 0), max);
        inp.value = cant;
        return cant;
    }

    function recalcular() {
        let total = 0, gratis = 0;
        document.querySelectorAll('tr[data-clave]').forEach(tr => {
            const cant = cantidadDe(tr);
            const precio = parseFloat(tr.dataset.precio);
            const c = cortesias[tr.dataset.clave];
            const cort = c ? Math.min(c.cantidad, cant) : 0;
            if (c && cort <= 0) delete cortesias[tr.dataset.clave];
            const linea = Math.round((cant - cort) * precio * 100) / 100;
            tr.querySelector('.linea-total').textContent = fmt(linea);
            tr.querySelector('.cort-info').textContent = cort > 0 ? `🎁 ${cort} de cortesía · ${c.motivo}` : '';
            tr.querySelector('.btn-cortesia-mesa')?.classList.toggle('activa', cort > 0);
            tr.classList.toggle('es-cortesia', cort > 0);
            tr.classList.toggle('sep-elegido', cant > 0);
            total += linea;
            gratis += cort * precio;
        });
        BRUTO = Math.round(total * 100) / 100;
        GRATIS = Math.round(gratis * 100) / 100;
        aplicarDescuento();
    }

    // Cobrar mesa: 🎁 en una fila = esos productos van gratis (otra vez 🎁 = se quita la cortesía)
    if (CORTESIAS && !DIRECTA) {
        document.addEventListener('click', e => {
            const b = e.target.closest('.btn-cortesia-mesa');
            if (!b) return;
            const tr = b.closest('tr[data-clave]');
            const clave = tr.dataset.clave;
            if (cortesias[clave]) { delete cortesias[clave]; recalcular(); return; }
            const cant = cantidadDe(tr);
            if (cant <= 0) { alert('Primero elige cuántos de este producto se cobran en esta cuenta.'); return; }
            abrirCortesia(tr.children[0].firstChild.textContent.trim(), cant, (n, motivo) => { cortesias[clave] = { cantidad: n, motivo }; recalcular(); });
        });
    }

    if (SEPARADAS) {
        document.addEventListener('click', e => {
            const tr = e.target.closest('tr[data-clave]');
            if (!tr) return;
            const inp = tr.querySelector('.sep-cant');
            if (e.target.closest('.sep-mas')) { inp.value = (parseFloat(inp.value) || 0) + 1; recalcular(); }
            if (e.target.closest('.sep-menos')) { inp.value = (parseFloat(inp.value) || 0) - 1; recalcular(); }
        });
        document.addEventListener('change', e => { if (e.target.classList.contains('sep-cant')) recalcular(); });
        el('sep_todo').addEventListener('click', () => { document.querySelectorAll('tr[data-clave]').forEach(tr => tr.querySelector('.sep-cant').value = tr.dataset.max); recalcular(); });
        el('sep_nada').addEventListener('click', () => { document.querySelectorAll('.sep-cant').forEach(i => i.value = 0); recalcular(); });
    }

