/**
 * Tiempo real (Laravel Reverb): las pantallas de mesas, cocina y avisos del QR se enteran al instante de lo que cambia
 * en su sucursal. La conexión se abre solo en las pantallas que la usan (window.tushpaEnVivo), no en todas.
 *
 *   const vivo = tushpaEnVivo(['cocina'], () => cargar());   // cada vez que algo cambia en esos temas
 *   vivo.conectado()                                        // true = no hace falta preguntar seguido al servidor
 *
 * El canal de la sucursal lo pone la página en <meta name="tushpa-canal">. Sin Reverb configurado (o si se cae), las
 * pantallas siguen actualizándose solas cada pocos segundos, como antes.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echo = null;
const sinConexion = { conectado: () => false };

function conectar() {
    if (echo) return echo;
    const segura = (import.meta.env.VITE_REVERB_SCHEME || location.protocol.replace(':', '')) === 'https';
    const puerto = Number(import.meta.env.VITE_REVERB_PORT) || (segura ? 443 : 80);
    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        // En el servidor el tiempo real entra por el mismo dominio de la empresa (Apache lo pasa a Reverb)
        wsHost: import.meta.env.VITE_REVERB_HOST || location.hostname,
        wsPort: puerto,
        wssPort: puerto,
        forceTLS: segura,
        enabledTransports: ['ws', 'wss'],
    });
    return echo;
}

window.tushpaEnVivo = function (temas, alCambiar) {
    const canal = document.querySelector('meta[name="tushpa-canal"]')?.content;
    if (!canal || !import.meta.env.VITE_REVERB_APP_KEY) return sinConexion;
    try {
        const e = conectar();
        e.private(canal).listen('.cambio', (datos) => {
            if (!temas || (datos.temas || []).some((t) => temas.includes(t))) alCambiar(datos);
        });
        return { conectado: () => e.connector?.pusher?.connection?.state === 'connected' };
    } catch (err) {
        return sinConexion;
    }
};
