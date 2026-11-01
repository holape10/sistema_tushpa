/**
 * Avisos al celular de los mozos (Web Push): pedido del QR, llaman al mozo, piden la cuenta y plato listo, aunque la
 * pantalla esté apagada. Solo en sucursales con QR de mesas (la pantalla de mesas pone el botón [data-avisos-celular]):
 *   - Si el navegador ya dio permiso, el celular se registra solo, sin tocar nada.
 *   - Si aún no lo dio, aparece "Permitir avisos del QR" (el navegador solo pregunta tras un toque) y luego se oculta.
 *   data-clave = clave pública VAPID · data-guardar / data-quitar = rutas para guardar o quitar este equipo · data-icono.
 * En iPhone solo funciona con la app instalada (Compartir → Agregar a inicio).
 */
const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function enviar(url, datos) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF() },
        body: JSON.stringify(datos),
    });
}

function claveBinaria(base64) {
    const relleno = '='.repeat((4 - (base64.length % 4)) % 4);
    const crudo = atob((base64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...crudo].map((c) => c.charCodeAt(0)));
}

const soportado = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

async function registrar(boton) {
    const registro = await navigator.serviceWorker.ready;
    const s = (await registro.pushManager.getSubscription())
        || (await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: claveBinaria(boton.dataset.clave) }));
    const r = await enviar(boton.dataset.guardar, s.toJSON());
    if (!r.ok) throw new Error();
    return registro;
}

async function iniciar(boton) {
    if (!soportado() || Notification.permission === 'denied') return;
    // Ya dio permiso antes: se registra solo (también si cambió de usuario en ese celular)
    if (Notification.permission === 'granted') {
        registrar(boton).catch(() => {});
        return;
    }
    boton.classList.replace('hidden', 'inline-flex');
    boton.addEventListener('click', async () => {
        boton.disabled = true;
        try {
            if (await Notification.requestPermission() !== 'granted') {
                alert('El celular no dio permiso para avisos. Actívalo en la configuración del navegador (Notificaciones) y vuelve a entrar.');
                return;
            }
            const registro = await registrar(boton);
            boton.classList.replace('inline-flex', 'hidden');
            registro.showNotification('🔔 Avisos activados', { body: 'Aquí te llegarán los pedidos del QR, llamadas y platos listos.', icon: boton.dataset.icono });
        } catch (e) {
            alert('No se pudo activar los avisos en este equipo. En iPhone primero instala la app (Compartir → Agregar a inicio).');
        } finally {
            boton.disabled = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-avisos-celular]').forEach((b) => iniciar(b));
});
