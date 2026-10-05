// Punto Venta de escritorio: detalle editable, líneas libres, medios de pago con comisión y lector de barras.
// Componente de Alpine; los datos del servidor llegan en window.PV.
document.addEventListener('alpine:init', () => {
    const CFG = window.PV;
    const PORTADOR = { tdicod: '1', num: '00000000', nom: 'VENTA AL PORTADOR', dir: '' };
    const NOMBRES_COMPROBANTE = { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' };
    const CLAVE_BORRADOR = (CFG.farmacia ? 'pv_farmacia_' : 'pv_escritorio_') + CFG.usuario;
    const redondear = n => Math.round((Number(n) || 0) * 100) / 100;

    Alpine.data('puntoVenta', () => ({
        cfg: CFG,

        // Búsqueda
        busqueda: '',
        resultados: [],
        resultadosAbiertos: false,
        resultadoActivo: 0,
        buscando: false,
        cantidadPendiente: 1,
        _ctrl: null,
        _teclas: '',
        _horaTecla: 0,

        // Venta
        carrito: [],
        observaciones: '',
        _sigKey: 1,
        tdocod: CFG.tdocodPred,
        estadopago: null,
        fecVen: '',
        medios: [],
        nuevoMedio: null,
        nuevoMonto: null,
        paga: null,

        // Cliente
        cliente: { ...PORTADOR },
        msgCliente: { texto: '', ok: true },
        buscandoDoc: false,
        _ultimoDoc: '',
        sugerencias: [],
        sugActiva: 0,

        procesando: false,
        ultima: null,
        avisos: [],

        // Producto con presentaciones esperando que el cajero elija cómo venderlo (unidad, SACO, CAJA…)
        elegir: null,
        elegirActivo: 0,

        // Proforma abierta (editar o cobrar) y la última guardada
        proformaId: null,
        proformaNumero: '',
        ultimaProforma: null,

        iniciar() {
            if (!CFG.comprobantes.some(c => c.tdocod === this.tdocod)) this.tdocod = CFG.comprobantes[0]?.tdocod;
            this.nuevoMedio = CFG.medios[0]?.id ?? null;
            this.reiniciarPago();

            if (CFG.proforma) {
                this.cargarProforma(CFG.proforma);
            } else {
                try {
                    const b = JSON.parse(localStorage.getItem(CLAVE_BORRADOR) || 'null');
                    if (b && Array.isArray(b.carrito) && b.carrito.length) {
                        this.carrito = b.carrito.map(it => ({ factor: 1, presentaciones: [], presentacion: null, ...it, key: this._sigKey++, flash: false }));
                        this.observaciones = b.observaciones || '';
                        this.proformaId = b.proformaId || null;
                        this.proformaNumero = b.proformaNumero || '';
                        this.aviso(this.proformaId ? `Se recuperó la proforma ${this.proformaNumero} en edición` : 'Se recuperó la venta que estaba en curso');
                    }
                } catch (e) { /* sin almacenamiento */ }
            }

            const guardar = () => {
                try {
                    localStorage.setItem(CLAVE_BORRADOR, JSON.stringify({
                        carrito: this.carrito.map(({ flash, key, ...it }) => it), observaciones: this.observaciones,
                        proformaId: this.proformaId, proformaNumero: this.proformaNumero,
                    }));
                } catch (e) { /* sin almacenamiento */ }
            };
            this.$watch('carrito', guardar);
            this.$watch('observaciones', guardar);
            this.$watch('proformaId', guardar);

            this.$nextTick(() => this.$refs.buscador.focus());
        },

        // ---------- Totales ----------
        get base() { return redondear(this.carrito.reduce((s, it) => s + redondear(it.cantidad * it.precio), 0)); },
        get esContado() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.estadopago);
            return !e || e.cre_dia_tip === 'CONTADO';
        },
        get esPortador() { return this.cliente.num.trim() === PORTADOR.num; },
        // El medio AUTO (el predeterminado) toma lo que falta para completar el total
        montoDe(m) {
            if (!m.auto) return redondear(m.monto);
            const otros = this.medios.filter(x => x !== m).reduce((s, x) => s + redondear(x.monto), 0);
            return redondear(Math.max(0, this.base - otros));
        },
        comisionDe(m) { return redondear(this.montoDe(m) * (m.comision || 0) / 100); },
        get sumaMedios() { return this.esContado ? redondear(this.medios.reduce((s, m) => s + this.montoDe(m), 0)) : 0; },
        get comisionTotal() { return this.esContado ? redondear(this.medios.reduce((s, m) => s + this.comisionDe(m), 0)) : 0; },
        get total() { return redondear(this.base + this.comisionTotal); },
        get diferencia() { return redondear(this.base - this.sumaMedios); },

        // ---------- Formato ----------
        soles(n) { return 'S/ ' + this.num2(n); },
        num2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        num(n) { return String(redondear(n)); },
        redondear,
        nombreComprobante(cod) { return NOMBRES_COMPROBANTE[cod] || CFG.comprobantes.find(c => c.tdocod === cod)?.tdodes || cod; },
        // ---------- Farmacia: lotes ----------
        textoVence(l) {
            if (!l.vence) return 'sin fecha';
            const f = l.vence.split('-').reverse().join('/');
            if (l.dias === null || l.dias === undefined) return 'vence ' + f;
            if (l.dias < 0) return 'VENCIDO ' + f;
            if (l.dias === 0) return 'vence HOY';
            return 'vence ' + f + ' (' + l.dias + (l.dias === 1 ? ' día)' : ' días)');
        },
        /** Color según cercanía al vencimiento; suave = fondo claro para el detalle */
        colorLote(l, suave = false) {
            const conDias = l.dias !== null && l.dias !== undefined;
            const vencido = l.vencido || (conDias && l.dias < 0);
            const pronto = !vencido && conDias && l.dias <= CFG.diasAlerta;
            if (suave) return vencido ? 'bg-rose-100 text-rose-700' : (pronto ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700');
            return vencido ? 'bg-rose-600' : (pronto ? 'bg-amber-500' : 'bg-emerald-600');
        },
        /** De qué lotes saldrá la línea (mismo orden que el servidor: elegido, vigentes por vencimiento, sin lote, vencidos) */
        planLotes(it) {
            if (!it.lotes || !it.lotes.length) return [];
            const vigentes = it.lotes.filter(l => !l.vencido);
            const elegido = vigentes.find(l => l.lote === it.lote);
            const orden = [...(elegido ? [elegido] : []), ...vigentes.filter(l => l !== elegido),
                           { lote: null, stock: it.sin_lote || 0 }, ...it.lotes.filter(l => l.vencido)];
            let falta = redondear((Number(it.cantidad) || 0) * (it.factor || 1));
            const plan = [];
            for (const l of orden) {
                if (falta <= 0) break;
                const toma = Math.min(falta, l.stock);
                if (toma > 0) { plan.push({ ...l, cantidad: redondear(toma) }); falta = redondear(falta - toma); }
            }
            if (falta > 0) {
                const sin = plan.find(p => !p.lote);
                if (sin) sin.cantidad = redondear(sin.cantidad + falta); else plan.push({ lote: null, cantidad: falta });
            }
            return plan;
        },

        enfocar(id) { this.$nextTick(() => { const el = document.getElementById(id); if (el) { el.focus(); el.select?.(); } }); },

        // ---------- Búsqueda ----------
        async buscar() {
            const q = this.busqueda.trim();
            if (q.length < 2) { this.resultados = []; this.resultadosAbiertos = false; return []; }
            this._ctrl?.abort();
            this._ctrl = new AbortController();
            this.buscando = true;
            try {
                const r = await fetch(CFG.rutas.productos + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: this._ctrl.signal });
                const data = await r.json();
                if (this.busqueda.trim() !== q) return [];
                this.resultados = data;
                this.resultadoActivo = 0;
                this.resultadosAbiertos = true;
                return data;
            } catch (e) {
                if (e.name !== 'AbortError') this.aviso('No se pudo buscar. Revisa tu conexión.', 'error');
                return [];
            } finally {
                this.buscando = false;
            }
        },

        async porCodigo(codigo) {
            try {
                const r = await fetch(CFG.rutas.productos + '?codigo=' + encodeURIComponent(codigo), { headers: { Accept: 'application/json' } });
                return (await r.json())[0] || null;
            } catch (e) {
                this.aviso('No se pudo consultar el código.', 'error');
                return null;
            }
        },

        // Enter: código exacto (lector de barras) o el resultado resaltado
        async enterBuscador() {
            const q = this.busqueda.trim();
            if (!q) return;
            if (/\s/.test(q) && this.resultadosAbiertos && this.resultados[this.resultadoActivo]) {
                this.agregar(this.resultados[this.resultadoActivo]);
                return;
            }
            const exacto = await this.porCodigo(q);
            if (exacto) { this.agregar(exacto, false, this.presDeCodigo(exacto)); return; }
            const lista = this.resultadosAbiertos && this.resultados.length ? this.resultados : await this.buscar();
            if (lista.length) this.agregar(lista[this.resultadoActivo] || lista[0]);
            else { this.sonido(false); this.aviso(`“${q}” no pertenece a ningún producto`, 'error'); }
        },

        moverResultado(paso) {
            if (!this.resultados.length) return;
            this.resultadosAbiertos = true;
            this.resultadoActivo = Math.min(Math.max(this.resultadoActivo + paso, 0), this.resultados.length - 1);
        },

        // ---------- Detalle ----------
        /** @param editar true = lleva el cursor a la cantidad (búsqueda); false = sigue escaneando (código de barras) */
        /**
         * @param pres undefined = si tiene presentaciones se pregunta con el modal; null = unidad base; objeto = esa presentación
         */
        agregar(p, editar = true, pres) {
            if (pres === undefined) {
                if ((p.presentaciones || []).length) { this.abrirElegir(p, editar); return; }
                pres = null;
            }
            const precio = pres ? pres.precio : p.precio;
            let it = this.carrito.find(x => x.id === p.id && (x.presentacion || null) === (pres?.id ?? null)
                && redondear(x.precio) === redondear(precio) && !x.lote);
            if (it) {
                it.cantidad = redondear(it.cantidad + 1);
            } else {
                it = this.nuevoItem(p, pres);
                this.carrito.push(it);
                it = this.carrito[this.carrito.length - 1];
            }
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 600);
            this.sonido(true);
            // Farmacia: avisa qué lote vender si está pronto a vencer, o si todo lo que queda está vencido
            if (CFG.farmacia && it.lotes && it.lotes.length) {
                const primero = it.lotes.find(l => !l.vencido);
                if (!primero) this.aviso(`⚠ ${it.descripcion}: todos sus lotes están VENCIDOS`, 'error');
                else if (primero.dias !== null && primero.dias <= CFG.diasAlerta) this.aviso(`💊 Vender lote ${primero.lote}: ${this.textoVence(primero)}`);
            }
            this.busqueda = '';
            this.resultados = [];
            this.resultadosAbiertos = false;
            if (editar) this.enfocar('cant-' + it.key);
            else this.$refs.buscador.focus();
        },

        /** Línea del detalle a partir de un producto del buscador (con su presentación, si se eligió) */
        nuevoItem(p, pres = null, extra = {}) {
            return { key: this._sigKey++, id: p.id, codigo: p.codigo, descripcion: p.nombre, nombre: p.nombre, cantidad: 1,
                     precio: pres ? pres.precio : p.precio, precioBase: pres ? pres.precio : p.precio, precioUnidad: p.precio,
                     dinamico: !!p.dinamico, unidad: p.unidad || 'Unidad', presentaciones: p.presentaciones || [],
                     presentacion: pres?.id ?? null, factor: pres?.factor ?? 1,
                     stock: p.stock, flash: false,
                     lotes: p.lotes || [], sin_lote: p.sin_lote || 0, control_lote: !!p.control_lote, lote: '', ...extra };
        },

        /** Código leído (lector o cámara): si era el de una presentación va esa; si era el del producto, la unidad base */
        presDeCodigo(p) {
            return p.presentacion ? ((p.presentaciones || []).find(x => x.id === p.presentacion) || null) : null;
        },

        // ---------- Modal: elegir presentación ----------
        abrirElegir(p, editar) {
            this.elegir = { p, editar, opciones: [null, ...(p.presentaciones || [])] };
            this.elegirActivo = 0;
            this.busqueda = '';
            this.resultados = [];
            this.resultadosAbiertos = false;
            this.$refs.buscador.blur();
        },
        elegirOpcion(i) {
            if (!this.elegir) return;
            const { p, editar, opciones } = this.elegir;
            this.elegir = null;
            this.agregar(p, editar, opciones[i] ?? null);
        },
        cerrarElegir() {
            this.elegir = null;
            this.$nextTick(() => this.$refs.buscador.focus());
        },
        /** Nombre de la presentación elegida en una línea del detalle */
        nombrePresentacion(it) {
            const pr = it.presentacion ? (it.presentaciones || []).find(x => x.id === it.presentacion) : null;
            return pr ? pr.nombre + ' x' + this.num(pr.factor) + ' ' + (it.unidad || '') : (it.unidad || 'Unidad');
        },

        lineaLibre() {
            const it = { key: this._sigKey++, id: null, codigo: '', descripcion: '', cantidad: 1, precio: 0, precioBase: 0, stock: null, flash: false,
                         presentaciones: [], presentacion: null, factor: 1 };
            this.carrito.push(it);
            this.enfocar('des-' + it.key);
        },

        normalizar(it) {
            if (!(Number(it.cantidad) > 0)) it.cantidad = 1;
            if (!(Number(it.precio) >= 0)) it.precio = it.precioBase;
            it.cantidad = redondear(it.cantidad);
            it.precio = redondear(it.precio);
        },

        quitar(i) { this.carrito.splice(i, 1); },

        vaciar() {
            if (confirm('¿Quitar todas las líneas del detalle?')) { this.carrito = []; this.$refs.buscador.focus(); }
        },

        // ---------- Pago ----------
        reiniciarPago() {
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.estadopago = contado ? contado.cre_dia_id : null;
            const pred = CFG.medios.find(m => m.predeterminado) || CFG.medios[0];
            this.medios = pred ? [{ ...pred, monto: 0, auto: true }] : [];
            this.paga = null;
            this.nuevoMonto = null;
            this.fecVen = '';
        },

        cambioEstadoPago() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.estadopago);
            if (e && e.cre_dia_tip !== 'CONTADO') {
                const f = new Date(CFG.hoy + 'T00:00:00');
                f.setDate(f.getDate() + (parseInt(e.cre_dia_fac) || 1));
                this.fecVen = f.toISOString().slice(0, 10);
                this.paga = null;
            }
        },

        agregarMedio() {
            const medio = CFG.medios.find(m => m.id == this.nuevoMedio);
            if (!medio) return;
            const monto = redondear(this.nuevoMonto ?? Math.max(0, this.diferencia));
            if (!(monto > 0)) { this.aviso('Ingresa el monto del medio de pago.', 'error'); return; }

            const existe = this.medios.find(m => m.id === medio.id);
            if (existe) {
                // Si era el AUTO queda fijo con el monto escrito; si no, se suma a lo que ya tenía
                existe.monto = existe.auto ? monto : redondear(existe.monto + monto);
                existe.auto = false;
            } else {
                this.medios.push({ ...medio, monto, auto: false });
            }
            // El medio AUTO se queda con el resto; si ya no queda nada, se quita
            const auto = this.medios.find(m => m.auto);
            if (auto && this.montoDe(auto) <= 0) this.medios = this.medios.filter(m => m !== auto);
            this.nuevoMonto = null;
        },

        editarMonto(m, valor) {
            m.auto = false;
            m.monto = redondear(Math.max(0, parseFloat(valor) || 0));
        },

        quitarMedio(i) { this.medios.splice(i, 1); },

        // ---------- Cliente ----------
        nuevoCliente() {
            this.cliente = { tdicod: '1', num: '', nom: '', dir: '' };
            this._ultimoDoc = '';
            this.mensajeCliente('Escribe el DNI o RUC: se busca solo. Si no existe, completa el nombre y se guarda con la venta.', true);
            this.$nextTick(() => this.$refs.doc.focus());
        },

        clienteVarios() {
            this.cliente = { ...PORTADOR };
            this._ultimoDoc = '';
            this.mensajeCliente('', true);
        },

        autoBuscarDoc() {
            const v = this.cliente.num.trim();
            if (/^\d{11}$/.test(v)) this.cliente.tdicod = '6';
            else if (/^\d{8}$/.test(v)) this.cliente.tdicod = '1';
            if (/^(10|15|17|20)\d{9}$/.test(v) && v !== this._ultimoDoc) this.buscarDoc();
        },

        blurDoc() {
            const v = this.cliente.num.trim();
            if (/^\d{8}$/.test(v) && v !== PORTADOR.num && v !== this._ultimoDoc) this.buscarDoc();
            if (v === '') this.clienteVarios();
        },

        async buscarDoc() {
            const doc = this.cliente.num.trim();
            if (!doc || doc === PORTADOR.num || this.buscandoDoc) return;
            this._ultimoDoc = doc;
            this.buscandoDoc = true;
            this.mensajeCliente(doc.length === 11 ? 'Buscando RUC en SUNAT…' : 'Buscando cliente…', true);
            try {
                const r = await fetch(CFG.rutas.cliente + '/' + encodeURIComponent(doc), { headers: { Accept: 'application/json' } });
                const d = await r.json();
                if (d.error) { this.cliente.nom = ''; this.cliente.dir = ''; this.mensajeCliente(d.error, false); return; }
                this.usarCliente({ num: doc, ...d });
            } catch (e) {
                this.mensajeCliente('No se pudo consultar. Escribe los datos manualmente.', false);
            } finally {
                this.buscandoDoc = false;
            }
        },

        async sugerirClientes() {
            const q = this.cliente.nom.trim();
            if (q.length < 2 || q === PORTADOR.nom) { this.sugerencias = []; return; }
            try {
                const r = await fetch(CFG.rutas.clientes + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                this.sugerencias = await r.json();
                this.sugActiva = 0;
            } catch (e) { this.sugerencias = []; }
        },

        usarCliente(c) {
            this.cliente = { tdicod: c.tdicod || (String(c.num).length === 11 ? '6' : '1'), num: c.num, nom: c.nom || '', dir: c.dir && c.dir !== '--' ? c.dir : '' };
            this._ultimoDoc = c.num;
            this.sugerencias = [];
            if (this.cliente.tdicod === '6' && CFG.comprobantes.some(x => x.tdocod === '01')) {
                this.tdocod = '01';
                this.mensajeCliente('✔ Cliente con RUC: se cambió a FACTURA.', true);
            } else {
                if (this.tdocod === '01') this.tdocod = CFG.tdocodPred === '01' ? '03' : CFG.tdocodPred;
                this.mensajeCliente('✔ Cliente seleccionado.', true);
            }
        },

        mensajeCliente(texto, ok) { this.msgCliente = { texto, ok }; },

        elegirComprobante(cod) {
            this.tdocod = cod;
            if (cod === '01' && this.cliente.tdicod !== '6') {
                this.mensajeCliente('La factura necesita el RUC del cliente.', false);
                if (this.esPortador) { this.cliente = { tdicod: '6', num: '', nom: '', dir: '' }; }
                this.$nextTick(() => this.$refs.doc.focus());
            } else if (!this.msgCliente.ok) {
                this.mensajeCliente('', true);
            }
        },

        // ---------- Registrar ----------
        validar() {
            if (!this.carrito.length) return 'Agrega al menos un producto.';
            if (this.carrito.some(it => !it.descripcion.trim())) return 'Escribe la descripción de todas las líneas.';
            if (this.carrito.some(it => !(it.cantidad > 0) || !(it.precio > 0))) return 'Revisa las cantidades y precios (deben ser mayores a 0).';
            const num = this.cliente.num.trim();
            if (!num || !this.cliente.nom.trim()) return 'Completa el documento y el nombre del cliente.';
            if (this.tdocod === '01' && !(this.cliente.tdicod === '6' && /^(10|15|17|20)\d{9}$/.test(num))) return 'La factura necesita un RUC válido.';
            if (this.tdocod === '03' && this.total >= 700 && num === PORTADOR.num) return 'Para boletas desde S/ 700 debes identificar al cliente (DNI).';
            if (!this.esContado) {
                if (num === PORTADOR.num) return 'Para vender a crédito debes identificar al cliente.';
                if (!this.fecVen || this.fecVen <= CFG.hoy) return 'La fecha de vencimiento debe ser posterior a hoy.';
            } else {
                if (!this.medios.some(m => this.montoDe(m) > 0)) return 'Agrega un medio de pago.';
                if (Math.abs(this.diferencia) > 0.01) return 'Los medios de pago no cuadran con el total (' + (this.diferencia > 0 ? 'faltan ' : 'sobran ') + this.soles(Math.abs(this.diferencia)) + ').';
            }
            return null;
        },

        async registrar(imprimir) {
            if (this.procesando) return;
            const error = this.validar();
            if (error) { this.sonido(false); this.aviso(error, 'error'); return; }

            const paga = this.esContado ? (Number(this.paga) || 0) : 0;
            if (paga > 0 && paga < this.total && !confirm(`El cliente paga ${this.soles(paga)} y el total es ${this.soles(this.total)}. ¿Continuar igual?`)) return;

            // Farmacia: confirmar si alguna línea saldría de un lote vencido (no alcanza el stock vigente)
            if (CFG.farmacia) {
                const vencida = this.carrito.find(it => this.planLotes(it).some(p => p.lote && p.vencido));
                if (vencida && !confirm(`${vencida.descripcion} saldría de un lote VENCIDO porque no alcanza el stock vigente. ¿Vender igual?`)) return;
            }

            const medios = this.esContado ? this.medios.filter(m => this.montoDe(m) > 0) : [];
            const body = {
                items: this.itemsParaEnviar(),
                proforma_id: this.proformaId,
                tdocod: this.tdocod,
                estadopago: this.estadopago,
                fecEmi: CFG.hoy,
                fecVen: this.esContado ? null : this.fecVen,
                tdicod: this.cliente.tdicod,
                clinum: this.cliente.num.trim(),
                clinom: this.cliente.nom.trim(),
                clidir: this.cliente.dir.trim(),
                observaciones: this.observaciones.trim(),
                paga,
                // Montos sin comisión: el servidor calcula y suma la comisión de cada medio
                id_med_pag: medios.map(m => m.id),
                mon_med_pag: medios.map(m => this.montoDe(m)),
            };

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.registrar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify(body),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (la venta se conserva).', 'error'); return; }
                if (r.status === 401) { window.location.reload(); return; }
                const d = await r.json();
                if (r.status === 422) { this.sonido(false); this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo registrar la venta.', 'error'); return; }

                this.ultima = d;
                this.sonido(true);
                this.aviso(`✔ ${d.numero} registrada` + (d.vuelto > 0 ? ` · Vuelto ${this.soles(d.vuelto)}` : ''), 'ok');
                if (imprimir) this.imprimirUltima();
                this.nuevaVenta();
            } catch (e) {
                this.aviso('Error de conexión con el servidor. Intenta otra vez.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        async imprimirUltima() {
            if (!this.ultima) return;
            // Primero directo a la impresora (sin vista previa); si el agente no está conectado, por el navegador
            const directo = await (window.TushpaImpresion ? TushpaImpresion.comprobante(this.ultima.id) : false);
            if (directo) { this.aviso(`🖨 ${this.ultima.numero} enviado a la impresora`, 'ok'); return; }
            // El ticket se imprime solo al cargar (imprimir=1) dentro de un iframe oculto
            this.$refs.impresion.src = this.ultima.ticket + '&imprimir=1&t=' + Date.now();
        },

        nuevaVenta() {
            this.carrito = [];
            this.observaciones = '';
            this.soltarProforma(false);
            this.clienteVarios();
            this.tdocod = CFG.tdocodPred;
            this.reiniciarPago();
            this.$nextTick(() => this.$refs.buscador.focus());
        },

        cancelar() {
            if (this.carrito.length && !confirm('¿Cancelar esta venta? Se borrará el detalle.')) return;
            this.nuevaVenta();
        },

        // ---------- Proformas ----------
        itemsParaEnviar() {
            return this.carrito.map(it => ({ id: it.id, presentacion: it.presentacion || null, descripcion: it.descripcion.trim(),
                cantidad: it.cantidad, precio: it.precio, lote: it.lote || null }));
        },

        cargarProforma(pf) {
            this.carrito = pf.items.map(i => {
                if (!i.producto) {
                    // Línea libre, o producto que ya no se vende: queda como línea libre (no mueve stock)
                    return { key: this._sigKey++, id: null, codigo: '', descripcion: i.descripcion, cantidad: i.cantidad, precio: i.precio,
                             precioBase: i.precio, stock: null, flash: false, presentaciones: [], presentacion: null, factor: 1 };
                }
                const pres = i.presentacion ? (i.producto.presentaciones || []).find(x => x.id === i.presentacion) : null;
                return this.nuevoItem(i.producto, pres, { descripcion: i.descripcion, cantidad: i.cantidad, precio: i.precio, precioBase: i.precio, lote: i.lote || '' });
            });
            if (pf.items.some(i => i.no_disponible)) this.aviso('Algún producto de la proforma ya no está activo: quedó como línea libre.', 'error');
            this.observaciones = pf.observaciones || '';
            this.proformaId = pf.id;
            this.proformaNumero = pf.numero;
            if (pf.cliente && pf.cliente.num) this.usarCliente(pf.cliente);
            this.aviso(`Proforma ${pf.numero} abierta: edítala o cóbrala`, 'ok');
        },

        /** Deja de editar la proforma; el detalle queda como una venta nueva */
        soltarProforma(limpiarUrl = true) {
            this.proformaId = null;
            this.proformaNumero = '';
            if (limpiarUrl || new URLSearchParams(location.search).has('proforma')) {
                history.replaceState(null, '', location.pathname);
            }
        },

        async guardarProforma() {
            if (this.procesando) return;
            if (!this.carrito.length) { this.aviso('Agrega productos a la proforma.', 'error'); return; }
            if (this.carrito.some(it => !it.descripcion.trim())) { this.aviso('Escribe la descripción de todas las líneas.', 'error'); return; }
            if (this.carrito.some(it => !(it.cantidad > 0) || !(it.precio > 0))) { this.aviso('Revisa las cantidades y precios (deben ser mayores a 0).', 'error'); return; }
            if (!this.cliente.num.trim() || !this.cliente.nom.trim()) { this.aviso('Completa el documento y el nombre del cliente.', 'error'); return; }

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.proforma, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify({
                        id: this.proformaId, origen: CFG.farmacia ? 'FARMACIA' : 'PV', items: this.itemsParaEnviar(),
                        tdicod: this.cliente.tdicod, clinum: this.cliente.num.trim(), clinom: this.cliente.nom.trim(),
                        clidir: this.cliente.dir.trim(), observaciones: this.observaciones.trim(),
                    }),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (el detalle se conserva).', 'error'); return; }
                if (r.status === 401) { window.location.reload(); return; }
                const d = await r.json();
                if (r.status === 422) { this.sonido(false); this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo guardar la proforma.', 'error'); return; }

                this.ultimaProforma = d;
                this.sonido(true);
                this.aviso(`✔ Proforma ${d.numero} ${d.editada ? 'actualizada' : 'guardada'}`, 'ok');
                this.nuevaVenta();
            } catch (e) {
                this.aviso('Error de conexión con el servidor. Intenta otra vez.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        imprimirProforma() {
            if (this.ultimaProforma) this.$refs.impresion.src = this.ultimaProforma.imprimir + '?imprimir=1&t=' + Date.now();
        },

        // ---------- Teclado ----------
        teclaGlobal(e) {
            if (this.elegir) {
                // Modal de presentación: flechas + Enter, o el número de la opción; Esc cancela
                const n = this.elegir.opciones.length;
                if (e.key === 'ArrowDown') { e.preventDefault(); this.elegirActivo = (this.elegirActivo + 1) % n; }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.elegirActivo = (this.elegirActivo - 1 + n) % n; }
                else if (e.key === 'Enter') { e.preventDefault(); this.elegirOpcion(this.elegirActivo); }
                else if (e.key === 'Escape') { e.preventDefault(); this.cerrarElegir(); }
                else if (/^[1-9]$/.test(e.key) && Number(e.key) <= n) { e.preventDefault(); this.elegirOpcion(Number(e.key) - 1); }
                return;
            }
            if (e.key === 'F2') { e.preventDefault(); this.$refs.buscador.focus(); return; }
            if (e.key === 'F9') { e.preventDefault(); this.registrar(true); return; }
            if (e.key === 'F10') { e.preventDefault(); this.registrar(false); return; }
            if (e.key === 'F8') { e.preventDefault(); this.guardarProforma(); return; }

            // Lector de barras con el foco fuera de los campos: teclas muy seguidas terminadas en Enter
            if (e.target.closest('input, textarea, select')) return;
            const ahora = Date.now();
            if (ahora - this._horaTecla > 60) this._teclas = '';
            this._horaTecla = ahora;
            if (e.key === 'Enter' && this._teclas.length >= 3) {
                e.preventDefault();
                const codigo = this._teclas;
                this._teclas = '';
                this.porCodigo(codigo).then(p => p ? this.agregar(p, false, this.presDeCodigo(p))
                    : (this.sonido(false), this.aviso(`El código ${codigo} no pertenece a ningún producto`, 'error')));
            } else if (e.key.length === 1) {
                this._teclas += e.key;
            }
        },

        // ---------- Avisos y sonido ----------
        aviso(texto, tipo = 'info') {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, texto, tipo });
            if (this.avisos.length > 3) this.avisos.shift();
            setTimeout(() => { this.avisos = this.avisos.filter(a => a.id !== id); }, tipo === 'error' ? 4500 : 2500);
        },

        sonido(ok) {
            try {
                this._audio = this._audio || new (window.AudioContext || window.webkitAudioContext)();
                const osc = this._audio.createOscillator();
                const gan = this._audio.createGain();
                osc.frequency.value = ok ? 1250 : 220;
                gan.gain.value = 0.06;
                osc.connect(gan).connect(this._audio.destination);
                osc.start();
                osc.stop(this._audio.currentTime + (ok ? 0.06 : 0.25));
            } catch (e) { /* sin audio */ }
        },
    }));
});
