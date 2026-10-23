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
                cobro: null, ventas: [], aviso: null, avisoTipo: 'ok', procesando: false, volvio: false, subidasAlVolver: 0,
                ultima: null, enviarCocina: true, pisoActual: null, pvResultados: [], pvActivo: 0, pvAbierto: false, pvMedio: null, pvMonto: '',

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
                    // Comercios (sin mesas) abren en la vista de Punto de venta; se recuerda la última que usó
                    if (this.p) {
                        let guardada = null;
                        try { guardada = localStorage.getItem('tushpa_caja_vista'); } catch (e) { /* sin almacenamiento */ }
                        this.irA(['pv', 'venta', 'mesas'].includes(guardada) ? guardada : (this.p.mesas.length ? 'mesas' : 'pv'));
                    }
                    await this.refrescar();
                    this.agente = await C.agenteDisponible();
                    // Volvió el internet: sube lo vendido y ofrece volver al sistema normal
                    window.addEventListener('online', () => {
                        this.online = true;
                        this.volvio = true;
                        setTimeout(async () => { const antes = this.pendientes; await this.subir(true); this.subidasAlVolver = Math.max(0, antes - this.pendientes); }, 1500);
                    });
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

                // ---------------------------------------------------------------- vista punto de venta (como la del sistema)

                irA(vista) {
                    if (vista === 'pv' || vista === 'venta') {
                        this.mesa = null;
                        if (!this.cobro || this.cobro.modal) this.pvNuevo();
                        if (vista === 'pv') this.$nextTick(() => this.$refs.pvBuscador?.focus());
                    } else if (this.cobro && !this.cobro.modal) {
                        this.cobro = null;
                    }
                    if (['pv', 'venta', 'mesas'].includes(vista)) { try { localStorage.setItem('tushpa_caja_vista', vista); } catch (e) { /* sin almacenamiento */ } }
                    this.vista = vista;
                },

                get pvComprobantes() { return ['01', '03', '13'].filter((t) => this.p?.series[t]); },
                tipoComprobante(t) { return C.TIPOS[t]; },
                nombreComprobante(t) { return { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' }[t]; },

                pvNuevo() {
                    const tdocod = this.pvComprobantes.includes(this.p.empresa.tdocod) ? this.p.empresa.tdocod : this.pvComprobantes[0];
                    this.cobro = { modal: false, tdocod, tdicod: tdocod === '01' ? '6' : '1', clinum: tdocod === '01' ? '' : '00000000',
                        clinom: tdocod === '01' ? '' : 'VENTA AL PORTADOR', clidir: '', observaciones: '', paga: '', pagos: [] };
                    this.pvMedio = this.p.medios[0]?.id;
                    this.pvMonto = '';
                },

                pvElegirComprobante(t) {
                    const portador = !this.cobro.clinum || this.cobro.clinum === '00000000';
                    this.cobro.tdocod = t;
                    if (t === '01' && portador) Object.assign(this.cobro, { tdicod: '6', clinum: '', clinom: '' });
                    if (t !== '01' && !this.cobro.clinum) Object.assign(this.cobro, { tdicod: '1', clinum: '00000000', clinom: 'VENTA AL PORTADOR' });
                },

                pvBuscar() {
                    const texto = this.busqueda.trim(), q = sinTildes(texto);
                    this.pvResultados = q ? this.p.productos.filter((x) => sinTildes(x.nombre).includes(q) || sinTildes(x.codigo) === q || x.barra === texto).slice(0, 30) : [];
                    this.pvActivo = 0;
                    this.pvAbierto = !!q;
                },

                pvEnter() {
                    const t = this.busqueda.trim();
                    const exacto = this.p.productos.find((y) => (y.barra && y.barra === t) || y.codigo === t);
                    const x = exacto || this.pvResultados[this.pvActivo];
                    if (x) this.pvAgregar(x);
                },

                pvAgregar(x) {
                    this.busqueda = '';
                    this.pvResultados = [];
                    this.pvAbierto = false;
                    this.tocar(x);
                    this.$nextTick(() => this.$refs.pvBuscador?.focus());
                },

                pvMover(paso) { this.pvActivo = Math.min(Math.max(this.pvActivo + paso, 0), Math.max(this.pvResultados.length - 1, 0)); },

                pvLineaLibre() {
                    const clave = 'L' + Date.now();
                    this.carrito.push({ clave, id: null, nombre: '', precio: 0, presentacion: null, categoria: null, cantidad: 1, enviado: 0, observacion: '', libre: true });
                    this.$nextTick(() => document.getElementById('des-' + clave)?.focus());
                },

                get pvPagado() { return this.cobro ? r2(this.cobro.pagos.reduce((s, x) => s + Number(x.monto || 0), 0)) : 0; },
                get pvRestante() { return r2(this.total - this.pvPagado); },

                pvAgregarMedio() {
                    const monto = r2(Number(this.pvMonto) || this.pvRestante);
                    if (monto <= 0) return;
                    this.cobro.pagos.push({ medio: Number(this.pvMedio), monto, operacion: '' });
                    this.pvMonto = '';
                },

                nombreMedio(id) { return this.p.medios.find((m) => m.id === Number(id))?.nombre || ''; },

                /** IMPRIMIR / REGISTRAR: lo que falta pagar va con el medio elegido arriba */
                async pvRegistrar(imprimir) {
                    if (this.procesando) return;
                    if (!this.p.turno) { this.avisar('No tienes turno de caja abierto. Ábrelo cuando haya internet para poder vender.', 'error'); return; }
                    if (!this.carrito.length) { this.avisar('Agrega productos al detalle.', 'error'); this.$refs.pvBuscador?.focus(); return; }
                    if (this.carrito.some((i) => !String(i.nombre).trim() || !(Number(i.precio) > 0) || !(Number(i.cantidad) > 0))) {
                        this.avisar('Revisa el detalle: cada línea necesita descripción, cantidad y precio.', 'error');
                        return;
                    }
                    const pagos = this.cobro.pagos;
                    const aCocina = this.vista === 'venta' && this.enviarCocina ? this.carrito.filter((i) => i.id).map((i) => ({ ...i })) : [];
                    if (this.pvRestante > 0.009) this.cobro.pagos = [...pagos, { medio: Number(this.pvMedio), monto: this.pvRestante, operacion: '' }];
                    if (!(await this.confirmarCobro(imprimir))) { if (this.cobro) this.cobro.pagos = pagos; return; }
                    if (aCocina.length) await this.imprimirComandas(aCocina, false);
                },

                pvCancelar() {
                    if (this.carrito.length && !confirm('¿Cancelar esta venta?')) return;
                    this.carrito = [];
                    this.pvNuevo();
                    this.$refs.pvBuscador?.focus();
                },

                pvTecla(e) {
                    if (this.vista !== 'pv' || this.cobro?.modal || !this.p) return;
                    if (e.key === 'F2') { e.preventDefault(); this.$refs.pvBuscador?.focus(); }
                    if (e.key === 'F9') { e.preventDefault(); this.pvRegistrar(true); }
                    if (e.key === 'F10') { e.preventDefault(); this.pvRegistrar(false); }
                },

                // ---------------------------------------------------------------- mesas

                estadoMesa(m) { return this.mesasAbiertas.find((x) => x.id === m.id); },
                totalMesa(m) { return C.totales(m.items, false, 1).total.toFixed(2); },
                get pisos() { return [...new Set((this.p?.mesas || []).map((m) => m.piso || 'Salón'))]; },

                async abrirMesa(m) {
                    if (this.cobro && !this.cobro.modal) this.cobro = null;
                    this.mesa = this.estadoMesa(m) ? JSON.parse(JSON.stringify(this.estadoMesa(m))) : { id: m.id, nombre: m.nombre, items: [], abierta: Date.now() };
                    this.busqueda = '';
                    this.vista = 'mesa';
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
                    return `<div class="c"><b>${anulacion ? 'ANULACIÓN' : 'COMANDA'}<br><span style="font-size:20px">${this.esc(this.mesa ? this.mesa.nombre : 'PARA LLEVAR')}</span></b><br>SIN INTERNET · ${new Date().toTimeString().slice(0, 5)}<br>Atiende: ${this.esc(this.p.usuario.nombre)}</div><hr>`
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
                    this.cobro = { modal: true, tdocod: this.p.empresa.tdocod, clinum: '', clinom: '', clidir: '', observaciones: '', paga: '',
                        pagos: [{ medio, monto: this.total, operacion: '' }] };
                },

                get sumaPagos() { return r2(this.cobro.pagos.reduce((s, x) => s + Number(x.monto || 0), 0)); },
                get vuelto() { return r2(Number(this.cobro.paga || 0) - this.total); },

                agregarPago() {
                    const falta = r2(this.total - this.sumaPagos);
                    this.cobro.pagos.push({ medio: this.p.medios[1]?.id || this.p.medios[0]?.id, monto: falta > 0 ? falta : 0, operacion: '' });
                },

                async confirmarCobro(imprimir = true) {
                    const c = this.cobro, total = this.total;
                    const doc = c.clinum.trim() === '00000000' ? '' : c.clinum.trim();
                    let cliente = { tdicod: '1', clinum: '00000000', clinom: 'VENTA AL PORTADOR', clidir: '' };
                    if (c.tdocod === '01') {
                        if (!/^(10|15|17|20)\d{9}$/.test(doc)) { this.avisar('Para factura escribe un RUC válido de 11 dígitos.', 'error'); return; }
                        if (!c.clinom.trim()) { this.avisar('Escribe la razón social del cliente (sin internet no se puede buscar el RUC).', 'error'); return; }
                        cliente = { tdicod: '6', clinum: doc, clinom: c.clinom.trim().toUpperCase(), clidir: c.clidir.trim().toUpperCase() };
                    } else if (doc) {
                        const ruc = /^(10|15|17|20)\d{9}$/.test(doc);
                        if (!ruc && !/^\d{8}$/.test(doc)) { this.avisar('El DNI debe tener 8 dígitos o el RUC 11 (o déjalo vacío).', 'error'); return; }
                        if (!c.clinom.trim()) { this.avisar('Escribe el nombre del cliente (sin internet no se puede buscar el DNI).', 'error'); return; }
                        cliente = { tdicod: ruc ? '6' : '1', clinum: doc, clinom: c.clinom.trim().toUpperCase(), clidir: c.clidir.trim().toUpperCase() };
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
                            items: this.lista.map((i) => ({ id: i.id, descripcion: String(i.nombre).trim().toUpperCase(), cantidad: i.cantidad, precio: i.precio, presentacion: i.presentacion })),
                            pagos: c.pagos.filter((x) => Number(x.monto) > 0).map((x) => ({ medio: Number(x.medio), monto: r2(x.monto), operacion: x.operacion || null,
                                nombre: this.p.medios.find((m) => m.id === Number(x.medio))?.nombre })),
                        };
                        await C.guardarVenta(venta);
                        if (imprimir) await this.imprimirVenta(venta);
                        if (this.mesa) { await C.borrarMesa(this.mesa.id); this.mesa = null; this.vista = 'mesas'; } else { this.carrito = []; }
                        this.ultima = venta;
                        if (c.modal) { this.cobro = null; } else { this.pvNuevo(); this.$nextTick(() => this.$refs.pvBuscador?.focus()); }
                        this.avisar(`Venta ${serie}-${String(numero).padStart(8, '0')} guardada en esta PC.` + (this.online ? '' : ' Se subirá sola cuando vuelva el internet.'));
                        await this.refrescar();
                        if (this.online) this.subir(true);
                        return true;
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
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden" x-data="cajaSinInternet()" x-cloak @keydown.window="pvTecla($event)">
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
                    <button type="button" @click="irA('pv')" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'pv' ? 'bg-white text-indigo-700' : 'text-gray-500'">Punto de venta</button>
                    <button type="button" x-show="p.mesas.length" @click="irA('venta')" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'venta' ? 'bg-white text-indigo-700' : 'text-gray-500'">PV Restaurante</button>
                    <button type="button" x-show="p.mesas.length" @click="mesa ? (vista = 'mesa') : irA('mesas')" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'mesas' || vista === 'mesa' ? 'bg-white text-indigo-700' : 'text-gray-500'">
                        Mesas <span class="text-xs" x-show="mesasAbiertas.length" x-text="'(' + mesasAbiertas.length + ')'"></span></button>
                    <button type="button" @click="irA('ventas'); refrescar()" class="px-4 py-2 rounded-t-lg text-sm font-bold" :class="vista === 'ventas' ? 'bg-white text-indigo-700' : 'text-gray-500'">Ventas de esta PC</button>
                </nav>

                {{-- Punto de venta: el mismo diseño de la pantalla del sistema (comercios) --}}
                <div x-show="vista === 'pv'" class="flex-1 overflow-y-auto px-3 pb-3 pt-2">
                    <template x-if="cobro && !cobro.modal">
                        <div class="grid lg:grid-cols-12 gap-4 items-start max-w-[1600px] mx-auto">
                            {{-- Izquierda: búsqueda, detalle y medios de pago --}}
                            <div class="lg:col-span-7 space-y-4 min-w-0">
                                <div class="relative" @click.outside="pvAbierto = false">
                                    <svg class="w-7 h-7 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                                    <input x-ref="pvBuscador" type="search" autocomplete="off" x-model="busqueda" @input="pvBuscar()" @focus="pvAbierto = pvResultados.length > 0"
                                           @keydown.enter.prevent.stop="pvEnter()" @keydown.arrow-down.prevent="pvMover(1)" @keydown.arrow-up.prevent="pvMover(-1)" @keydown.escape="pvAbierto = false"
                                           placeholder="Buscar producto o escanear código…  (F2)"
                                           class="w-full h-16 pl-14 pr-4 rounded-2xl border-0 shadow-sm text-2xl font-bold text-slate-800 placeholder:text-slate-300 placeholder:font-normal focus:ring-4 focus:ring-indigo-300">
                                    <div x-show="pvAbierto" class="absolute z-30 left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl ring-2 ring-indigo-400 overflow-hidden max-h-[65vh] overflow-y-auto">
                                        <template x-for="(x, i) in pvResultados" :key="x.id">
                                            <button type="button" @click="pvAgregar(x)" @mouseenter="pvActivo = i" :class="i === pvActivo ? 'bg-indigo-600 text-white' : 'text-slate-800'"
                                                    class="w-full flex items-center gap-4 px-5 py-3 text-left border-b border-slate-100 last:border-0">
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-xl font-bold leading-snug" x-text="x.nombre"></span>
                                                    <span class="block text-sm opacity-70" x-text="x.codigo + (x.presentaciones.length ? ' · ' + x.presentaciones.map((y) => y.nombre).join(', ') : '')"></span>
                                                </span>
                                                <span class="text-xl font-extrabold whitespace-nowrap" x-text="'S/ ' + x.precio.toFixed(2)"></span>
                                            </button>
                                        </template>
                                        <p x-show="!pvResultados.length" class="px-5 py-6 text-center text-slate-400">No se encontró “<span x-text="busqueda"></span>”.</p>
                                    </div>
                                </div>

                                <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                                    <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-800 text-white">
                                        <h2 class="font-bold tracking-wide flex-1">DETALLE <span x-show="carrito.length" class="text-slate-300 font-normal" x-text="'· ' + carrito.length + (carrito.length === 1 ? ' línea' : ' líneas')"></span></h2>
                                        <button type="button" x-show="carrito.length" @click="carrito = []" class="text-xs px-2.5 py-1.5 rounded-lg text-rose-200 hover:bg-white/10">Vaciar</button>
                                        <button type="button" @click="pvLineaLibre()" class="text-sm font-bold px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400">+ Línea libre</button>
                                    </div>
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                                            <tr><th class="text-left px-4 py-2">Producto</th><th class="px-2 py-2 w-24">Cant.</th><th class="px-2 py-2 w-28">P. Unit.</th><th class="text-right px-4 py-2 w-28">Total</th><th class="w-10"></th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="(it, i) in carrito" :key="it.clave">
                                                <tr>
                                                    <td class="px-4 py-1.5">
                                                        <input type="text" x-model="it.nombre" :id="'des-' + it.clave" maxlength="150" :readonly="!it.libre" :placeholder="it.libre ? 'Descripción (línea libre)' : ''"
                                                               @keydown.enter.prevent="document.getElementById('cant-' + it.clave)?.focus()"
                                                               class="w-full bg-transparent border-0 border-b border-transparent hover:border-slate-200 focus:border-indigo-400 focus:ring-0 px-0 py-1 font-semibold uppercase text-slate-800">
                                                        <span class="block text-[11px] text-slate-400 -mt-0.5" x-text="it.libre ? 'Línea libre · no mueve stock' : (p.productos.find((x) => x.id === it.id)?.codigo || '')"></span>
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <input type="number" step="any" min="0.01" x-model.number="it.cantidad" :id="'cant-' + it.clave"
                                                               @keydown.enter.prevent="it.libre ? document.getElementById('pre-' + it.clave)?.focus() : $refs.pvBuscador.focus()"
                                                               class="w-full rounded-lg border-slate-200 text-center font-bold focus:ring-indigo-300 focus:border-indigo-400">
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <input type="number" step="any" min="0.01" x-model.number="it.precio" :id="'pre-' + it.clave" :readonly="!it.libre" @keydown.enter.prevent="$refs.pvBuscador.focus()"
                                                               :class="it.libre ? 'border-dashed border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-slate-50 text-slate-700 cursor-not-allowed'"
                                                               class="w-full rounded-lg text-right font-semibold">
                                                    </td>
                                                    <td class="text-right px-4 py-1.5 font-bold text-slate-800 whitespace-nowrap" x-text="'S/ ' + ((Number(it.cantidad) || 0) * (Number(it.precio) || 0)).toFixed(2)"></td>
                                                    <td class="pr-2">
                                                        <button type="button" @click="carrito.splice(i, 1)" class="p-1.5 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Quitar línea">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <p x-show="!carrito.length" class="py-12 text-center text-slate-400">Busca un producto o escanea su código para empezar.<br><span class="text-xs">Enter en el buscador agrega el producto elegido</span></p>
                                    <div class="px-4 py-3 border-t border-slate-100">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Observaciones</label>
                                        <textarea x-model="cobro.observaciones" rows="1" maxlength="90" class="mt-1 w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-300 focus:border-indigo-400"></textarea>
                                    </div>
                                </section>

                                <section class="bg-white rounded-2xl shadow-sm p-4">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <label class="flex-1 min-w-[160px]">
                                            <span class="text-xs font-bold text-slate-500 uppercase">Medio de pago</span>
                                            <select x-model.number="pvMedio" class="mt-1 w-full h-11 rounded-xl border-slate-200 font-semibold">
                                                <template x-for="m in p.medios" :key="m.id"><option :value="m.id" x-text="m.nombre" :selected="m.id === pvMedio"></option></template>
                                            </select>
                                        </label>
                                        <label class="w-40">
                                            <span class="text-xs font-bold text-slate-500 uppercase">Monto</span>
                                            <input type="number" step="any" min="0" x-model="pvMonto" @keydown.enter.prevent="pvAgregarMedio()" :placeholder="Math.max(0, pvRestante).toFixed(2)"
                                                   class="mt-1 w-full h-11 rounded-xl border-slate-200 text-right font-semibold">
                                        </label>
                                        <button type="button" @click="pvAgregarMedio()" class="h-11 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold">+ Agregar</button>
                                    </div>
                                    <table class="w-full text-sm mt-3" x-show="cobro.pagos.length">
                                        <thead class="text-xs uppercase text-slate-500 border-b border-slate-100"><tr><th class="text-left py-2">Medio</th><th class="text-right py-2 w-32">Monto</th><th class="w-8"></th></tr></thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <template x-for="(m, i) in cobro.pagos" :key="i">
                                                <tr>
                                                    <td class="py-1.5 font-semibold">
                                                        <span x-text="nombreMedio(m.medio)"></span>
                                                        <input x-show="nombreMedio(m.medio).toUpperCase() !== 'EFECTIVO'" type="text" maxlength="30" x-model="m.operacion" placeholder="N° operación"
                                                               class="mt-1 block w-full max-w-[11rem] h-8 rounded-lg border-slate-200 text-xs font-normal uppercase">
                                                    </td>
                                                    <td class="py-1.5"><input type="number" step="any" min="0" x-model.number="m.monto" class="w-full h-9 rounded-lg border-slate-200 text-right font-semibold"></td>
                                                    <td class="text-right"><button type="button" @click="cobro.pagos.splice(i, 1)" class="p-1 rounded text-slate-300 hover:text-rose-600" aria-label="Quitar medio">✕</button></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <p x-show="carrito.length && pvRestante > 0.009" class="mt-2 rounded-lg bg-slate-50 text-slate-600 text-sm px-3 py-2">
                                        <span x-text="cobro.pagos.length ? 'Lo que falta' : 'Todo'"></span> (<b x-text="'S/ ' + pvRestante.toFixed(2)"></b>) se cobra con <b x-text="nombreMedio(pvMedio)"></b>.
                                    </p>
                                    <p x-show="carrito.length && pvRestante < -0.009" class="mt-2 rounded-lg bg-amber-50 text-amber-800 text-sm px-3 py-2">
                                        <strong>Excede el total por:</strong> <span x-text="'S/ ' + Math.abs(pvRestante).toFixed(2)"></span>
                                    </p>
                                </section>
                            </div>

                            {{-- Derecha: comprobante, cliente y totales --}}
                            <div class="lg:col-span-5 space-y-4 min-w-0">
                                <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h2 class="text-xs font-bold text-slate-500 uppercase">Comprobante</h2>
                                        <span class="text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold" x-text="p.turno ? 'Turno N° ' + p.turno.numero : 'Sin turno'"></span>
                                    </div>
                                    <div class="grid gap-1.5 p-1 bg-slate-100 rounded-xl" :style="`grid-template-columns: repeat(${pvComprobantes.length}, minmax(0, 1fr))`">
                                        <template x-for="t in pvComprobantes" :key="t">
                                            <button type="button" @click="pvElegirComprobante(t)" :class="cobro.tdocod === t ? 'bg-white text-indigo-700 shadow' : 'text-slate-500 hover:text-slate-700'"
                                                    class="h-10 rounded-lg text-sm font-bold transition" x-text="nombreComprobante(t)"></button>
                                        </template>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label>
                                            <span class="text-xs font-semibold text-slate-500">F. Pago</span>
                                            <input type="text" value="CONTADO" readonly class="mt-1 w-full h-10 rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold">
                                        </label>
                                        <label>
                                            <span class="text-xs font-semibold text-slate-500">F. Emisión</span>
                                            <input type="text" :value="new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })" readonly class="mt-1 w-full h-10 rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-500">
                                        </label>
                                    </div>
                                </section>

                                <section class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
                                    <h2 class="text-xs font-bold text-slate-500 uppercase">Cliente</h2>
                                    <div class="flex gap-2">
                                        <select x-model="cobro.tdicod" class="h-10 w-28 shrink-0 rounded-xl border-slate-200 text-sm" aria-label="Tipo de documento">
                                            <option value="1">DNI</option><option value="6">RUC</option>
                                        </select>
                                        <input type="text" inputmode="numeric" maxlength="11" x-model="cobro.clinum" @focus="$event.target.select()" placeholder="DNI / RUC"
                                               class="flex-1 min-w-0 h-10 rounded-xl border-slate-200 font-semibold" aria-label="Número de documento">
                                    </div>
                                    <input type="text" x-model="cobro.clinom" @focus="$event.target.select()" placeholder="Nombre o razón social" class="w-full h-10 rounded-xl border-slate-200 text-sm uppercase font-semibold" aria-label="Nombre o razón social">
                                    <input type="text" x-model="cobro.clidir" placeholder="Dirección" maxlength="150" class="w-full h-10 rounded-xl border-slate-200 text-sm" aria-label="Dirección">
                                    <p class="text-[11px] text-slate-400">Sin internet no se busca el DNI/RUC: escribe el nombre tal como figura.</p>
                                </section>

                                <section class="bg-white rounded-2xl shadow-sm p-4">
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-500">Total</p>
                                            <p class="text-2xl font-extrabold text-indigo-700" x-text="total.toFixed(2)"></p>
                                        </div>
                                        <label>
                                            <span class="text-xs font-semibold text-slate-500">Paga con</span>
                                            <input type="number" step="any" min="0" x-model.number="cobro.paga" placeholder="0.00"
                                                   class="w-full h-10 rounded-xl border-2 border-emerald-300 text-right text-lg font-bold focus:ring-emerald-200 focus:border-emerald-500">
                                        </label>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-500">Vuelto</p>
                                            <p class="text-2xl font-extrabold" :class="cobro.paga > 0 && cobro.paga < total ? 'text-rose-600' : 'text-emerald-600'" x-text="cobro.paga > 0 ? (cobro.paga - total).toFixed(2) : '0.00'"></p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 mt-4">
                                        <button type="button" @click="pvRegistrar(true)" :disabled="procesando" class="h-14 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                                            IMPRIMIR<span class="block text-[10px] font-semibold opacity-80">F9</span></button>
                                        <button type="button" @click="pvRegistrar(false)" :disabled="procesando" class="h-14 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                                            REGISTRAR<span class="block text-[10px] font-semibold opacity-80">F10</span></button>
                                        <button type="button" @click="pvCancelar()" :disabled="procesando" class="h-14 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-extrabold tracking-wide shadow disabled:opacity-50">CANCELAR</button>
                                    </div>
                                    <p x-show="procesando" class="text-center text-sm text-slate-500 mt-2">Registrando…</p>
                                    <template x-if="ultima">
                                        <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                            <span class="text-slate-600">Última: <strong x-text="ultima.serie + '-' + String(ultima.numero).padStart(8, '0')"></strong> · <span x-text="'S/ ' + Number(ultima.total).toFixed(2)"></span>
                                                <template x-if="ultima.paga > ultima.total"><span class="text-emerald-600 font-semibold" x-text="' · Vuelto S/ ' + (ultima.paga - ultima.total).toFixed(2)"></span></template>
                                            </span>
                                            <button type="button" @click="reimprimir(ultima)" class="text-xs font-semibold text-indigo-700 hover:underline">Reimprimir</button>
                                        </div>
                                    </template>
                                </section>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- PV Restaurante: como la pantalla del sistema (datos del comprobante arriba; productos y cobro abajo) --}}
                <div x-show="vista === 'venta'" class="flex-1 overflow-y-auto px-3 pb-3 pt-2">
                    <template x-if="cobro && !cobro.modal">
                        <div class="space-y-4 max-w-[1600px] mx-auto">
                            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                                <div class="flex items-center gap-2 px-4 py-3 bg-slate-700 text-white text-sm font-bold uppercase">
                                    <span class="flex-1">Datos del comprobante
                                        <span class="ml-2 normal-case text-[11px] px-2 py-0.5 rounded-full bg-emerald-500" x-text="p.turno ? 'Turno N° ' + p.turno.numero + ' abierto' : 'Sin turno'"></span></span>
                                </div>
                                <div class="p-4 grid sm:grid-cols-3 gap-3 text-sm">
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">Comprobante</span>
                                        <select x-model="cobro.tdocod" @change="pvElegirComprobante(cobro.tdocod)" class="mt-1 w-full h-9 rounded border-slate-300 text-sm">
                                            <template x-for="t in pvComprobantes" :key="t"><option :value="t" x-text="tipoComprobante(t)" :selected="cobro.tdocod === t"></option></template>
                                        </select>
                                    </label>
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">Estado pago</span>
                                        <input type="text" value="CONTADO" readonly class="mt-1 w-full h-9 rounded border-slate-300 bg-slate-50 text-sm">
                                    </label>
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">F. emisión</span>
                                        <input type="text" :value="new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })" readonly class="mt-1 w-full h-9 rounded border-slate-300 bg-slate-50 text-sm">
                                    </label>
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">Tipo</span>
                                        <select x-model="cobro.tdicod" class="mt-1 w-full h-9 rounded border-slate-300 text-sm"><option value="1">DNI</option><option value="6">RUC</option></select>
                                    </label>
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">DNI / RUC</span>
                                        <input type="text" inputmode="numeric" maxlength="11" x-model="cobro.clinum" @focus="$event.target.select()" class="mt-1 w-full h-9 rounded border-slate-300 text-sm">
                                    </label>
                                    <label>
                                        <span class="text-xs font-bold text-slate-600">Nombre o razón social <span class="font-normal text-slate-400">(sin internet se escribe a mano)</span></span>
                                        <input type="text" x-model="cobro.clinom" @focus="$event.target.select()" class="mt-1 w-full h-9 rounded border-slate-300 text-sm uppercase">
                                    </label>
                                    <label class="sm:col-span-3">
                                        <span class="text-xs font-bold text-slate-600">Dirección</span>
                                        <input type="text" x-model="cobro.clidir" maxlength="150" class="mt-1 w-full h-9 rounded border-slate-300 text-sm">
                                    </label>
                                    <label class="sm:col-span-3">
                                        <span class="text-xs font-bold text-slate-600">Observaciones</span>
                                        <textarea x-model="cobro.observaciones" rows="2" maxlength="90" class="mt-1 w-full rounded border-slate-300 text-sm"></textarea>
                                    </label>
                                </div>
                            </section>

                            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                                <div class="flex items-center gap-2 px-4 py-3 bg-slate-700 text-white text-sm font-bold uppercase">
                                    <span class="px-2 py-0.5 rounded bg-orange-500 text-xs">Punto de venta</span>
                                    <span class="flex-1">Venta directa</span>
                                    <label class="flex items-center gap-1.5 normal-case text-xs"><input type="checkbox" x-model="enviarCocina" class="rounded text-indigo-600"> Enviar a cocina</label>
                                </div>
                                <div class="p-4 grid lg:grid-cols-12 gap-4">
                                    <div class="lg:col-span-7 space-y-3 min-w-0">
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" @click="categoria = null" class="px-3 py-1.5 rounded-md text-xs font-bold text-white shadow-sm" :class="!categoria ? 'bg-slate-800 ring-2 ring-amber-400' : 'bg-slate-500'">TODAS</button>
                                            <template x-for="c in p.categorias" :key="c.id">
                                                <button type="button" @click="categoria = c.id" class="px-3 py-1.5 rounded-md text-xs font-bold text-white uppercase shadow-sm"
                                                        :class="categoria === c.id ? 'ring-2 ring-amber-400' : ''" :style="'background:' + color(c.id)" x-text="c.nombre"></button>
                                            </template>
                                        </div>
                                        <input x-model="busqueda" @keydown.enter.prevent="buscarEnter()" placeholder="Buscar producto…" class="w-full h-9 rounded border-slate-300 text-sm">
                                        <div class="grid grid-cols-3 sm:grid-cols-4 xl:grid-cols-5 gap-2 max-h-72 overflow-y-auto p-0.5">
                                            <template x-for="x in productos" :key="x.id">
                                                <button type="button" @click="tocar(x)" class="rounded-lg border border-slate-200 bg-white px-1 py-2 text-center min-h-[78px] flex flex-col justify-center hover:-translate-y-0.5 hover:shadow-md hover:border-sky-400 transition">
                                                    <span class="block text-[11px] font-bold uppercase leading-tight line-clamp-2" x-text="x.nombre"></span>
                                                    <span class="block text-xs text-slate-500 mt-1" x-text="'S/ ' + x.precio.toFixed(2)"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <table class="w-full text-xs">
                                            <thead class="bg-slate-700 text-white"><tr><th class="py-1.5 px-2 text-left">PRODUCTO</th><th class="w-28">CANT.</th><th class="w-20">PRECIO</th><th class="w-20">TOTAL</th><th class="w-8"></th></tr></thead>
                                            <tbody class="divide-y">
                                                <template x-for="(it, i) in carrito" :key="it.clave">
                                                    <tr>
                                                        <td class="py-1.5 px-2 font-semibold uppercase" x-text="it.nombre"></td>
                                                        <td>
                                                            <div class="flex items-center justify-center gap-1">
                                                                <button type="button" @click="cambiar(it, -1)" class="w-6 h-6 rounded bg-purple-600 text-white font-bold">−</button>
                                                                <input type="number" step="any" min="0.01" x-model.number="it.cantidad" class="w-12 h-6 px-1 rounded border-slate-300 text-center text-xs">
                                                                <button type="button" @click="cambiar(it, 1)" class="w-6 h-6 rounded bg-purple-600 text-white font-bold">+</button>
                                                            </div>
                                                        </td>
                                                        <td class="text-center" x-text="Number(it.precio).toFixed(2)"></td>
                                                        <td class="text-center font-bold" x-text="((Number(it.cantidad) || 0) * it.precio).toFixed(2)"></td>
                                                        <td><button type="button" @click="carrito.splice(i, 1)" class="text-rose-500 font-bold px-1" aria-label="Quitar">✕</button></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <p x-show="!carrito.length" class="text-center text-xs text-slate-400 py-2">Toca un producto para agregarlo.</p>
                                    </div>

                                    <div class="lg:col-span-5 lg:border-l-2 lg:border-dashed lg:border-slate-300 lg:pl-4 space-y-3 text-sm">
                                        <div class="rounded-md bg-amber-50 border-l-4 border-amber-400 px-3 py-2 text-lg text-rose-700">Por cobrar: <b x-text="'S/ ' + total.toFixed(2)"></b></div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label>
                                                <span class="text-xs font-bold text-slate-600">PAGA CON S/</span>
                                                <input type="number" step="any" min="0" x-model.number="cobro.paga" placeholder="0.00" class="mt-1 w-full h-10 rounded border-slate-300 text-center text-lg font-bold">
                                            </label>
                                            <div>
                                                <span class="text-xs font-bold text-slate-600">VUELTO S/</span>
                                                <p class="mt-1 h-10 rounded bg-slate-100 flex items-center justify-center text-lg font-bold" :class="cobro.paga > 0 && cobro.paga < total ? 'text-rose-600' : 'text-emerald-600'"
                                                   x-text="cobro.paga > 0 ? (cobro.paga - total).toFixed(2) : '0.00'"></p>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-xs font-bold text-slate-600">MEDIOS DE PAGO</span>
                                            <select x-model.number="pvMedio" class="mt-1 w-full h-9 rounded border-slate-300 text-sm">
                                                <template x-for="m in p.medios" :key="m.id"><option :value="m.id" x-text="m.nombre" :selected="m.id === pvMedio"></option></template>
                                            </select>
                                            <div class="flex gap-2 mt-2">
                                                <input type="number" step="any" min="0" x-model="pvMonto" @keydown.enter.prevent="pvAgregarMedio()" :placeholder="Math.max(0, pvRestante).toFixed(2)" class="flex-1 h-9 rounded border-slate-300 text-sm text-right">
                                                <button type="button" @click="pvAgregarMedio()" class="px-3 h-9 rounded bg-sky-600 text-white text-xs font-bold">+ AGREGAR</button>
                                            </div>
                                            <template x-for="(m, i) in cobro.pagos" :key="i">
                                                <div class="flex items-center gap-2 mt-1.5 text-xs">
                                                    <span class="flex-1 font-semibold" x-text="nombreMedio(m.medio)"></span>
                                                    <input x-show="nombreMedio(m.medio).toUpperCase() !== 'EFECTIVO'" type="text" maxlength="30" x-model="m.operacion" placeholder="N° operación" class="w-28 h-7 rounded border-slate-300 text-xs uppercase">
                                                    <span class="w-16 text-right font-bold" x-text="Number(m.monto).toFixed(2)"></span>
                                                    <button type="button" @click="cobro.pagos.splice(i, 1)" class="text-rose-500" aria-label="Quitar medio">✕</button>
                                                </div>
                                            </template>
                                            <p x-show="carrito.length && pvRestante > 0.009" class="mt-2 text-xs text-slate-500">
                                                <span x-text="cobro.pagos.length ? 'Lo que falta' : 'Todo'"></span> (<b x-text="'S/ ' + pvRestante.toFixed(2)"></b>) se cobra con <b x-text="nombreMedio(pvMedio)"></b>.
                                            </p>
                                        </div>
                                        <button type="button" @click="pvRegistrar(true)" :disabled="procesando" class="w-full h-11 rounded bg-emerald-500 hover:bg-emerald-600 text-white font-bold uppercase disabled:opacity-50"
                                                x-text="procesando ? 'Registrando…' : 'Registrar venta'"></button>
                                        <button type="button" @click="pvCancelar()" class="w-full h-9 rounded border border-slate-300 text-slate-700 text-xs font-bold uppercase hover:bg-slate-50">Cancelar</button>
                                        <template x-if="ultima">
                                            <div class="flex items-center justify-between gap-2 rounded bg-slate-50 px-3 py-2 text-xs">
                                                <span>Última: <b x-text="ultima.serie + '-' + String(ultima.numero).padStart(8, '0')"></b> · <span x-text="'S/ ' + Number(ultima.total).toFixed(2)"></span></span>
                                                <button type="button" @click="reimprimir(ultima)" class="font-semibold text-indigo-700 hover:underline">Reimprimir</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </template>
                </div>

                {{-- Mesas: elegir la mesa (como la pantalla de Comandas) --}}
                <div x-show="vista === 'mesas'" class="flex-1 overflow-y-auto px-3 pb-3 pt-2 space-y-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-800">Elige la mesa o el tipo de pedido</h1>
                        <p class="text-xs text-slate-500" x-text="p.usuario.nombre"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="piso in pisos" :key="piso">
                            <button type="button" @click="pisoActual = piso" class="px-4 py-2.5 rounded-full text-sm font-bold uppercase shadow-sm"
                                    :class="(pisoActual || pisos[0]) === piso ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700'" x-text="piso"></button>
                        </template>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        <template x-for="m in p.mesas.filter((x) => (x.piso || 'Salón') === (pisoActual || pisos[0]))" :key="m.id">
                            <button type="button" @click="abrirMesa(m)" class="h-[70px] rounded-2xl text-center text-white shadow-sm hover:brightness-110"
                                    :class="estadoMesa(m) ? 'bg-orange-500' : 'bg-emerald-700'">
                                <span class="block text-lg font-extrabold" x-text="m.nombre"></span>
                                <span class="block text-xs font-semibold" x-text="estadoMesa(m) ? 'S/ ' + totalMesa(estadoMesa(m)) : 'Libre'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <button type="button" @click="enviarCocina = true; irA('venta')" class="h-14 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-lg font-extrabold shadow-sm">🥡 PARA LLEVAR</button>
                        <button type="button" @click="irA('venta')" class="h-14 rounded-2xl bg-orange-500 hover:bg-orange-600 text-white text-lg font-extrabold shadow-sm">🧾 PUNTO DE VENTA</button>
                    </div>
                    <p class="text-xs text-slate-500">Son las mesas atendidas en esta PC sin internet. Las que ya estaban abiertas en el sistema se cobran cuando vuelva el internet.</p>
                </div>

                {{-- Pedido de la mesa (como la pantalla de comanda): productos a la izquierda, pedido a la derecha --}}
                <div x-show="vista === 'mesa'" class="flex-1 flex flex-col min-h-0 px-3 pb-3 pt-2 gap-3">
                    <div class="flex items-center gap-3 bg-white rounded-xl shadow-sm px-3 py-2">
                        <button type="button" @click="salirMesa()" class="px-3 py-1.5 rounded-lg bg-slate-100 text-sm font-semibold hover:bg-slate-200">← Mesas</button>
                        <h2 class="text-lg font-extrabold uppercase" x-text="'Mesa ' + (mesa ? (p.mesas.find((x) => x.id === mesa.id)?.piso || '') + ' - ' + mesa.nombre : '')"></h2>
                    </div>
                    <input x-model="busqueda" @keydown.enter.prevent="buscarEnter()" placeholder="🔎 Buscar producto…" class="h-11 rounded-xl border-slate-200 shadow-sm text-sm">
                    <div class="grid grid-rows-2 grid-flow-col auto-cols-max gap-2 overflow-x-auto pb-1">
                        <button type="button" @click="categoria = null" class="px-4 py-2 rounded-full text-sm font-semibold shadow-sm" :class="!categoria ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700'">TODAS</button>
                        <template x-for="c in p.categorias" :key="c.id">
                            <button type="button" @click="categoria = c.id" class="px-4 py-2 rounded-full text-sm font-semibold uppercase shadow-sm" :class="categoria === c.id ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700'" x-text="c.nombre"></button>
                        </template>
                    </div>
                    <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2 content-start">
                        <template x-for="x in productos" :key="x.id">
                            <button type="button" @click="tocar(x)" class="text-left rounded-xl bg-white shadow-sm p-3 hover:ring-2 hover:ring-indigo-300 min-h-[84px] flex flex-col">
                                <span class="block text-sm font-bold uppercase leading-tight line-clamp-2 flex-1" x-text="x.nombre"></span>
                                <span class="block text-sm text-indigo-700 font-bold mt-2" x-text="'S/ ' + x.precio.toFixed(2) + (x.presentaciones.length ? ' +' : '')"></span>
                            </button>
                        </template>
                        <p x-show="!productos.length" class="col-span-full text-center text-sm text-slate-400 py-8">No hay productos con ese nombre.</p>
                    </div>
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
            <aside x-show="vista === 'mesa' && mesa" class="w-80 xl:w-96 bg-white border-l flex flex-col">
                <div class="p-3 border-b flex items-center gap-2">
                    <h2 class="font-bold flex-1">🧾 Pedido</h2>
                    <span class="font-extrabold text-emerald-700" x-text="'S/ ' + total.toFixed(2)"></span>
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

    {{-- Volvió el internet --}}
    <div x-show="volvio && online" x-transition class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md text-center space-y-3">
            <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center">
                <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 16.5a5 5 0 017 0M5 13a10 10 0 0114 0M2 8.8a15 15 0 0120 0M12 20h.01"/></svg>
            </div>
            <h2 class="text-xl font-extrabold text-gray-800">¡Volvió el internet!</h2>
            <p class="text-sm text-gray-600" x-show="subiendo">Subiendo tus ventas al sistema…</p>
            <p class="text-sm text-gray-600" x-show="!subiendo && !pendientes && !errores">Tus ventas ya están en el sistema<span x-show="subidasAlVolver" x-text="' (' + subidasAlVolver + ' subidas)'"></span>. Se enviarán a SUNAT.</p>
            <p class="text-sm text-amber-700 font-semibold" x-show="!subiendo && (pendientes || errores)">Quedan <span x-text="pendientes + errores"></span> venta(s) sin subir: revísalas en "Ventas de esta PC".</p>
            <div x-show="mesasAbiertas.length" class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-3 py-2 text-left">
                Tienes <b x-text="mesasAbiertas.length"></b> mesa(s) abiertas en esta PC. <b>Cóbralas aquí</b> antes de volver: en el sistema normal no aparecen.
            </div>
            <div class="grid gap-2 pt-1">
                <a href="{{ url('/') }}" x-show="!mesasAbiertas.length" class="block py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700">Volver al sistema normal</a>
                <button type="button" @click="volvio = false; if (mesasAbiertas.length) vista = 'mesas'" class="py-3 rounded-xl font-bold"
                        :class="mesasAbiertas.length ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700'"
                        x-text="mesasAbiertas.length ? 'Cobrar las mesas abiertas' : 'Seguir en esta pantalla'"></button>
            </div>
        </div>
    </div>

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
    <template x-if="cobro && cobro.modal">
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
