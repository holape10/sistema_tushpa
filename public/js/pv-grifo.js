// PV Grifo: combustible por importe (S/ 20) o por galones, productos de la tienda, placa obligatoria para factura.
// Componente de Alpine; los datos del servidor llegan en window.PVG.
document.addEventListener('alpine:init', () => {
    const CFG = window.PVG;
    const NOMBRES = { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' };
    const PORTADOR = { tdicod: '1', num: '00000000', nom: 'VENTA AL PORTADOR', dir: '' };
    const CLAVE = 'pv_grifo_' + CFG.usuario;
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const r3 = n => Math.round((Number(n) || 0) * 1000) / 1000;
    const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    // Por nombre: verde = 90 / regular, ámbar = 95 / premium, azul = 97, rojo = diésel, gris = GLP / otros
    const COLORES = [
        [/diesel|d2|db5|petr/, 'from-rose-500 to-red-700'], [/97|super|súper/, 'from-sky-500 to-indigo-700'],
        [/95|premium/, 'from-amber-400 to-orange-600'], [/90|84|regular/, 'from-emerald-500 to-green-700'],
        [/glp|gas/, 'from-violet-500 to-fuchsia-700'],
    ];

    Alpine.data('pvGrifo', () => ({
        cfg: CFG,
        catalogo: CFG.productos.map(p => ({ ...p, pres: p.pres || [], _q: norm(p.nombre + ' ' + p.codigo + ' ' + (p.barra || '')) })),
        cat: null,
        busqueda: '',
        carrito: [],
        _key: 1,
        despacho: null,   // { p, modo: 'S' | 'G', valor: '20', editar?: índice }
        elegir: null,     // producto con presentaciones
        ventaAbierta: false,

        tdocod: CFG.comprobantes.some(c => c.tdocod === CFG.tdocodPred) ? CFG.tdocodPred : (CFG.comprobantes[0]?.tdocod || '13'),
        cliente: { ...PORTADOR },
        msgCliente: { texto: '', ok: true },
        buscandoDoc: false,
        _ultimoDoc: '',
        placa: '',
        placasCliente: [],
        guia: '',
        intento: false,

        estadopago: null,
        fecVen: '',
        medio: CFG.medios[0]?.id ?? null,
        paga: null,

        proformaId: null,
        proformaNumero: '',
        procesando: false,
        venta: null,
        avisos: [],
        _cierre: null,

        iniciar() {
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.estadopago = contado?.cre_dia_id ?? null;

            if (CFG.proforma) {
                this.cargarProforma(CFG.proforma);
            } else {
                try {
                    const g = JSON.parse(localStorage.getItem(CLAVE) || '[]');
                    if (Array.isArray(g) && g.length) this.carrito = g.map(it => ({ ...it, key: this._key++, flash: false }));
                } catch (e) { /* sin almacenamiento */ }
            }
            this.$watch('carrito', v => {
                try { localStorage.setItem(CLAVE, JSON.stringify(v.map(({ flash, key, ...it }) => it))); } catch (e) {}
            });
            // Precios dinámicos: se refrescan cada 5 minutos (no cambia lo que ya está en la venta)
            setInterval(() => this.refrescarPrecios(), 5 * 60 * 1000);
            this.mantenerPantalla();
        },

        // ---------- Calculados ----------
        get combustibles() { return this.catalogo.filter(p => p.combustible); },
        get filtrados() {
            const palabras = norm(this.busqueda).split(/\s+/).filter(Boolean);
            return this.catalogo.filter(p => palabras.every(w => p._q.includes(w)));
        },
        get combustiblesVisibles() {
            if (this.cat !== null && this.cat !== 'comb' && !this.busqueda.trim()) return [];
            return this.filtrados.filter(p => p.combustible);
        },
        get tiendaVisibles() {
            if (this.cat === 'comb' && !this.busqueda.trim()) return [];
            return this.filtrados.filter(p => !p.combustible && (this.cat === null || this.busqueda.trim() || p.cat === this.cat));
        },
        totalLinea(it) { return it.importe !== null && it.importe !== undefined ? r2(it.importe) : r2(it.cant * it.precio); },
        get total() { return r2(this.carrito.reduce((s, it) => s + this.totalLinea(it), 0)); },
        get esContado() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.estadopago);
            return !e || e.cre_dia_tip === 'CONTADO';
        },
        get medioActual() { return CFG.medios.find(m => m.id === this.medio); },
        get comision() { return this.esContado ? r2(this.total * (this.medioActual?.comision || 0) / 100) : 0; },
        get totalCobrar() { return r2(this.total + this.comision); },
        get esEfectivo() { return /efectivo/i.test(this.medioActual?.nombre || '') || CFG.medios.length <= 1; },
        get billetes() { return [10, 20, 50, 100, 200].filter(b => b > this.totalCobrar).slice(0, 3); },
        get faltaPlaca() { return this.intento && this.tdocod === '01' && !this.placa.trim(); },
        get despachoEquivale() {
            const d = this.despacho;
            if (!d) return '';
            const v = parseFloat(d.valor) || 0;
            return d.modo === 'S'
                ? '= ' + this.num3(v / d.p.precio) + ' ' + d.p.abrev
                : '= ' + this.soles(v * d.p.precio);
        },

        soles(n) { return 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        num(n) { return String(r2(n)); },
        num3(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 3, maximumFractionDigits: 3 }); },
        nombreComprobante(c) { return NOMBRES[c] || CFG.comprobantes.find(x => x.tdocod === c)?.tdodes || c; },
        colorCombustible(p) {
            const n = norm(p.nombre);
            return (COLORES.find(([re]) => re.test(n)) || [null, 'from-slate-600 to-slate-900'])[1];
        },
        cantidadEn(id) { return this.carrito.filter(it => it.id === id).reduce((s, it) => s + it.cant, 0); },

        // ---------- Agregar ----------
        tocar(p) {
            if (p.combustible) { this.despacho = { p, modo: 'S', valor: '' }; return; }
            this.agregar(p);
        },

        /** @param pres undefined = si tiene presentaciones se pregunta; null = unidad base; objeto = esa presentación */
        agregar(p, pres) {
            if (pres === undefined && p.pres.length) { this.elegir = p; return; }
            this.elegir = null;
            const precio = pres ? pres.precio : p.precio;
            let it = this.carrito.find(x => !x.combustible && x.id === p.id && (x.presentacion || null) === (pres?.id ?? null) && x.precio === precio && x.importe === null);
            if (it) {
                it.cant++;
            } else {
                this.carrito.push({ key: this._key++, id: p.id, nombre: p.nombre + (pres ? ' (' + pres.nombre + ')' : ''), precio, cant: 1, importe: null,
                    combustible: false, abrev: p.abrev, presentacion: pres?.id ?? null, flash: false });
                it = this.carrito[this.carrito.length - 1];
            }
            this.destacar(it);
        },

        destacar(it) {
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 600);
            this.sonido(true);
        },

        /** Escribir la cantidad: total = cantidad × precio */
        editarCantidad(it, v) {
            const cant = r3(parseFloat(v));
            if (!(cant >= 0.001)) { this.aviso('La cantidad debe ser mayor a 0.', 'error'); return; }
            it.cant = cant;
            it.importe = null;
        },
        /** Escribir el total (S/ 5 de un galón a S/ 20): la cantidad se recalcula (0.25) y eso sale del stock */
        editarTotal(it, v) {
            const total = r2(parseFloat(v));
            if (!(total > 0)) { this.aviso('El total debe ser mayor a 0.', 'error'); return; }
            const cant = r3(total / it.precio);
            if (!(cant >= 0.001)) { this.aviso('El importe es muy pequeño para el precio.', 'error'); return; }
            it.cant = cant;
            // Si coincide justo con cantidad × precio no hace falta fijar el importe
            it.importe = r2(cant * it.precio) === total ? null : total;
        },

        cambiar(i, d) {
            this.carrito[i].cant += d;
            if (this.carrito[i].cant <= 0) this.carrito.splice(i, 1);
        },

        vaciar() { if (confirm('¿Vaciar la venta?')) this.carrito = []; },

        // ---------- Despacho de combustible ----------
        cambiarModo(m) { if (this.despacho) { this.despacho.modo = m; this.despacho.valor = ''; } },
        teclaDespacho(k) {
            let v = this.despacho.valor;
            const dec = this.despacho.modo === 'S' ? 2 : 3;
            if (k === '⌫') v = v.slice(0, -1);
            else if (k === '.') { if (!v.includes('.')) v = (v || '0') + '.'; }
            else if (!new RegExp('\\.\\d{' + dec + '}$').test(v) && v.length < 9) v = (v === '0' ? '' : v) + k;
            this.despacho.valor = v;
        },
        editarDespacho(i) {
            const it = this.carrito[i];
            const p = this.catalogo.find(x => x.id === it.id) || { id: it.id, nombre: it.nombre, precio: it.precio, abrev: it.abrev, unidad: 'Galón', combustible: true };
            const porImporte = it.importe !== null;
            this.despacho = { p: { ...p, precio: it.precio }, modo: porImporte ? 'S' : 'G', valor: String(porImporte ? it.importe : it.cant), editar: i };
        },
        confirmarDespacho() {
            const d = this.despacho;
            const v = parseFloat(d.valor) || 0;
            if (!(v > 0)) { this.aviso(d.modo === 'S' ? 'Escribe el importe en soles.' : 'Escribe la cantidad.', 'error'); return; }
            const cant = d.modo === 'S' ? r3(v / d.p.precio) : r3(v);
            if (!(cant >= 0.001)) { this.aviso('El importe es muy pequeño para el precio.', 'error'); return; }
            const linea = { id: d.p.id, nombre: d.p.nombre, precio: d.p.precio, cant, importe: d.modo === 'S' ? r2(v) : null,
                            combustible: true, abrev: d.p.abrev, presentacion: null };
            if (d.editar !== undefined) {
                Object.assign(this.carrito[d.editar], linea);
                this.destacar(this.carrito[d.editar]);
            } else {
                this.carrito.push({ key: this._key++, ...linea, flash: false });
                this.destacar(this.carrito[this.carrito.length - 1]);
            }
            this.despacho = null;
        },

        // Lector de barras o Enter en el buscador
        enterBuscador() {
            const q = this.busqueda.trim();
            if (!q) return;
            for (const x of this.catalogo) {
                const pres = x.pres.find(pr => pr.codigo_barra && pr.codigo_barra === q);
                if (pres) { this.agregar(x, pres); this.busqueda = ''; return; }
            }
            const exacto = this.catalogo.find(x => x.codigo === q || (x.barra && x.barra === q));
            if (exacto) { exacto.combustible ? this.tocar(exacto) : this.agregar(exacto, null); this.busqueda = ''; return; }
            const lista = [...this.combustiblesVisibles, ...this.tiendaVisibles];
            if (lista.length === 1) { this.tocar(lista[0]); this.busqueda = ''; }
            else if (!lista.length) { this.sonido(false); this.aviso(`No se encontró “${q}”`, 'error'); }
        },

        async refrescarPrecios() {
            try {
                const r = await fetch(CFG.rutas.precios, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const precios = await r.json();
                this.catalogo.forEach(p => { if (precios[p.id] !== undefined) p.precio = Number(precios[p.id]); });
            } catch (e) { /* sin conexión: se mantienen los precios */ }
        },

        // ---------- Cliente ----------
        elegirComprobante(c) {
            this.tdocod = c;
            if (c === '01' && this.cliente.tdicod !== '6') {
                if (this.cliente.num === PORTADOR.num) this.cliente = { tdicod: '6', num: '', nom: '', dir: '' };
                this.msgCliente = { texto: 'La factura necesita el RUC del cliente y la placa.', ok: false };
                this.ventaAbierta = true;
                this.$nextTick(() => this.$refs.doc.focus());
            }
        },
        clienteVarios() {
            this.cliente = { ...PORTADOR };
            this._ultimoDoc = '';
            this.placasCliente = [];
            this.msgCliente = { texto: '', ok: true };
            if (this.tdocod === '01') this.tdocod = CFG.tdocodPred === '01' ? '03' : CFG.tdocodPred;
        },
        async autoDoc() {
            const d = this.cliente.num.trim();
            if (/^\d{11}$/.test(d)) this.cliente.tdicod = '6';
            else if (/^\d{8}$/.test(d)) this.cliente.tdicod = '1';
            if (!/^(\d{8}|\d{11})$/.test(d) || d === this._ultimoDoc || d === PORTADOR.num) return;
            this._ultimoDoc = d;
            if (d.length === 11 && CFG.comprobantes.some(c => c.tdocod === '01')) this.tdocod = '01';
            this.buscandoDoc = true;
            this.msgCliente = { texto: d.length === 11 ? 'Buscando RUC en SUNAT…' : 'Buscando…', ok: true };
            try {
                const r = await (await fetch(CFG.rutas.cliente + '/' + d, { headers: { Accept: 'application/json' } })).json();
                if (this.cliente.num.trim() !== d) return;
                if (r.error) { this.msgCliente = { texto: r.error + ' Escribe el nombre.', ok: false }; return; }
                this.cliente.nom = r.nom || '';
                this.cliente.dir = r.dir && r.dir !== '--' ? r.dir : '';
                if (r.tdicod) this.cliente.tdicod = r.tdicod;
                this.msgCliente = { texto: '✔ Cliente encontrado', ok: true };
            } catch (e) {
                this.msgCliente = { texto: 'Sin conexión: escribe los datos.', ok: false };
            } finally {
                this.buscandoDoc = false;
            }
            this.cargarPlacas(d);
        },
        async cargarPlacas(doc) {
            try {
                this.placasCliente = await (await fetch(CFG.rutas.placas + '?doc=' + encodeURIComponent(doc), { headers: { Accept: 'application/json' } })).json();
                if (this.placasCliente.length === 1 && !this.placa) this.placa = this.placasCliente[0];
            } catch (e) { this.placasCliente = []; }
        },

        // ---------- Pago ----------
        elegirEstadoPago(e) {
            this.estadopago = e.cre_dia_id;
            if (e.cre_dia_tip !== 'CONTADO') {
                const f = new Date(CFG.hoy + 'T00:00:00');
                f.setDate(f.getDate() + (parseInt(e.cre_dia_fac) || 1));
                this.fecVen = f.toISOString().slice(0, 10);
                this.paga = null;
            }
        },

        validar() {
            if (!this.carrito.length) return 'La venta está vacía.';
            const num = this.cliente.num.trim();
            if (!num || !this.cliente.nom.trim()) return 'Completa el documento y el nombre del cliente.';
            if (this.tdocod === '01') {
                if (!(this.cliente.tdicod === '6' && /^(10|15|17|20)\d{9}$/.test(num))) return 'La factura necesita un RUC válido.';
                if (!this.placa.trim()) return 'La placa del vehículo es obligatoria para emitir factura.';
            }
            if (this.tdocod === '03' && this.total >= 700 && num === PORTADOR.num) return 'Para boletas desde S/ 700 ingresa el DNI del cliente.';
            if (!this.esContado) {
                if (num === PORTADOR.num) return 'Para vender a crédito identifica al cliente.';
                if (!this.fecVen || this.fecVen <= CFG.hoy) return 'La fecha de vencimiento debe ser posterior a hoy.';
            }
            return null;
        },

        itemsParaEnviar() {
            return this.carrito.map(it => ({ id: it.id, presentacion: it.presentacion || null, cantidad: it.cant, precio: it.precio,
                importe: it.importe ?? null }));
        },

        async cobrar() {
            if (this.procesando) return;
            this.intento = true;
            const error = this.validar();
            if (error) { this.ventaAbierta = true; this.sonido(false); this.aviso(error, 'error'); return; }

            const paga = this.esContado && this.esEfectivo ? (Number(this.paga) || 0) : 0;
            if (paga > 0 && paga < this.totalCobrar && !confirm(`Paga ${this.soles(paga)} y el total es ${this.soles(this.totalCobrar)}. ¿Continuar?`)) return;

            const body = {
                items: this.itemsParaEnviar(),
                proforma_id: this.proformaId,
                tdocod: this.tdocod, estadopago: this.estadopago, fecEmi: CFG.hoy, fecVen: this.esContado ? null : this.fecVen,
                tdicod: this.cliente.tdicod, clinum: this.cliente.num.trim(), clinom: this.cliente.nom.trim(), clidir: this.cliente.dir.trim(),
                placa: this.placa.trim(), guia_remision: this.guia.trim(),
                paga,
                // Monto sin recargo: el servidor calcula y suma la comisión del medio
                id_med_pag: this.esContado && this.medio ? [this.medio] : [],
                mon_med_pag: this.esContado && this.medio ? [this.total] : [],
            };

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.registrar, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify(body),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (la venta se conserva).', 'error'); return; }
                if (r.status === 401) { window.location.reload(); return; }
                const d = await r.json();
                if (r.status === 422) { this.sonido(false); this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo registrar.', 'error'); return; }

                const directo = await (window.TushpaImpresion ? TushpaImpresion.comprobante(d.id) : false);
                if (!directo) this.$refs.impresion.src = d.ticket + '&imprimir=1&t=' + Date.now();
                this.venta = d;
                this.carrito = [];
                this.ventaAbierta = false;
                this.soltarProforma();
                this.sonido(true);
                this._cierre = setTimeout(() => this.nuevaVenta(), 8000);
            } catch (e) {
                this.aviso('Error de conexión. Intenta de nuevo.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        nuevaVenta() {
            clearTimeout(this._cierre);
            this.venta = null;
            this.clienteVarios();
            this.tdocod = CFG.comprobantes.some(c => c.tdocod === CFG.tdocodPred) ? CFG.tdocodPred : (CFG.comprobantes[0]?.tdocod || '13');
            this.placa = ''; this.guia = ''; this.paga = null; this.intento = false;
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.estadopago = contado?.cre_dia_id ?? null;
            this.fecVen = '';
            this.medio = CFG.medios[0]?.id ?? null;
            this.busqueda = '';
        },

        // ---------- Proformas ----------
        cargarProforma(pf) {
            this.carrito = pf.items.map(i => {
                const p = i.id ? this.catalogo.find(x => x.id === i.id) : null;
                return { key: this._key++, id: p ? p.id : null, nombre: i.descripcion, precio: i.precio, cant: i.cantidad,
                         importe: i.importe ?? null, combustible: !!p?.combustible, abrev: p?.abrev || '', presentacion: i.presentacion || null, flash: false };
            }).filter(it => it.id);
            if (this.carrito.length < pf.items.length) this.aviso('Algún producto de la proforma ya no está activo y se quitó.', 'error');
            this.proformaId = pf.id;
            this.proformaNumero = pf.numero;
            if (pf.cliente && pf.cliente.num && pf.cliente.num !== PORTADOR.num) {
                this.cliente = { tdicod: pf.cliente.tdicod || '1', num: pf.cliente.num, nom: pf.cliente.nom, dir: pf.cliente.dir || '' };
                this._ultimoDoc = pf.cliente.num;
                this.cargarPlacas(pf.cliente.num);
            }
            this.aviso(`Proforma ${pf.numero} abierta: edítala o cóbrala`, 'ok');
        },
        soltarProforma() {
            this.proformaId = null;
            this.proformaNumero = '';
            if (new URLSearchParams(location.search).has('proforma')) history.replaceState(null, '', location.pathname);
        },
        async guardarProforma() {
            if (this.procesando || !this.carrito.length) return;
            if (!this.cliente.num.trim() || !this.cliente.nom.trim()) { this.ventaAbierta = true; this.aviso('Completa el documento y el nombre del cliente.', 'error'); return; }
            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.proforma, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify({
                        id: this.proformaId, origen: 'GRIFO', items: this.itemsParaEnviar(),
                        tdicod: this.cliente.tdicod, clinum: this.cliente.num.trim(), clinom: this.cliente.nom.trim(), clidir: this.cliente.dir.trim(),
                        observaciones: this.placa.trim() ? ('PLACA ' + this.placa.trim()).slice(0, 100) : '',
                    }),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página.', 'error'); return; }
                const d = await r.json();
                if (r.status === 422) { this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.aviso(d.mensaje || 'No se pudo guardar la proforma.', 'error'); return; }
                this.$refs.impresion.src = d.imprimir + '?imprimir=1&t=' + Date.now();
                this.carrito = [];
                this.ventaAbierta = false;
                this.soltarProforma();
                this.nuevaVenta();
                this.sonido(true);
                this.aviso(`✔ Proforma ${d.numero} ${d.editada ? 'actualizada' : 'guardada'} · imprimiendo`, 'ok');
            } catch (e) {
                this.aviso('Error de conexión. Intenta de nuevo.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        // ---------- Pantalla y teclado ----------
        pantallaCompleta() {
            if (document.fullscreenElement) document.exitFullscreen?.();
            else document.documentElement.requestFullscreen?.().catch(() => {});
        },
        async mantenerPantalla() {
            const pedir = async () => { try { await navigator.wakeLock?.request('screen'); } catch (e) {} };
            await pedir();
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') pedir(); });
        },
        tecla(e) {
            if (this.venta && (e.key === 'Enter' || e.key === 'Escape')) { e.preventDefault(); this.nuevaVenta(); return; }
            if (this.despacho) {
                if (e.key === 'Escape') { this.despacho = null; return; }
                if (e.target.closest('input')) return;
                if (e.key === 'Enter') { e.preventDefault(); this.confirmarDespacho(); }
                else if (/^[0-9.]$/.test(e.key)) { e.preventDefault(); this.teclaDespacho(e.key); }
                else if (e.key === 'Backspace') { e.preventDefault(); this.teclaDespacho('⌫'); }
                return;
            }
            if (this.elegir && e.key === 'Escape') { this.elegir = null; return; }
            if (e.key === 'F9') { e.preventDefault(); this.cobrar(); }
            if (e.key === 'F2') { e.preventDefault(); this.$refs.buscador.focus(); }
        },

        aviso(texto, tipo = 'info') {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, texto, tipo });
            if (this.avisos.length > 3) this.avisos.shift();
            setTimeout(() => { this.avisos = this.avisos.filter(a => a.id !== id); }, tipo === 'error' ? 4500 : 2200);
        },
        sonido(ok) {
            try {
                navigator.vibrate?.(ok ? 25 : [60, 40, 60]);
                this._audio = this._audio || new (window.AudioContext || window.webkitAudioContext)();
                const o = this._audio.createOscillator(), g = this._audio.createGain();
                o.frequency.value = ok ? 1200 : 220; g.gain.value = 0.05;
                o.connect(g).connect(this._audio.destination); o.start(); o.stop(this._audio.currentTime + (ok ? 0.05 : 0.2));
            } catch (e) {}
        },
    }));
});
