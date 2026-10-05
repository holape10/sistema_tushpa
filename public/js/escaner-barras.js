/**
 * Lector de códigos de barras con la cámara del celular, reutilizable en cualquier pantalla.
 *
 *   EscanerBarras.abrir(codigo => { ... })   // cámara en vivo; sin HTTPS ofrece tomar una foto
 *   EscanerBarras.foto(codigo => { ... })    // abre la cámara del celular y lee el código de la foto
 *
 * La cámara en vivo del navegador solo funciona con HTTPS (o localhost). Con http:// se usa una foto:
 * <input capture> abre la app de cámara del teléfono y la imagen se decodifica aquí mismo.
 * No depende de Tailwind (los estilos van en línea) para poder usarse en cualquier vista.
 */
(function () {
    const LIBRERIA = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    let cargando = null;

    function cargarLibreria() {
        if (window.Html5Qrcode) return Promise.resolve();
        cargando ??= new Promise((ok, falla) => {
            const s = document.createElement('script');
            s.src = LIBRERIA;
            s.onload = ok;
            s.onerror = () => { cargando = null; falla(new Error('No se pudo cargar el lector (revisa tu conexión a internet).')); };
            document.head.appendChild(s);
        });
        return cargando;
    }

    function el(tag, estilo, texto) {
        const e = document.createElement(tag);
        if (estilo) e.style.cssText = estilo;
        if (texto) e.textContent = texto;
        return e;
    }

    const BOTON = 'border:0;border-radius:12px;padding:12px 16px;font-weight:700;font-size:15px;cursor:pointer;';

    /** Lee el código de una foto tomada con la cámara del teléfono */
    async function decodificarFoto(archivo) {
        await cargarLibreria();
        let div = document.getElementById('escaner-barras-foto');
        if (!div) {
            div = el('div', 'display:none');
            div.id = 'escaner-barras-foto';
            document.body.appendChild(div);
        }
        const lector = new Html5Qrcode('escaner-barras-foto', { verbose: false });
        try {
            return await lector.scanFile(archivo, false);
        } finally {
            try { lector.clear(); } catch (e) { /* nada que limpiar */ }
        }
    }

    function pedirFoto(alLeer, alFallar) {
        const input = el('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.setAttribute('capture', 'environment');
        input.style.display = 'none';
        input.addEventListener('change', async () => {
            const archivo = input.files && input.files[0];
            input.remove();
            if (!archivo) return;
            try {
                alLeer(await decodificarFoto(archivo));
            } catch (e) {
                alFallar('No se encontró un código en la foto. Acércate más, con buena luz y el código derecho.');
            }
        });
        document.body.appendChild(input);
        input.click();
    }

    window.EscanerBarras = {
        foto(alLeer) {
            pedirFoto(alLeer, msg => alert(msg));
        },

        async abrir(alLeer) {
            const capa = el('div', 'position:fixed;inset:0;z-index:9999;background:#000;display:flex;flex-direction:column;color:#fff;font-family:inherit');
            const cabecera = el('div', 'display:flex;align-items:center;justify-content:space-between;padding:14px 16px;padding-top:max(14px, env(safe-area-inset-top))');
            const titulos = el('div');
            titulos.appendChild(el('div', 'font-weight:700;font-size:17px', 'Escanear código de barras'));
            const estado = el('div', 'font-size:12px;opacity:.65', 'Apunta la cámara al código');
            titulos.appendChild(estado);
            const cerrarBtn = el('button', BOTON + 'background:rgba(255,255,255,.15);color:#fff;padding:8px 14px', '✕');
            cerrarBtn.type = 'button';
            cerrarBtn.setAttribute('aria-label', 'Cerrar escáner');
            cabecera.append(titulos, cerrarBtn);

            const zona = el('div', 'flex:1;display:flex;align-items:center;justify-content:center;overflow:hidden');
            const visor = el('div', 'width:100%;max-width:460px');
            visor.id = 'escaner-barras-visor';
            zona.appendChild(visor);

            const pie = el('div', 'padding:16px;display:grid;gap:10px;padding-bottom:max(16px, env(safe-area-inset-bottom))');
            const aviso = el('p', 'margin:0;font-size:13px;color:#fda4af;text-align:center;display:none');
            const fotoBtn = el('button', BOTON + 'background:#10b981;color:#fff', '📷 Tomar foto del código');
            fotoBtn.type = 'button';
            pie.append(aviso, fotoBtn);
            capa.append(cabecera, zona, pie);
            document.body.appendChild(capa);

            let lector = null;
            let terminado = false;
            const cerrar = async () => {
                if (terminado) return;
                terminado = true;
                try { if (lector && lector.isScanning) await lector.stop(); lector?.clear(); } catch (e) { /* ya detenido */ }
                capa.remove();
            };
            const leido = codigo => {
                codigo = String(codigo || '').trim();
                if (!codigo) return;
                navigator.vibrate?.(60);
                cerrar();
                alLeer(codigo);
            };
            const mostrarAviso = texto => { aviso.textContent = texto; aviso.style.display = 'block'; };

            cerrarBtn.onclick = cerrar;
            fotoBtn.onclick = () => pedirFoto(leido, mostrarAviso);

            if (!window.isSecureContext) {
                estado.textContent = 'Sin HTTPS: usa la foto';
                mostrarAviso('La cámara en vivo necesita que el sistema se abra con https://. Mientras tanto toma una foto del código.');
                return;
            }
            try {
                await cargarLibreria();
                if (terminado) return;
                lector = new Html5Qrcode('escaner-barras-visor', { verbose: false, experimentalFeatures: { useBarCodeDetectorIfSupported: true } });
                await lector.start(
                    { facingMode: 'environment' },
                    { fps: 12, qrbox: (w, h) => ({ width: Math.floor(Math.min(w * 0.85, 340)), height: Math.floor(Math.min(h * 0.5, 200)) }) },
                    leido,
                    () => {}
                );
            } catch (e) {
                mostrarAviso(e && e.message && e.message.includes('lector') ? e.message
                    : 'No se pudo abrir la cámara. Revisa que hayas dado permiso, o toma una foto del código.');
            }
        },
    };
})();
