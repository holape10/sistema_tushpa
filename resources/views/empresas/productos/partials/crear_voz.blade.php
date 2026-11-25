{{-- Crear productos hablando o con foto (IA): se dicta o se fotografía el paquete, la IA llena nombre, precio, stock inicial,
     código de barras y presentaciones; se revisa y se acepta. La foto queda como imagen del producto. --}}
@php($unidadesVoz = \App\Models\UnidadMedida::orderBy('umenom')->pluck('umenom', 'umecod'))
<div x-data="crearConVoz()" @crear-con-voz.window="abrir(false)" @crear-con-foto.window="abrir(true)" x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto" @click.self="cerrar()" @keydown.escape.window="cerrar()">
    <div class="w-full max-w-3xl my-6 bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white">
            <p class="flex-1 font-bold" x-text="conFoto ? '📷 Crear productos con foto (IA)' : '🎤 Crear productos con voz (IA)'"></p>
            <button type="button" @click="cerrar()" class="text-2xl leading-none text-white/80 hover:text-white" aria-label="Cerrar">×</button>
        </div>

        <div class="p-5 space-y-4">
            {{-- 1a. Foto del paquete: uno por foto; la IA lee marca, contenido y código de barras --}}
            <div x-show="conFoto" class="rounded-2xl border-2 border-dashed border-sky-300 bg-sky-50 p-4 flex flex-wrap items-center gap-4">
                <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="camara" @change="leerFotos($event)">
                <input type="file" accept="image/*" multiple class="hidden" x-ref="galeria" @change="leerFotos($event)">
                <button type="button" @click="$refs.camara.click()" :disabled="leyendo > 0"
                        class="w-16 h-16 shrink-0 rounded-2xl bg-sky-600 hover:bg-sky-700 text-white flex items-center justify-center disabled:opacity-50" title="Tomar foto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.66-.9l.82-1.2A2 2 0 0110.07 4h3.86a2 2 0 011.66.9l.82 1.2a2 2 0 001.66.9H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <div class="flex-1 min-w-[14rem] text-sm text-sky-900">
                    <p class="font-bold">Toma una foto a cada producto, uno por uno</p>
                    <p class="text-sky-800/80">De frente y con buena luz, que se lea la etiqueta. La IA llena el nombre, la marca, el contenido y el código de barras; tú pones el precio.
                        <button type="button" @click="$refs.galeria.click()" class="underline font-semibold">o elige varias fotos</button></p>
                    <p x-show="leyendo > 0" class="mt-1 font-bold text-violet-700 animate-pulse" x-text="'Leyendo ' + leyendo + ' foto(s)…'"></p>
                </div>
            </div>

            {{-- 1b. Dictar (con foto: precio o stock para la siguiente foto, por ejemplo «a 4.50, stock 12») --}}
            <div class="flex items-start gap-3">
                <button type="button" @click="escuchar()" :disabled="pensando"
                        :class="escuchando ? 'bg-rose-500 animate-pulse' : 'bg-violet-600 hover:bg-violet-700'"
                        class="w-14 h-14 shrink-0 rounded-full text-white flex items-center justify-center disabled:opacity-50" :title="escuchando ? 'Detener' : 'Hablar'">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v4m-4 0h8M12 15a3 3 0 003-3V5a3 3 0 00-6 0v7a3 3 0 003 3z"/></svg>
                </button>
                <div class="flex-1">
                    <textarea x-model="texto" :rows="conFoto ? 2 : 3" :placeholder="conFoto
                                  ? 'Opcional, antes de la foto: di o escribe el precio y el stock, por ejemplo «a 4.50, stock 12». Si no, lo pones después.'
                                  : 'Toca el micrófono y di, por ejemplo: «Jabón Bolívar a 3.50, stock 24, la caja de 12 a 38 soles. Arroz Costeño 5 kilos a 22 soles, stock 10, categoría abarrotes»'"
                              class="w-full rounded-xl border-gray-300 text-sm"></textarea>
                    <div class="flex items-center gap-2 mt-2" x-show="!conFoto">
                        <button type="button" @click="interpretar()" :disabled="pensando || texto.trim().length < 3"
                                class="h-9 px-4 rounded-xl bg-slate-900 text-white text-sm font-bold disabled:opacity-40" x-text="pensando ? 'Entendiendo…' : '✨ Entender'"></button>
                        <p class="text-sm text-violet-700" x-text="respuesta"></p>
                    </div>
                </div>
            </div>

            {{-- 2. Revisar lo que entendió (todo se puede corregir) --}}
            <template x-for="(p, i) in productos" :key="i">
                <div class="rounded-2xl border p-4" :class="p.resultado ? (p.resultado.ok ? 'border-emerald-300 bg-emerald-50' : 'border-rose-300 bg-rose-50') : 'border-gray-200'">
                    <div class="flex items-start gap-2">
                        <img x-show="p.vista" :src="p.vista" alt="" class="w-20 h-20 shrink-0 rounded-xl object-cover border border-gray-200">
                        <div class="flex-1 grid sm:grid-cols-6 gap-2 text-xs font-semibold text-gray-500">
                            <label class="sm:col-span-3">Nombre<input x-model="p.nombre" maxlength="150" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm uppercase"></label>
                            <label>Precio S/<input type="number" step="0.01" min="0" x-model="p.precio" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm text-right font-bold" :class="!(p.precio > 0) && 'border-rose-400 bg-rose-50'"></label>
                            <label>Costo S/<input type="number" step="0.01" min="0" x-model="p.costo" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm text-right"></label>
                            <label>Stock inicial<input type="number" step="any" min="0" x-model="p.stock" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm text-right"></label>
                            <label class="sm:col-span-2">Unidad
                                <select x-model="p.unidad" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm">
                                    @foreach ($unidadesVoz as $cod => $nom)<option value="{{ $cod }}">{{ $nom }} ({{ $cod }})</option>@endforeach
                                </select></label>
                            <label class="sm:col-span-2">Categoría<input x-model="p.categoria" maxlength="50" placeholder="(sin categoría)" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm uppercase"></label>
                            <label class="sm:col-span-2">Código de barras<input x-model="p.codigo_barra" maxlength="50" inputmode="numeric" placeholder="(opcional)" class="block w-full mt-1 h-9 rounded-lg border-gray-300 text-sm"></label>
                        </div>
                        <button type="button" @click="productos.splice(i, 1)" class="text-gray-300 hover:text-rose-600 text-lg px-1" title="Quitar">✕</button>
                    </div>
                    <div class="mt-3" x-show="p.presentaciones.length">
                        <p class="text-xs font-bold text-gray-600 mb-1">Presentaciones</p>
                        <template x-for="(x, j) in p.presentaciones" :key="j">
                            <div class="flex flex-wrap items-center gap-2 mb-1 text-sm">
                                <input x-model="x.nombre" class="flex-1 min-w-[10rem] h-8 rounded-lg border-gray-300 text-sm uppercase">
                                <span class="text-xs text-gray-500">trae</span>
                                <input type="number" step="any" min="0" x-model="x.factor" class="w-20 h-8 rounded-lg border-gray-300 text-sm text-right">
                                <span class="text-xs text-gray-500">a S/</span>
                                <input type="number" step="0.01" min="0" x-model="x.precio" class="w-24 h-8 rounded-lg border-gray-300 text-sm text-right font-bold">
                                <button type="button" @click="p.presentaciones.splice(j, 1)" class="text-gray-300 hover:text-rose-600">✕</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="p.resultado" class="mt-2 text-sm font-semibold" :class="p.resultado?.ok ? 'text-emerald-700' : 'text-rose-700'"
                       x-text="p.resultado ? (p.resultado.ok ? '✓ Creado' : '✕ ' + p.resultado.mensaje) : ''"></p>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50">
            <p class="flex-1 text-xs text-gray-500">Revisa antes de crear: el stock inicial entra al kardex del almacén predeterminado.</p>
            <button type="button" @click="cerrar()" class="h-10 px-4 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold">Cerrar</button>
            <button type="button" @click="crear()" :disabled="creando || !pendientes().length"
                    class="h-10 px-5 rounded-xl bg-emerald-600 text-white text-sm font-bold disabled:opacity-40"
                    x-text="creando ? 'Creando…' : '✓ Crear ' + pendientes().length + ' producto(s)'"></button>
        </div>
    </div>
</div>

<script>
    function crearConVoz() {
        const csrf = @js(csrf_token());
        return {
            abierto: false, conFoto: false, leyendo: 0, escuchando: false, pensando: false, creando: false, creados: false, texto: '', respuesta: '', productos: [],
            abrir(conFoto) { this.conFoto = conFoto; this.abierto = true; },
            cerrar() {
                this._rec?.stop();
                this.abierto = false;
                // Si se creó algo, la lista se actualiza
                if (this.creados) location.reload();
            },
            escuchar() {
                if (this.escuchando) { this._rec?.stop(); return; }
                const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!Rec || !window.isSecureContext) { tushpaAviso('La voz necesita Chrome o Edge y https://. También puedes escribirlo.', 'error'); return; }
                const rec = new Rec();
                rec.lang = 'es-PE';
                rec.continuous = true;
                rec.interimResults = true;
                const antes = this.texto ? this.texto.trim() + ' ' : '';
                rec.onstart = () => { this.escuchando = true; };
                // Con foto, lo dicho (precio, stock) espera a la siguiente foto
                rec.onend = () => { this.escuchando = false; if (!this.conFoto && this.texto.trim().length >= 3) this.interpretar(); };
                rec.onerror = e => { this.escuchando = false; if (e.error === 'not-allowed') tushpaAviso('Permite el uso del micrófono.', 'error'); };
                rec.onresult = e => { this.texto = antes + [...e.results].map(r => r[0].transcript).join(' '); };
                this._rec = rec;
                rec.start();
            },
            async interpretar() {
                if (this.pensando) return;
                this.pensando = true;
                try {
                    const r = await fetch(@js(route('productos.voz.interpretar')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ texto: this.texto }),
                    });
                    const d = await r.json();
                    if (!d.ok) { this.respuesta = d.mensaje || d.message || 'No te entendí.'; return; }
                    this.respuesta = d.respuesta;
                    // Lo nuevo se suma a lo que ya estaba en la lista (se puede dictar por partes)
                    this.productos.push(...d.productos.map(p => this.nuevo(p)));
                    this.texto = '';
                } catch (e) {
                    this.respuesta = 'Sin conexión con el servidor.';
                } finally {
                    this.pensando = false;
                }
            },
            // Cada foto se lee aparte (en orden) y su producto se suma a la lista con la foto a la vista
            async leerFotos(e) {
                const fotos = [...e.target.files];
                e.target.value = '';
                const texto = this.texto.trim();
                this.texto = '';
                this.leyendo += fotos.length;
                for (const foto of fotos) {
                    const datos = new FormData();
                    datos.append('foto', foto);
                    datos.append('texto', texto);
                    try {
                        const r = await fetch(@js(route('productos.foto.leer')), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: datos });
                        const d = await r.json();
                        if (!d.ok) { tushpaAviso(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo leer la foto.'), 'error'); continue; }
                        this.respuesta = d.respuesta;
                        this.productos.push(...d.productos.map(p => this.nuevo(p, URL.createObjectURL(foto))));
                    } catch (err) {
                        tushpaAviso('Sin conexión con el servidor.', 'error');
                    } finally {
                        this.leyendo--;
                    }
                }
            },
            nuevo(p, vista = null) {
                return { ...p, precio: p.precio ?? '', costo: p.costo ?? '', stock: p.stock ?? '', categoria: p.categoria ?? '', codigo_barra: p.codigo_barra ?? '', foto: p.foto ?? null, vista, resultado: null };
            },
            pendientes() { return this.productos.filter(p => !p.resultado?.ok); },
            async crear() {
                const lista = this.pendientes();
                if (lista.some(p => !(Number(p.precio) > 0))) { tushpaAviso('Falta el precio de algún producto.', 'error'); return; }
                this.creando = true;
                try {
                    const r = await fetch(@js(route('productos.voz.crear')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ productos: lista.map(p => ({ nombre: p.nombre, precio: p.precio, costo: p.costo || null, stock: p.stock || null,
                            unidad: p.unidad, categoria: p.categoria || null, codigo_barra: p.codigo_barra || null, foto: p.foto, presentaciones: p.presentaciones })) }),
                    });
                    const d = await r.json();
                    if (r.status === 422) { tushpaAviso(Object.values(d.errors)[0][0], 'error'); return; }
                    (d.resultados || []).forEach((res, k) => { lista[k].resultado = res; });
                    if (d.ok) { this.creados = true; tushpaAviso('Productos creados.', 'ok'); }
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    this.creando = false;
                }
            },
        };
    }
</script>
