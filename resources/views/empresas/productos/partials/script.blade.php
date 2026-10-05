{{-- Estado del formulario de producto (crear y editar): código de barras, imagen, equivalencia, presentaciones y precios dinámicos --}}
@php
    $p = $producto ?? null;
    $hora = fn($h) => $h ? substr($h, 0, 5) : '';
    $inicial = [
        'tipo' => (string) old('promocion', $p ? (int) $p->promocion : 0),
        'umecod' => old('umecod', $p->umecod ?? 'NIU'),
        'precio' => old('propun', $p ? (float) $p->propun : ''),
        'costo' => old('costo', $p ? (float) $p->costo : 0),
        'codigoBarra' => old('codigo_barra', $p->codigo_barra ?? ''),
        'imagen' => $p?->imagen_url,
        'umeEquivalente' => old('ume_equivalente', $p->ume_equivalente ?? ''),
        'factorEquivalente' => old('factor_equivalente', $p && $p->ume_equivalente ? (float) $p->factor_equivalente : ''),
        'presentaciones' => array_values(old('presentaciones', $p ? $p->presentaciones->map(fn($x) => [
            'id' => $x->id_presentacion, 'umecod' => $x->umecod, 'nombre' => $x->nombre,
            'factor' => (float) $x->factor, 'precio' => (float) $x->precio, 'codigo_barra' => $x->codigo_barra,
        ])->all() : [])),
        'precios' => array_values(old('precios', $p ? $p->preciosDinamicos->map(fn($r) => [
            'dia' => (int) $r->dia, 'hora_inicio' => $hora($r->hora_inicio), 'hora_fin' => $hora($r->hora_fin),
            'precio' => (float) $r->precio, 'activo' => (bool) $r->activo,
        ])->all() : [])),
        'unidades' => $unidades->pluck('umenom', 'umecod'),
    ];
@endphp
<script src="{{ asset('js/escaner-barras.js') }}?v={{ filemtime(public_path('js/escaner-barras.js')) }}"></script>
<script>
    function productoForm() {
        const ini = @json($inicial);
        let key = 1;
        return {
            ...ini,
            quitarImagen: false,
            vistaImagen: ini.imagen,
            modalPrecios: false,
            presentaciones: ini.presentaciones.map(x => ({ key: key++, id: x.id ?? null, umecod: x.umecod || 'NIU', nombre: x.nombre || '',
                factor: x.factor ?? '', precio: x.precio ?? '', codigo_barra: x.codigo_barra || '' })),
            precios: ini.precios.map(r => ({ key: key++, dia: String(r.dia ?? 0), hora_inicio: r.hora_inicio || '', hora_fin: r.hora_fin || '',
                precio: r.precio ?? '', activo: r.activo === undefined ? true : (r.activo === true || r.activo === '1' || r.activo === 1) })),

            get usaEquivalente() { return this.tipo == '4' && !!this.umeEquivalente; },
            nombreUnidad(c) { return this.unidades[c] || c || ''; },
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            cruzaMedianoche(r) { return r.hora_inicio && r.hora_fin && r.hora_fin <= r.hora_inicio; },

            escanear(destino) {
                EscanerBarras.abrir(codigo => {
                    if (destino === 'producto') this.codigoBarra = codigo;
                    else destino.codigo_barra = codigo;
                });
            },

            elegirImagen(e) {
                const archivo = e.target.files[0];
                if (!archivo) return;
                if (archivo.size > 4 * 1024 * 1024) { alert('La imagen pesa más de 4 MB. Elige una más liviana.'); e.target.value = ''; return; }
                this.vistaImagen = URL.createObjectURL(archivo);
                this.quitarImagen = false;
            },
            borrarImagen() {
                this.vistaImagen = null;
                this.quitarImagen = true;
                this.$refs.imagen.value = '';
            },

            agregarPresentacion() {
                this.presentaciones.push({ key: key++, id: null, umecod: 'BX', nombre: '', factor: '', precio: '', codigo_barra: '' });
            },
            sugerido(pr) {
                const s = (Number(pr.factor) || 0) * (Number(this.precio) || 0);
                return s > 0 ? s.toFixed(2) : '0.00';
            },

            agregarPrecio() {
                this.precios.push({ key: key++, dia: '0', hora_inicio: '', hora_fin: '', precio: '', activo: true });
            },
        };
    }
</script>
