// Registro y edición de compras. Los cálculos aquí son solo para ver; el servidor recalcula todo al guardar.
document.addEventListener('alpine:init', () => {
    const CFG = window.COMPRA;
    const F = Number(CFG.factorIgv) || 1.18;
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const r4 = n => Math.round((Number(n) || 0) * 10000) / 10000;

    Alpine.data('compraForm', () => ({
        cfg: CFG,
        doc: {},
        prov: { tdicod: '6', num: '', nom: '', dir: '' },
        items: [],
        conLote: false,
        actualizarCosto: true,
        guardando: false,
        _key: 1,

        busqueda: '', resultados: [], resultadosAbiertos: false, resultadoActivo: 0, buscando: false, _ctrl: null,
        buscandoProv: false, sugerencias: [], sugActiva: 0, msgProv: { texto: '', ok: true }, _ultimoProv: '',
        avisos: [],
        elegir: null, // producto con presentaciones esperando que se elija cómo se compra

        iniciar() {
            const c = CFG.compra;
            const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
            this.doc = c ? {
                tdocod: c.tdocod, serie: c.serie, numero: c.numero, fecEmi: c.fecEmi, fecVen: c.fecVen, fecIng: c.fecIng || c.fecEmi,
                estadopago: c.estadopago, moneda: c.moneda || 'PEN', tip_cam: c.tip_cam, id_almacen: c.id_almacen, observaciones: c.observaciones || '',
            } : {
                tdocod: '01', serie: '', numero: '', fecEmi: CFG.hoy, fecVen: CFG.hoy, fecIng: CFG.hoy,
                estadopago: contado?.cre_dia_id, moneda: 'PEN', tip_cam: null, id_almacen: CFG.almacenes[0]?.id_almacen, observaciones: '',
            };
            if (c) {
                this.prov = { ...c.proveedor };
                this._ultimoProv = c.proveedor.num;
                this.items = c.items.map(i => ({ ...i, key: this._key++, flash: false }));
                this.conLote = c.items.some(i => i.lote || i.vencimiento || i.control_lote);
            }
            this.$nextTick(() => (c ? this.$refs.buscador : this.$refs.provNum)?.focus());
        },

        get soloLectura() { return !!CFG.compra && CFG.compra.estado !== 'Registrado'; },
        get titulo() {
            return CFG.compra ? `Compra ${CFG.compra.serie}-${CFG.compra.numero}` + (this.soloLectura ? ' (anulada)' : '') : 'Nueva compra';
        },
        get esContado() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.doc.estadopago);
            return !e || e.cre_dia_tip === 'CONTADO';
        },

        // ---------- Cálculos por línea (costo = costo unitario con IGV) ----------
        gravado(it) { return it.tip_igv === '10'; },
        total(it) { return r2(it.cantidad * it.costo); },
        subtotal(it) { return this.gravado(it) ? r2(this.total(it) / F) : this.total(it); },
        valUni(it) { return this.gravado(it) ? r4(it.costo / F) : r4(it.costo); },
        fleteUnd(it) { return it.cantidad > 0 ? r4((Number(it.flete) || 0) / it.cantidad) : 0; },
        // Tipo de cambio SUNAT (venta) de la fecha de emisión, como pide SUNAT para compras en dólares
        buscandoTc: false, tcInfo: '',
        async consultarTipoCambio() {
            this.buscandoTc = true; this.tcInfo = '';
            try {
                const d = await (await fetch(`${CFG.rutas.tipoCambio}?moneda=USD&fecha=${encodeURIComponent(this.doc.fecEmi)}`, { headers: { Accept: 'application/json' } })).json();
                if (d.ok) { this.doc.tip_cam = d.venta; this.tcInfo = `SUNAT ${d.fecha_sunat || d.fecha}: compra ${d.compra} · venta ${d.venta}`; }
                else this.aviso(d.mensaje || 'No se pudo consultar el tipo de cambio.', 'error');
            } catch (e) { this.aviso('No se pudo consultar el tipo de cambio.', 'error'); } finally { this.buscandoTc = false; }
        },
        costoFinal(it) { return r4((Number(it.costo) + this.fleteUnd(it)) * (this.doc.moneda === 'USD' ? (Number(this.doc.tip_cam) || 0) : 1)); },
        setValUni(it, v) { v = Math.max(0, parseFloat(v) || 0); it.costo = r4(this.gravado(it) ? v * F : v); },
        setTotal(it, v) { v = Math.max(0, parseFloat(v) || 0); if (it.cantidad > 0) it.costo = r4(v / it.cantidad); },
        setSubtotal(it, v) { v = Math.max(0, parseFloat(v) || 0); if (it.cantidad > 0) it.costo = r4((this.gravado(it) ? v * F : v) / it.cantidad); },

        get totales() {
            const t = { grav: 0, exo: 0, inaf: 0, igv: 0, total: 0 };
            for (const it of this.items) {
                const sub = this.subtotal(it), tot = this.total(it);
                if (it.tip_igv === '10') t.grav += sub; else if (it.tip_igv === '20') t.exo += sub; else t.inaf += sub;
                t.igv += tot - sub;
                t.total += tot;
            }
            for (const k in t) t[k] = r2(t[k]);
            return t;
        },

        num(n) { return String(r2(n)); },
        num2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fmt4(n) { return String(r4(n)); },
        enfocar(id) { this.$nextTick(() => { const el = document.getElementById(id); el?.focus(); el?.select?.(); }); },

        cambioPago() {
            const e = CFG.estadopagos.find(x => x.cre_dia_id == this.doc.estadopago);
            if (e && e.cre_dia_tip !== 'CONTADO') {
                const f = new Date(this.doc.fecEmi + 'T00:00:00');
                f.setDate(f.getDate() + (parseInt(e.cre_dia_fac) || 30));
                this.doc.fecVen = f.toISOString().slice(0, 10);
            }
        },

        // ---------- Productos ----------
        async buscar() {
            const q = this.busqueda.trim();
            if (q.length < 2) { this.resultados = []; this.resultadosAbiertos = false; return []; }
            this._ctrl?.abort();
            this._ctrl = new AbortController();
            this.buscando = true;
            try {
                const r = await fetch(`${CFG.rutas.productos}?q=${encodeURIComponent(q)}&almacen=${this.doc.id_almacen}`, { headers: { Accept: 'application/json' }, signal: this._ctrl.signal });
                const data = await r.json();
                if (this.busqueda.trim() !== q) return [];
                this.resultados = data; this.resultadoActivo = 0; this.resultadosAbiertos = true;
                return data;
            } catch (e) { return []; } finally { this.buscando = false; }
        },

        async enterBuscador() {
            const q = this.busqueda.trim();
            if (!q) return;
            if (!/\s/.test(q)) {
                try {
                    const r = await fetch(`${CFG.rutas.productos}?codigo=${encodeURIComponent(q)}&almacen=${this.doc.id_almacen}`, { headers: { Accept: 'application/json' } });
                    const exacto = (await r.json())[0];
                    if (exacto) { this.agregar(exacto, exacto.presentacion ? (exacto.presentaciones.find(x => x.id === exacto.presentacion) || null) : null); return; }
                } catch (e) { /* sigue con la búsqueda por nombre */ }
            }
            const lista = this.resultadosAbiertos && this.resultados.length ? this.resultados : await this.buscar();
            if (lista.length) this.agregar(lista[this.resultadoActivo] || lista[0]);
            else this.aviso(`“${q}” no pertenece a ningún producto que maneje stock`, 'error');
        },

        moverResultado(p) {
            if (!this.resultados.length) return;
            this.resultadosAbiertos = true;
            this.resultadoActivo = Math.min(Math.max(this.resultadoActivo + p, 0), this.resultados.length - 1);
        },

        /** @param pres undefined = si tiene presentaciones se pregunta con el modal; null = unidad base; objeto = esa presentación */
        agregar(p, pres) {
            if (pres === undefined) {
                if ((p.presentaciones || []).length) {
                    this.elegir = p;
                    this.busqueda = ''; this.resultados = []; this.resultadosAbiertos = false;
                    return;
                }
                pres = null;
            }
            this.elegir = null;
            // Con lote, el mismo producto puede venir en varias líneas; sin lote se suma a la existente
            // Farmacia: los productos con control de lote abren las columnas de lote y vencimiento
            if (p.control_lote) this.conLote = true;
            let it = this.conLote ? null : this.items.find(x => x.id === p.id && (x.presentacion || null) === (pres?.id ?? null));
            if (it) {
                it.cantidad = r2(it.cantidad + 1);
            } else {
                // El costo sugerido de una presentación es el costo unitario x factor
                this.items.push({ key: this._key++, id: p.id, codigo: p.codigo, nombre: p.nombre, tip_igv: CFG.tipIgvPred,
                    presentacion: pres?.id ?? null, factor: pres?.factor ?? 1,
                    presentacion_nombre: pres ? pres.nombre + ' x' + this.num(pres.factor) + ' ' + (p.unidad || '') : null,
                    cantidad: 1, costo: r4(p.costo * (pres?.factor ?? 1)), flete: 0, lote: '', vencimiento: '', control_lote: !!p.control_lote, flash: false });
                it = this.items[this.items.length - 1];
            }
            it.flash = true;
            setTimeout(() => { it.flash = false; }, 600);
            this.busqueda = ''; this.resultados = []; this.resultadosAbiertos = false;
            this.enfocar('cant-' + it.key);
        },

        // ---------- Proveedor ----------
        autoBuscarProv() {
            const v = this.prov.num.trim();
            if (/^\d{11}$/.test(v)) { this.prov.tdicod = '6'; if (v !== this._ultimoProv) this.buscarProv(); }
            else if (/^\d{8}$/.test(v)) this.prov.tdicod = '1';
        },

        async buscarProv() {
            const doc = this.prov.num.trim();
            if (!doc || this.buscandoProv) return;
            this._ultimoProv = doc;
            this.buscandoProv = true;
            this.msgProv = { texto: doc.length === 11 ? 'Buscando RUC…' : 'Buscando…', ok: true };
            try {
                const d = await (await fetch(`${CFG.rutas.proveedor}/${encodeURIComponent(doc)}`, { headers: { Accept: 'application/json' } })).json();
                if (d.error) { this.msgProv = { texto: d.error, ok: false }; return; }
                this.prov.nom = d.nom; this.prov.dir = d.dir && d.dir !== '--' ? d.dir : '';
                if (d.tdicod) this.prov.tdicod = d.tdicod;
                this.msgProv = { texto: '✔ Proveedor encontrado', ok: true };
            } catch (e) {
                this.msgProv = { texto: 'No se pudo consultar. Escribe los datos.', ok: false };
            } finally { this.buscandoProv = false; }
        },

        async sugerirProv() {
            const q = this.prov.nom.trim();
            if (q.length < 2) { this.sugerencias = []; return; }
            try {
                this.sugerencias = await (await fetch(`${CFG.rutas.proveedores}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })).json();
                this.sugActiva = 0;
            } catch (e) { this.sugerencias = []; }
        },

        usarProv(p) {
            this.prov = { tdicod: p.tdicod || (String(p.num).length === 11 ? '6' : '1'), num: p.num, nom: p.nom, dir: p.dir && p.dir !== '--' ? p.dir : '' };
            this._ultimoProv = p.num;
            this.sugerencias = [];
            this.msgProv = { texto: '✔ Proveedor seleccionado', ok: true };
        },

        // ---------- Guardar ----------
        validar() {
            if (!this.doc.serie.trim() || !String(this.doc.numero).trim()) return 'Completa la serie y el número del documento.';
            if (!/^\d+$/.test(String(this.doc.numero).trim())) return 'El número del documento solo lleva dígitos.';
            if (!this.prov.num.trim() || !this.prov.nom.trim()) return 'Completa el documento y el nombre del proveedor.';
            if (this.doc.moneda === 'USD' && !(this.doc.tip_cam > 0)) return 'Ingresa el tipo de cambio.';
            if (!this.items.length) return 'Agrega al menos un producto.';
            if (this.items.some(it => !(it.cantidad > 0))) return 'Todas las cantidades deben ser mayores a 0.';
            if (this.items.some(it => !(it.costo >= 0))) return 'Revisa los costos.';
            if (!this.esContado && this.doc.fecVen < this.doc.fecEmi) return 'El vencimiento no puede ser antes de la emisión.';
            const sinLote = this.items.find(it => it.control_lote && (!String(it.lote || '').trim() || !it.vencimiento));
            if (sinLote) return `Ingresa el lote y la fecha de vencimiento de ${sinLote.nombre}.`;
            const vencido = this.conLote && this.items.find(it => it.vencimiento && it.vencimiento < this.doc.fecIng);
            if (vencido) return `El lote de ${vencido.nombre} ya está vencido (${vencido.vencimiento}).`;
            return null;
        },

        async guardar() {
            if (this.guardando || this.soloLectura) return;
            const error = this.validar();
            if (error) { this.aviso(error, 'error'); return; }
            if (this.items.some(it => !(it.costo > 0)) && !confirm('Hay productos con costo 0 (por ejemplo, bonificaciones). ¿Guardar igual?')) return;

            const body = {
                ...this.doc,
                numero: String(this.doc.numero).trim(),
                serie: this.doc.serie.trim().toUpperCase(),
                fecVen: this.esContado ? null : this.doc.fecVen,
                prov_tdicod: this.prov.tdicod, prov_num: this.prov.num.trim(), prov_nom: this.prov.nom.trim(), prov_dir: this.prov.dir.trim(),
                actualizar_costo: this.actualizarCosto,
                items: this.items.map(it => ({
                    id: it.id, presentacion: it.presentacion || null, tip_igv: it.tip_igv, cantidad: it.cantidad, costo: it.costo, flete: Number(it.flete) || 0,
                    lote: this.conLote ? it.lote : null, vencimiento: this.conLote ? (it.vencimiento || null) : null,
                })),
            };

            this.guardando = true;
            try {
                const r = await fetch(CFG.rutas.guardar, {
                    method: CFG.rutas.metodo,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                    body: JSON.stringify(body),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página.', 'error'); return; }
                const d = await r.json();
                if (r.status === 422) { this.aviso(Object.values(d.errors)[0][0], 'error'); return; }
                if (d.estado !== 'success') { this.aviso(d.mensaje || 'No se pudo guardar.', 'error'); return; }
                window.location.href = d.redirect;
            } catch (e) {
                this.aviso('Error de conexión con el servidor.', 'error');
            } finally {
                this.guardando = false;
            }
        },

        teclaGlobal(e) {
            if (this.elegir) {
                if (e.key === 'Escape') { e.preventDefault(); this.elegir = null; }
                else if (/^[1-9]$/.test(e.key)) {
                    const ops = [null, ...this.elegir.presentaciones];
                    if (Number(e.key) <= ops.length) { e.preventDefault(); this.agregar(this.elegir, ops[Number(e.key) - 1]); }
                }
                return;
            }
            if (e.key === 'F2') { e.preventDefault(); this.$refs.buscador.focus(); }
            if (e.key === 'F9') { e.preventDefault(); this.guardar(); }
        },

        aviso(texto, tipo = 'info') {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, texto, tipo });
            setTimeout(() => { this.avisos = this.avisos.filter(a => a.id !== id); }, tipo === 'error' ? 4500 : 2500);
        },
    }));
});
