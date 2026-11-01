/**
 * Tiempo real (Laravel Reverb): las pantallas de mesas, cocina y avisos del QR se enteran al instante de lo que cambia
 * en su sucursal. La conexión se abre solo en las pantallas que la usan (window.tushpaEnVivo), no en todas.
 *
 *   const vivo = tushpaEnVivo(['cocina'], () => cargar());   // cada vez que algo cambia en esos temas
 *   vivo.conectado()                                        // true = no hace falta preguntar seguido al servidor
 *
 * El canal de la sucursal y la clave pública de Reverb los pone la página en <meta name="tushpa-canal" data-clave>
 * (así activar Reverb en el servidor no obliga a recompilar los estilos). Sin Reverb configurado (o si se cae), las
 * pantallas siguen actualizándose solas cada pocos segundos, como antes.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echo = null;
const sinConexion = { conectado: () => false };

function conectar(clave) {
    if (echo) return echo;
    const segura = (import.meta.env.VITE_REVERB_SCHEME || location.protocol.replace(':', '')) === 'https';
    const puerto = Number(import.meta.env.VITE_REVERB_PORT) || (segura ? 443 : 80);
    echo = new Echo({
        broadcaster: 'reverb',
        key: clave,
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
    const meta = document.querySelector('meta[name="tushpa-canal"]');
    const canal = meta?.content;
    const clave = meta?.dataset.clave || import.meta.env.VITE_REVERB_APP_KEY;
    if (!canal || !clave) return sinConexion;
    try {
        const e = conectar(clave);
        e.private(canal).listen('.cambio', (datos) => {
            if (!temas || (datos.temas || []).some((t) => temas.includes(t))) alCambiar(datos);
        });
        return { conectado: () => e.connector?.pusher?.connection?.state === 'connected' };
    } catch (err) {
        return sinConexion;
    }
};
