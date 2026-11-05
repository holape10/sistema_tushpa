{{-- Lo que hace cada fila del .dbf: guardar al momento, ver si cuadra y si el IGV está en su casilla. Variables: $libro, $periodo --}}
<script>
    const SIRE_DBF = { libro: @json($libro), periodo: @json($periodo), url: @json(route('sire.dbf.guardar')), cuenta: @json(route('sire.detalle.cuenta')) };
    const solesDbf = n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /** Igual que SireDbf::revisarIgv: cada IGV va del 10 % al 18 % de su base (si no, está en otra casilla) */
    function igvAlertaDbf(m) {
        if (SIRE_DBF.libro !== 'compras') return null;
        const pares = [['base_dg', 'igv_dg', 'IGV 1', 'B.I. gravadas'], ['base_dgng', 'igv_dgng', 'IGV 2', 'B.I. grav. y no grav.'], ['base_dng', 'igv_dng', 'IGV 3', 'B.I. no gravadas']];
        const dos = n => (Number(n) || 0).toFixed(2);
        for (const [kb, ki, nIgv, nBase] of pares) {
            const base = Math.round((Number(m[kb]) || 0) * 100) / 100, igv = Math.round((Number(m[ki]) || 0) * 100) / 100;
            if (!base && !igv) continue;
            if (!base) return `${nIgv} tiene monto pero su base (${nBase}) está en 0: el IGV va al lado de su base.`;
            if (!igv) return `${nBase} tiene monto pero su ${nIgv} está en 0. Si no lleva IGV, va en «No gravado».`;
            const tol = Math.max(0.10, Math.abs(base) * 0.005);
            if ((igv > 0) !== (base > 0) || Math.abs(igv) < Math.abs(base) * 0.10 - tol || Math.abs(igv) > Math.abs(base) * 0.18 + tol) {
                return `${nIgv} (${dos(igv)}) no corresponde a su base ${nBase} (${dos(base)}; al 18 % sería ${dos(base * 0.18)}). Revisa que cada monto esté en su casilla.`;
            }
        }
        return null;
    }

    function sireDbf(totales) {
        return {
            totales, filtro: 'todos', soles: solesDbf,
            visible(f) {
                return this.filtro === 'todos' || (this.filtro === 'descuadre' && !f.excluido && (Math.abs(f.diferencia) >= 0.01 || f.alerta))
                    || (this.filtro === 'excluidos' && f.excluido) || (this.filtro === 'sinxml' && !f.xml) || (this.filtro === 'sincuenta' && f.sinCuenta > 0);
            },
        };
    }

    function filaDbf(fila) {
        return {
            fila, detalle: false, guardando: false, soles: solesDbf,
            async guardar(cambios) {
                this.guardando = true;
                try {
                    const r = await fetch(SIRE_DBF.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ libro: SIRE_DBF.libro, periodo: SIRE_DBF.periodo, clave: this.fila.clave, ...cambios }),
                    });
                    const d = await r.json();
                    if (!r.ok) {
                        tushpaAviso(d.message || 'No se pudo guardar.', false);
                        return;
                    }
                    this.fila.diferencia = d.diferencia;
                    this.fila.alerta = d.alerta;
                    this.fila.editado = d.editado;
                    this.totales = d.totales;
                } catch (e) {
                    tushpaAviso('Se cortó la conexión: el cambio no se guardó.', false);
                } finally {
                    this.guardando = false;
                }
            },
            /** Al tocar un monto se ve al instante si cuadra y si el IGV está en su casilla; se guarda igual */
            cambiarMontos() {
                const m = this.fila.montos;
                const partes = Object.entries(m).filter(([k]) => k !== 'total').reduce((s, [, v]) => s + (Number(v) || 0), 0);
                this.fila.diferencia = Math.round((m.total - partes) * 100) / 100;
                this.fila.alerta = igvAlertaDbf(m);
                this.guardar({ montos: m });
            },
            async restaurar() {
                await this.guardar({ montos: null });
                location.reload();
            },
            /** Cuenta de una o varias líneas del XML: el sistema la aprende; si la puso también a otras, recarga para mostrarlas */
            async cuentaLinea(lineas, campo, todas = false) {
                const cuenta = campo.value.trim();
                if (!cuenta) return;
                campo.disabled = true;
                try {
                    const r = await fetch(SIRE_DBF.cuenta, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ lineas, cuenta }),
                    });
                    const d = await r.json();
                    if (!r.ok) {
                        tushpaAviso(d.message || 'No se pudo guardar la cuenta.', false);
                        return;
                    }
                    if (todas || d.cambiadas > lineas.length) {
                        location.reload();
                        return;
                    }
                    if (campo.dataset.vacia === '1') {
                        campo.dataset.vacia = '0';
                        this.fila.sinCuenta = Math.max(0, this.fila.sinCuenta - 1);
                    }
                    campo.classList.remove('bg-rose-50');
                    campo.classList.add('bg-emerald-50');
                } catch (e) {
                    tushpaAviso('Se cortó la conexión: la cuenta no se guardó.', false);
                } finally {
                    campo.disabled = false;
                }
            },
        };
    }
</script>
