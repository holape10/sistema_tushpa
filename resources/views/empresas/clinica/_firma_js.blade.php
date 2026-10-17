{{--
    Firma del paciente (sesión, presupuesto o consentimiento): ventana con dos formas de firmar
      1) Aquí: con el dedo, lápiz o mouse (tablet, celular o PC)
      2) En el celular del paciente: QR o enlace por WhatsApp (vence en 30 minutos); esta pantalla espera y se actualiza sola
    Uso: tushpaFirmar({ tipo: 'presupuesto', id: 5, titulo: 'Presupuesto N° 00005', alFirmar: () => recargar() })
    No depende de Alpine ni de Tailwind (también la usa la hoja del consentimiento).
--}}
@once
<script>
    (function () {
        const BASE = @js(url('clinica/firma'));
        const CSRF = @js(csrf_token());

        // Recuadro para firmar: dedo, lápiz o mouse (pointer events) sin que la página se mueva
        window.tushpaPad = function (c) {
            const ctx = c.getContext('2d');
            let dibujando = false;
            const pad = { firmado: false };
            pad.preparar = function () {
                const r = window.devicePixelRatio || 1;
                c.width = c.offsetWidth * r; c.height = c.offsetHeight * r;
                ctx.setTransform(r, 0, 0, r, 0, 0); ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
                pad.firmado = false;
            };
            const punto = e => { const b = c.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
            c.style.touchAction = 'none';
            c.onpointerdown = e => { e.preventDefault(); dibujando = true; c.setPointerCapture(e.pointerId); const [x, y] = punto(e); ctx.beginPath(); ctx.moveTo(x, y); };
            c.onpointermove = e => { if (!dibujando) return; e.preventDefault(); const [x, y] = punto(e); ctx.lineTo(x, y); ctx.stroke(); pad.firmado = true; };
            c.onpointerup = c.onpointercancel = () => { dibujando = false; };
            pad.png = () => pad.firmado ? c.toDataURL('image/png') : null;
            pad.preparar();
            return pad;
        };

        const el = (tag, estilo, html) => { const e = document.createElement(tag); if (estilo) e.style.cssText = estilo; if (html !== undefined) e.innerHTML = html; return e; };
        const boton = (texto, fondo, color) => el('button', `padding:10px 16px;border:0;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;background:${fondo};color:${color}`, texto);

        window.tushpaFirmar = function ({ tipo, id, titulo, alFirmar }) {
            let espera = null;
            const fondo = el('div', 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:12px;font-family:system-ui,Segoe UI,Arial,sans-serif');
            const caja = el('div', 'background:#fff;border-radius:18px;width:100%;max-width:560px;max-height:95vh;overflow:auto;padding:18px;box-shadow:0 20px 50px rgba(0,0,0,.3)');
            const cerrar = () => { clearInterval(espera); fondo.remove(); };
            caja.append(el('div', 'font-weight:800;font-size:17px;color:#0f172a', '✍️ Firma del paciente'), el('div', 'font-size:13px;color:#64748b;margin:2px 0 12px', titulo || ''));
            const pestanas = el('div', 'display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px');
            const tAqui = boton('Firmar aquí', '#0f766e', '#fff'), tCel = boton('📱 En su celular (QR)', '#f1f5f9', '#334155');
            pestanas.append(tAqui, tCel);
            const zonaAqui = el('div'), zonaCel = el('div', 'display:none;text-align:center');
            // 1) Firmar en esta pantalla
            const lienzo = el('canvas', 'width:100%;height:200px;border:2px dashed #94a3b8;border-radius:14px;background:#f8fafc;display:block');
            zonaAqui.append(el('div', 'font-size:13px;color:#475569;margin-bottom:6px', 'El paciente firma con el dedo, lápiz o mouse:'), lienzo);
            const filaAqui = el('div', 'display:flex;gap:8px;justify-content:space-between;margin-top:10px');
            const bBorrar = boton('Borrar', '#f1f5f9', '#334155'), bGuardar = boton('Guardar firma', '#0f766e', '#fff'), bCancelar = boton('Cancelar', '#f1f5f9', '#334155');
            const derecha = el('div', 'display:flex;gap:8px'); derecha.append(bCancelar, bGuardar); filaAqui.append(bBorrar, derecha); zonaAqui.append(filaAqui);
            // 2) En el celular del paciente
            const bCerrar2 = boton('Cerrar', '#f1f5f9', '#334155');
            caja.append(pestanas, zonaAqui, zonaCel);
            fondo.append(caja); document.body.append(fondo);
            const pad = window.tushpaPad(lienzo);
            const listo = (msg) => { cerrar(); if (window.tushpaAviso) window.tushpaAviso(msg, 'ok'); if (alFirmar) alFirmar(); };

            bBorrar.onclick = () => pad.preparar();
            bCancelar.onclick = bCerrar2.onclick = cerrar;
            bGuardar.onclick = async () => {
                const firma = pad.png();
                if (!firma) { alert('El paciente debe firmar dentro del recuadro.'); return; }
                bGuardar.disabled = true;
                const r = await fetch(`${BASE}/${tipo}/${id}`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ firma }) });
                const j = await r.json().catch(() => ({}));
                bGuardar.disabled = false;
                if (j.ok) listo('Firma guardada.'); else alert(j.mensaje || Object.values(j.errors || {}).flat()[0] || 'No se pudo guardar la firma.');
            };
            tAqui.onclick = () => { zonaAqui.style.display = ''; zonaCel.style.display = 'none'; tAqui.style.background = '#0f766e'; tAqui.style.color = '#fff'; tCel.style.background = '#f1f5f9'; tCel.style.color = '#334155'; clearInterval(espera); };
            tCel.onclick = async () => {
                zonaAqui.style.display = 'none'; zonaCel.style.display = ''; tCel.style.background = '#0f766e'; tCel.style.color = '#fff'; tAqui.style.background = '#f1f5f9'; tAqui.style.color = '#334155';
                zonaCel.innerHTML = '<p style="color:#64748b">Generando el código…</p>';
                const r = await fetch(`${BASE}/${tipo}/${id}/enlace`, { headers: { Accept: 'application/json' } });
                const j = await r.json().catch(() => ({}));
                if (!j.ok) { zonaCel.innerHTML = '<p style="color:#b91c1c">No se pudo generar el enlace.</p>'; return; }
                zonaCel.innerHTML = '';
                zonaCel.append(
                    el('div', 'font-size:14px;color:#334155;margin-bottom:6px', 'El paciente escanea este código con la cámara de su celular y firma ahí:'),
                    el('div', 'display:inline-block;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px', j.qr),
                    el('div', 'font-size:12px;color:#64748b;margin:6px 0', `El código vence en ${j.minutos} minutos.`),
                );
                const fila = el('div', 'display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:8px 0');
                if (j.whatsapp) { const a = el('a', 'padding:10px 14px;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:14px;text-decoration:none', 'Enviar enlace por WhatsApp'); a.href = j.whatsapp; a.target = '_blank'; fila.append(a); }
                const copiar = boton('Copiar enlace', '#f1f5f9', '#334155');
                copiar.onclick = () => { navigator.clipboard?.writeText(j.url); copiar.textContent = '✔ Copiado'; };
                fila.append(copiar);
                const estado = el('div', 'font-size:14px;font-weight:700;color:#0f766e;margin-top:6px', '⏳ Esperando la firma del paciente…');
                zonaCel.append(fila, estado, el('div', 'margin-top:10px', '')); zonaCel.lastChild.append(bCerrar2);
                clearInterval(espera);
                espera = setInterval(async () => {
                    const e = await fetch(`${BASE}/${tipo}/${id}/estado`, { headers: { Accept: 'application/json' } }).then(x => x.json()).catch(() => ({}));
                    if (e.firmado) listo('El paciente ya firmó.');
                }, 3000);
            };
        };
    })();
</script>
@endonce
