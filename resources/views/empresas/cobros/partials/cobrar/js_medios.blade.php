{{-- JS: medios de pago (número de operación, dólares) (va dentro de cobrar.blade.php) --}}
    // Medios de pago. Con EXIGE_OP, los que no son EFECTIVO piden el número de operación
    const esEfectivo = opt => (opt?.dataset.nom || '').trim().toUpperCase() === 'EFECTIVO';
    function mostrarOperacion() {
        if (!EXIGE_OP) return;
        el('op_med_pag').style.display = esEfectivo(el('med_pag').selectedOptions[0]) ? 'none' : 'block';
    }
    if (EXIGE_OP) {
        el('med_pag').addEventListener('change', mostrarOperacion);
        mostrarOperacion();
    }

    function renderMedios() {
        const tb = el('tbody_med_pag');
        tb.innerHTML = '';
        medios.forEach((m, i) => {
            tb.insertAdjacentHTML('beforeend',
                `<tr><td><span class="label label-success">${m.nom}</span> S/ ${fmt(m.monto)}${m.usd ? ` <b style="color:#15803d">(US$ ${fmt(m.monto / TC)})</b>` : ''}${m.op ? ` <small class="text-muted">Op. ${m.op.replace(/[<>&"]/g, '')}</small>` : ''}</td>
                 <td style="width:30px"><button type="button" class="btn btn-danger btn-xs" onclick="quitarMedio(${i})">&times;</button></td></tr>`);
        });
        const suma = medios.reduce((a, m) => a + m.monto, 0);
        el('lbl_restante').textContent = fmt(TOTAL - suma);
        el('mon_med_pag').value = fmt(Math.max(0, TOTAL - suma));
        equivalenteDolares();
    }
    function quitarMedio(i) { medios.splice(i, 1); renderMedios(); }
    // Medio pagado en dólares: cuántos US$ son
    function equivalenteDolares() {
        if (!TC) return;
        const monto = parseFloat(el('mon_med_pag').value) || 0;
        el('med_usd_equiv').textContent = el('med_usd').checked && monto > 0 ? `= US$ ${fmt(monto / TC)}` : '';
    }
    if (TC) {
        el('med_usd').addEventListener('change', equivalenteDolares);
        el('mon_med_pag').addEventListener('input', equivalenteDolares);
    }

    el('btn_agregar_medio').addEventListener('click', function () {
        const sel = el('med_pag');
        const monto = parseFloat(el('mon_med_pag').value) || 0;
        const usd = !!(TC && el('med_usd').checked);
        if (monto <= 0) return;
        if (medios.some(m => m.id == sel.value && m.usd === usd)) { alert('EL MEDIO DE PAGO YA SE ENCUENTRA AGREGADO'); return; }
        const op = EXIGE_OP ? el('op_med_pag').value.trim() : '';
        if (EXIGE_OP && !esEfectivo(sel.selectedOptions[0]) && !op) { alert('Escribe el número de operación del pago con ' + sel.selectedOptions[0].dataset.nom + '.'); el('op_med_pag').focus(); return; }
        if (op && medios.some(m => (m.op || '').toUpperCase() === op.toUpperCase())) { alert('El N° de operación ' + op + ' ya está en este cobro.'); el('op_med_pag').focus(); return; }
        medios.push({ id: sel.value, nom: sel.selectedOptions[0].dataset.nom + (usd ? ' US$' : ''), monto: monto, op: op, usd: usd });
        if (EXIGE_OP) el('op_med_pag').value = '';
        if (TC) el('med_usd').checked = false;
        renderMedios();
    });

