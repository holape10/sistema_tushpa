/*
 * Enviar un comprobante por WhatsApp (Panel de ventas, PV Móvil y otras pantallas).
 *   TushpaWhatsApp.abrir(idComprobante)
 *
 * - Si el cliente tiene celular guardado: abre su chat de WhatsApp con el mensaje y el enlace al PDF A4.
 * - Si no tiene: pide el número (y lo guarda en la venta y en el cliente para la próxima vez).
 * - En celulares que lo permiten, también se puede mandar el ARCHIVO PDF (se elige WhatsApp y el contacto).
 * En la página: <script src="js/whatsapp-cpe.js" data-base="{{ url('/') }}" data-csrf="{{ csrf_token() }}"></script>
 */
(function () {
    const script = document.currentScript;
    const BASE = (script && script.dataset.base) || '';
    const TOKEN = (script && script.dataset.csrf) || '';
    const csrf = () => TOKEN || document.querySelector('meta[name=csrf-token]')?.content || window.CSRF || '';
    let datos = null, idActual = null, modal = null;

    const estilos = `
        .twa-fondo{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:2147482000;display:flex;align-items:flex-end;justify-content:center;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
        @media(min-width:640px){.twa-fondo{align-items:center}}
        .twa-caja{background:#fff;width:100%;max-width:420px;border-radius:24px 24px 0 0;padding:22px 20px calc(20px + env(safe-area-inset-bottom));box-shadow:0 20px 50px rgba(0,0,0,.3)}
        @media(min-width:640px){.twa-caja{border-radius:24px}}
        .twa-cab{display:flex;align-items:center;gap:12px}
        .twa-logo{width:46px;height:46px;border-radius:50%;background:#25d366;display:flex;align-items:center;justify-content:center;flex:0 0 46px}
        .twa-t{font-size:18px;font-weight:800;color:#0f172a;margin:0}
        .twa-s{font-size:13px;color:#64748b;margin:2px 0 0}
        .twa-lbl{display:block;font-size:13px;font-weight:700;color:#334155;margin:18px 0 6px}
        .twa-fila{display:flex;border:2px solid #e2e8f0;border-radius:14px;overflow:hidden}
        .twa-fila:focus-within{border-color:#25d366}
        .twa-pre{padding:0 12px;display:flex;align-items:center;background:#f8fafc;color:#475569;font-weight:700;font-size:16px}
        .twa-in{flex:1;border:0;outline:0;font-size:20px;font-weight:700;padding:12px;letter-spacing:1px;min-width:0}
        .twa-ayuda{font-size:12px;color:#94a3b8;margin:6px 0 0}
        .twa-error{font-size:13px;color:#b45309;background:#fffbeb;border-radius:10px;padding:8px 10px;margin:10px 0 0;display:none}
        .twa-btn{width:100%;border:0;border-radius:14px;padding:15px;font-size:16px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px}
        .twa-verde{background:#25d366;color:#fff}.twa-verde:hover{background:#1ebe5a}
        .twa-borde{background:#fff;color:#128c7e;border:2px solid #25d366}
        .twa-gris{background:#f1f5f9;color:#475569;font-weight:700;padding:12px}
        .twa-btn:disabled{opacity:.6;cursor:wait}`;
    const icono = '<svg width="26" height="26" viewBox="0 0 24 24" fill="#fff"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.05 21.5a9.4 9.4 0 01-4.8-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 1117.97-3.55c0 5.2-4.23 9.43-9.43 9.43zm8.02-17.45A11.25 11.25 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.66L.62 23.25l5.65-1.48a11.3 11.3 0 005.41 1.38c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01z"/></svg>';

    function aviso(texto, ok) {
        if (window.tushpaAviso) window.tushpaAviso(texto, ok); else alert(texto);
    }

    // Comparte el ARCHIVO PDF (Android/iPhone y algunos navegadores de PC); si no se puede, devuelve false
    function puedeCompartirArchivo() {
        try { return !!(navigator.canShare && navigator.canShare({ files: [new File(['x'], 'x.pdf', { type: 'application/pdf' })] })); } catch (e) { return false; }
    }

    async function compartirArchivo(boton) {
        boton.disabled = true; const txt = boton.innerHTML; boton.textContent = 'Preparando PDF…';
        try {
            const r = await fetch(datos.pdf);
            if (!r.ok) throw new Error();
            const archivo = new File([await r.blob()], datos.archivo, { type: 'application/pdf' });
            await navigator.share({ files: [archivo], title: datos.numero, text: datos.texto.split('\n\n')[0] });
            cerrar();
        } catch (e) {
            if (e && e.name === 'AbortError') { boton.disabled = false; boton.innerHTML = txt; return; }
            aviso('No se pudo compartir el archivo. Usa "Enviar al WhatsApp del cliente" (le llega el enlace al PDF).', 'aviso');
        }
        boton.disabled = false; boton.innerHTML = txt;
    }

    function abrirChat(numero) {
        const url = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(datos.texto);
        window.open(url, '_blank', 'noopener');
    }

    function cerrar() { modal?.remove(); modal = null; }

    function mostrarModal() {
        cerrar();
        if (!document.getElementById('twa-estilos')) {
            const st = document.createElement('style'); st.id = 'twa-estilos'; st.textContent = estilos; document.head.appendChild(st);
        }
        const local = (datos.telefono || '').replace(/^51(?=9\d{8}$)/, '');
        modal = document.createElement('div');
        modal.className = 'twa-fondo';
        modal.innerHTML = `
            <div class="twa-caja" role="dialog" aria-modal="true">
                <div class="twa-cab"><div class="twa-logo">${icono}</div>
                    <div style="min-width:0"><p class="twa-t">Enviar por WhatsApp</p><p class="twa-s"></p></div></div>
                <label class="twa-lbl" for="twa-num">Número de WhatsApp del cliente</label>
                <div class="twa-fila"><span class="twa-pre">+51</span><input id="twa-num" class="twa-in" inputmode="tel" maxlength="15" placeholder="987 654 321" autocomplete="off"></div>
                <p class="twa-ayuda">Se guarda en el cliente para la próxima vez. Otro país: escribe el código completo (ej. 573001234567).</p>
                <p class="twa-error"></p>
                <button type="button" class="twa-btn twa-verde" data-accion="chat">${icono.replace('width="26" height="26"', 'width="20" height="20"')} Enviar al WhatsApp del cliente</button>
                <button type="button" class="twa-btn twa-borde" data-accion="archivo" style="display:none">📄 Enviar el archivo PDF</button>
                <button type="button" class="twa-btn twa-gris" data-accion="cerrar">Cancelar</button>
            </div>`;
        modal.querySelector('.twa-s').textContent = datos.numero + ' · ' + datos.cliente;
        const input = modal.querySelector('#twa-num');
        input.value = local;
        const error = modal.querySelector('.twa-error');
        if (puedeCompartirArchivo()) modal.querySelector('[data-accion=archivo]').style.display = 'flex';

        modal.addEventListener('click', async (e) => {
            if (e.target === modal) return cerrar();
            const b = e.target.closest('[data-accion]');
            if (!b) return;
            const accion = b.dataset.accion;
            if (accion === 'cerrar') return cerrar();
            if (accion === 'archivo') return compartirArchivo(b);
            // Guarda el número y abre el chat
            const numero = input.value.replace(/\D/g, '');
            if (!(numero.length === 9 && numero.startsWith('9')) && !(numero.length >= 10 && numero.length <= 15)) {
                error.textContent = 'Escribe el celular de 9 dígitos (empieza con 9).'; error.style.display = 'block'; input.focus(); return;
            }
            b.disabled = true;
            try {
                const r = await fetch(BASE + '/ventas/' + idActual + '/telefono', { method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ telefono: numero }) });
                const d = await r.json();
                if (!d.ok) { error.textContent = d.mensaje || 'Revisa el número.'; error.style.display = 'block'; b.disabled = false; return; }
                datos.telefono = d.telefono;
                abrirChat(d.telefono);
                cerrar();
            } catch (err) {
                // Sin conexión con el servidor: igual se abre el chat (no se guarda el número)
                abrirChat(numero.length === 9 ? '51' + numero : numero); cerrar();
            }
        });
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') modal.querySelector('[data-accion=chat]').click(); if (e.key === 'Escape') cerrar(); });
        document.body.appendChild(modal);
        setTimeout(() => input.focus(), 50);
    }

    window.TushpaWhatsApp = {
        /** @param {number} id IdCpe_cabecera  @param {boolean} preguntar  true = mostrar el modal aunque ya tenga número */
        async abrir(id, preguntar = false) {
            idActual = id;
            try {
                const r = await fetch(BASE + '/ventas/' + id + '/whatsapp', { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error();
                datos = await r.json();
            } catch (e) { return aviso('No se pudo preparar el mensaje. Revisa tu conexión.', false); }
            // Con número guardado va directo al chat (en celulares también se ofrece mandar el archivo)
            if (datos.telefono && !preguntar && !puedeCompartirArchivo()) return abrirChat(datos.telefono);
            mostrarModal();
        },
    };
})();
