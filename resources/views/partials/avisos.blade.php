{{--
    Avisos del sistema: tarjetas grandes arriba al centro, con ícono, título y botón para cerrar; se apilan si llegan varios.
    Uso desde cualquier pantalla:  tushpaAviso('Venta registrada')            → verde "Listo"
                                   tushpaAviso('No hay stock', false)          → rojo "No se pudo"
                                   tushpaAviso('Revisa el RUC', 'aviso')       → ámbar "Atención"
                                   tushpaAviso('Sincronizando…', 'info')       → azul
    Los mensajes de la sesión (guardado, errores de formulario) también se muestran así al cargar la página.
--}}
@once
<div id="tushpa-avisos" aria-live="polite" style="position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:2147483000;display:flex;flex-direction:column;gap:10px;width:min(460px,calc(100vw - 24px));pointer-events:none"></div>
<style>
    .tav { pointer-events:auto; display:flex; align-items:flex-start; gap:12px; padding:14px 16px 16px; border-radius:16px; background:#fff; color:#0f172a;
           box-shadow:0 18px 40px -12px rgba(15,23,42,.45), 0 0 0 1px rgba(15,23,42,.06); border-left:6px solid var(--c); position:relative; overflow:hidden;
           font-family:system-ui,-apple-system,'Segoe UI',sans-serif; animation:tav-entra .28s cubic-bezier(.2,1.4,.4,1) both; }
    .tav.sale { animation:tav-sale .22s ease-in both; }
    .tav-ico { flex:0 0 36px; height:36px; border-radius:50%; background:var(--c); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:19px; }
    .tav-t { font-weight:800; font-size:15px; margin:1px 0 2px; color:var(--c); }
    .tav-m { font-size:14.5px; line-height:1.4; color:#334155; word-break:break-word; }
    .tav-x { margin-left:auto; border:0; background:transparent; color:#94a3b8; font-size:22px; line-height:1; cursor:pointer; padding:0 2px; }
    .tav-x:hover { color:#0f172a; }
    .tav-barra { position:absolute; left:0; bottom:0; height:4px; background:var(--c); opacity:.35; animation:tav-barra linear forwards; }
    @keyframes tav-entra { from { opacity:0; transform:translateY(-18px) scale(.96); } to { opacity:1; transform:none; } }
    @keyframes tav-sale { to { opacity:0; transform:translateY(-12px) scale(.97); } }
    @keyframes tav-barra { from { width:100%; } to { width:0; } }
</style>
<script>
    (function () {
        const TIPOS = {
            ok: { c: '#059669', t: 'Listo', i: '✓', ms: 4500 },
            error: { c: '#dc2626', t: 'No se pudo', i: '!', ms: 9000 },
            aviso: { c: '#d97706', t: 'Atención', i: '!', ms: 8000 },
            info: { c: '#2563eb', t: 'Información', i: 'i', ms: 5000 },
        };
        window.tushpaAviso = function (texto, tipo = true, titulo = null) {
            if (!texto) return;
            const k = tipo === true ? 'ok' : (tipo === false ? 'error' : (TIPOS[tipo] ? tipo : 'info'));
            const d = TIPOS[k];
            const caja = document.getElementById('tushpa-avisos');
            if (!caja) return alert(texto);
            // El mismo mensaje dos veces seguidas no se repite
            if (caja.lastElementChild?.dataset.texto === texto) caja.lastElementChild.remove();
            const el = document.createElement('div');
            el.className = 'tav';
            el.dataset.texto = texto;
            el.style.setProperty('--c', d.c);
            el.setAttribute('role', k === 'error' ? 'alert' : 'status');
            el.innerHTML = '<div class="tav-ico"></div><div style="min-width:0;flex:1"><p class="tav-t"></p><p class="tav-m"></p></div><button type="button" class="tav-x" aria-label="Cerrar">&times;</button><div class="tav-barra"></div>';
            el.querySelector('.tav-ico').textContent = d.i;
            el.querySelector('.tav-t').textContent = titulo || d.t;
            el.querySelector('.tav-m').textContent = texto;
            el.querySelector('.tav-barra').style.animationDuration = d.ms + 'ms';
            const cerrar = () => { el.classList.add('sale'); setTimeout(() => el.remove(), 220); };
            el.querySelector('.tav-x').onclick = cerrar;
            let t = setTimeout(cerrar, d.ms);
            // Si se pasa el mouse encima, no se cierra (para alcanzar a leerlo)
            el.onmouseenter = () => { clearTimeout(t); el.querySelector('.tav-barra').style.animationPlayState = 'paused'; };
            el.onmouseleave = () => { t = setTimeout(cerrar, 2500); };
            caja.appendChild(el);
            while (caja.children.length > 4) caja.firstElementChild.remove();
        };

        // Mensajes que dejó el servidor al guardar
        document.addEventListener('DOMContentLoaded', () => {
            @if (session('success'))
                tushpaAviso(@json((string) session('success')), 'ok');
            @endif
            @if (session('ok'))
                tushpaAviso(@json((string) session('ok')), 'ok');
            @endif
            @if (session('error'))
                tushpaAviso(@json((string) session('error')), 'error');
            @endif
            @if (isset($errors) && $errors->any())
                tushpaAviso(@json($errors->count() === 1 ? $errors->first() : $errors->first().' (y '.($errors->count() - 1).' dato(s) más: revísalos en la pantalla)'), 'aviso', 'Revisa estos datos');
            @endif
        });
    })();
</script>
@endonce
