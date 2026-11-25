/**
 * Balanza electrónica conectada a la PC por cable (USB o serial RS-232), leída desde el navegador (Chrome o Edge en
 * computadora, con https:// o localhost). La mayoría de balanzas comerciales mandan el peso todo el tiempo como texto
 * ("  1.250 kg", "ST,GS,+0001.250kg", "1250 g"); aquí se toma el último número de cada línea y se pasa a kilos.
 *
 *   Balanza.disponible()          ¿el navegador puede leer balanzas?
 *   Balanza.conectar()            pide elegir el puerto (la primera vez) y empieza a leer
 *   Balanza.reconectar()          al abrir la pantalla: vuelve a usar el puerto ya permitido, sin preguntar
 *   Balanza.configurar()          velocidad (baudios) y el comando para pedir el peso, si la balanza lo necesita
 *   window 'balanza'              evento con detail = { kg, estable, conectada } cada vez que cambia
 *
 * La configuración queda en este equipo (localStorage). Si la balanza solo manda el peso cuando se le pide, se pone su
 * comando (por ejemplo "P" o "W") y se le pide cada medio segundo.
 */
(function () {
    const CLAVE = 'balanza-config';
    const estado = { kg: null, estable: false, conectada: false };
    let puerto = null;
    let lector = null;
    let pedirCada = null;
    let historial = [];

    function config() {
        try { return Object.assign({ baudios: 9600, comando: '' }, JSON.parse(localStorage.getItem(CLAVE) || '{}')); } catch (e) { return { baudios: 9600, comando: '' }; }
    }

    function avisar() {
        window.dispatchEvent(new CustomEvent('balanza', { detail: { ...estado } }));
    }

    /** "ST,GS,+0001.250kg" → 1.25 · "1250 g" → 1.25 · "2,500" → 2.5 · null si no hay número */
    function leerPeso(linea) {
        const numeros = [...linea.matchAll(/([-+]?\d+(?:[.,]\d+)?)\s*(kg|g|lb)?/gi)];
        if (!numeros.length) return null;
        const [, valor, unidad] = numeros[numeros.length - 1];
        let kg = parseFloat(valor.replace(',', '.'));
        if (Number.isNaN(kg)) return null;
        const u = (unidad || '').toLowerCase();
        if (u === 'g') kg /= 1000;
        if (u === 'lb') kg *= 0.45359237;
        // "US" (inestable) / "ST" (estable) cuando la balanza lo dice; si no, estable = 3 lecturas iguales seguidas
        const marcaInestable = /\bUS\b|unstable|motion/i.test(linea);
        return { kg: Math.round(Math.max(kg, 0) * 1000) / 1000, marcaInestable, marcaEstable: /\bST\b|stable/i.test(linea) };
    }

    function procesar(linea) {
        const p = leerPeso(linea);
        if (!p) return;
        historial = [...historial.slice(-2), p.kg];
        const estable = p.marcaEstable || (!p.marcaInestable && historial.length === 3 && historial.every(k => k === p.kg));
        if (p.kg !== estado.kg || estable !== estado.estable) {
            estado.kg = p.kg;
            estado.estable = estable;
            avisar();
        }
    }

    async function leer() {
        const decodificador = new TextDecoder();
        let resto = '';
        while (puerto?.readable) {
            lector = puerto.readable.getReader();
            try {
                for (;;) {
                    const { value, done } = await lector.read();
                    if (done) break;
                    resto += decodificador.decode(value, { stream: true });
                    // Cada lectura termina en salto de línea o en los separadores STX/ETX que usan algunas balanzas
                    const partes = resto.split(/[\r\n\x02\x03]+/);
                    resto = partes.pop();
                    partes.filter(Boolean).forEach(procesar);
                    if (resto.length > 200) resto = '';
                }
            } catch (e) {
                break;
            } finally {
                lector.releaseLock();
            }
        }
        cerrado();
    }

    function cerrado() {
        clearInterval(pedirCada);
        estado.conectada = false;
        estado.kg = null;
        avisar();
    }

    async function abrir(p) {
        const c = config();
        await p.open({ baudRate: Number(c.baudios) || 9600 });
        puerto = p;
        estado.conectada = true;
        avisar();
        if (c.comando) {
            const escritor = p.writable.getWriter();
            const bytes = new TextEncoder().encode(c.comando.replace(/\\r/g, '\r').replace(/\\n/g, '\n'));
            pedirCada = setInterval(() => escritor.write(bytes).catch(() => {}), 500);
        }
        leer();
    }

    window.Balanza = {
        estado,
        disponible() { return 'serial' in navigator && window.isSecureContext; },

        async conectar() {
            if (!this.disponible()) throw new Error('La balanza se conecta desde Chrome o Edge en una computadora (con https://).');
            if (estado.conectada) return;
            await abrir(await navigator.serial.requestPort());
        },

        async reconectar() {
            if (!this.disponible() || estado.conectada) return;
            const permitidos = await navigator.serial.getPorts();
            if (permitidos.length) {
                try { await abrir(permitidos[0]); } catch (e) { /* la balanza está apagada o desconectada */ }
            }
        },

        async desconectar() {
            try { await lector?.cancel(); await puerto?.close(); } catch (e) { /* ya estaba cerrada */ }
            puerto = null;
            cerrado();
        },

        configurar() {
            const c = config();
            const baudios = prompt('Velocidad de la balanza (baudios). Casi siempre 9600; mira el manual de la balanza:', c.baudios);
            if (baudios === null) return false;
            const comando = prompt('¿La balanza manda el peso sola? Déjalo vacío.\nSi hay que pedírselo, escribe su comando (por ejemplo P o W):', c.comando);
            if (comando === null) return false;
            localStorage.setItem(CLAVE, JSON.stringify({ baudios: Number(baudios) || 9600, comando: comando.trim() }));
            return true;
        },

        leerPeso,
    };

    navigator.serial?.addEventListener?.('disconnect', () => { puerto = null; cerrado(); });
})();
