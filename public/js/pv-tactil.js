// PV táctil: catálogo en memoria (filtra al instante), pedido con -50% / precio / notas, y cobro que emite e imprime.
document.addEventListener('alpine:init', () => {
    const CFG = window.PVT;
    const NOMBRES = { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' };
    const CLAVE = 'pv_tactil_' + CFG.usuario;
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const r3 = n => Math.round((Number(n) || 0) * 1000) / 1000;
    const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const colores = Object.fromEntries(CFG.categorias.map(c => [c.cat_id, c.color || '#64748b']));
    // Índice de búsqueda precalculado: filtrar es solo comparar textos ya normalizados
    const catalogo = CFG.productos.map(p => ({ ...p, pres: p.pres || [], _q: norm(p.nombre + ' ' + p.codigo + ' ' + (p.barra || '')) }));

    Alpine.data('pvTactil', () => ({
        cfg: CFG,
        catalogo, // reactivo: los precios dinámicos se refrescan cada pocos minutos
        elegir: null, // producto con presentaciones esperando que se elija KG / SACO…
        cat: null,
        busqueda: '',
        carrito: [],
        _key: 1,
        tdocod: CFG.comprobantes.some(c => c.tdocod === CFG.tdocodPred) ? CFG.tdocodPred : (CFG.comprobantes[0]?.tdocod || '13'),
        nombre: '',
        paga: null,
        doc: '', docNombre: '', docOk: false, docTdicod: '1', buscandoDoc: false, _ultimoDoc: '',
        medio: CFG.medios[0]?.id ?? null,
        pedidoAbierto: false,
        teclado: null,
        rapido: null, // celular: cantidad y precio del producto tocado, antes de agregarlo
        venta: null,
        procesando: false,
        intento: false,
        avisos: [],
        _cierre: null,
        // Pago por partes: lo que paga hoy y hasta cuándo paga el resto
        partes: false,
        aCuenta: null,
        venceSaldo: (() => { const d = new Date(CFG.hoy + 'T12:00:00'); d.setDate(d.getDate() + 15); return d.toISOString().slice(0, 10); })(),

        iniciar() {
            try {
                const g = JSON.parse(localStorage.getItem(CLAVE) || '[]');
                if (Array.isArray(g) && g.length) this.carrito = g.map(it => ({ ...it, key: this._key++, flash: false, verNota: false }));
            } catch (e) { /* sin almacenamiento */ }
            this.$watch('carrito', v => {
                try { localStorage.setItem(CLAVE, JSON.stringify(v.map(({ flash, verNota, key, ...it }) => it))); } catch (e) {}
            });
            this.mantenerPantalla();
            setInterval(() => this.refrescarPrecios(), 5 * 60 * 1000);
        },

        /** Precio dinámico: trae los precios vigentes (no cambia lo que ya está en el pedido) */
        async refrescarPrecios() {
            try {
                const r = await fetch(CFG.rutas.precios, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const precios = await r.json();
                this.catalogo.forEach(p => { if (precios[p.id] !== undefined) p.precio = Number(precios[p.id]); });
            } catch (e) { /* sin conexión: se mantienen los precios */ }
        },

        // ---------- Calculados ----------
        get visibles() {
            const palabras = norm(this.busqueda).split(/\s+/).filter(Boolean);
            return this.catalogo.filter(p => (this.cat === null || palabras.length || p.cat === this.cat)
                && palabras.every(w => p._q.includes(w)));
        },
        get total() { return r2(this.carrito.reduce((s, it) => s + this.totalDe(it), 0)); },
        get unidades() { return r3(this.carrito.reduce((s, it) => s + it.cant, 0)); },
        // Total de la línea; si la sucursal redondea a 10 céntimos (al más cercano o hacia abajo), ya redondeado
        totalDe(it) {
            const t = r2(it.precio * it.cant);
            if (CFG.redondeo === 'CERCANO') return r2(Math.round(r2(t * 10)) / 10);
            if (CFG.redondeo === 'ABAJO') return r2(Math.floor(r2(t * 10)) / 10);
            return t;
        },
        num3(n) { return String(r3(n)); },
        get medioActual() { return CFG.medios.find(m => m.id === this.medio); },
        get comision() { return r2(this.total * (this.medioActual?.comision || 0) / 100); },
        get totalCobrar() { return r2(this.total + this.comision); },
        get esEfectivo() { return /efectivo/i.test(this.medioActual?.nombre || ''); },
        get billetes() { return [10, 20, 50, 100, 200].filter(b => b > this.totalCobrar).slice(0, 3); },
        // Hay datos de cliente para el comprobante: documento válido y su nombre
        get conDoc() { return (this.tdocod !== '13' || this.partes) && /^(\d{8}|\d{11})$/.test(this.doc.trim()) && this.nombre.trim().length >= 3; },
        get faltaDoc() { return this.intento && !!this.validarCliente(); },
        validarCliente() {
            const d = this.doc.trim();
            if (this.tdocod === '13') return null;
            if (this.tdocod === '01') {
                if (!/^(10|15|17|20)\d{9}$/.test(d)) return 'La factura necesita el RUC del cliente (11 dígitos).';
                if (this.nombre.trim().length < 3) return 'Escribe la razón social del cliente.';
                return null;
            }
            if (d && !/^(\d{8}|\d{11})$/.test(d)) return 'El DNI tiene 8 dígitos y el RUC 11.';
            if (d && this.nombre.trim().length < 3) return 'Escribe el nombre del cliente.';
            if (this.total >= 700 && !this.conDoc) return 'Para boletas desde S/ 700 SUNAT pide el DNI y el nombre del cliente.';
            return null;
        },

        soles(n) { return 'S/ ' + this.num(n); },
        num(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        nombreComprobante(c) { return NOMBRES[c] || c; },
        colorCat(c) { return colores[c] || '#cbd5e1'; },
        cantidadEn(id) { return this.carrito.filter(it => it.id === id).reduce((s, it) => s + it.cant, 0); },

        // ---------- Pedido ----------
        /** @param pres undefined = preguntar si tiene presentaciones; null = unidad base; objeto = esa presentación */
        agregar(p, pres) {
            if (pres === undefined && p.pres.length) { this.elegir = p; return; }
            this.elegir = null;
            const precio = pres ? pres.precio : p.precio;
            const presId = pres?.id ?? null;
            // Se suma a la línea igual (mismo producto y presentación, precio normal y sin nota); si no, línea nueva
            let it = this.carrito.find(x => x.id === p.id && (x.presentacion || null) === presId && !x.desc50
                && x.precio === x.precioBase && x.precioBase === precio && !x.nota);
            if (it) {
                it.cant = r3(it.cant + 1);
            } else {
                this.carrito.push({ key: this._key++, id: p.id, nombre: p.nombre + (pres ? ' (' + pres.nombre + ')' : ''), precio, precioBase: precio,
                    presentacion: presId, cant: 1, desc50: false, nota: '', verNota: false, flash: false });
                it = this.carrito[this.carrito.length - 1];
            }
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 500);
            this.sonido(true);
        },
        /** Tocar un producto: en celular se pone la cantidad (y el precio) de una vez; en PC se agrega directo */
        tocar(p, pres) {
            if (pres === undefined && p.pres.length) { this.elegir = p; return; }
            if (!window.matchMedia('(max-width: 767px)').matches) { this.agregar(p, pres ?? null); return; }
            this.elegir = null;
            const precio = pres ? pres.precio : p.precio;
            this.rapido = { p, pres: pres ?? null, nombre: p.nombre + (pres ? ' (' + pres.nombre + ')' : ''), base: precio,
                cant: '1', precio: String(precio), campo: 'cant', nuevo: true };
        },
        rapidoTecla(k) {
            const r = this.rapido, campo = r.campo;
            let v = r.nuevo ? '' : r[campo];
            r.nuevo = false;
            const decimales = campo === 'cant' ? 3 : 2;
            if (k === '⌫') v = v.slice(0, -1);
            else if (k === '.') { if (!v.includes('.')) v = (v || '0') + '.'; }
            else if (!new RegExp('\\.\\d{' + decimales + '}$').test(v)) v += k;
            r[campo] = v;
        },
        rapidoSumar(d) {
            const r = this.rapido;
            r.cant = String(Math.max(1, r3((parseFloat(r.cant) || 0) + d)));
            r.nuevo = true;
        },
        rapidoAgregar() {
            const r = this.rapido;
            const cant = r3(parseFloat(r.cant)), precio = r2(parseFloat(r.precio));
            if (!(cant > 0)) { this.aviso('La cantidad debe ser mayor a 0.', 'error'); return; }
            if (!(precio > 0)) { this.aviso('El precio debe ser mayor a 0.', 'error'); return; }
            if (precio !== r.base && !CFG.puedePrecio) { this.aviso('No tienes permiso para cambiar el precio.', 'error'); return; }
            const presId = r.pres?.id ?? null;
            let it = this.carrito.find(x => x.id === r.p.id && (x.presentacion || null) === presId && !x.desc50 && x.precio === precio && !x.nota);
            if (it) {
                it.cant = r3(it.cant + cant);
            } else {
                this.carrito.push({ key: this._key++, id: r.p.id, nombre: r.nombre, precio, precioBase: precio, presentacion: presId,
                    cant, desc50: false, nota: '', verNota: false, flash: false });
            }
            this.rapido = null;
            this.sonido(true);
            this.aviso('Agregado: ' + this.num3(cant) + ' × ' + r.nombre);
        },
        cambiar(i, d) {
            this.carrito[i].cant = r3(this.carrito[i].cant + d);
            if (this.carrito[i].cant <= 0) this.carrito.splice(i, 1);
        },
        toggle50(i) {
            const it = this.carrito[i];
            it.desc50 = !it.desc50;
            it.precio = it.desc50 ? r2(it.precioBase / 2) : it.precioBase;
        },
        editarPrecio(i) {
            if (!CFG.puedePrecio) { this.aviso('No tienes permiso para cambiar el precio.', 'error'); return; }
            if (this.carrito[i].desc50) { this.aviso('Quita el -50% antes de cambiar el precio.', 'error'); return; }
            this.teclado = { i, tipo: 'precio', nombre: this.carrito[i].nombre, valor: String(this.carrito[i].precio) };
        },
        // Cantidad con decimales (1.5 kg, 1.35 kg): se escribe con el teclado, hasta 3 decimales
        editarCantidad(i) {
            this.teclado = { i, tipo: 'cantidad', nombre: this.carrito[i].nombre, valor: '0' };
        },
        teclaNum(k) {
            let v = this.teclado.valor === '0' ? '' : this.teclado.valor;
            const decimales = this.teclado.tipo === 'cantidad' ? 3 : 2;
            if (k === '⌫') v = v.slice(0, -1);
            else if (k === '.') { if (!v.includes('.')) v = (v || '0') + '.'; }
            else if (!new RegExp('\\.\\d{' + decimales + '}$').test(v)) v += k;
            this.teclado.valor = v || '0';
        },
        guardarTeclado() {
            if (this.teclado.tipo !== 'cantidad') return this.guardarPrecio();
            const v = r3(parseFloat(this.teclado.valor));
            if (!(v > 0)) { this.aviso('La cantidad debe ser mayor a 0.', 'error'); return; }
            this.carrito[this.teclado.i].cant = v;
            this.teclado = null;
        },
        guardarPrecio() {
            const v = r2(parseFloat(this.teclado.valor));
            if (!(v > 0)) { this.aviso('El precio debe ser mayor a 0.', 'error'); return; }
            const it = this.carrito[this.teclado.i];
            it.precio = v; it.precioBase = v;
            this.teclado = null;
        },
        vaciar() { if (confirm('¿Vaciar el pedido?')) this.carrito = []; },

        // Lector de barras o Enter en el buscador: código exacto o único resultado
        enterBuscador() {
            const q = this.busqueda.trim();
            if (!q) return;
            // Código de barras de una presentación: se agrega ya elegida
            for (const x of this.catalogo) {
                const pres = x.pres.find(pr => pr.codigo_barra && pr.codigo_barra === q);
                if (pres) { this.agregar(x, pres); this.busqueda = ''; return; }
            }
            const exacto = this.catalogo.find(x => x.codigo === q || (x.barra && x.barra === q));
            if (exacto) { this.agregar(exacto, null); this.busqueda = ''; return; }
            const p = this.visibles.length === 1 ? this.visibles[0] : null;
            if (p) { this.agregar(p); this.busqueda = ''; }
            else { this.sonido(false); this.aviso(`No se encontró “${q}”`, 'error'); }
        },

        // ---------- Cliente (boleta / factura) ----------
        async autoDoc() {
            const d = this.doc.trim();
            this.docOk = false;
            this.docNombre = '';
            if (!/^(\d{8}|\d{11})$/.test(d) || d === this._ultimoDoc) return;
            this.nombre = '';
            this._ultimoDoc = d;
            this.docTdicod = d.length === 11 ? '6' : '1';
            if (d.length === 11 && this.tdocod !== '01' && CFG.comprobantes.some(c => c.tdocod === '01')) this.tdocod = '01';
            this.buscandoDoc = true;
            try {
                const r = await (await fetch(CFG.rutas.cliente + '/' + d, { headers: { Accept: 'application/json' } })).json();
                if (this.doc.trim() !== d) return;
                if (r.error) { this.docNombre = 'No encontrado: escribe el nombre'; return; }
                this.docNombre = r.nom; this.nombre = r.nom; this.docOk = true;
                if (r.tdicod) this.docTdicod = r.tdicod;
            } catch (e) { this.docNombre = 'Sin conexión'; } finally { this.buscandoDoc = false; }
        },

        // ---------- Cobrar ----------
        validar() {
            if (!this.carrito.length) return 'El pedido está vacío.';
            const errorCliente = this.validarCliente();
            if (errorCliente) return errorCliente;
            if (this.partes) {
                if (!this.conDoc) return 'Para pagar por partes escribe el DNI y el nombre del cliente.';
                if (!((Number(this.aCuenta) || 0) < this.total)) return 'Por partes, lo que paga hoy debe ser menor al total.';
                if (!this.venceSaldo || this.venceSaldo <= CFG.hoy) return 'Elige hasta cuándo paga el resto (después de hoy).';
            }
            if (!CFG.contado) return 'No hay forma de pago CONTADO configurada.';
            return null;
        },

        async cobrar() {
            if (this.procesando) return;
            this.intento = true;
            const error = this.validar();
            if (error) { this.pedidoAbierto = true; this.sonido(false); this.aviso(error, 'error'); return; }

            const paga = this.esEfectivo && !this.partes ? (Number(this.paga) || 0) : 0;
            if (paga > 0 && paga < this.totalCobrar && !confirm(`Paga ${this.soles(paga)} y el total es ${this.soles(this.totalCobrar)}. ¿Continuar?`)) return;

            const conDoc = this.conDoc;
            const body = {
                items: this.itemsParaEnviar(),
                tdocod: this.tdocod,
                estadopago: CFG.contado,
                fecEmi: CFG.hoy,
                tdicod: conDoc ? (this.doc.trim().length === 11 ? '6' : '1') : '1',
                clinum: conDoc ? this.doc.trim() : '00000000',
                clinom: conDoc ? this.nombre.trim().toUpperCase() : 'VENTA AL PORTADOR',
                paga,
                // Monto sin recargo: el servidor calcula y suma la comisión del medio
                id_med_pag: [this.medio],
                mon_med_pag: [this.total],
                // Por partes: al crédito; lo que paga hoy (con el medio elegido) es el primer abono
                ...(this.partes ? { partes: 1, a_cuenta: Number(this.aCuenta) || 0, a_cuenta_medio: this.medio, fecVen: this.venceSaldo } : {}),
            };

            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.registrar, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify(body),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (el pedido se conserva).', 'error'); return; }
                const d = await r.json();
                if (r.status === 422) { this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo registrar.', 'error'); return; }

                // Imprime el comprobante: directo a la impresora; si el agente no está conectado, por el navegador
                const directo = await (window.TushpaImpresion ? TushpaImpresion.comprobante(d.id) : false);
                if (!directo) this.$refs.impresion.src = d.ticket + '&imprimir=1&t=' + Date.now();
                this.venta = d;
                this.carrito = [];
                this.pedidoAbierto = false;
                this.sonido(true);
                this._cierre = setTimeout(() => this.nuevaVenta(), 7000);
            } catch (e) {
                this.aviso('Error de conexión. Intenta de nuevo.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        itemsParaEnviar() {
            return this.carrito.map(it => ({ id: it.id, presentacion: it.presentacion || null, cantidad: it.cant, precio: it.precio,
                // Total redondeado por la sucursal: va como importe de la línea (la cantidad y el precio no cambian)
                ...(Math.abs(this.totalDe(it) - r2(it.precio * it.cant)) >= 0.005 ? { importe: this.totalDe(it) } : {}),
                descripcion: (it.nombre + (it.nota.trim() ? ' - ' + it.nota.trim() : '')).slice(0, 150) }));
        },

        /** Guarda el pedido como proforma e imprime; se edita o cobra luego desde Proformas (en el Punto Venta) */
        async guardarProforma() {
            if (this.procesando) return;
            if (!this.carrito.length) { this.aviso('El pedido está vacío.', 'error'); return; }
            const conDoc = this.conDoc;
            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.proforma, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify({
                        origen: 'TACTIL', items: this.itemsParaEnviar(),
                        tdicod: conDoc ? (this.doc.trim().length === 11 ? '6' : '1') : '1', clinum: conDoc ? this.doc.trim() : '00000000',
                        clinom: conDoc ? this.nombre.trim().toUpperCase() : 'VENTA AL PORTADOR',
                    }),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página (el pedido se conserva).', 'error'); return; }
                const d = await r.json();
                if (r.status === 422) { this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.sonido(false); this.aviso(d.mensaje || 'No se pudo guardar la proforma.', 'error'); return; }

                this.$refs.impresion.src = d.imprimir + '?imprimir=1&t=' + Date.now();
                this.carrito = [];
                this.pedidoAbierto = false;
                this.nuevaVenta();
                this.sonido(true);
                this.aviso(`✔ Proforma ${d.numero} guardada · imprimiendo`);
            } catch (e) {
                this.aviso('Error de conexión. Intenta de nuevo.', 'error');
            } finally {
                this.procesando = false;
            }
        },

        nuevaVenta() {
            clearTimeout(this._cierre);
            this.venta = null;
            this.nombre = ''; this.paga = null; this.doc = ''; this.docNombre = ''; this.docOk = false; this._ultimoDoc = '';
            this.intento = false;
            this.partes = false; this.aCuenta = null;
            this.medio = CFG.medios[0]?.id ?? null;
            this.tdocod = CFG.comprobantes.some(c => c.tdocod === CFG.tdocodPred) ? CFG.tdocodPred : (CFG.comprobantes[0]?.tdocod || '13');
            this.busqueda = '';
        },

        // ---------- Pantalla táctil ----------
        pantallaCompleta() {
            if (document.fullscreenElement) document.exitFullscreen?.();
            else document.documentElement.requestFullscreen?.().catch(() => {});
        },
        async mantenerPantalla() {
            // Evita que la pantalla se apague mientras el PV está abierto (Screen Wake Lock)
            const pedir = async () => { try { await navigator.wakeLock?.request('screen'); } catch (e) {} };
            await pedir();
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') pedir(); });
        },
        tecla(e) {
            if (this.venta && (e.key === 'Enter' || e.key === 'Escape')) { e.preventDefault(); this.nuevaVenta(); return; }
            if (this.elegir && e.key === 'Escape') { this.elegir = null; return; }
            if (this.rapido && e.key === 'Escape') { this.rapido = null; return; }
            if (e.key === 'F9') { e.preventDefault(); this.cobrar(); }
            if (e.key === 'F2') { e.preventDefault(); this.$refs.buscador.focus(); }
        },

        aviso(texto, tipo = 'info') {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, texto, tipo });
            setTimeout(() => { this.avisos = this.avisos.filter(a => a.id !== id); }, tipo === 'error' ? 4000 : 2000);
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
