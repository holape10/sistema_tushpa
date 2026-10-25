/*
 * CAJA SIN INTERNET (TUSHPA)
 * Mientras hay internet, la PC de caja guarda un "paquete" con todo lo necesario para vender sola (productos, precios,
 * mesas, medios de pago, impresoras, su serie propia). Si se cae el internet, la pantalla /caja-sin-internet vende con
 * esos datos, imprime directo con el agente de la PC (http://127.0.0.1:17777) y guarda las ventas en la PC.
 * Al volver el internet las sube solas (cualquier pantalla del sistema abierta lo hace) y ahí se envían a SUNAT.
 *
 * window.CajaOffline: paquete(), actualizarPaquete(), siguienteNumero(), guardarVenta(), pendientes(), sincronizar(),
 *                     mesas(), guardarMesa(), borrarMesa(), imprimir(), agenteDisponible(), ticketVenta(), ticketComanda(),
 *                     ticketPrecuenta(), totales(), numeroLetras(), mantener()
 */
(function () {
    'use strict';
    const CFG = window.TUSHPA_CAJA || {};
    const AGENTE = 'http://127.0.0.1:17777';
    const DB_NOMBRE = 'tushpa_caja_' + location.host;

    // ------------------------------------------------------------------ almacenamiento en la PC (IndexedDB)

    let conexion = null;
    function db() {
        conexion ??= new Promise((ok, mal) => {
            const r = indexedDB.open(DB_NOMBRE, 1);
            r.onupgradeneeded = () => {
                const d = r.result;
                d.createObjectStore('kv');
                d.createObjectStore('ventas', { keyPath: 'uuid' });
                d.createObjectStore('mesas', { keyPath: 'id' });
            };
            r.onsuccess = () => ok(r.result);
            r.onerror = () => mal(r.error);
        });
        return conexion;
    }

    async function operar(almacen, modo, fn) {
        const d = await db();
        return new Promise((ok, mal) => {
            const tx = d.transaction(almacen, modo);
            let resultado;
            Promise.resolve(fn(tx.objectStore(almacen), tx)).then((r) => { resultado = r; });
            tx.oncomplete = () => ok(resultado);
            tx.onerror = () => mal(tx.error);
            tx.onabort = () => mal(tx.error);
        });
    }
    const pedir = (req) => new Promise((ok, mal) => { req.onsuccess = () => ok(req.result); req.onerror = () => mal(req.error); });
    const leer = (almacen, clave) => operar(almacen, 'readonly', (s) => pedir(s.get(clave)));
    const escribir = (almacen, valor, clave) => operar(almacen, 'readwrite', (s) => pedir(clave === undefined ? s.put(valor) : s.put(valor, clave)));
    const todos = (almacen) => operar(almacen, 'readonly', (s) => pedir(s.getAll()));
    const quitar = (almacen, clave) => operar(almacen, 'readwrite', (s) => pedir(s.delete(clave)));

    // ------------------------------------------------------------------ identidad de la PC y paquete de datos

    /** Nombre de esta caja (el de "¿Qué caja?" o el que se le dio al activarla). Vacío = esta PC no es caja sin internet */
    function pcLocal() {
        try { return localStorage.getItem('tushpa_pc') || ''; } catch (e) { return ''; }
    }

    /**
     * Activa sola esta PC como caja sin internet (sin que el cajero haga nada): solo en computadoras (no celulares),
     * con internet. Si la PC eligió "¿Qué caja?", el servidor usa ese nombre; si no, se le inventa uno.
     */
    async function activarSola() {
        if (pcLocal()) return true;
        const esComputadora = window.matchMedia('(pointer: fine)').matches && window.innerWidth >= 900;
        if (!navigator.onLine || !esComputadora) return false;
        await actualizarPaquete('PC-' + Math.random().toString(36).slice(2, 6).toUpperCase());
        return true;
    }

    async function paquete() {
        return leer('kv', 'paquete');
    }

    async function actualizarPaquete(nombre) {
        if (!CFG.paquete) throw new Error('Falta la dirección del paquete.');
        const r = await fetch(CFG.paquete + '?pc=' + encodeURIComponent(nombre || pcLocal()), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        if (r.status === 401 || r.redirected) throw new Error('SESION');
        const d = await r.json().catch(() => ({}));
        if (!r.ok || !d.ok) throw new Error(d.mensaje || 'No se pudo bajar los datos de la caja.');
        await escribir('kv', d, 'paquete');
        await escribir('kv', Date.now(), 'paquete_fecha');
        try { localStorage.setItem('tushpa_pc', d.pc); } catch (e) { /* sin almacenamiento */ }
        guardarPantalla();
        return d;
    }

    /** Guarda la pantalla de la caja en la PC (el service worker la sirve desde ahí cuando no hay internet) */
    function guardarPantalla() {
        if (!CFG.pagina || !('caches' in window) || !navigator.onLine) return;
        caches.open('tushpa-caja').then((c) => fetch(CFG.pagina, { credentials: 'same-origin', cache: 'no-store' })
            .then((r) => (r.ok && !r.redirected ? c.put(CFG.pagina, r) : null))).catch(() => {});
    }

    /**
     * Siguiente número de la serie propia de esta caja. Se guarda ANTES de imprimir: aunque se corte la luz, ese número
     * no se vuelve a usar. Arranca después del mayor entre lo que dice el servidor y lo que ya usó esta PC.
     */
    async function siguienteNumero(tdocod) {
        const p = await paquete();
        const serie = p?.series?.[tdocod];
        if (!serie) throw new Error('Esta caja no tiene serie para ese comprobante. Conéctate a internet una vez para bajarla.');
        return operar('kv', 'readwrite', async (s) => {
            const clave = 'numero_' + serie.serie;
            const usado = (await pedir(s.get(clave))) || 0;
            const numero = Math.max(Number(usado), Number(serie.ultimo || 0)) + 1;
            s.put(numero, clave);
            return { serie: serie.serie, numero };
        });
    }

    // ------------------------------------------------------------------ ventas guardadas y subida

    async function guardarVenta(venta) {
        venta.estado ??= 'PENDIENTE';
        venta.guardada ??= Date.now();
        await escribir('ventas', venta);
        return venta;
    }

    async function pendientes() {
        return (await todos('ventas')).filter((v) => v.estado !== 'OK').sort((a, b) => a.guardada - b.guardada);
    }

    async function ventas() {
        return (await todos('ventas')).sort((a, b) => b.guardada - a.guardada);
    }

    let subiendo = null;
    /** Sube lo pendiente de a 20. Sin internet o con error de red se queda pendiente para el siguiente intento. */
    async function sincronizar() {
        if (subiendo) return subiendo;
        subiendo = (async () => {
            let lista = (await pendientes()).filter((v) => v.estado === 'PENDIENTE');
            if (!lista.length || !navigator.onLine || !CFG.sincronizar) return { subidas: 0, errores: 0, quedan: lista.length };
            // Token fresco (la sesión pudo renovarse mientras no había internet)
            let p;
            try { p = await actualizarPaquete(); } catch (e) { return { subidas: 0, errores: 0, quedan: lista.length, sesion: e.message === 'SESION' }; }
            // Cada venta se sube con el usuario que la hizo (su turno): las de otro cajero esperan a que él ingrese
            const ajenas = lista.filter((v) => v.usuario && v.usuario !== p.usuario.id).length;
            lista = lista.filter((v) => !v.usuario || v.usuario === p.usuario.id);
            let subidas = 0, errores = 0;
            for (let i = 0; i < lista.length; i += 20) {
                const lote = lista.slice(i, i + 20);
                let r;
                try {
                    r = await fetch(CFG.sincronizar, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': p.csrf },
                        body: JSON.stringify({ pc: pcLocal(), ventas: lote.map(limpiar) }),
                    });
                } catch (e) {
                    break; // se cayó otra vez: sigue pendiente
                }
                if (r.status === 419 || r.status === 401) return { subidas, errores, quedan: lista.length - subidas, sesion: true };
                const d = await r.json().catch(() => null);
                if (r.status === 422 && d?.errors) {
                    // Datos que el servidor no acepta: se marcan para revisarlos (no se reintentan solos)
                    for (const v of lote) { v.estado = 'ERROR'; v.error = Object.values(d.errors).flat()[0]; await guardarVenta(v); errores++; }
                    continue;
                }
                if (!r.ok || !d?.ok) break;
                for (const v of lote) {
                    const res = d.resultados[v.uuid];
                    if (!res) continue;
                    v.estado = res.ok ? 'OK' : 'ERROR';
                    v.error = res.ok ? null : res.mensaje;
                    v.id = res.id;
                    v.subida = Date.now();
                    await guardarVenta(v);
                    res.ok ? subidas++ : errores++;
                }
            }
            await limpiarViejas();
            const quedan = (await pendientes()).length;
            window.dispatchEvent(new CustomEvent('caja-offline-sincronizada', { detail: { subidas, errores, quedan, ajenas } }));
            return { subidas, errores, quedan, ajenas };
        })().finally(() => { subiendo = null; });
        return subiendo;
    }

    /** Lo que se manda al servidor (sin los datos que solo usa la pantalla) */
    function limpiar(v) {
        const { estado, error, guardada, subida, id, ticket, usuario, mesa_nombre, ...resto } = v;
        return resto;
    }

    /** Las ventas ya subidas se guardan 3 días (para reimprimir) y luego se borran */
    async function limpiarViejas() {
        const limite = Date.now() - 3 * 86400000;
        for (const v of await todos('ventas')) {
            if (v.estado === 'OK' && (v.subida || 0) < limite) await quitar('ventas', v.uuid);
        }
    }

    async function reintentar(uuid) {
        const v = await leer('ventas', uuid);
        if (v) { v.estado = 'PENDIENTE'; v.error = null; await guardarVenta(v); }
        return sincronizar();
    }

    // ------------------------------------------------------------------ mesas abiertas sin internet

    const mesas = () => todos('mesas');
    const guardarMesa = (m) => escribir('mesas', m);
    const borrarMesa = (id) => quitar('mesas', id);

    // ------------------------------------------------------------------ totales (igual que el servidor)

    const r2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    function totales(items, gravado, factor) {
        const total = r2(items.reduce((s, i) => s + r2(i.cantidad * i.precio), 0));
        const base = gravado ? r2(total / factor) : 0;
        return { total, gravada: base, igv: gravado ? r2(total - base) : 0, exonerada: gravado ? 0 : total };
    }

    // ------------------------------------------------------------------ monto en letras (leyenda 1000 de SUNAT)

    const UNI = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
        'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO',
        'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    const DEC = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    const CEN = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    function letras(n) {
        if (n >= 1000000) { const m = Math.floor(n / 1000000), r = n % 1000000; return (m === 1 ? 'UN MILLON' : letras(m) + ' MILLONES') + (r ? ' ' + letras(r) : ''); }
        if (n >= 1000) { const m = Math.floor(n / 1000), r = n % 1000; return (m === 1 ? 'MIL' : letras(m).replace(/VEINTIUNO$/, 'VEINTIUN').replace(/UNO$/, 'UN') + ' MIL') + (r ? ' ' + letras(r) : ''); }
        if (n >= 100) { if (n === 100) return 'CIEN'; const r = n % 100; return CEN[Math.floor(n / 100)] + (r ? ' ' + letras(r) : ''); }
        if (n < 30) return UNI[n];
        const u = n % 10; return DEC[Math.floor(n / 10)] + (u ? ' Y ' + UNI[u] : '');
    }
    function numeroLetras(monto) {
        monto = r2(monto);
        const entero = Math.floor(monto), cent = Math.round((monto - entero) * 100);
        return 'SON ' + (entero === 0 ? 'CERO' : letras(entero)).trim() + ' CON ' + String(cent).padStart(2, '0') + '/100 SOLES';
    }

    // ------------------------------------------------------------------ tickets ESC/POS (igual que Escpos.php)

    // Página de códigos PC850 (la misma que elige el encabezado: ESC t 2)
    const CP850 = { 'Ç': 0x80, 'ü': 0x81, 'é': 0x82, 'â': 0x83, 'ä': 0x84, 'à': 0x85, 'ç': 0x87, 'ê': 0x88, 'ë': 0x89, 'è': 0x8A, 'ï': 0x8B, 'î': 0x8C, 'ì': 0x8D,
        'Ä': 0x8E, 'É': 0x90, 'ô': 0x93, 'ö': 0x94, 'ò': 0x95, 'û': 0x96, 'ù': 0x97, 'Ö': 0x99, 'Ü': 0x9A, '£': 0x9C, 'á': 0xA0, 'í': 0xA1, 'ó': 0xA2,
        'ú': 0xA3, 'ñ': 0xA4, 'Ñ': 0xA5, 'ª': 0xA6, 'º': 0xA7, '¿': 0xA8, '¡': 0xAD, 'Á': 0xB5, 'Â': 0xB6, 'À': 0xB7, 'Ã': 0xC7, 'ã': 0xC6, 'Ê': 0xD2,
        'Ë': 0xD3, 'È': 0xD4, 'Í': 0xD6, 'Î': 0xD7, 'Ï': 0xD8, 'Ì': 0xDE, 'Ó': 0xE0, 'Ô': 0xE2, 'Ò': 0xE3, 'õ': 0xE4, 'Õ': 0xE5, 'Ú': 0xE9, 'Û': 0xEA,
        'Ù': 0xEB, '°': 0xF8, '·': 0xFA, '×': 0x9E };
    function cp850(texto) {
        const bytes = [];
        for (const c of String(texto)) {
            const code = c.charCodeAt(0);
            if (code < 128) bytes.push(code);
            else if (CP850[c] !== undefined) bytes.push(CP850[c]);
            else {
                const base = c.normalize('NFD').replace(/[̀-ͯ]/g, '');
                bytes.push(base.length === 1 && base.charCodeAt(0) < 128 ? base.charCodeAt(0) : 0x3F);
            }
        }
        return bytes;
    }

    /** Como wordwrap() de PHP con corte de palabras largas */
    function partir(texto, ancho) {
        const lineas = [];
        for (const parrafo of String(texto).split('\n')) {
            let actual = '';
            for (let palabra of parrafo.split(' ')) {
                while (palabra.length > ancho) {
                    if (actual) { lineas.push(actual); actual = ''; }
                    lineas.push(palabra.slice(0, ancho));
                    palabra = palabra.slice(ancho);
                }
                if (!actual) actual = palabra;
                else if ((actual + ' ' + palabra).length <= ancho) actual += ' ' + palabra;
                else { lineas.push(actual); actual = palabra; }
            }
            lineas.push(actual);
        }
        return lineas;
    }

    class Ticket {
        constructor(columnas = 42, encabezadoBase64 = null) {
            this.columnas = columnas;
            // Inicializa y elige PC850; si hay encabezado (logo, nombre, RUC), ya trae esa inicialización
            this.bytes = encabezadoBase64 ? Array.from(atob(encabezadoBase64), (c) => c.charCodeAt(0)) : [0x1B, 0x40, 0x1B, 0x74, 0x02];
        }
        crudo(...b) { this.bytes.push(...b); return this; }
        alinear(a) { return this.crudo(0x1B, 0x61, { izq: 0, centro: 1, der: 2 }[a] ?? 0); }
        negrita(on = true) { return this.crudo(0x1B, 0x45, on ? 1 : 0); }
        tamano(ancho = 1, alto = 1) { return this.crudo(0x1D, 0x21, ((ancho - 1) << 4) | (alto - 1)); }
        fuente(f = 'a') { return this.crudo(0x1B, 0x4D, f === 'b' ? 1 : 0); }
        columnasB() { return Math.round(this.columnas * 4 / 3); }
        texto(t = '') { this.bytes.push(...cp850(t), 0x0A); return this; }
        parrafo(t, ancho) { partir(t, ancho || this.columnas).forEach((l) => this.texto(l)); return this; }
        linea(c = '-') { return this.texto(c.repeat(this.columnas)); }
        dosColumnas(izq, der) {
            const espacio = this.columnas - der.length - 1;
            izq = izq.length > espacio ? izq.slice(0, espacio) : izq;
            return this.texto(izq + ' '.repeat(Math.max(1, this.columnas - izq.length - der.length)) + der);
        }
        item(desc, cant, precio, total) {
            this.parrafo(desc);
            return this.dosColumnas('  ' + numero(cant) + ' x ' + dinero(precio), dinero(total));
        }
        avanzar(n = 1) { return this.crudo(0x1B, 0x64, Math.max(0, Math.min(255, n))); }
        qr(datos, tamano = 5) {
            const d = cp850(datos), len = d.length + 3;
            return this.crudo(0x1D, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00)
                .crudo(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, Math.max(1, Math.min(16, tamano)))
                .crudo(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x31)
                .crudo(0x1D, 0x28, 0x6B, len % 256, Math.floor(len / 256), 0x31, 0x50, 0x30, ...d)
                .crudo(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30);
        }
        cortar() { return this.crudo(0x1D, 0x56, 0x42, 0x00); }
        abrirCajon() { return this.crudo(0x1B, 0x70, 0x00, 0x19, 0xFA); }
        base64(copias = 1) {
            let s = '';
            const un = this.bytes;
            for (let c = 0; c < Math.max(1, copias); c++) for (let i = 0; i < un.length; i++) s += String.fromCharCode(un[i]);
            return btoa(s);
        }
    }
    const dinero = (n) => Number(n).toFixed(2);
    const numero = (n) => String(Number(Number(n).toFixed(2)));
    const TIPOS = { '03': 'BOLETA DE VENTA ELECTRÓNICA', '01': 'FACTURA ELECTRÓNICA', '13': 'NOTA DE VENTA' };

    /** Ticket del comprobante (diseño del sistema: encabezado, datos, productos, totales, QR de SUNAT y pie) */
    function ticketVenta(p, v, impresora) {
        const e = p.empresa;
        const t = new Ticket(impresora.columnas, p.encabezado);
        const num = v.serie + '-' + String(v.numero).padStart(8, '0');
        const tot = totales(v.items, e.gravado, v.mesa ? e.factor_mesa : e.factor);
        t.linea('=').negrita().texto(TIPOS[v.tdocod]).tamano(1, 2).texto(num).tamano().negrita(false).linea('=');
        t.alinear('izq').texto('Fecha : ' + v.fecha.slice(8, 10) + '/' + v.fecha.slice(5, 7) + '/' + v.fecha.slice(0, 4) + ' ' + v.fecha.slice(11, 16))
            .parrafo('Cliente: ' + v.clinom).texto((v.tdicod === '6' ? 'RUC' : 'DOC') + '   : ' + v.clinum);
        if (v.clidir && v.clidir !== '--') t.parrafo('Dir.  : ' + v.clidir);
        if (v.mesa_nombre) t.texto('Mesa  : ' + v.mesa_nombre);
        t.parrafo('Cajero: ' + p.usuario.nombre).texto('Pago  : CONTADO');
        if (v.observaciones) t.parrafo('Obs.  : ' + v.observaciones);
        t.linea();
        v.items.forEach((i) => t.item(i.descripcion || i.nombre, i.cantidad, i.precio, r2(i.cantidad * i.precio)));
        t.linea();
        if (tot.gravada > 0) t.dosColumnas('OP. GRAVADA', dinero(tot.gravada));
        if (tot.exonerada > 0) t.dosColumnas('OP. EXONERADA', dinero(tot.exonerada));
        if (tot.igv > 0) t.dosColumnas('IGV', dinero(tot.igv));
        t.negrita().tamano(1, 2).dosColumnas('TOTAL S/', dinero(tot.total)).tamano().negrita(false);
        v.pagos.forEach((pg) => t.dosColumnas(pg.nombre || 'PAGO', dinero(pg.monto)));
        if (v.paga > 0) t.dosColumnas('PAGA CON', dinero(v.paga));
        if (v.paga > tot.total) t.dosColumnas('VUELTO', dinero(v.paga - tot.total));
        t.parrafo(numeroLetras(tot.total));
        if (v.tdocod !== '13') {
            const qr = [e.ruc, v.tdocod, v.serie, v.numero, dinero(tot.igv), dinero(tot.total), v.fecha.slice(0, 10), v.tdicod, v.clinum, ''].join('|') + '|';
            t.avanzar().alinear('centro').qr(qr).avanzar().texto('Representación impresa de la ' + TIPOS[v.tdocod].toLowerCase())
                .texto('Consulta tu comprobante en:').texto(e.consulta);
        }
        t.alinear('centro');
        if (!e.gravado) t.parrafo(e.leyenda);
        t.texto('¡Gracias por su preferencia!');
        if (e.firma) t.fuente('b').parrafo(e.firma, t.columnasB()).fuente();
        return t.avanzar(3).cortar();
    }

    /** Comanda para cocina o barra (igual a la del sistema, con aviso de que salió sin internet) */
    function ticketComanda(p, impresora, destino, items, atiende, anulacion = false) {
        const t = new Ticket(impresora.columnas);
        t.alinear('centro');
        if (anulacion) t.negrita().tamano(2, 2).texto('ANULACIÓN').tamano().negrita(false);
        else t.negrita().texto('COMANDA - ' + impresora.nombre.toUpperCase()).negrita(false);
        const ahora = new Date();
        t.tamano(2, 2).negrita().texto(destino).negrita(false).tamano()
            .texto('SIN INTERNET · ' + ahora.toLocaleDateString('es-PE') + ' ' + ahora.toTimeString().slice(0, 5)).texto('Atiende: ' + (atiende || ''));
        t.alinear('izq').linea('=');
        items.forEach((i) => {
            t.tamano(1, 2).negrita().parrafo(numero(i.cantidad) + '  ' + i.nombre).negrita(false).tamano();
            if (i.observacion) t.parrafo('   >> ' + i.observacion);
        });
        return t.linea('=').avanzar(3).cortar();
    }

    function ticketPrecuenta(p, impresora, mesa) {
        const t = new Ticket(impresora.columnas, p.encabezado);
        const tot = totales(mesa.items, p.empresa.gravado, p.empresa.factor_mesa);
        t.linea('=').negrita().tamano(1, 2).texto('PRECUENTA').tamano().texto(mesa.nombre).negrita(false).linea('=').alinear('izq');
        mesa.items.forEach((i) => t.item(i.nombre, i.cantidad, i.precio, r2(i.cantidad * i.precio)));
        t.linea().negrita().tamano(1, 2).dosColumnas('TOTAL S/', dinero(tot.total)).tamano().negrita(false)
            .alinear('centro').texto('Este no es un comprobante de pago');
        return t.avanzar(3).cortar();
    }

    // ------------------------------------------------------------------ impresión con el agente de esta PC

    async function agenteDisponible() {
        try {
            const r = await fetch(AGENTE + '/estado', { signal: AbortSignal.timeout(1500) });
            return r.ok;
        } catch (e) {
            return false;
        }
    }

    async function imprimir(impresora, ticket, referencia, copias = 1) {
        const r = await fetch(AGENTE + '/imprimir', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, signal: AbortSignal.timeout(15000),
            body: JSON.stringify({ ruta: impresora.ruta, conexion: impresora.conexion, contenido: ticket.base64(copias), referencia }),
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok || !d.ok) throw new Error(d.error || 'El agente no pudo imprimir.');
        return true;
    }

    // ------------------------------------------------------------------ en todas las pantallas: datos al día y subida

    function mantener() {
        if (!pcLocal()) return; // esta PC no se activó como caja sin internet
        const ciclo = async () => {
            if (!navigator.onLine || document.hidden) return;
            // Si la pantalla de la caja no quedó guardada en la PC (o se borró), se guarda de nuevo
            if ('caches' in window && !(await caches.match(CFG.pagina, { ignoreSearch: true, ignoreVary: true }).catch(() => null))) guardarPantalla();
            const fecha = await leer('kv', 'paquete_fecha').catch(() => 0);
            // Cada 2 minutos: las mesas abiertas en el sistema deben estar al día si se cae el internet
            if (!fecha || Date.now() - fecha > 2 * 60000) await actualizarPaquete().catch(() => {});
            await sincronizar().catch(() => {});
        };
        setTimeout(ciclo, 3000);
        setInterval(ciclo, 60000);
        window.addEventListener('online', () => setTimeout(() => sincronizar().catch(() => {}), 1500));
    }

    window.CajaOffline = {
        pcLocal, activarSola, paquete, actualizarPaquete, siguienteNumero, guardarVenta, ventas, pendientes, sincronizar, reintentar,
        mesas, guardarMesa, borrarMesa, totales, numeroLetras, Ticket, ticketVenta, ticketComanda, ticketPrecuenta,
        agenteDisponible, imprimir, mantener, TIPOS,
    };
})();
