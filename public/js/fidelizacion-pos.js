/*
 * Puntos del cliente en caja, ANTES de cobrar (Punto de venta, PV Farmacia, PV, PV Móvil, Cobrar mesa).
 * Muestra los puntos que tiene, los que gana con esta compra y los premios que le alcanzan; el cajero toca
 * "Canjear" y el premio se descuenta al cobrar (sale impreso en el comprobante).
 *
 * En la página: <div data-fidelizacion data-doc="cliente.num" data-total="total"></div>        (pantallas con Alpine)
 *          o:   <div data-fidelizacion data-doc-input="#clinum" data-total-input="#total_comp"></div> (formularios normales)
 *          y:   <script src="js/fidelizacion-pos.js" data-previa="..." data-reservar="..." data-csrf="..."></script>
 */
(function () {
    const script = document.currentScript;
    const URL_PREVIA = script.dataset.previa, URL_RESERVAR = script.dataset.reservar, CSRF = script.dataset.csrf;

    const leer = (el, ruta) => {
        const raiz = el.closest('[x-data]') || document.querySelector('[x-data]');
        if (!raiz || !window.Alpine) return '';
        let v = window.Alpine.$data(raiz);
        for (const k of ruta.split('.')) { v = v?.[k]; }
        return v ?? '';
    };
    const valor = (el, tipo) => {
        const sel = el.dataset[tipo + 'Input'];
        if (sel) {
            const campo = document.querySelector(sel);
            return campo ? (campo.value ?? campo.textContent ?? '') : '';
        }
        return el.dataset[tipo] ? leer(el, el.dataset[tipo]) : '';
    };
    const num = (v) => parseFloat(String(v).replace(/,/g, '')) || 0;
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function montar(el) {
        let ultimo = '', d = null, temporizador = null, ocupado = false;

        async function consultar(doc, total) {
            try {
                const r = await fetch(URL_PREVIA + '?doc=' + encodeURIComponent(doc) + '&total=' + total, { headers: { 'Accept': 'application/json' } });
                d = r.ok ? await r.json() : null;
            } catch (e) { d = null; }
            pintar(doc, total);
        }

        async function reservar(doc, total, premioId) {
            if (ocupado) return;
            ocupado = true;
            try {
                const r = await fetch(URL_RESERVAR, { method: 'POST', body: JSON.stringify({ doc, total, premio_id: premioId }),
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF } });
                const res = await r.json();
                if (res.ok) { d = res; pintar(doc, total); if (premioId && window.tushpaAviso) window.tushpaAviso('El premio se descontará al cobrar esta venta.', 'puntos', '★ Premio reservado'); }
                else if (window.tushpaAviso) window.tushpaAviso(res.mensaje || 'No se pudo reservar.', 'aviso'); else alert(res.mensaje);
            } catch (e) {}
            ocupado = false;
        }

        function pintar(doc, total) {
            if (!d || !d.activo) { el.innerHTML = ''; return; }
            const caja = (fondo, html) => `<div style="background:${fondo};color:#fff;border-radius:14px;padding:12px 14px;margin:8px 0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;font-size:13px;line-height:1.45">${html}</div>`;
            if (!d.valido) { el.innerHTML = caja('#64748b', '★ Escribe el DNI o RUC del cliente para ver y sumar sus puntos.'); return; }
            if (d.nuevo || !d.cliente) {
                el.innerHTML = caja('#7c3aed', `★ Cliente nuevo en puntos: con esta compra gana <b>${d.ganara}</b> ${d.ganara === 1 ? 'punto' : 'puntos'}.`);
                return;
            }
            const filas = d.premios.map((p) => {
                const vence = p.vence ? ` <span style="opacity:.8;font-size:11px">· vence ${esc(p.vence)}</span>` : '';
                if (d.reservado === p.premio_id) {
                    return `<div style="display:flex;align-items:center;gap:8px;background:#f59e0b;color:#451a03;border-radius:10px;padding:7px 10px;margin-top:6px;font-weight:700">
                        🎁 <span style="flex:1">${esc(p.nombre)} (−${p.puntos} pts) · se descuenta al cobrar</span>
                        <button type="button" data-quitar style="border:0;background:#b91c1c;color:#fff;border-radius:6px;width:24px;height:24px;font-weight:900;cursor:pointer">×</button></div>`;
                }
                if (p.alcanza && !d.reservado) {
                    return `<div style="display:flex;align-items:center;gap:8px;margin-top:6px">✔ <span style="flex:1"><b>${esc(p.nombre)}</b> (${p.puntos} pts)${vence}</span>
                        <button type="button" data-canjear="${p.premio_id}" style="border:0;background:#fff;color:#047857;border-radius:8px;padding:4px 10px;font-weight:800;cursor:pointer">+ Canjear</button></div>`;
                }
                return `<div style="margin-top:4px;opacity:.85">🔒 ${p.alcanza ? '' : `Faltan <b>${p.falta}</b> pts: `}${esc(p.nombre)}${vence}</div>`;
            }).join('');
            const titulo = `<div style="font-size:15px;font-weight:800">★ Puntos de ${esc(d.cliente)}: ${d.actual}
                ${d.ganara ? `<span style="font-weight:600;opacity:.9">+${d.ganara} con esta compra = <b>${d.con_compra}</b></span>` : ''}</div>`;
            const queda = d.reservado ? `<div style="margin-top:8px;font-weight:700">Después del canje le quedan ${d.quedaria} puntos.</div>` : '';
            el.innerHTML = caja(d.reservado ? '#0e7490' : '#059669', titulo + (filas || '<div style="margin-top:4px;opacity:.85">Aún no hay premios creados.</div>') + queda);
            el.querySelectorAll('[data-canjear]').forEach((b) => b.onclick = () => reservar(doc, total, parseInt(b.dataset.canjear, 10)));
            el.querySelector('[data-quitar]')?.addEventListener('click', () => reservar(doc, total, null));
        }

        // Revisa cada momento si cambió el cliente o el total
        setInterval(() => {
            const doc = String(valor(el, 'doc')).trim(), total = num(valor(el, 'total')).toFixed(2);
            const clave = doc + '|' + total;
            if (clave === ultimo) return;
            ultimo = clave;
            clearTimeout(temporizador);
            temporizador = setTimeout(() => consultar(doc, total), 400);
        }, 700);
    }

    const iniciar = () => document.querySelectorAll('[data-fidelizacion]').forEach(montar);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
