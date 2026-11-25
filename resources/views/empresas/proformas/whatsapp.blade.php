@extends('layouts.app')
@section('title', 'Pedido de WhatsApp')

@section('content')
{{-- Pedido de WhatsApp (IA): el mensaje o la foto de la lista del cliente → proforma con los productos y precios de la tienda → respuesta con el total --}}
<div x-data="pedidoWhatsapp()" class="max-w-4xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">💬 Pedido de WhatsApp (IA)</h1>
        <p class="mt-1 text-white/90">Copia el mensaje del cliente y pégalo aquí, o sube la foto de su lista. La IA arma el pedido con tus productos y tus precios;
            tú lo revisas, lo guardas como proforma y le respondes con el total en un toque.</p>
    </section>

    {{-- 1. El mensaje --}}
    <section x-show="!guardada" class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 space-y-3">
        <textarea x-model="texto" rows="5" placeholder="Pega aquí el mensaje. Ej.: «Buenas, mándame 2 kilos de arroz, un aceite primor, 3 jaboncitos bolívar y una lejía. Soy Rosa»"
                  class="w-full rounded-xl border-gray-300 text-sm"></textarea>
        <div class="flex flex-wrap items-center gap-2">
            <input type="file" accept="image/*" class="hidden" x-ref="foto" @change="foto = $event.target.files[0] || null; vista = foto ? URL.createObjectURL(foto) : null">
            <button type="button" @click="$refs.foto.click()" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-bold">📷 Foto de la lista</button>
            <template x-if="vista">
                <span class="flex items-center gap-2"><img :src="vista" alt="" class="w-11 h-11 rounded-lg object-cover">
                    <button type="button" @click="foto = null; vista = null; $refs.foto.value = ''" class="text-xs text-rose-600 font-bold">Quitar</button></span>
            </template>
            <button type="button" @click="armar()" :disabled="leyendo || (!texto.trim() && !foto)"
                    class="ml-auto h-11 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black disabled:opacity-40"
                    x-text="leyendo ? 'Armando el pedido…' : '✨ Armar pedido'"></button>
        </div>
    </section>

    <datalist id="productos-pedido">
        <template x-for="p in productos" :key="p.id"><option :value="p.nombre"></option></template>
    </datalist>

    {{-- 2. Revisar --}}
    <section x-show="lineas.length && !guardada" x-cloak class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="p-4 grid sm:grid-cols-2 gap-3 border-b border-gray-100">
            <label class="text-xs font-semibold text-gray-500">Cliente<input x-model="cliente" maxlength="120" placeholder="CLIENTES VARIOS" class="block w-full mt-1 h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
            <label class="text-xs font-semibold text-gray-500">Celular del cliente (para responderle)<input x-model="celular" inputmode="tel" maxlength="15" placeholder="987654321" class="block w-full mt-1 h-10 rounded-lg border-gray-300 text-sm"></label>
            <p x-show="aviso" class="sm:col-span-2 rounded-xl bg-amber-50 text-amber-800 px-3 py-2 text-sm" x-text="'❓ Pregúntale al cliente: ' + aviso"></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-3 py-2 text-left">Pidió</th><th class="px-3 py-2 text-left">Producto</th><th class="px-2 py-2 w-24">Cant.</th>
                        <th class="px-2 py-2 w-28">Precio</th><th class="px-3 py-2 text-right w-24">Importe</th><th class="w-8"></th></tr>
                </thead>
                <tbody>
                    <template x-for="(l, j) in lineas" :key="l.key">
                        <tr class="border-t border-gray-100 align-top" :class="!l.id && 'bg-rose-50/60'">
                            <td class="px-3 py-2 text-xs text-gray-500" x-text="l.texto"></td>
                            <td class="px-3 py-2">
                                <input list="productos-pedido" x-model="l.nombre" @change="elegir(l)" placeholder="Elige el producto…"
                                       class="w-full h-9 rounded-lg text-sm uppercase" :class="l.id ? 'border-gray-300' : 'border-rose-400'">
                                <p x-show="!l.id" class="mt-1 text-xs text-rose-600 font-semibold">No está en tus productos: elige uno parecido o quita la línea.</p>
                            </td>
                            <td class="px-2 py-2"><input type="number" step="any" min="0" x-model.number="l.cantidad" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                            <td class="px-2 py-2"><input type="number" step="0.01" min="0" x-model.number="l.precio" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                            <td class="px-3 py-2 text-right font-bold" x-text="soles(importe(l))"></td>
                            <td class="py-2"><button type="button" @click="lineas.splice(j, 1)" class="text-gray-300 hover:text-rose-600 px-1" title="Quitar">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 border-t border-gray-100">
            <button type="button" @click="lineas.push({ key: ++clave, texto: '(agregado)', id: null, nombre: '', cantidad: 1, precio: 0 })" class="text-sm font-bold text-indigo-700">+ Agregar producto</button>
            <p class="ml-auto text-lg">Total <b x-text="soles(total())"></b></p>
            <button type="button" @click="guardar()" :disabled="guardando || lineas.some(l => !l.id) || !lineas.length"
                    class="h-11 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black disabled:opacity-40"
                    x-text="guardando ? 'Guardando…' : '✓ Guardar proforma'"></button>
        </div>
    </section>

    {{-- 3. Responder al cliente --}}
    <section x-show="guardada" x-cloak class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 space-y-3">
        <p class="font-extrabold text-emerald-700" x-text="'✓ Proforma ' + guardada?.numero + ' guardada por ' + soles(guardada?.total)"></p>
        <p class="text-sm text-gray-600">Este es el mensaje para el cliente (puedes cambiarlo):</p>
        <textarea x-model="respuesta" rows="9" class="w-full rounded-xl border-gray-300 text-sm"></textarea>
        <div class="flex flex-wrap gap-2">
            <a :href="enlaceWhatsapp()" target="_blank" rel="noopener" class="h-11 px-5 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black">💬 Enviar por WhatsApp</a>
            <button type="button" @click="copiar()" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 font-bold">📋 Copiar</button>
            <a :href="guardada?.caja" class="h-11 px-4 inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold">💵 Cobrar en caja</a>
            <a :href="guardada?.imprimir" target="_blank" class="h-11 px-4 inline-flex items-center rounded-xl bg-gray-100 text-gray-700 font-bold">🖨 Imprimir</a>
            <button type="button" @click="nuevo()" class="ml-auto h-11 px-4 rounded-xl text-indigo-700 font-bold">+ Otro pedido</button>
        </div>
    </section>
</div>

<script>
    function pedidoWhatsapp() {
        const csrf = @js(csrf_token());
        const negocio = @js($negocio);
        return {
            productos: @js($productos), clave: 0, texto: '', foto: null, vista: null, leyendo: false, guardando: false,
            cliente: '', celular: '', aviso: '', lineas: [], guardada: null, respuesta: '',
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            importe(l) { return Math.round((Number(l.cantidad) || 0) * (Number(l.precio) || 0) * 100) / 100; },
            total() { return Math.round(this.lineas.reduce((s, l) => s + this.importe(l), 0) * 100) / 100; },
            elegir(l) {
                const n = (l.nombre || '').trim().toUpperCase();
                const p = this.productos.find(x => x.nombre.toUpperCase() === n);
                l.id = p ? p.id : null;
                if (p) l.precio = p.precio;
            },
            async armar() {
                this.leyendo = true;
                const datos = new FormData();
                datos.append('texto', this.texto);
                if (this.foto) datos.append('foto', this.foto);
                try {
                    const r = await fetch(@js(route('proformas.whatsapp.leer')), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: datos });
                    const d = await r.json();
                    if (!d.ok) { tushpaAviso(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo armar el pedido.'), 'error'); return; }
                    this.cliente = this.cliente || d.cliente || '';
                    this.aviso = d.aviso;
                    // El precio es siempre el de la tienda
                    this.lineas = d.lineas.map(l => {
                        const p = this.productos.find(x => x.id === l.producto);
                        return { key: ++this.clave, texto: l.texto, id: p ? p.id : null, nombre: p ? p.nombre : '', cantidad: l.cantidad, precio: p ? p.precio : 0 };
                    });
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    this.leyendo = false;
                }
            },
            async guardar() {
                this.guardando = true;
                try {
                    const r = await fetch(@js(route('proformas.guardar')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ origen: 'WHATSAPP', tdicod: '1', clinum: '00000000', clinom: (this.cliente || 'CLIENTES VARIOS').trim(),
                            observaciones: ('WhatsApp ' + this.celular).trim(),
                            items: this.lineas.map(l => ({ id: l.id, cantidad: l.cantidad, precio: l.precio })) }),
                    });
                    const d = await r.json();
                    if (r.status === 422) { tushpaAviso(Object.values(d.errors)[0][0], 'error'); return; }
                    if (d.estado !== 'success') { tushpaAviso(d.mensaje || 'No se pudo guardar la proforma.', 'error'); return; }
                    this.guardada = { numero: d.numero, total: d.total, imprimir: d.imprimir, caja: @js(route('pv.index')) + '?proforma=' + d.id };
                    this.respuesta = this.mensaje(d);
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    this.guardando = false;
                }
            },
            mensaje(d) {
                const nombre = (this.cliente || '').trim().split(' ')[0];
                const lineas = this.lineas.map(l => '• ' + (+l.cantidad) + ' ' + l.nombre + ' — ' + this.soles(this.importe(l)));
                return ['Hola' + (nombre ? ' ' + nombre.charAt(0) + nombre.slice(1).toLowerCase() : '') + ' 👋, este es tu pedido en ' + negocio + ':', '',
                    ...lineas, '', '*Total: ' + this.soles(d.total) + '*', 'Pedido N° ' + d.numero, '', '¿Te lo preparamos? 😊'].join('\n');
            },
            enlaceWhatsapp() {
                const n = (this.celular || '').replace(/\D/g, '');
                return 'https://wa.me/' + (n.length === 9 ? '51' + n : n) + '?text=' + encodeURIComponent(this.respuesta);
            },
            async copiar() {
                try { await navigator.clipboard.writeText(this.respuesta); tushpaAviso('Mensaje copiado.', 'ok'); } catch (e) { tushpaAviso('No se pudo copiar.', 'error'); }
            },
            nuevo() {
                Object.assign(this, { texto: '', foto: null, vista: null, cliente: '', celular: '', aviso: '', lineas: [], guardada: null, respuesta: '' });
                this.$refs.foto.value = '';
            },
        };
    }
</script>
@endsection
