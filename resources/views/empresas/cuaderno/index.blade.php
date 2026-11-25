@extends('layouts.app')
@section('title', 'Pasar cuaderno con foto')

@section('content')
{{-- Pasar el cuaderno con foto (IA): una foto por hoja; la IA lee cada línea y la empareja con un producto; se revisa y se registra --}}
<div x-data="cuadernoFoto()" class="max-w-5xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-amber-500 via-orange-500 to-rose-500 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">📒 Pasar el cuaderno con foto (IA)</h1>
        <p class="mt-1 text-white/90">¿Anotas tus ventas a mano? Tómale foto a cada hoja: la IA lee cada línea, la busca en tus productos y suma.
            Tú revisas, corriges si hace falta y la registras como una venta con la fecha del cuaderno (el stock se descuenta).</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="camara" @change="leerFotos($event)">
            <input type="file" accept="image/*" multiple class="hidden" x-ref="galeria" @change="leerFotos($event)">
            <button type="button" @click="$refs.camara.click()" class="h-12 px-5 rounded-2xl bg-white text-orange-700 font-black shadow">📷 Tomar foto a la hoja</button>
            <button type="button" @click="$refs.galeria.click()" class="h-12 px-5 rounded-2xl bg-white/20 hover:bg-white/30 font-bold">🖼️ Elegir varias fotos</button>
            <label class="ml-auto flex items-center gap-2 text-sm font-semibold">Registrar como
                <select x-model="tdocod" class="h-10 rounded-xl border-0 text-gray-800 text-sm font-bold">
                    <option value="13">Nota de venta</option>
                    <option value="03">Boleta</option>
                </select></label>
        </div>
    </section>

    @unless ($turnoAbierto)
        <div class="rounded-2xl bg-rose-50 ring-1 ring-rose-200 text-rose-800 p-4 text-sm">
            <b>Abre tu turno de caja</b> para poder registrar las hojas (toda venta queda en un turno). Puedes leer las fotos igual.
            <a href="{{ route('pv.index') }}" class="font-bold underline">Ir al Punto de venta</a>
        </div>
    @endunless

    <p x-show="!hojas.length" class="text-center text-gray-400 py-10">Todavía no hay hojas. Toma la foto de frente, con buena luz y que se vea la hoja completa con el total de abajo.</p>

    <datalist id="productos-cuaderno">
        <template x-for="p in productos" :key="p.id"><option :value="p.nombre"></option></template>
    </datalist>

    <template x-for="(h, i) in hojas" :key="h.key">
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden" :class="h.venta && 'ring-2 ring-emerald-300'">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
                <img :src="h.vista" alt="" class="w-16 h-16 rounded-xl object-cover cursor-zoom-in" @click="window.open(h.vista, '_blank')">
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-extrabold text-gray-800" x-text="'Hoja ' + (i + 1)"></p>
                    <p x-show="h.leyendo" class="text-sm font-bold text-violet-700 animate-pulse">Leyendo la hoja… (puede tardar hasta un minuto)</p>
                    <p x-show="h.error" class="text-sm font-bold text-rose-600" x-text="h.error"></p>
                    <p x-show="h.aviso" class="text-xs text-amber-700" x-text="'⚠ ' + h.aviso"></p>
                </div>
                <label x-show="h.lineas.length" class="text-xs font-semibold text-gray-500">Fecha de la hoja
                    <input type="date" x-model="h.fecha" max="{{ now()->toDateString() }}" :disabled="!!h.venta" class="block mt-1 h-9 rounded-lg border-gray-300 text-sm"></label>
                <button type="button" x-show="!h.venta" @click="hojas.splice(i, 1)" class="text-gray-300 hover:text-rose-600 text-xl px-1" title="Quitar hoja">✕</button>
            </div>

            <div x-show="h.lineas.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr><th class="px-3 py-2 text-left">En el cuaderno</th><th class="px-3 py-2 text-left">Producto del sistema</th>
                            <th class="px-2 py-2 w-24">Cant.</th><th class="px-2 py-2 w-28">Precio</th><th class="px-3 py-2 text-right w-28">Importe</th><th class="w-8"></th></tr>
                    </thead>
                    <tbody>
                        <template x-for="(l, j) in h.lineas" :key="l.key">
                            <tr class="border-t border-gray-100 align-top" :class="!l.id && 'bg-rose-50/60'">
                                <td class="px-3 py-2 text-gray-500 text-xs" x-text="l.texto"></td>
                                <td class="px-3 py-2">
                                    <input list="productos-cuaderno" x-model="l.nombre" @change="elegir(l)" :disabled="!!h.venta" placeholder="Escribe o elige el producto…"
                                           class="w-full h-9 rounded-lg text-sm uppercase" :class="l.id ? 'border-gray-300' : 'border-rose-400'">
                                    <template x-if="!l.id && !h.venta">
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="text-rose-600 font-semibold">No está en tus productos.</span>
                                            @if ($puedeCrear)
                                                <button type="button" @click="crearProducto(l)" :disabled="l.creando" class="px-2 py-1 rounded-lg bg-indigo-600 text-white font-bold disabled:opacity-50"
                                                        x-text="l.creando ? 'Creando…' : '+ Crear «' + nombreSugerido(l) + '» a S/ ' + Number(l.precio).toFixed(2)"></button>
                                            @endif
                                        </div>
                                    </template>
                                </td>
                                <td class="px-2 py-2"><input type="number" step="any" min="0" x-model.number="l.cantidad" :disabled="!!h.venta" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" x-model.number="l.precio" :disabled="!!h.venta" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right">
                                    <b x-text="soles(importe(l))"></b>
                                    <span x-show="Math.abs(importe(l) - l.importe) > 0.009" class="block text-[11px] text-amber-700" x-text="'cuaderno ' + soles(l.importe)"></span>
                                </td>
                                <td class="py-2"><button type="button" x-show="!h.venta" @click="h.lineas.splice(j, 1)" class="text-gray-300 hover:text-rose-600 px-1" title="Quitar línea">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="h.lineas.length" class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 border-t border-gray-100">
                <button type="button" x-show="!h.venta" @click="agregarLinea(h)" class="text-sm font-bold text-indigo-700">+ Agregar línea</button>
                <div class="ml-auto text-right text-sm">
                    <p>Suma <b class="text-lg" x-text="soles(suma(h))"></b>
                        <template x-if="h.total !== null"><span> · cuaderno <b x-text="soles(h.total)"></b></span></template></p>
                    <p x-show="h.total !== null && Math.abs(suma(h) - h.total) > 0.009" class="text-xs font-bold text-amber-700"
                       x-text="'Diferencia de ' + soles(Math.abs(suma(h) - h.total)) + ': revisa las líneas antes de registrar'"></p>
                    <p x-show="h.total !== null && Math.abs(suma(h) - h.total) <= 0.009" class="text-xs font-bold text-emerald-700">✓ Cuadra con el total del cuaderno</p>
                </div>
                <template x-if="!h.venta">
                    <button type="button" @click="registrar(h)" :disabled="h.registrando || h.lineas.some(l => !l.id) || !h.fecha"
                            class="h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black disabled:opacity-40"
                            x-text="h.registrando ? 'Registrando…' : '✓ Registrar esta hoja'"></button>
                </template>
                <p x-show="h.venta" class="font-bold text-emerald-700" x-text="'✓ ' + h.venta"></p>
            </div>
        </section>
    </template>
</div>

<script>
    function cuadernoFoto() {
        const csrf = @js(csrf_token());
        let clave = 0;
        return {
            productos: @js($productos), tdocod: '13', hojas: [],
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            importe(l) { return Math.round((Number(l.cantidad) || 0) * (Number(l.precio) || 0) * 100) / 100; },
            suma(h) { return Math.round(h.lineas.reduce((s, l) => s + this.importe(l), 0) * 100) / 100; },
            buscar(nombre) { const n = (nombre || '').trim().toUpperCase(); return this.productos.find(p => p.nombre.toUpperCase() === n); },
            elegir(l) {
                const p = this.buscar(l.nombre);
                l.id = p ? p.id : null;
                if (p && !(Number(l.precio) > 0)) l.precio = p.precio;
            },
            linea(l) {
                const p = this.productos.find(x => x.id === l.producto);
                return { key: ++clave, texto: l.texto, id: p ? p.id : null, nombre: p ? p.nombre : '', cantidad: l.cantidad, precio: l.precio, importe: l.importe, creando: false };
            },
            agregarLinea(h) { h.lineas.push({ key: ++clave, texto: '(agregada)', id: null, nombre: '', cantidad: 1, precio: 0, importe: 0, creando: false }); },
            // Nombre para crear el producto: sin la cantidad de adelante ni el "x precio" de atrás
            nombreSugerido(l) {
                return (l.nombre || l.texto).replace(/^\s*[\d½¼¾\/.,]+\s+/, '').replace(/\s*X\s*[\d.,]+\s*$/i, '').trim().toUpperCase();
            },
            async leerFotos(e) {
                const fotos = [...e.target.files];
                e.target.value = '';
                // Una a la vez, en orden: cada hoja aparece apenas se lee
                const nuevas = fotos.map(f => ({ key: ++clave, archivo: f, vista: URL.createObjectURL(f), leyendo: true, error: '', aviso: '', fecha: '', total: null, lineas: [], venta: null, registrando: false }));
                this.hojas.push(...nuevas);
                for (const hoja of nuevas) {
                    const h = this.hojas.find(x => x.key === hoja.key);
                    if (!h) continue;
                    const datos = new FormData();
                    datos.append('foto', hoja.archivo);
                    try {
                        const r = await fetch(@js(route('cuaderno.leer')), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: datos });
                        const d = await r.json();
                        if (!d.ok) { h.error = d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo leer la hoja.'); continue; }
                        h.fecha = d.fecha || @js(now()->toDateString());
                        h.total = d.total;
                        h.aviso = d.aviso;
                        h.lineas = d.lineas.map(l => this.linea(l));
                    } catch (err) {
                        h.error = 'Sin conexión con el servidor.';
                    } finally {
                        h.leyendo = false;
                    }
                }
            },
            async crearProducto(l) {
                l.creando = true;
                const nombre = this.nombreSugerido(l);
                try {
                    const r = await fetch(@js(route('productos.voz.crear')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ productos: [{ nombre, precio: Number(l.precio) || 0, unidad: 'NIU' }] }),
                    });
                    const d = await r.json();
                    const res = (d.resultados || [])[0];
                    if (r.status === 422) { tushpaAviso(Object.values(d.errors)[0][0], 'error'); return; }
                    if (!res?.ok || !res.id) { tushpaAviso(res?.mensaje || d.message || 'No se pudo crear el producto.', 'error'); return; }
                    this.productos.push({ id: res.id, nombre: res.nombre, precio: Number(l.precio) || 0 });
                    // Las demás líneas con el mismo texto toman el producto nuevo
                    this.hojas.forEach(h => h.lineas.filter(x => !x.id && this.nombreSugerido(x) === nombre).forEach(x => { x.id = res.id; x.nombre = res.nombre; }));
                    tushpaAviso('Producto creado: ' + res.nombre, 'ok');
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    l.creando = false;
                }
            },
            async registrar(h) {
                if (h.total !== null && Math.abs(this.suma(h) - h.total) > 0.009
                    && !confirm('La suma (' + this.soles(this.suma(h)) + ') no cuadra con el total del cuaderno (' + this.soles(h.total) + '). ¿Registrar igual?')) return;
                h.registrando = true;
                try {
                    const r = await fetch(@js(route('cuaderno.registrar')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ fecha: h.fecha, tdocod: this.tdocod, nombre: 'hoja del ' + h.fecha,
                            lineas: h.lineas.map(l => ({ id: l.id, cantidad: l.cantidad, precio: l.precio })) }),
                    });
                    const d = await r.json();
                    if (!d.ok) { tushpaAviso(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo registrar.'), 'error'); return; }
                    h.venta = d.mensaje;
                    tushpaAviso(d.mensaje, 'ok');
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    h.registrando = false;
                }
            },
        };
    }
</script>
@endsection
