// PV Móvil: carrito, búsqueda (texto, voz, lector de barras y cámara) y cobro.
// Se registra como componente de Alpine; los datos del servidor llegan en window.POS.
document.addEventListener('alpine:init', () => {
    const CFG = window.POS;
    const PORTADOR = { tdicod: '1', num: '00000000', nom: 'VENTA AL PORTADOR', dir: '' };
    const NOMBRES_COMPROBANTE = { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' };
    const NUMEROS_VOZ = {
        un: 1, uno: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6, siete: 7, ocho: 8, nueve: 9, diez: 10,
        once: 11, doce: 12, docena: 12, quince: 15, veinte: 20,
    };
    const CLAVE_CARRITO = 'pv_movil_carrito_' + CFG.usuario;
    const SCRIPT_ESCANER = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const redondear = n => Math.round((Number(n) || 0) * 100) / 100;

    Alpine.data('posMovil', () => ({
        cfg: CFG,

        // Búsqueda
        busqueda: '',
        resultados: [],
        resultadosAbiertos: false,
        resultadoActivo: 0,
        buscando: false,
        cantidadPendiente: 1, // cantidad dictada por voz ("3 inca kola") para el producto que se elija
        _ctrlBusqueda: null,

        // Voz
        vozDisponible: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
        escuchando: false,
        _reconocedor: null,

        // Cámara
        escaner: false,
        escaneoContinuo: true,
        ultimoEscaneo: '',
        errorCamara: '',
        _lector: null,
        _ultimoCodigo: { codigo: '', hora: 0 },

        // Lector de barras USB/Bluetooth cuando el foco no está en un campo
        _teclas: '',
        _horaTecla: 0,

        // Carrito y cobro
        carrito: [],
        cobroAbierto: false,
        tdocod: CFG.tdocodPred,
        cliente: { ...PORTADOR },
        msgCliente: { texto: '', ok: true },
        buscandoDoc: false,
        _ultimoDoc: '',
        sugerencias: [],
        sugActiva: 0,
        estadopago: null,
        fecVen: '',
        medioUnico: null,
        dividir: false,
        montosMedios: {},
        paga: null,
        imprimir: true,
        procesando: false,
        venta: null,
        avisos: [],

        // Producto con presentaciones esperando que se elija cómo venderlo (unidad, SACO, CAJA…)
        elegir: null,

        // Proforma abierta (editar o cobrar) y la última guardada
        proformaId: null,
        proformaNumero: '',
        ultimaProforma: null,

        iniciar() {
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.estadopago = contado ? contado.cre_dia_id : null;
            this.medioUnico = CFG.medios.length ? CFG.medios[0].id_med_pag : null; // vienen con el predeterminado primero
            if (!CFG.comprobantes.some(c => c.tdocod === this.tdocod)) this.tdocod = CFG.comprobantes[0]?.tdocod;

            if (CFG.proforma) {
                this.cargarProforma(CFG.proforma);
            } else {
                try {
                    const guardado = JSON.parse(localStorage.getItem(CLAVE_CARRITO) || '[]');
                    if (Array.isArray(guardado)) this.carrito = guardado.map(it => ({ factor: 1, presentaciones: [], presentacion: null, ...it, flash: false }));
                    const pf = JSON.parse(localStorage.getItem(CLAVE_CARRITO + '_proforma') || 'null');
                    if (pf && pf.id && this.carrito.length) { this.proformaId = pf.id; this.proformaNumero = pf.numero; }
                    if (this.carrito.length) this.aviso(this.proformaId ? `Se recuperó la proforma ${this.proformaNumero}` : 'Se recuperó el carrito anterior');
                } catch (e) { /* sin almacenamiento: se empieza vacío */ }
            }

            this.$watch('carrito', valor => {
                try {
                    localStorage.setItem(CLAVE_CARRITO, JSON.stringify(valor.map(({ flash, ...it }) => it)));
                } catch (e) { /* almacenamiento no disponible */ }
            });
            this.$watch('proformaId', id => {
                try {
                    localStorage.setItem(CLAVE_CARRITO + '_proforma', JSON.stringify(id ? { id, numero: this.proformaNumero } : null));
                } catch (e) { /* almacenamiento no disponible */ }
            });

            if (this.esEscritorio()) this.$nextTick(() => this.$refs.buscador.focus());
        },

        // ---------- Calculados ----------
        get total() { return redondear(this.carrito.reduce((s, it) => s + redondear(it.cantidad * it.precio), 0)); },
        get unidades() { return redondear(this.carrito.reduce((s, it) => s + (Number(it.cantidad) || 0), 0)); },
        get esContado() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.estadopago);
            return !e || e.cre_dia_tip === 'CONTADO';
        },
        get esPortador() { return this.cliente.num.trim() === PORTADOR.num; },
        get faltaMedios() {
            return redondear(this.total - Object.values(this.montosMedios).reduce((s, m) => s + (Number(m) || 0), 0));
        },
        // "Paga con" solo tiene sentido si entra efectivo (o si no hay un medio llamado efectivo)
        get efectivoEnJuego() {
            const efectivo = CFG.medios.find(m => /efectivo/i.test(m.nom_med_pag));
            if (!efectivo) return true;
            return this.dividir ? Number(this.montosMedios[efectivo.id_med_pag]) > 0 : this.medioUnico == efectivo.id_med_pag;
        },
        get billetes() { return [10, 20, 50, 100, 200].filter(b => b > this.total).slice(0, 4); },

        // ---------- Formato ----------
        soles(n) { return 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        num(n) { return String(redondear(n)); },
        nombreComprobante(cod) {
            return NOMBRES_COMPROBANTE[cod] || CFG.comprobantes.find(c => c.tdocod === cod)?.tdodes || cod;
        },
        esEscritorio() { return window.matchMedia('(pointer: fine)').matches; },

        // ---------- Búsqueda ----------
        async buscar() {
            const q = this.busqueda.trim();
            if (q.length < 2) { this.resultados = []; this.resultadosAbiertos = false; return []; }

            this._ctrlBusqueda?.abort();
            this._ctrlBusqueda = new AbortController();
            this.buscando = true;
            try {
                const r = await fetch(CFG.rutas.productos + '?q=' + encodeURIComponent(q),
                    { headers: { Accept: 'application/json' }, signal: this._ctrlBusqueda.signal });
                const data = await r.json();
                if (this.busqueda.trim() !== q) return []; // ya se agregó o se escribió otra cosa
                this.resultados = data;
                this.resultadoActivo = 0;
                this.resultadosAbiertos = true;
                return this.resultados;
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
                const data = await r.json();
                return data[0] || null;
            } catch (e) {
                this.aviso('No se pudo consultar el código. Revisa tu conexión.', 'error');
                return null;
            }
        },

        // Enter en el buscador: lector de barras (código exacto) o el resultado resaltado
        async enterBuscador() {
            const q = this.busqueda.trim();
            if (!q) return;
            // Con espacios es un nombre: se toma el resultado resaltado sin consultar el código
            if (/\s/.test(q) && this.resultadosAbiertos && this.resultados[this.resultadoActivo]) {
                this.agregar(this.resultados[this.resultadoActivo]);
                return;
            }
            const exacto = await this.porCodigo(q);
            if (exacto) { this.agregar(exacto, null, this.presDeCodigo(exacto)); return; }
            const lista = this.resultadosAbiertos && this.resultados.length ? this.resultados : await this.buscar();
            if (lista.length) this.agregar(lista[this.resultadoActivo] || lista[0]);
            else { this.sonido(false); this.aviso(`“${q}” no pertenece a ningún producto`, 'error'); }
        },

        moverResultado(paso) {
            if (!this.resultados.length) return;
            this.resultadosAbiertos = true;
            this.resultadoActivo = Math.min(Math.max(this.resultadoActivo + paso, 0), this.resultados.length - 1);
        },

        // ---------- Carrito ----------
        /** @param pres undefined = si tiene presentaciones se pregunta; null = unidad base; objeto = esa presentación */
        agregar(p, cantidad = null, pres) {
            const cant = cantidad ?? this.cantidadPendiente ?? 1;
            this.cantidadPendiente = 1;

            if (pres === undefined) {
                if ((p.presentaciones || []).length) {
                    this.elegir = { p, cantidad: cant };
                    this.busqueda = '';
                    this.resultados = [];
                    this.resultadosAbiertos = false;
                    this.$refs.buscador.blur();
                    return;
                }
                pres = null;
            }
            let i = this.carrito.findIndex(it => it.id === p.id && (it.presentacion || null) === (pres?.id ?? null));
            if (i >= 0) {
                this.carrito[i].cantidad = redondear(this.carrito[i].cantidad + cant);
                // El último tocado sube arriba para verlo sin hacer scroll
                const [it] = this.carrito.splice(i, 1);
                this.carrito.unshift(it);
            } else {
                this.carrito.unshift(this.nuevoItem(p, pres, { cantidad: cant }));
            }
            const it = this.carrito[0];
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 600);

            this.sonido(true);
            this.aviso(`+${this.num(cant)} ${p.nombre}`, 'ok');
            this.busqueda = '';
            this.resultados = [];
            this.resultadosAbiertos = false;
            if (this.esEscritorio()) this.$refs.buscador.focus();
            else this.$refs.buscador.blur(); // en el celular se baja el teclado para ver el carrito
        },

        /** Línea del carrito a partir de un producto (con su presentación, si se eligió) */
        nuevoItem(p, pres = null, extra = {}) {
            const precio = pres ? pres.precio : p.precio;
            return { id: p.id, codigo: p.codigo, nombre: p.nombre, precio, precioBase: precio, precioUnidad: p.precio,
                dinamico: !!p.dinamico, unidad: p.unidad || 'Unidad', presentaciones: p.presentaciones || [],
                presentacion: pres?.id ?? null, factor: pres?.factor ?? 1, imagen: p.imagen || null,
                stock: p.stock, cantidad: 1, flash: false, ...extra };
        },

        /** Código leído (lector o cámara): si era el de una presentación va esa; si era el del producto, la unidad base */
        presDeCodigo(p) {
            return p.presentacion ? ((p.presentaciones || []).find(x => x.id === p.presentacion) || null) : null;
        },

        elegirOpcion(pres) {
            if (!this.elegir) return;
            const { p, cantidad } = this.elegir;
            this.elegir = null;
            this.agregar(p, cantidad, pres);
        },

        nombreLinea(it) {
            const pres = it.presentacion ? it.presentaciones.find(x => x.id === it.presentacion) : null;
            return it.nombre + (pres ? ' (' + pres.nombre + ')' : '');
        },

        cambiarCantidad(i, paso) {
            const nueva = redondear(this.carrito[i].cantidad + paso);
            if (nueva <= 0) { this.quitar(i); return; }
            this.carrito[i].cantidad = nueva;
        },

        normalizar(i) {
            const it = this.carrito[i];
            if (!(Number(it.cantidad) > 0)) it.cantidad = 1;
            if (!(Number(it.precio) > 0)) it.precio = it.precioBase;
            it.cantidad = redondear(it.cantidad);
            it.precio = redondear(it.precio);
        },

        quitar(i) {
            const [it] = this.carrito.splice(i, 1);
            this.aviso(`Se quitó ${it.nombre}`);
        },

        vaciar() {
            if (confirm('¿Vaciar todo el carrito?')) this.carrito = [];
        },

        // ---------- Voz ----------
        alternarVoz() {
            if (this.escuchando) { this._reconocedor?.stop(); return; }
            if (!window.isSecureContext) {
                this.aviso('La voz necesita HTTPS (o localhost). Abre el sistema con https://', 'error');
                return;
            }
            const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
            const rec = new Rec();
            rec.lang = 'es-PE';
            rec.interimResults = true;
            rec.maxAlternatives = 1;

            rec.onstart = () => { this.escuchando = true; this.busqueda = ''; };
            rec.onend = () => { this.escuchando = false; };
            rec.onerror = e => {
                this.escuchando = false;
                if (e.error === 'not-allowed') this.aviso('Permite el uso del micrófono para buscar por voz.', 'error');
                else if (e.error !== 'no-speech' && e.error !== 'aborted') this.aviso('No se pudo usar el micrófono.', 'error');
            };
            rec.onresult = async e => {
                const res = e.results[e.results.length - 1];
                const texto = res[0].transcript.trim();
                this.busqueda = texto;
                if (!res.isFinal) return;

                // "3 inca kola" / "tres inca kola" => cantidad 3 + búsqueda
                const m = texto.toLowerCase().match(/^(\d+(?:[.,]\d+)?|[a-záéíóú]+)\s+(.+)$/i);
                let cantidad = 1;
                if (m) {
                    const n = /^\d/.test(m[1]) ? parseFloat(m[1].replace(',', '.')) : NUMEROS_VOZ[m[1]];
                    if (n > 0) { cantidad = n; this.busqueda = m[2]; }
                }
                this.cantidadPendiente = cantidad;
                const lista = await this.buscar();
                if (lista.length === 1) this.agregar(lista[0], cantidad);
                else if (!lista.length) this.sonido(false);
            };

            this._reconocedor = rec;
            rec.start();
        },

        // ---------- Cámara ----------
        async abrirEscaner() {
            this.errorCamara = '';
            this.ultimoEscaneo = '';
            this.escaner = true;

            if (!window.isSecureContext) {
                // Sin HTTPS no hay cámara en vivo: se toma una foto del código con la cámara del celular
                this.escaner = false;
                if (window.EscanerBarras) { EscanerBarras.foto(codigo => this.codigoEscaneado(codigo)); return; }
                this.escaner = true;
                this.errorCamara = 'La cámara solo funciona con HTTPS (o localhost). Abre el sistema con https:// o usa un lector de barras.';
                return;
            }
            try {
                await this.cargarScript(SCRIPT_ESCANER);
                await this.$nextTick();
                this._lector = new Html5Qrcode('lector-camara', { verbose: false, experimentalFeatures: { useBarCodeDetectorIfSupported: true } });
                await this._lector.start(
                    { facingMode: 'environment' },
                    { fps: 12, qrbox: (w, h) => ({ width: Math.floor(Math.min(w * 0.85, 340)), height: Math.floor(Math.min(h * 0.5, 200)) }) },
                    codigo => this.codigoEscaneado(codigo),
                    () => {}
                );
            } catch (e) {
                this.errorCamara = 'No se pudo abrir la cámara. Revisa que hayas dado permiso.';
            }
        },

        async codigoEscaneado(codigo) {
            // La cámara lee el mismo código varias veces por segundo: se ignora el repetido por 2 s
            const ahora = Date.now();
            if (codigo === this._ultimoCodigo.codigo && ahora - this._ultimoCodigo.hora < 2000) return;
            this._ultimoCodigo = { codigo, hora: ahora };

            const p = await this.porCodigo(codigo);
            if (!p) {
                this.sonido(false);
                this.aviso(`El código ${codigo} no pertenece a ningún producto`, 'error');
                return;
            }
            this.agregar(p, 1, this.presDeCodigo(p));
            this.ultimoEscaneo = p.nombre;
            if (!this.escaneoContinuo) this.cerrarEscaner();
        },

        async cerrarEscaner() {
            this.escaner = false;
            try { if (this._lector?.isScanning) await this._lector.stop(); this._lector?.clear(); } catch (e) { /* ya estaba detenido */ }
            this._lector = null;
        },

        cargarScript(src) {
            if (window.Html5Qrcode) return Promise.resolve();
            return new Promise((ok, falla) => {
                const s = document.createElement('script');
                s.src = src;
                s.onload = ok;
                s.onerror = () => falla(new Error('No se pudo cargar el lector'));
                document.head.appendChild(s);
            });
        },

        // ---------- Teclado ----------
        teclaGlobal(e) {
            if (this.elegir) {
                if (e.key === 'Escape') { e.preventDefault(); this.elegir = null; }
                else if (/^[1-9]$/.test(e.key)) {
                    const ops = [null, ...this.elegir.p.presentaciones];
                    if (Number(e.key) <= ops.length) { e.preventDefault(); this.elegirOpcion(ops[Number(e.key) - 1]); }
                }
                return;
            }
            if (this.venta) {
                if (e.key === 'Enter') { e.preventDefault(); this.nuevaVenta(); }
                return;
            }
            if (e.key === 'F2') { e.preventDefault(); this.$refs.buscador.focus(); return; }
            if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) { e.preventDefault(); this.cobrar(); return; }

            // Lector de barras con el foco fuera de los campos: llega como teclas muy seguidas terminadas en Enter
            if (e.target.closest('input, textarea, select')) return;
            const ahora = Date.now();
            if (ahora - this._horaTecla > 60) this._teclas = '';
            this._horaTecla = ahora;
            if (e.key === 'Enter' && this._teclas.length >= 3) {
                e.preventDefault();
                const codigo = this._teclas;
                this._teclas = '';
                this.porCodigo(codigo).then(p => p ? this.agregar(p, 1, this.presDeCodigo(p))
                    : (this.sonido(false), this.aviso(`El código ${codigo} no pertenece a ningún producto`, 'error')));
            } else if (e.key.length === 1) {
                this._teclas += e.key;
            }
        },

        // ---------- Cliente ----------
        autoBuscarDoc() {
            const v = this.cliente.num.trim();
            if (/^\d{11}$/.test(v)) this.cliente.tdicod = '6';
            else if (/^\d{8}$/.test(v)) this.cliente.tdicod = '1';
            // El RUC completo se busca solo; el DNI al salir del campo (8 dígitos también puede ser el inicio de un RUC)
            if (/^(10|15|17|20)\d{9}$/.test(v) && v !== this._ultimoDoc) this.buscarDoc();
        },

        blurDoc() {
            const v = this.cliente.num.trim();
            if (/^\d{8}$/.test(v) && v !== PORTADOR.num && v !== this._ultimoDoc) this.buscarDoc();
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
                if (d.error) {
                    this.cliente.nom = '';
                    this.cliente.dir = '';
                    this.mensajeCliente(d.error, false);
                    return;
                }
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
            // RUC => factura; un DNI no puede llevar factura
            if (this.cliente.tdicod === '6' && CFG.comprobantes.some(x => x.tdocod === '01')) {
                this.tdocod = '01';
                this.mensajeCliente('✔ Cliente con RUC: se cambió a FACTURA.', true);
            } else {
                if (this.tdocod === '01') this.tdocod = CFG.tdocodPred === '01' ? '03' : CFG.tdocodPred;
                this.mensajeCliente('✔ Cliente seleccionado.', true);
            }
        },

        clienteVarios() {
            this.cliente = { ...PORTADOR };
            this._ultimoDoc = '';
            this.mensajeCliente('', true);
            if (this.tdocod === '01') this.tdocod = CFG.tdocodPred === '01' ? '03' : CFG.tdocodPred;
        },

        mensajeCliente(texto, ok) { this.msgCliente = { texto, ok }; },

        elegirComprobante(cod) {
            this.tdocod = cod;
            if (cod === '01' && this.cliente.tdicod !== '6') {
                this.mensajeCliente('La factura necesita el RUC del cliente.', false);
                if (this.esPortador) { this.cliente.num = ''; this.cliente.nom = ''; }
            } else if (!this.msgCliente.ok) {
                this.mensajeCliente('', true);
            }
        },

        // ---------- Pago ----------
        elegirEstadoPago(e) {
            this.estadopago = e.cre_dia_id;
            if (e.cre_dia_tip !== 'CONTADO') {
                const f = new Date(CFG.hoy + 'T00:00:00');
                f.setDate(f.getDate() + (parseInt(e.cre_dia_fac) || 1));
                this.fecVen = f.toISOString().slice(0, 10);
            }
        },

        alternarDividir() {
            this.dividir = !this.dividir;
            this.montosMedios = {};
            if (this.dividir && this.medioUnico) this.montosMedios[this.medioUnico] = this.total;
        },

        completarMedio(id) {
            const actual = Number(this.montosMedios[id]) || 0;
            this.montosMedios[id] = redondear(Math.max(0, actual + this.faltaMedios));
        },

        abrirCobro() {
            if (!this.carrito.length) { this.aviso('Agrega productos para cobrar'); return; }
            this.resultadosAbiertos = false;
            this.cobroAbierto = true;
        },

        validar() {
            if (!this.carrito.length) return 'El carrito está vacío.';
            if (this.carrito.some(it => !(it.cantidad > 0) || !(it.precio > 0))) return 'Revisa las cantidades y precios del carrito.';
            const num = this.cliente.num.trim();
            if (!num || !this.cliente.nom.trim()) return 'Completa el documento y el nombre del cliente.';
            if (this.tdocod === '01' && !(this.cliente.tdicod === '6' && /^(10|15|17|20)\d{9}$/.test(num))) {
                return 'La factura necesita un RUC válido.';
            }
            // SUNAT: boletas desde S/ 700 deben identificar al cliente
            if (this.tdocod === '03' && this.total >= 700 && num === PORTADOR.num) {
                return 'Para boletas desde S/ 700 debes identificar al cliente (DNI).';
            }
            if (!this.esContado) {
                if (num === PORTADOR.num) return 'Para vender a crédito debes identificar al cliente.';
                if (!this.fecVen || this.fecVen <= CFG.hoy) return 'La fecha de vencimiento debe ser posterior a hoy.';
            }
            if (this.esContado && this.dividir && Math.abs(this.faltaMedios) > 0.01) {
                return 'Los medios de pago no cuadran con el total (' + (this.faltaMedios > 0 ? 'faltan ' : 'sobran ') + this.soles(Math.abs(this.faltaMedios)) + ').';
            }
            return null;
        },

        async cobrar() {
            if (this.procesando) return;
            // En el celular, el primer toque abre la hoja de cobro
            if (!this.cobroAbierto && !window.matchMedia('(min-width: 1024px)').matches) { this.abrirCobro(); return; }

            const error = this.validar();
            if (error) { this.sonido(false); this.aviso(error, 'error'); return; }

            const paga = Number(this.paga) || 0;
            if (this.esContado && this.efectivoEnJuego && paga > 0 && paga < this.total
                && !confirm(`El cliente paga ${this.soles(paga)} y el total es ${this.soles(this.total)}. ¿Continuar igual?`)) return;

            let ids = [], montos = [];
            if (this.esContado) {
                if (this.dividir) {
                    CFG.medios.forEach(m => {
                        const monto = redondear(this.montosMedios[m.id_med_pag]);
                        if (monto > 0) { ids.push(m.id_med_pag); montos.push(monto); }
                    });
                } else if (this.medioUnico) {
                    ids = [this.medioUnico];
                    montos = [this.total];
                }
            }

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
                paga: this.esContado && this.efectivoEnJuego ? paga : 0,
                id_med_pag: ids,
                mon_med_pag: montos,
            };

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.registrar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(body),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (el carrito se conserva).', 'error'); return; }
                if (r.status === 401) { window.location.reload(); return; }
                const d = await r.json();
                if (r.status === 422) { this.sonido(false); this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo registrar la venta.', 'error'); return; }

                this.carrito = [];
                this.soltarProforma();
                this.cobroAbierto = false;
                // Si se pidió imprimir: directo a la impresora; si no se puede, el ticket se imprime en el navegador
                const directo = this.imprimir ? await (window.TushpaImpresion ? TushpaImpresion.comprobante(d.id) : false) : false;
                if (directo) this.aviso(`🖨 ${d.numero}: ${directo.mensaje || 'enviado a la impresora'}`, 'ok');
                this.venta = { ...d, ticket: d.ticket + (this.imprimir && !directo ? '&imprimir=1' : '') };
                this.sonido(true);
            } catch (e) {
                this.aviso('Error de conexión con el servidor. Intenta otra vez.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        async imprimirTicket() {
            const directo = await (window.TushpaImpresion ? TushpaImpresion.comprobante(this.venta.id) : false);
            if (directo) { this.aviso('🖨 ' + (directo.mensaje || 'Enviado a la impresora'), 'ok'); return; }
            try { this.$refs.ticket.contentWindow.print(); }
            catch (e) { window.open(this.venta.ticket.replace('embed=1', 'embed=0'), '_blank'); }
        },

        nuevaVenta() {
            this.venta = null;
            this.clienteVarios();
            this.tdocod = CFG.tdocodPred;
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.estadopago = contado ? contado.cre_dia_id : null;
            this.medioUnico = CFG.medios.length ? CFG.medios[0].id_med_pag : null;
            this.dividir = false;
            this.montosMedios = {};
            this.paga = null;
            this.$nextTick(() => { if (this.esEscritorio()) this.$refs.buscador.focus(); });
        },

        // ---------- Proformas ----------
        itemsParaEnviar() {
            return this.carrito.map(it => ({ id: it.id, presentacion: it.presentacion || null, cantidad: it.cantidad, precio: it.precio,
                ...(it.id ? {} : { descripcion: it.nombre }) }));
        },

        cargarProforma(pf) {
            this.carrito = pf.items.map(i => {
                if (!i.producto) {
                    // Línea libre, o producto que ya no se vende: queda como línea libre (no mueve stock)
                    return { id: null, codigo: '', nombre: i.descripcion, precio: i.precio, precioBase: i.precio, precioUnidad: i.precio,
                        presentaciones: [], presentacion: null, factor: 1, stock: null, cantidad: i.cantidad, flash: false };
                }
                const pres = i.presentacion ? (i.producto.presentaciones || []).find(x => x.id === i.presentacion) : null;
                return this.nuevoItem(i.producto, pres, { cantidad: i.cantidad, precio: i.precio, precioBase: i.precio });
            });
            if (pf.items.some(i => i.no_disponible)) this.aviso('Algún producto de la proforma ya no está activo: quedó como línea libre.', 'error');
            this.proformaId = pf.id;
            this.proformaNumero = pf.numero;
            if (pf.cliente && pf.cliente.num && pf.cliente.num !== PORTADOR.num) this.usarCliente(pf.cliente);
            this.aviso(`Proforma ${pf.numero} abierta: edítala o cóbrala`, 'ok');
        },

        soltarProforma() {
            this.proformaId = null;
            this.proformaNumero = '';
            if (new URLSearchParams(location.search).has('proforma')) history.replaceState(null, '', location.pathname);
        },

        async guardarProforma() {
            if (this.procesando) return;
            if (!this.carrito.length) { this.aviso('Agrega productos a la proforma.', 'error'); return; }
            if (this.carrito.some(it => !(it.cantidad > 0) || !(it.precio > 0))) { this.aviso('Revisa las cantidades y precios del carrito.', 'error'); return; }
            if (!this.cliente.num.trim() || !this.cliente.nom.trim()) { this.aviso('Completa el documento y el nombre del cliente.', 'error'); return; }

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.proforma, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({
                        id: this.proformaId, origen: 'POS', items: this.itemsParaEnviar(),
                        tdicod: this.cliente.tdicod, clinum: this.cliente.num.trim(), clinom: this.cliente.nom.trim(), clidir: this.cliente.dir.trim(),
                    }),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (el carrito se conserva).', 'error'); return; }
                if (r.status === 401) { window.location.reload(); return; }
                const d = await r.json();
                if (r.status === 422) { this.sonido(false); this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo guardar la proforma.', 'error'); return; }

                this.ultimaProforma = d;
                this.carrito = [];
                this.soltarProforma();
                this.cobroAbierto = false;
                this.nuevaVenta();
                this.sonido(true);
                this.aviso(`✔ Proforma ${d.numero} ${d.editada ? 'actualizada' : 'guardada'}`, 'ok');
            } catch (e) {
                this.aviso('Error de conexión con el servidor. Intenta otra vez.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        // ---------- Avisos y sonido ----------
        aviso(texto, tipo = 'info') {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, texto, tipo });
            if (this.avisos.length > 3) this.avisos.shift();
            setTimeout(() => { this.avisos = this.avisos.filter(a => a.id !== id); }, tipo === 'error' ? 4000 : 1800);
        },

        sonido(ok) {
            try {
                navigator.vibrate?.(ok ? 40 : [80, 60, 80]);
                this._audio = this._audio || new (window.AudioContext || window.webkitAudioContext)();
                const osc = this._audio.createOscillator();
                const gan = this._audio.createGain();
                osc.type = 'sine';
                osc.frequency.value = ok ? 1250 : 220;
                gan.gain.value = 0.08;
                osc.connect(gan).connect(this._audio.destination);
                osc.start();
                osc.stop(this._audio.currentTime + (ok ? 0.08 : 0.25));
            } catch (e) { /* sin audio */ }
        },
    }));
});
