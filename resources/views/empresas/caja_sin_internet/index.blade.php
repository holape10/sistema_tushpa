{{--
    Caja sin internet: pantalla independiente (sin menú ni datos del servidor) para que se abra guardada en la PC.
    Todo lo que muestra sale del paquete guardado en la PC (window.CajaOffline).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <style>[x-cloak]{display:none!important}</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
    <title>Caja sin internet</title>
    <script>
        window.TUSHPA_CAJA = {
            paquete: @json(route('offline.paquete')),
            sincronizar: @json(route('offline.sincronizar')),
            pagina: @json(route('offline.index')),
        };
    </script>
    <script src="{{ asset('js/caja-sin-internet.js') }}?v={{ filemtime(public_path('js/caja-sin-internet.js')) }}"></script>
    <script>
        function cajaSinInternet() {
            const C = window.CajaOffline;
            const r2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
            const sinTildes = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            const ahora = () => {
                const d = new Date(), z = (n) => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())} ${z(d.getHours())}:${z(d.getMinutes())}:${z(d.getSeconds())}`;
            };
            const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            }));

            return {
                p: null, cargando: true, online: navigator.onLine, agente: null, pendientes: 0, errores: 0, nombrePc: '', subiendo: false,
                vista: 'venta', busqueda: '', categoria: null, carrito: [], mesasAbiertas: [], mesa: null, elegirPresentacion: null,
                cobro: null, ventas: [], aviso: null, avisoTipo: 'ok', procesando: false,

                async init() {
                    this.p = await C.paquete().catch(() => null);
                    this.nombrePc = C.pcLocal();
                    if (this.online) {
                        try { this.p = this.nombrePc ? await C.actualizarPaquete() : (await C.activarSola() ? await C.paquete() : null); this.nombrePc = this.p?.pc || ''; } catch (e) {
                            if (e.message === 'SESION') this.avisar('Tu sesión venció. Ingresa de nuevo al sistema para subir las ventas.', 'error');
                            else if (!this.p) this.avisar(e.message, 'error');
                        }
                    }
                    this.cargando = false;
                    await this.refrescar();
                    this.agente = await C.agenteDisponible();
                    window.addEventListener('online', () => { this.online = true; setTimeout(() => this.subir(), 1500); });
                    window.addEventListener('offline', () => { this.online = false; });
                    window.addEventListener('caja-offline-sincronizada', () => this.refrescar());
                    setInterval(async () => { this.agente = await C.agenteDisponible(); if (this.online) this.subir(true); }, 60000);
                },

                async activar() {
                    const nombre = this.nombrePc.trim().toUpperCase().replace(/[^A-Z0-9_-]/g, '');
                    if (!nombre) { this.avisar('Escribe un nombre para esta caja (por ejemplo CAJA1).', 'error'); return; }
                    try { this.p = await C.actualizarPaquete(nombre); this.nombrePc = this.p.pc; this.avisar('Listo: esta PC ya puede vender sin internet.'); }
                    catch (e) { this.avisar(e.message === 'SESION' ? 'Tu sesión venció. Ingresa de nuevo al sistema.' : e.message, 'error'); }
                },

                async refrescar() {
                    const pend = await C.pendientes();
                    this.pendientes = pend.filter((v) => v.estado === 'PENDIENTE').length;
                    this.errores = pend.filter((v) => v.estado === 'ERROR').length;
                    this.mesasAbiertas = (await C.mesas()).filter((m) => m.items.length);
                    this.ventas = (await C.ventas()).slice(0, 50);
                },

                async subir(silencio = false) {
                    if (!this.online || this.subiendo) return;
                    this.subiendo = true;
                    try {
                        const r = await C.sincronizar();
                        if (r.ajenas) this.avisar(`${r.ajenas} venta(s) son de otro cajero: se subirán cuando él ingrese al sistema en esta PC.`, 'error');
                        if (r.sesion) this.avisar('Tu sesión venció: ingresa de nuevo al sistema (en otra pestaña) y vuelve a "Subir ahora".', 'error');
                        else if (!silencio && r.subidas) this.avisar(`${r.subidas} venta(s) subidas al sistema. Se enviarán a SUNAT.`);
                        else if (!silencio && !r.quedan) this.avisar('No hay ventas pendientes.');
                        if (r.errores) this.avisar(`${r.errores} venta(s) no se pudieron subir. Revísalas en "Ventas de esta PC".`, 'error');
                        if (!r.sesion) this.p = await C.paquete();
                    } finally {
                        this.subiendo = false;
                        await this.refrescar();
                    }
                },

                avisar(texto, tipo = 'ok') {
                    this.aviso = texto; this.avisoTipo = tipo;
                    clearTimeout(this._aviso);
                    this._aviso = setTimeout(() => { this.aviso = null; }, tipo === 'error' ? 9000 : 4000);
                },

                // ---------------------------------------------------------------- productos

                get productos() {
                    if (!this.p) return [];
                    const q = sinTildes(this.busqueda.trim());
                    return this.p.productos.filter((x) => (!this.categoria || x.categoria === this.categoria)
                        && (!q || sinTildes(x.nombre).includes(q) || x.codigo === this.busqueda.trim() || x.barra === this.busqueda.trim())).slice(0, 120);
                },
                color(cat) { return this.p?.categorias.find((c) => c.id === cat)?.color || '#6366f1'; },

                /** Lector de código de barras: Enter con un código exacto lo agrega */
                buscarEnter() {
                    const t = this.busqueda.trim();
                    const x = this.p.productos.find((y) => y.barra === t || y.codigo === t) || (this.productos.length === 1 ? this.productos[0] : null);
                    if (x) { this.tocar(x); this.busqueda = ''; }
                },

                tocar(x) {
                    if (x.presentaciones.length) { this.elegirPresentacion = x; return; }
                    this.agregar(x, null);
                },

                agregar(x, pres) {
                    this.elegirPresentacion = null;
                    const lista = this.mesa ? this.mesa.items : this.carrito;
                    const clave = x.id + '-' + (pres?.id || 0);
                    // En mesa, lo que ya salió a cocina no se toca: se agrega una línea nueva
                    const linea = lista.find((i) => i.clave === clave && !(this.mesa && i.enviado > 0));
                    if (linea) linea.cantidad++;
                    else lista.push({ clave, id: x.id, nombre: pres ? x.nombre + ' (' + pres.nombre + ')' : x.nombre, precio: pres ? pres.precio : x.precio,
                        presentacion: pres?.id || null, categoria: x.categoria, cantidad: 1, enviado: 0, observacion: '' });
                    this.guardarMesaActual();
                },

                cambiar(i, delta) {
                    const lista = this.lista;
                    if (this.mesa && i.enviado > 0 && i.cantidad + delta < i.enviado) {
                        this.avisar('Eso ya salió a cocina. Para anularlo usa "Anular" en la línea.', 'error');
                        return;
                    }
                    i.cantidad = r2(i.cantidad + delta);
                    if (i.cantidad <= 0) lista.splice(lista.indexOf(i), 1);
                    this.guardarMesaActual();
                },

                async anularLinea(i) {
                    if (!confirm(`¿Anular ${i.nombre}? Se avisará a cocina.`)) return;
                    if (i.enviado > 0) await this.imprimirComandas([{ ...i, cantidad: i.enviado }], true);
                    this.lista.splice(this.lista.indexOf(i), 1);
                    this.guardarMesaActual();
                },

                get lista() { return this.mesa ? this.mesa.items : this.carrito; },
                get factor() { return this.mesa ? this.p.empresa.factor_mesa : this.p.empresa.factor; },
                get total() { return C.totales(this.lista, this.p?.empresa.gravado, this.factor || 1.18).total; },

                // ---------------------------------------------------------------- mesas

                estadoMesa(m) { return this.mesasAbiertas.find((x) => x.id === m.id); },
                totalMesa(m) { return C.totales(m.items, false, 1).total.toFixed(2); },
                get pisos() { return [...new Set((this.p?.mesas || []).map((m) => m.piso || 'Salón'))]; },

                async abrirMesa(m) {
                    this.mesa = this.estadoMesa(m) ? JSON.parse(JSON.stringify(this.estadoMesa(m))) : { id: m.id, nombre: m.nombre, items: [], abierta: Date.now() };
                    this.vista = 'venta';
                },

                async guardarMesaActual() {
                    if (!this.mesa) return;
                    const copia = JSON.parse(JSON.stringify(this.mesa));
                    if (copia.items.length) await C.guardarMesa(copia); else await C.borrarMesa(copia.id);
                    this.mesasAbiertas = (await C.mesas()).filter((x) => x.items.length);
                },

                salirMesa() { this.mesa = null; this.vista = 'mesas'; },

                get porEnviar() { return this.mesa ? this.mesa.items.filter((i) => i.cantidad > i.enviado) : []; },

                async enviarComanda() {
                    const lineas = this.porEnviar.map((i) => ({ ...i, cantidad: r2(i.cantidad - i.enviado) }));
                    if (!lineas.length) return;
                    const impresa = await this.imprimirComandas(lineas, false);
                    this.mesa.items.forEach((i) => { i.enviado = i.cantidad; });
                    await this.guardarMesaActual();
                    if (impresa) this.avisar('Comanda enviada.');
                },

                /** Cada producto a la impresora de su categoría (o a caja/barra si la PC tiene barra propia) */
                async imprimirComandas(lineas, anulacion) {
                    const grupos = {};
                    for (const l of lineas) {
                        const destinos = this.p.destinos_comanda || this.p.categorias.find((c) => c.id === l.categoria)?.impresoras || [];
                        for (const d of destinos.length ? destinos : [this.p.impresora_caja].filter(Boolean)) (grupos[d] ??= []).push(l);
                    }
                    const ids = Object.keys(grupos);
                    const fallos = [];
                    for (const id of ids) {
                        const imp = this.p.impresoras.find((x) => x.id === Number(id));
                        if (!imp) continue;
                        const t = C.ticketComanda(this.p, imp, this.mesa ? this.mesa.nombre : 'PARA LLEVAR', grupos[id], this.p.usuario.nombre, anulacion);
                        try { await C.imprimir(imp, t, 'COMANDA ' + (this.mesa?.nombre || ''), this.p.copias?.comanda || 1); }
                        catch (e) { fallos.push(imp.nombre); }
                    }
                    // Sin ticketera de cocina o sin agente: la comanda sale por la impresora del navegador
                    if (!ids.length || fallos.length) {
                        this.imprimirNavegador(this.htmlComanda(lineas, anulacion));
                        this.avisar((fallos.length ? 'No respondió: ' + fallos.join(', ') + '. ' : '') + 'La comanda se abrió para imprimir desde el navegador; llévala a cocina.', 'error');
                        return false;
                    }
                    return true;
                },

                htmlComanda(lineas, anulacion) {
                    return `<div class="c"><b>${anulacion ? 'ANULACIÓN' : 'COMANDA'}<br><span style="font-size:20px">${this.esc(this.mesa ? this.mesa.nombre : 'PARA LLEVAR')}</span></b><br>SIN INTERNET · ${new Date().toLocaleTimeString('es-PE').slice(0, 5)}<br>Atiende: ${this.esc(this.p.usuario.nombre)}</div><hr>`
                        + lineas.map((l) => `<p style="font-size:15px;margin:4px 0"><b>${l.cantidad} ${this.esc(l.nombre)}</b>${l.observacion ? '<br>&nbsp;&nbsp;&gt;&gt; ' + this.esc(l.observacion) : ''}</p>`).join('') + '<hr>';
                },

                async precuenta() {
                    const imp = this.impresoraCaja();
                    if (!imp) { this.imprimirNavegador(this.htmlPrecuenta()); return; }
                    try { await C.imprimir(imp, C.ticketPrecuenta(this.p, imp, this.mesa), 'PRECUENTA ' + this.mesa.nombre); this.avisar('Precuenta impresa.'); }
                    catch (e) { this.avisar(e.message + ' Se abrirá para imprimir desde el navegador.', 'error'); this.imprimirNavegador(this.htmlPrecuenta()); }
                },

                impresoraCaja() { return this.p.impresoras.find((x) => x.id === this.p.impresora_caja) || null; },

                // ---------------------------------------------------------------- cobrar

                abrirCobro() {
                    if (!this.p.turno) { this.avisar('No tienes turno de caja abierto. Ábrelo cuando haya internet para poder vender.', 'error'); return; }
                    if (!this.lista.length) return;
                    if (this.mesa && this.porEnviar.length && !confirm('Hay productos que aún no salieron a cocina. ¿Cobrar igual?')) return;
                    const medio = this.p.medios[0]?.id;
                    this.cobro = { tdocod: this.p.empresa.tdocod, clinum: '', clinom: '', clidir: '', observaciones: '', paga: '',
                        pagos: [{ medio, monto: this.total, operacion: '' }] };
                },

                get sumaPagos() { return r2(this.cobro.pagos.reduce((s, x) => s + Number(x.monto || 0), 0)); },
                get vuelto() { return r2(Number(this.cobro.paga || 0) - this.total); },

                agregarPago() {
                    const falta = r2(this.total - this.sumaPagos);
                    this.cobro.pagos.push({ medio: this.p.medios[1]?.id || this.p.medios[0]?.id, monto: falta > 0 ? falta : 0, operacion: '' });
                },

                async confirmarCobro() {
                    const c = this.cobro, total = this.total;
                    const doc = c.clinum.trim();
                    let cliente = { tdicod: '1', clinum: '00000000', clinom: 'VENTA AL PORTADOR', clidir: '' };
                    if (c.tdocod === '01') {
                        if (!/^(10|15|17|20)\d{9}$/.test(doc)) { this.avisar('Para factura escribe un RUC válido de 11 dígitos.', 'error'); return; }
                        if (!c.clinom.trim()) { this.avisar('Escribe la razón social del cliente (sin internet no se puede buscar el RUC).', 'error'); return; }
                        cliente = { tdicod: '6', clinum: doc, clinom: c.clinom.trim().toUpperCase(), clidir: c.clidir.trim().toUpperCase() };
                    } else if (doc) {
                        if (!/^\d{8}$/.test(doc)) { this.avisar('El DNI debe tener 8 dígitos (o déjalo vacío).', 'error'); return; }
                        if (!c.clinom.trim()) { this.avisar('Escribe el nombre del cliente (sin internet no se puede buscar el DNI).', 'error'); return; }
                        cliente = { tdicod: '1', clinum: doc, clinom: c.clinom.trim().toUpperCase(), clidir: c.clidir.trim().toUpperCase() };
                    } else if (c.tdocod === '03' && total > 700) {
                        this.avisar('Boletas de más de S/ 700 necesitan DNI y nombre del cliente (SUNAT).', 'error'); return;
                    }
                    if (Math.abs(this.sumaPagos - total) > 0.009) { this.avisar(`Los pagos suman S/ ${this.sumaPagos.toFixed(2)} y el total es S/ ${total.toFixed(2)}.`, 'error'); return; }

                    this.procesando = true;
                    try {
                        const { serie, numero } = await C.siguienteNumero(c.tdocod);
                        const venta = {
                            uuid: uuid(), usuario: this.p.usuario.id, tdocod: c.tdocod, serie, numero, fecha: ahora(), id_turno: this.p.turno.id, origen: this.p.empresa.origen,
                            mesa: this.mesa?.id || null, mesa_nombre: this.mesa?.nombre || null, mozo: null, ...cliente,
                            observaciones: c.observaciones.trim().slice(0, 90) || null, total, paga: Number(c.paga || 0),
                            items: this.lista.map((i) => ({ id: i.id, descripcion: i.nombre, cantidad: i.cantidad, precio: i.precio, presentacion: i.presentacion })),
                            pagos: c.pagos.filter((x) => Number(x.monto) > 0).map((x) => ({ medio: Number(x.medio), monto: r2(x.monto), operacion: x.operacion || null,
                                nombre: this.p.medios.find((m) => m.id === Number(x.medio))?.nombre })),
                        };
                        await C.guardarVenta(venta);
                        await this.imprimirVenta(venta);
                        if (this.mesa) { await C.borrarMesa(this.mesa.id); this.mesa = null; this.vista = 'mesas'; } else { this.carrito = []; }
                        this.cobro = null;
                        this.avisar(`Venta ${serie}-${String(numero).padStart(8, '0')} guardada en esta PC.` + (this.online ? '' : ' Se subirá sola cuando vuelva el internet.'));
                        await this.refrescar();
                        if (this.online) this.subir(true);
                    } catch (e) {
                        this.avisar(e.message, 'error');
                    } finally {
                        this.procesando = false;
                    }
                },

                async imprimirVenta(v) {
                    const imp = this.impresoraCaja();
                    if (imp) {
                        try { await C.imprimir(imp, C.ticketVenta(this.p, v, imp), v.serie + '-' + v.numero, this.p.copias?.comprobante || 1); return; }
                        catch (e) { this.avisar(e.message + ' Se abrirá para imprimir desde el navegador.', 'error'); }
                    }
                    this.imprimirNavegador(this.htmlVenta(v));
                },

                // ---------------------------------------------------------------- impresión desde el navegador (sin agente)

                esc(s) { return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); },

                htmlVenta(v) {
                    const e = this.p.empresa, tot = C.totales(v.items, e.gravado, v.mesa ? e.factor_mesa : e.factor), f = (n) => Number(n).toFixed(2);
                    const filas = v.items.map((i) => `<tr><td colspan="2">${this.esc(i.descripcion)}</td></tr><tr><td>&nbsp;&nbsp;${i.cantidad} x ${f(i.precio)}</td><td class="d">${f(i.cantidad * i.precio)}</td></tr>`).join('');
                    return `<div class="c"><b>${this.esc(e.nombre)}</b><br>${this.esc(e.razon)}<br>RUC ${e.ruc}<br>${this.esc(e.direccion)}</div><hr>
                        <div class="c"><b>${C.TIPOS[v.tdocod]}<br>${v.serie}-${String(v.numero).padStart(8, '0')}</b></div><hr>
                        Fecha: ${v.fecha}<br>Cliente: ${this.esc(v.clinom)}<br>Doc: ${v.clinum}${v.mesa_nombre ? '<br>Mesa: ' + this.esc(v.mesa_nombre) : ''}<hr>
                        <table>${filas}</table><hr><table>
                        ${tot.gravada ? `<tr><td>OP. GRAVADA</td><td class="d">${f(tot.gravada)}</td></tr><tr><td>IGV</td><td class="d">${f(tot.igv)}</td></tr>` : `<tr><td>OP. EXONERADA</td><td class="d">${f(tot.exonerada)}</td></tr>`}
                        <tr><td><b>TOTAL S/</b></td><td class="d"><b>${f(tot.total)}</b></td></tr>
                        ${v.paga > tot.total ? `<tr><td>PAGA CON</td><td class="d">${f(v.paga)}</td></tr><tr><td>VUELTO</td><td class="d">${f(v.paga - tot.total)}</td></tr>` : ''}</table>
                        <p>${C.numeroLetras(tot.total)}</p><div class="c">${e.gravado ? '' : this.esc(e.leyenda) + '<br>'}Consulta tu comprobante en:<br>${this.esc(e.consulta)}<br>¡Gracias por su preferencia!</div>`;
                },

                htmlPrecuenta() {
                    const f = (n) => Number(n).toFixed(2);
                    return `<div class="c"><b>PRECUENTA<br>${this.esc(this.mesa.nombre)}</b></div><hr><table>`
                        + this.mesa.items.map((i) => `<tr><td>${i.cantidad} ${this.esc(i.nombre)}</td><td class="d">${f(i.cantidad * i.precio)}</td></tr>`).join('')
                        + `</table><hr><table><tr><td><b>TOTAL S/</b></td><td class="d"><b>${f(this.total)}</b></td></tr></table><div class="c">Este no es un comprobante de pago</div>`;
                },

                imprimirNavegador(html) {
                    const marco = document.createElement('iframe');
                    marco.style.cssText = 'position:fixed;width:0;height:0;border:0';
                    document.body.appendChild(marco);
                    marco.contentDocument.write(`<style>@page{margin:0}body{width:72mm;margin:0 auto;font:12px monospace}
                        table{width:100%;border-collapse:collapse}.d{text-align:right}.c{text-align:center}hr{border:0;border-top:1px dashed #000}</style>${html}`);
                    marco.contentDocument.close();
                    setTimeout(() => { marco.contentWindow.print(); setTimeout(() => marco.remove(), 2000); }, 200);
                },

                async reimprimir(v) { await this.imprimirVenta(v); },
                async reintentar(v) { await C.reintentar(v.uuid); await this.refrescar(); },
            };
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
</head>
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden" x-data="cajaSinInternet()" x-cloak>
<div class="h-full flex flex-col">
    {{-- Barra: internet, agente, pendientes --}}
    <header class="bg-white border-b px-4 py-2 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-extrabold text-indigo-900 mr-2">Caja sin internet</span>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="online ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 animate-pulse'"
              x-text="online ? 'Con internet' : 'Sin internet'"></span>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="agente ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'"
              :title="agente ? 'El agente de impresión de esta PC responde' : 'El agente de esta PC no responde: se imprimirá desde el navegador'"
              x-text="agente === null ? 'Impresora…' : (agente ? 'Impresora lista' : 'Sin agente')"></span>
        <template x-if="p">
            <span class="text-xs text-gray-500">
                <b x-text="p.pc"></b> · <span x-text="p.usuario.nombre"></span> ·
                <span x-text="p.turno ? 'Turno #' + p.turno.numero : 'Sin turno abierto'" :class="p.turno ? '' : 'text-red-600 font-bold'"></span>
            </span>
        </template>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" @click="vista = 'ventas'" class="px-3 py-1.5 rounded-lg text-xs font-bold"
                    :class="pendientes || errores ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'">
                <span x-text="pendientes"></span> por subir<template x-if="errores"><span class="text-red-700"> · <span x-text="errores"></span> con error</span></template>
            </button>
            <button type="button" @click="subir()" :disabled="!online || subiendo" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold disabled:opacity-40"
                    x-text="subiendo ? 'Subiendo…' : 'Subir ahora'"></button>
            <a href="{{ url('/') }}" x-show="online" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold">Volver al sistema</a>
        </div>
    </header>

    {{-- Aviso --}}
    <div x-show="aviso" x-transition class="fixed top-14 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-semibold max-w-lg"
         :class="avisoTipo === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="aviso" @click="aviso = null"></div>

    <div x-show="cargando" class="flex-1 flex items-center justify-center text-gray-500">Cargando…</div>

    {{-- Esta PC aún no es caja sin internet --}}
    <div x-show="!cargando && !p" class="flex-1 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow p-6 max-w-md w-full space-y-3">
            <h1 class="text-lg font-bold">Activar esta PC como caja sin internet</h1>
            <p class="text-sm text-gray-600">Las PCs de caja se activan solas al usar el sistema con el turno abierto. Si esta no se activó, ponle un nombre: guardará los productos, precios y mesas, y tendrá su propia serie de comprobantes.</p>
            <template x-if="online">
                <form @submit.prevent="activar()" class="flex gap-2">
                    <input x-model="nombrePc" maxlength="20" placeholder="Nombre de esta caja (CAJA1)" class="flex-1 rounded-lg border-gray-300 text-sm uppercase">
                    <button class="px-4 rounded-lg bg-indigo-600 text-white text-sm font-bold">Activar</button>
                </form>
            </template>
            <p x-show="!online" class="text-sm text-red-600 font-semibold">No hay internet y esta PC no se activó antes: no se puede vender sin conexión.</p>
        </div>
    </div>

    <template x-if="!cargando && p">
        <div class="flex-1 flex min-h-0">
            {{-- Izquierda: productos / mesas / ventas --}}
            <section class="flex-1 flex flex-col min-w-0">
                <nav class="flex gap-1 px-3 pt-3">
                    <button type="button" @click="vista = 'venta'" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'venta' ? 'bg-white text-indigo-700' : 'text-gray-500'"
                            x-text="mesa ? 'Productos · ' + mesa.nombre : 'Venta rápida'"></button>
                    <button type="button" x-show="p.mesas.length" @click="vista = 'mesas'" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'mesas' ? 'bg-white text-indigo-700' : 'text-gray-500'">
                        Mesas <span class="text-xs" x-show="mesasAbiertas.length" x-text="'(' + mesasAbiertas.length + ')'"></span></button>
                    <button type="button" @click="vista = 'ventas'; refrescar()" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'ventas' ? 'bg-white text-indigo-700' : 'text-gray-500'">Ventas de esta PC</button>
                </nav>

                <div x-show="vista === 'venta'" class="flex-1 flex flex-col min-h-0 bg-white mx-3 mb-3 rounded-b-xl rounded-tr-xl p-3 gap-2">
                    <input x-model="busqueda" @keydown.enter.prevent="buscarEnter()" placeholder="Buscar producto o escanear código…" class="rounded-lg border-gray-300 text-sm" autofocus>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" @click="categoria = null" class="px-3 py-1 rounded-full text-xs font-bold" :class="!categoria ? 'bg-indigo-600 text-white' : 'bg-gray-100'">Todas</button>
                        <template x-for="c in p.categorias" :key="c.id">
                            <button type="button" @click="categoria = c.id" class="px-3 py-1 rounded-full text-xs font-bold" :class="categoria === c.id ? 'bg-indigo-600 text-white' : 'bg-gray-100'" x-text="c.nombre"></button>
                        </template>
                    </div>
                    <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 content-start">
                        <template x-for="x in productos" :key="x.id">
                            <button type="button" @click="tocar(x)" class="text-left rounded-xl border p-2 hover:bg-indigo-50 border-l-4" :style="'border-left-color:' + color(x.categoria)">
                                <span class="block text-sm font-semibold leading-tight line-clamp-2" x-text="x.nombre"></span>
                                <span class="block text-xs text-indigo-700 font-bold mt-1" x-text="'S/ ' + x.precio.toFixed(2) + (x.presentaciones.length ? ' +' : '')"></span>
                            </button>
                        </template>
                        <p x-show="!productos.length" class="col-span-full text-center text-sm text-gray-400 py-8">No hay productos con ese nombre.</p>
                    </div>
                </div>

                <div x-show="vista === 'mesas'" class="flex-1 overflow-y-auto bg-white mx-3 mb-3 rounded-b-xl rounded-tr-xl p-3 space-y-4">
                    <p class="text-xs text-gray-500">Son las mesas atendidas en esta PC sin internet. Las que ya estaban abiertas en el sistema se cobran cuando vuelva el internet.</p>
                    <template x-for="piso in pisos" :key="piso">
                        <div>
                            <h3 class="text-sm font-bold text-gray-600 mb-2" x-text="piso"></h3>
                            <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-7 gap-2">
                                <template x-for="m in p.mesas.filter((x) => (x.piso || 'Salón') === piso)" :key="m.id">
                                    <button type="button" @click="abrirMesa(m)" class="rounded-xl p-3 text-center font-bold text-sm"
                                            :class="estadoMesa(m) ? 'bg-orange-500 text-white' : 'bg-green-100 text-green-800'">
                                        <span x-text="m.nombre"></span>
                                        <span class="block text-xs font-normal" x-text="estadoMesa(m) ? 'S/ ' + totalMesa(estadoMesa(m)) : 'Libre'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="vista === 'ventas'" class="flex-1 overflow-y-auto bg-white mx-3 mb-3 rounded-b-xl rounded-tr-xl p-3">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500 text-left"><tr><th class="py-1">Comprobante</th><th>Hora</th><th>Cliente</th><th class="text-right">Total</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        <template x-for="v in ventas" :key="v.uuid">
                            <tr class="border-t">
                                <td class="py-1.5 font-semibold" x-text="v.serie + '-' + String(v.numero).padStart(8, '0')"></td>
                                <td x-text="v.fecha.slice(11, 16)"></td>
                                <td class="truncate max-w-[10rem]" x-text="v.clinom"></td>
                                <td class="text-right" x-text="Number(v.total).toFixed(2)"></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold"
                                          :class="{ 'bg-green-100 text-green-700': v.estado === 'OK', 'bg-amber-100 text-amber-800': v.estado === 'PENDIENTE', 'bg-red-100 text-red-700': v.estado === 'ERROR' }"
                                          x-text="v.estado === 'OK' ? 'Subida' : (v.estado === 'PENDIENTE' ? 'Por subir' : 'Error')" :title="v.error || ''"></span>
                                    <span x-show="v.error" class="block text-xs text-red-600" x-text="v.error"></span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="reimprimir(v)" class="text-xs text-indigo-700 hover:underline">Reimprimir</button>
                                    <button type="button" x-show="v.estado === 'ERROR'" @click="reintentar(v)" class="ml-2 text-xs text-red-700 hover:underline">Reintentar</button>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                    <p x-show="!ventas.length" class="text-center text-sm text-gray-400 py-8">Aún no hay ventas hechas sin internet en esta PC.</p>
                </div>
            </section>

            {{-- Derecha: pedido --}}
            <aside class="w-80 xl:w-96 bg-white border-l flex flex-col">
                <div class="p-3 border-b flex items-center gap-2">
                    <h2 class="font-bold flex-1" x-text="mesa ? mesa.nombre : 'Pedido'"></h2>
                    <button type="button" x-show="mesa" @click="salirMesa()" class="text-xs text-gray-500 hover:underline">Volver a mesas</button>
                    <button type="button" x-show="!mesa && carrito.length" @click="carrito = []" class="text-xs text-red-600 hover:underline">Vaciar</button>
                </div>
                <div class="flex-1 overflow-y-auto divide-y">
                    <template x-for="i in lista" :key="i.clave + '-' + lista.indexOf(i)">
                        <div class="p-2 text-sm">
                            <div class="flex gap-2">
                                <span class="flex-1 font-semibold leading-tight" x-text="i.nombre"></span>
                                <span class="font-bold" x-text="(i.cantidad * i.precio).toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-1 mt-1">
                                <button type="button" @click="cambiar(i, -1)" class="w-7 h-7 rounded bg-gray-100 font-bold">−</button>
                                <span class="w-8 text-center" x-text="i.cantidad"></span>
                                <button type="button" @click="cambiar(i, 1)" class="w-7 h-7 rounded bg-gray-100 font-bold">+</button>
                                <span class="text-xs text-gray-500 ml-1" x-text="'x ' + i.precio.toFixed(2)"></span>
                                <span x-show="mesa && i.enviado" class="ml-auto text-[11px] text-green-700 font-bold" x-text="'En cocina: ' + i.enviado"></span>
                                <button type="button" x-show="mesa && i.enviado" @click="anularLinea(i)" class="text-[11px] text-red-600 hover:underline ml-1">Anular</button>
                            </div>
                            <input x-show="mesa && i.cantidad > i.enviado" x-model="i.observacion" @change="guardarMesaActual()" placeholder="Nota para cocina" class="mt-1 w-full rounded border-gray-200 text-xs py-1">
                        </div>
                    </template>
                    <p x-show="!lista.length" class="p-6 text-center text-sm text-gray-400" x-text="mesa ? 'Agrega productos a la mesa' : 'Toca un producto para agregarlo'"></p>
                </div>
                <div class="p-3 border-t space-y-2">
                    <div class="flex items-end justify-between"><span class="text-sm text-gray-500">Total</span><span class="text-2xl font-extrabold" x-text="'S/ ' + total.toFixed(2)"></span></div>
                    <template x-if="mesa">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="enviarComanda()" :disabled="!porEnviar.length" class="py-2.5 rounded-lg bg-orange-500 text-white text-sm font-bold disabled:opacity-40">Enviar comanda</button>
                            <button type="button" @click="precuenta()" :disabled="!lista.length" class="py-2.5 rounded-lg bg-gray-200 text-sm font-bold disabled:opacity-40">Precuenta</button>
                        </div>
                    </template>
                    <button type="button" @click="abrirCobro()" :disabled="!lista.length" class="w-full py-3 rounded-lg bg-emerald-600 text-white font-bold disabled:opacity-40">Cobrar</button>
                </div>
            </aside>
        </div>
    </template>

    {{-- Elegir presentación --}}
    <div x-show="elegirPresentacion" class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4" @click.self="elegirPresentacion = null">
        <div class="bg-white rounded-2xl p-4 w-full max-w-sm space-y-2" x-show="elegirPresentacion">
            <h3 class="font-bold" x-text="elegirPresentacion?.nombre"></h3>
            <button type="button" @click="agregar(elegirPresentacion, null)" class="w-full flex justify-between px-3 py-2 rounded-lg bg-gray-100 text-sm">
                <span>Unidad</span><span x-text="'S/ ' + (elegirPresentacion?.precio || 0).toFixed(2)"></span></button>
            <template x-for="pr in (elegirPresentacion?.presentaciones || [])" :key="pr.id">
                <button type="button" @click="agregar(elegirPresentacion, pr)" class="w-full flex justify-between px-3 py-2 rounded-lg bg-gray-100 text-sm">
                    <span x-text="pr.nombre"></span><span x-text="'S/ ' + pr.precio.toFixed(2)"></span></button>
            </template>
        </div>
    </div>

    {{-- Cobrar --}}
    <template x-if="cobro">
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4">
            <form @submit.prevent="confirmarCobro()" class="bg-white rounded-2xl p-5 w-full max-w-lg space-y-3 max-h-full overflow-y-auto">
                <div class="flex items-center justify-between"><h3 class="text-lg font-bold">Cobrar</h3><span class="text-2xl font-extrabold" x-text="'S/ ' + total.toFixed(2)"></span></div>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="t in [['03', 'Boleta'], ['01', 'Factura'], ['13', 'Nota de venta']]" :key="t[0]">
                        <button type="button" x-show="p.series[t[0]]" @click="cobro.tdocod = t[0]" class="py-2 rounded-lg text-sm font-bold"
                                :class="cobro.tdocod === t[0] ? 'bg-indigo-600 text-white' : 'bg-gray-100'" x-text="t[1]"></button>
                    </template>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input x-model="cobro.clinum" inputmode="numeric" maxlength="11" :placeholder="cobro.tdocod === '01' ? 'RUC' : 'DNI (opcional)'" class="rounded-lg border-gray-300 text-sm">
                    <input x-model="cobro.clinom" :placeholder="cobro.tdocod === '01' ? 'Razón social' : 'Nombre'" class="col-span-2 rounded-lg border-gray-300 text-sm uppercase">
                    <input x-show="cobro.tdocod === '01'" x-model="cobro.clidir" placeholder="Dirección (opcional)" class="col-span-3 rounded-lg border-gray-300 text-sm uppercase">
                </div>
                <p class="text-[11px] text-gray-500">Sin internet no se puede buscar el DNI/RUC: escribe el nombre tal como figura. Sin DNI sale a "VENTA AL PORTADOR".</p>
                <div class="space-y-2">
                    <template x-for="(pg, n) in cobro.pagos" :key="n">
                        <div class="flex gap-2">
                            <select x-model.number="pg.medio" class="flex-1 rounded-lg border-gray-300 text-sm">
                                <template x-for="m in p.medios" :key="m.id"><option :value="m.id" x-text="m.nombre" :selected="m.id === pg.medio"></option></template>
                            </select>
                            <input x-model="pg.monto" type="number" step="0.01" min="0" class="w-28 rounded-lg border-gray-300 text-sm text-right">
                            <input x-model="pg.operacion" placeholder="N° operación" class="w-28 rounded-lg border-gray-300 text-sm">
                            <button type="button" x-show="cobro.pagos.length > 1" @click="cobro.pagos.splice(n, 1)" class="text-red-600 px-1">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="agregarPago()" class="text-xs text-indigo-700 font-bold hover:underline">+ Pagar con otro medio</button>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Paga con</label>
                    <input x-model="cobro.paga" type="number" step="0.10" min="0" class="w-28 rounded-lg border-gray-300 text-sm text-right">
                    <span x-show="vuelto > 0" class="ml-auto text-lg font-bold text-emerald-700" x-text="'Vuelto S/ ' + vuelto.toFixed(2)"></span>
                </div>
                <input x-model="cobro.observaciones" maxlength="90" placeholder="Observaciones (opcional)" class="w-full rounded-lg border-gray-300 text-sm">
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" @click="cobro = null" class="py-3 rounded-lg bg-gray-100 font-bold">Cancelar</button>
                    <button :disabled="procesando" class="py-3 rounded-lg bg-emerald-600 text-white font-bold disabled:opacity-50" x-text="procesando ? 'Guardando…' : 'Cobrar e imprimir'"></button>
                </div>
            </form>
        </div>
    </template>
</div>
</body>
</html>
