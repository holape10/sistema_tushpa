// PV táctil: catálogo en memoria (filtra al instante), pedido con -50% / precio / notas, y cobro que emite e imprime.
document.addEventListener('alpine:init', () => {
    const CFG = window.PVT;
    const NOMBRES = { '01': 'Factura', '03': 'Boleta', '13': 'N. Venta' };
    const CLAVE = 'pv_tactil_' + CFG.usuario;
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
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
        venta: null,
        procesando: false,
        intento: false,
        avisos: [],
        _cierre: null,

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
        get total() { return r2(this.carrito.reduce((s, it) => s + r2(it.precio * it.cant), 0)); },
        get unidades() { return this.carrito.reduce((s, it) => s + it.cant, 0); },
        get medioActual() { return CFG.medios.find(m => m.id === this.medio); },
        get comision() { return r2(this.total * (this.medioActual?.comision || 0) / 100); },
        get totalCobrar() { return r2(this.total + this.comision); },
        get esEfectivo() { return /efectivo/i.test(this.medioActual?.nombre || ''); },
        get billetes() { return [10, 20, 50, 100, 200].filter(b => b > this.totalCobrar).slice(0, 3); },
        get faltaNombre() {
            return this.intento && CFG.nombreDesde > 0 && this.total >= CFG.nombreDesde && this.nombre.trim().length < 3;
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
                it.cant++;
            } else {
                this.carrito.push({ key: this._key++, id: p.id, nombre: p.nombre + (pres ? ' (' + pres.nombre + ')' : ''), precio, precioBase: precio,
                    presentacion: presId, cant: 1, desc50: false, nota: '', verNota: false, flash: false });
                it = this.carrito[this.carrito.length - 1];
            }
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 500);
            this.sonido(true);
        },
        cambiar(i, d) {
            this.carrito[i].cant += d;
            if (this.carrito[i].cant <= 0) this.carrito.splice(i, 1);
        },
        toggle50(i) {
            const it = this.carrito[i];
            it.desc50 = !it.desc50;
            it.precio = it.desc50 ? r2(it.precioBase / 2) : it.precioBase;
        },
        editarPrecio(i) {
            if (this.carrito[i].desc50) { this.aviso('Quita el -50% antes de cambiar el precio.', 'error'); return; }
            this.teclado = { i, nombre: this.carrito[i].nombre, valor: String(this.carrito[i].precio) };
        },
        teclaNum(k) {
            let v = this.teclado.valor === '0' ? '' : this.teclado.valor;
            if (k === '⌫') v = v.slice(0, -1);
            else if (k === '.') { if (!v.includes('.')) v = (v || '0') + '.'; }
            else if (!/\.\d{2}$/.test(v)) v += k;
            this.teclado.valor = v || '0';
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
            this._ultimoDoc = d;
            this.docTdicod = d.length === 11 ? '6' : '1';
            if (d.length === 11 && this.tdocod !== '01' && CFG.comprobantes.some(c => c.tdocod === '01')) this.tdocod = '01';
            this.buscandoDoc = true;
            try {
                const r = await (await fetch(CFG.rutas.cliente + '/' + d, { headers: { Accept: 'application/json' } })).json();
                if (this.doc.trim() !== d) return;
                if (r.error) { this.docNombre = 'No encontrado'; return; }
                this.docNombre = r.nom; this.docOk = true;
                if (r.tdicod) this.docTdicod = r.tdicod;
            } catch (e) { this.docNombre = 'Sin conexión'; } finally { this.buscandoDoc = false; }
        },

        // ---------- Cobrar ----------
        validar() {
            if (!this.carrito.length) return 'El pedido está vacío.';
            if (this.faltaNombre) return `Desde S/ ${this.num(CFG.nombreDesde)} pon el nombre del cliente y su N° de beeper.`;
            const d = this.doc.trim();
            if (this.tdocod === '01' && !(this.docOk && /^(10|15|17|20)\d{9}$/.test(d))) return 'La factura necesita un RUC válido.';
            if (this.tdocod === '03' && this.total >= 700 && !this.docOk) return 'Para boletas desde S/ 700 ingresa el DNI del cliente.';
            if (!CFG.contado) return 'No hay forma de pago CONTADO configurada.';
            return null;
        },

        async cobrar() {
            if (this.procesando) return;
            this.intento = true;
            const error = this.validar();
            if (error) { this.pedidoAbierto = true; this.sonido(false); this.aviso(error, 'error'); return; }

            const paga = this.esEfectivo ? (Number(this.paga) || 0) : 0;
            if (paga > 0 && paga < this.totalCobrar && !confirm(`Paga ${this.soles(paga)} y el total es ${this.soles(this.totalCobrar)}. ¿Continuar?`)) return;

            const conDoc = this.tdocod !== '13' && this.docOk;
            const body = {
                items: this.itemsParaEnviar(),
                tdocod: this.tdocod,
                estadopago: CFG.contado,
                fecEmi: CFG.hoy,
                tdicod: conDoc ? this.docTdicod : '1',
                clinum: conDoc ? this.doc.trim() : '00000000',
                clinom: conDoc ? this.docNombre : 'VENTA AL PORTADOR',
                observaciones: this.nombre.trim() ? ('CLIENTE: ' + this.nombre.trim().toUpperCase()).slice(0, 100) : '',
                paga,
                // Monto sin recargo: el servidor calcula y suma la comisión del medio
                id_med_pag: [this.medio],
                mon_med_pag: [this.total],
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
                descripcion: (it.nombre + (it.nota.trim() ? ' - ' + it.nota.trim() : '')).slice(0, 150) }));
        },

        /** Guarda el pedido como proforma e imprime; se edita o cobra luego desde Proformas (en el Punto Venta) */
        async guardarProforma() {
            if (this.procesando) return;
            if (!this.carrito.length) { this.aviso('El pedido está vacío.', 'error'); return; }
            const conDoc = this.docOk;
            this.procesando = true;
            try {
                const r = await fetch(CFG.rutas.proforma, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify({
                        origen: 'TACTIL', items: this.itemsParaEnviar(),
                        tdicod: conDoc ? this.docTdicod : '1', clinum: conDoc ? this.doc.trim() : '00000000',
                        clinom: conDoc ? this.docNombre : (this.nombre.trim() || 'VENTA AL PORTADOR'),
                        observaciones: this.nombre.trim() && conDoc ? ('CLIENTE: ' + this.nombre.trim().toUpperCase()).slice(0, 100) : '',
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
