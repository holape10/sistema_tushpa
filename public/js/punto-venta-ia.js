// Punto Venta IA: se suma al componente del Punto Venta (punto-venta.js lo mezcla si existe window.PV_IA).
// El cajero habla o escribe; lo simple ("2 jabones") se resuelve sin IA y lo demás (pagos, varias cosas, cobrar) lo
// interpreta la IA en el servidor. Las acciones se aplican con los mismos métodos del Punto Venta.
window.PV_IA = (CFG) => {
    const redondear = n => Math.round((Number(n) || 0) * 100) / 100;
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const NUMEROS = {
        un: 1, uno: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6, siete: 7, ocho: 8, nueve: 9, diez: 10,
        once: 11, doce: 12, docena: 12, quince: 15, veinte: 20, treinta: 30, cincuenta: 50, cien: 100,
    };
    // Si la frase trae algo de esto, va a la IA (pagos, comprobante, cliente, cobrar, quitar, varias cosas)
    const COMPLEJO = /\b(y|paga|pago|pagan|efectivo|yape|plin|tarjeta|transferencia|deposito|depósito|boleta|factura|nota|cobra|cobrar|termina|registra|quita|saca|elimina|borra|cancela|dni|ruc|soles?|a|al|precio|cambia|que sean|ponle|vuelto|credito|crédito)\b|,/i;

    // "jabones" → "jabon", "galletas" → "galleta" (el catálogo busca por parte del nombre)
    const singular = (frase) => frase.split(/\s+/).map(p => {
        const w = p.toLowerCase();
        if (w.length > 4 && /[^aeiou]es$/.test(w)) return w.slice(0, -2);
        if (w.length > 3 && w.endsWith('s') && !w.endsWith('ss')) return w.slice(0, -1);
        return w;
    }).join(' ');

    return {
        ia: { escuchando: false, pensando: false, texto: '', ultimo: '', respuesta: '', escrito: '', elegir: [], crear: [] },

        iaEscuchar() {
            if (this.ia.escuchando) { this._iaRec?.stop(); return; }
            const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Rec) { this.aviso('Este navegador no reconoce la voz: usa Chrome o Edge, o escribe la instrucción.', 'error'); return; }
            if (!window.isSecureContext) { this.aviso('La voz necesita HTTPS. Abre el sistema con https://', 'error'); return; }
            const rec = new Rec();
            rec.lang = 'es-PE';
            rec.interimResults = true;
            rec.onstart = () => { this.ia.escuchando = true; this.ia.texto = ''; };
            rec.onend = () => { this.ia.escuchando = false; };
            rec.onerror = e => {
                this.ia.escuchando = false;
                if (e.error === 'not-allowed') this.aviso('Permite el uso del micrófono para hablarle al Punto Venta.', 'error');
            };
            rec.onresult = e => {
                const r = e.results[e.results.length - 1];
                this.ia.texto = r[0].transcript.trim();
                if (r.isFinal) this.iaProcesar(this.ia.texto);
            };
            this._iaRec = rec;
            rec.start();
        },

        async iaProcesar(texto) {
            texto = String(texto || '').trim();
            if (!texto || this.ia.pensando) return;
            this.ia.ultimo = texto;
            this.ia.pensando = true;
            try {
                // Lo simple ("2 jabones", "dame una inca kola") sin IA: se quita el "dame/quiero", se separa la cantidad y se busca
                const simple = texto.toLowerCase().replace(/[.!¡¿?]/g, '').replace(/^(quiero|dame|deme|agrega|agregame|agrégame|añade|pon|ponme|véndeme|vendeme|me das|me da)\s+/, '').trim();
                // Órdenes de una palabra, también sin IA
                if (/^(cobra|cobrar|registra|registrar|termina|terminar|listo)( la venta)?$/.test(simple)) { await this.iaAplicar([{ tipo: 'cobrar' }]); return; }
                if (/^(boleta|factura|nota|nota de venta)$/.test(simple)) { await this.iaAplicar([{ tipo: 'comprobante', valor: simple.split(' ')[0] }]); this.ia.respuesta = 'Comprobante: ' + simple; return; }
                if (/^(cancela|cancelar)( todo| la venta)?$/.test(simple)) { await this.iaAplicar([{ tipo: 'cancelar' }]); this.ia.respuesta = 'Venta cancelada'; return; }
                const m = simple.match(/^(\d+(?:[.,]\d+)?|[a-záéíóú]+)\s+(.+)$/);
                const n = m ? (/^\d/.test(m[1]) ? parseFloat(m[1].replace(',', '.')) : NUMEROS[m[1]]) : null;
                if (!COMPLEJO.test(texto) && simple.split(/\s+/).length <= 6) {
                    const cantidad = n > 0 ? n : 1;
                    const producto = singular(n > 0 ? m[2] : simple);
                    const r = await fetch(CFG.rutas.iaBuscar + '?producto=' + encodeURIComponent(producto), { headers: { Accept: 'application/json' } });
                    if (!r.ok) throw new Error();
                    const d = await r.json();
                    this.iaAplicar([{ tipo: 'agregar', producto, cantidad, precio: null, ...d }]);
                    return;
                }
                // Lo demás lo interpreta la IA, con el detalle y los medios de pago de esta venta
                const r = await fetch(CFG.rutas.iaInterpretar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({
                        texto, total: this.base,
                        detalle: this.carrito.map(it => ({ nombre: it.descripcion, cantidad: Number(it.cantidad) || 0, precio: Number(it.precio) || 0 })),
                        medios: CFG.medios.map(x => x.nombre),
                    }),
                });
                if (r.status === 419) { this.aviso('La sesión expiró. Recarga la página.', 'error'); return; }
                const d = await r.json();
                if (!d.ok) { this.ia.respuesta = d.mensaje || 'No te entendí.'; this.sonido(false); return; }
                this.ia.respuesta = d.respuesta || '';
                await this.iaAplicar(d.acciones || []);
            } catch (e) {
                this.ia.respuesta = 'No se pudo procesar. Revisa tu conexión e intenta de nuevo.';
                this.sonido(false);
            } finally {
                this.ia.pensando = false;
            }
        },

        async iaAplicar(acciones) {
            let cobrar = false;
            let pagosNuevos = null;
            for (const a of acciones) {
                const linea = Number.isInteger(a.linea) ? a.linea - 1 : -1;
                switch (a.tipo) {
                    case 'agregar':
                        if (a.encontrado) this.iaAgregar(a.encontrado, a.cantidad, a.precio);
                        else if ((a.opciones || []).length) this.ia.elegir.push({ texto: a.producto, cantidad: a.cantidad, precio: a.precio, opciones: a.opciones });
                        else if (a.producto) this.ia.crear.push({ nombre: String(a.producto).toUpperCase(), precio: a.precio || '', cantidad: a.cantidad || 1 });
                        break;
                    case 'quitar':
                        if (this.carrito[linea]) this.quitar(linea);
                        break;
                    case 'cantidad':
                        if (this.carrito[linea] && a.cantidad > 0) { this.carrito[linea].cantidad = redondear(a.cantidad); this.carrito[linea].importe = null; }
                        break;
                    case 'precio':
                        if (this.carrito[linea] && a.precio > 0) this.carrito[linea].precio = redondear(a.precio);
                        break;
                    case 'pago': {
                        const medio = CFG.medios.find(x => x.nombre.toLowerCase() === String(a.medio || '').toLowerCase())
                            || CFG.medios.find(x => x.nombre.toLowerCase().includes(String(a.medio || '').toLowerCase()));
                        if (!medio) { this.aviso(`No hay el medio de pago «${a.medio}»`, 'error'); break; }
                        pagosNuevos ??= [];
                        // Sin monto: ese medio se lleva lo que falte (como el medio predeterminado)
                        pagosNuevos.push({ ...medio, monto: a.monto > 0 ? redondear(a.monto) : 0, auto: !(a.monto > 0) });
                        break;
                    }
                    case 'comprobante': {
                        const cod = { boleta: '03', factura: '01', nota: '13' }[String(a.valor || '').toLowerCase()];
                        if (cod && CFG.comprobantes.some(c => c.tdocod === cod)) this.elegirComprobante(cod);
                        break;
                    }
                    case 'cliente': {
                        const doc = String(a.valor || '').replace(/\D/g, '');
                        if (doc.length === 8 || doc.length === 11) { this.cliente.num = doc; await this.buscarDoc(); }
                        break;
                    }
                    case 'cancelar':
                        this.nuevaVenta();
                        this.ia.elegir = [];
                        this.ia.crear = [];
                        break;
                    case 'cobrar':
                        cobrar = true;
                        break;
                }
            }
            if (pagosNuevos) {
                // Si todos dicen monto, el último no se fija: toma lo que falte (así cuadra con el total)
                if (!pagosNuevos.some(p => p.auto)) pagosNuevos[pagosNuevos.length - 1].auto = pagosNuevos.length === 1;
                this.medios = pagosNuevos;
            }
            if (cobrar) {
                if (this.ia.elegir.length || this.ia.crear.length) { this.ia.respuesta = 'Antes de cobrar, elige o crea los productos pendientes.'; return; }
                await this.registrar(true);
            }
        },

        iaAgregar(p, cantidad, precio) {
            // Con presentaciones o varios precios se pregunta cuál (como en el Punto Venta)
            if ((p.presentaciones || []).length || (p.precios || []).length || p.control_serie) { this.agregar(p, false); return; }
            this.agregar(p, false, null, redondear(cantidad || 1), precio > 0 ? redondear(precio) : p.precio);
        },

        iaElegir(i, p) {
            const e = this.ia.elegir[i];
            this.ia.elegir.splice(i, 1);
            this.iaAgregar(p, e.cantidad, e.precio);
        },

        async iaCrear(i) {
            const c = this.ia.crear[i];
            if (!c || c.enviando) return;
            c.enviando = true;
            try {
                const r = await fetch(CFG.rutas.iaProducto, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ nombre: c.nombre, precio: c.precio }),
                });
                const d = await r.json();
                if (!r.ok || !d.ok) { this.aviso(d.message || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo crear el producto.'), 'error'); return; }
                this.ia.crear.splice(this.ia.crear.indexOf(c), 1);
                this.aviso(`✔ Producto ${d.producto.nombre} creado`, 'ok');
                this.iaAgregar(d.producto, c.cantidad, null);
            } catch (e) {
                this.aviso('Sin conexión con el servidor.', 'error');
            } finally {
                c.enviando = false;
            }
        },
    };
};
