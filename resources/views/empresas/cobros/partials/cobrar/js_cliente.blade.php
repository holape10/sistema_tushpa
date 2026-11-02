{{-- JS: comprobante según el cliente y búsqueda por nombre (va dentro de cobrar.blade.php) --}}
    // ---------- Cumpleaños (solo con DNI): se anota para la fidelización y la caja ve si cumple hoy o este mes ----------
    function verCumple() {
        el('box_nac').style.display = el('tdicod').value === '1' ? '' : 'none';
        const v = el('fecha_nac').value, aviso = el('cumple_aviso');
        aviso.style.display = 'none';
        if (!v) return;
        const [, m, d] = v.split('-').map(Number), hoy = new Date();
        if (m === hoy.getMonth() + 1) {
            aviso.textContent = d === hoy.getDate() ? '🎉 ¡Hoy cumple años! Salúdalo' : '🎂 Cumple este mes (día ' + d + ')';
            aviso.style.display = 'block';
        }
    }
    el('tdicod').addEventListener('change', verCumple);
    el('fecha_nac').addEventListener('change', verCumple);
    verCumple();

    // ---------- Comprobante según el cliente: RUC = factura; si no, el predeterminado ----------
    function aplicarComprobante(tdicod) {
        const tieneFactura = !!el('tdocod').querySelector('option[value="01"]');
        if (tdicod === '6' && tieneFactura) {
            el('tdocod').value = '01';
            return 'FACTURA';
        }
        // Un DNI no puede llevar factura: vuelve al comprobante predeterminado (o boleta si el predeterminado es factura)
        if (el('tdocod').value === '01') el('tdocod').value = TDOCOD_PRED === '01' ? '03' : TDOCOD_PRED;
        return null;
    }

    function llenarCliente(c) {
        el('clinum').value = c.num ?? el('clinum').value;
        el('clinom').value = c.nom || '';
        el('clidir').value = c.dir || '--';
        // Cliente con varias sucursales: sus direcciones para elegir
        const dirs = Array.isArray(c.dirs) ? c.dirs : [];
        el('dirs_cliente').innerHTML = dirs.map(d => `<option value="${d.replace(/"/g, '&quot;')}"></option>`).join('');
        el('dirs_aviso').style.display = dirs.length > 1 ? '' : 'none';
        el('dirs_aviso').textContent = dirs.length > 1 ? `🏢 ${dirs.length} direcciones: borra el campo para elegir la sucursal` : '';
        el('clicor').value = c.cor || '';
        el('telefono').value = c.tel || '';
        if (c.tdicod) el('tdicod').value = c.tdicod;
        el('fecha_nac').value = c.nac ? String(c.nac).slice(0, 10) : '';
        verCumple();
        ultimoBuscado = el('clinum').value.trim();
        const comp = aplicarComprobante(c.tdicod);
        mensajeCliente(comp ? '✔ Cliente con RUC: se cambió a FACTURA.' : '✔ Cliente seleccionado.', 'ok');
    }

    // ---------- Búsqueda predictiva por nombre (base de datos propia) ----------
    const lista = el('sug_clientes');
    let sugerencias = [], activo = -1, timerSug = null, ctrlSug = null;

    function cerrarSugerencias() { lista.style.display = 'none'; activo = -1; }

    function pintarSugerencias() {
        lista.innerHTML = '';
        if (!sugerencias.length) { cerrarSugerencias(); return; }
        sugerencias.forEach((c, i) => {
            const li = document.createElement('li');
            li.className = i === activo ? 'activo' : '';
            const nom = document.createElement('span'); nom.textContent = c.nom;
            const det = document.createElement('small'); det.textContent = (c.tdicod === '6' ? 'RUC ' : 'DOC ') + c.num + (c.dir && c.dir !== '--' ? ' · ' + c.dir : '');
            li.append(nom, det);
            li.addEventListener('mousedown', ev => { ev.preventDefault(); llenarCliente(c); cerrarSugerencias(); });
            lista.appendChild(li);
        });
        lista.style.display = 'block';
    }

    el('clinom').addEventListener('input', function () {
        clearTimeout(timerSug);
        const q = this.value.trim();
        if (q.length < 2 || q === 'VENTA AL PORTADOR') { cerrarSugerencias(); return; }
        timerSug = setTimeout(() => {
            ctrlSug?.abort();
            ctrlSug = new AbortController();
            fetch(`{{ route('cobros.clientes') }}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' }, signal: ctrlSug.signal })
                .then(r => r.json())
                .then(data => { sugerencias = data; activo = data.length ? 0 : -1; pintarSugerencias(); })
                .catch(() => {});
        }, 250);
    });
    el('clinom').addEventListener('keydown', function (e) {
        if (lista.style.display !== 'block') return;
        if (e.key === 'ArrowDown') { e.preventDefault(); activo = Math.min(activo + 1, sugerencias.length - 1); pintarSugerencias(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activo = Math.max(activo - 1, 0); pintarSugerencias(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (sugerencias[activo]) { llenarCliente(sugerencias[activo]); cerrarSugerencias(); } }
        else if (e.key === 'Escape') { cerrarSugerencias(); }
    });
    el('clinom').addEventListener('blur', () => setTimeout(cerrarSugerencias, 150));
    el('clinom').addEventListener('focus', function () { if (this.value === 'VENTA AL PORTADOR') this.select(); });

