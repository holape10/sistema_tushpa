<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c2410c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Envía con nosotros · {{ $nombre }}</title>
    <meta name="description" content="Cotiza tu envío y pre-regístralo en línea: en la agencia solo dictas tu código.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" x-data="webEnvios()">
    @php
        $campo = 'mt-1 w-full h-11 rounded-xl border-slate-300 text-sm';
        $etiqueta = 'block text-xs font-semibold text-slate-500';
    @endphp
    <header class="bg-gradient-to-br from-orange-600 via-orange-600 to-rose-700 text-white rounded-b-[2rem] shadow-lg">
        <div class="max-w-2xl mx-auto px-5 pt-7 pb-8">
            <div class="flex items-center gap-3">
                @if ($logo)<img src="{{ $logo }}" alt="" class="h-12 w-12 rounded-xl bg-white object-contain p-1">@endif
                <div><p class="font-black text-lg leading-tight">{{ $nombre }}</p><p class="text-sm text-orange-100">Envía tus encomiendas con nosotros</p></div>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-2 text-sm font-bold">
                <button type="button" @click="paso = 'cotizar'" class="h-11 rounded-xl" :class="paso !== 'registrar' ? 'bg-white text-orange-700' : 'bg-white/15'"><i class="fas fa-calculator"></i> Cotizar</button>
                <button type="button" @click="paso = 'registrar'" class="h-11 rounded-xl" :class="paso === 'registrar' ? 'bg-white text-orange-700' : 'bg-white/15'"><i class="fas fa-pen-to-square"></i> Pre-registrar</button>
                <a href="{{ route('rastreo') }}" class="h-11 rounded-xl bg-white/15 flex items-center justify-center gap-1"><i class="fas fa-location-crosshairs"></i> Rastrear</a>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 -mt-4 pb-12 space-y-4">
        {{-- Código del pre-registro --}}
        <template x-if="codigo">
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <p class="text-5xl">✅</p>
                <p class="mt-2 font-black text-lg">¡Listo! Tu código de pre-registro</p>
                <p class="font-mono text-4xl font-black tracking-[.3em] text-orange-700 my-3" x-text="codigo"></p>
                <div class="inline-block" x-html="qr"></div>
                <p class="text-sm text-slate-500 mt-2">Lleva tu encomienda a la agencia y dicta este código (o muestra el QR): pesamos, confirmamos el precio y te damos tu guía.
                    Vale {{ $dias }} días. Tómale captura.</p>
                <button type="button" @click="codigo = ''; paso = 'cotizar'" class="mt-4 h-11 px-5 rounded-xl bg-slate-100 font-bold">Hacer otro</button>
            </div>
        </template>

        <form x-show="!codigo" @submit.prevent="paso === 'registrar' ? registrar() : cotizar()" class="space-y-4">
            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                <p class="font-black"><i class="fas fa-route text-orange-500"></i> ¿De dónde a dónde?</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach (['origen_id' => 'Lo dejo en', 'destino_id' => 'Va a'] as $k => $texto)
                        <label class="{{ $etiqueta }}">{{ $texto }}
                            <select x-model.number="f.{{ $k }}" required class="{{ $campo }}">
                                <option value="">Elige la agencia…</option>
                                @foreach ($agencias as $a)<option value="{{ $a->age_id }}">{{ $a->ciudad }} · {{ $a->nombre }}</option>@endforeach
                            </select></label>
                    @endforeach
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <label class="rounded-xl ring-2 p-3 cursor-pointer" :class="f.entrega === 'AGENCIA' ? 'ring-orange-400 bg-orange-50' : 'ring-slate-200'"><input type="radio" value="AGENCIA" x-model="f.entrega" class="sr-only"><b>Recoge en agencia</b></label>
                    <label class="rounded-xl ring-2 p-3 cursor-pointer" :class="f.entrega === 'DOMICILIO' ? 'ring-orange-400 bg-orange-50' : 'ring-slate-200'"><input type="radio" value="DOMICILIO" x-model="f.entrega" class="sr-only"><b>Entrega a domicilio</b></label>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between"><p class="font-black"><i class="fas fa-boxes-stacked text-orange-500"></i> ¿Qué envías?</p>
                    <div class="flex gap-1"><button type="button" @click="f.paquetes.push(paquete('PAQUETE'))" class="h-9 px-3 rounded-lg bg-orange-50 text-orange-700 text-xs font-bold">+ Paquete</button>
                        <button type="button" @click="f.paquetes.push(paquete('SOBRE'))" class="h-9 px-3 rounded-lg bg-orange-50 text-orange-700 text-xs font-bold">+ Sobre</button></div></div>
                <template x-for="(p, i) in f.paquetes" :key="i">
                    <div class="rounded-xl ring-1 ring-slate-200 p-3 grid grid-cols-4 gap-2 items-end">
                        <p class="col-span-4 text-sm font-bold flex justify-between"><span x-text="(p.tipo === 'SOBRE' ? '✉️ Sobre' : '📦 Paquete') + ' ' + (i + 1)"></span>
                            <button type="button" x-show="f.paquetes.length > 1" @click="f.paquetes.splice(i, 1)" class="text-rose-500 text-xs">Quitar</button></p>
                        <label class="{{ $etiqueta }}">Cantidad<input type="number" min="1" x-model.number="p.cantidad" class="{{ $campo }} text-right"></label>
                        <template x-if="p.tipo === 'PAQUETE'"><label class="{{ $etiqueta }}">Peso kg<input type="number" step="0.1" min="0" x-model.number="p.peso" required class="{{ $campo }} text-right"></label></template>
                        <template x-if="p.tipo === 'PAQUETE'"><label class="{{ $etiqueta }} col-span-2">Medidas cm (largo × ancho × alto)
                            <div class="flex gap-1"><input type="number" min="0" x-model.number="p.largo" placeholder="L" class="{{ $campo }} text-right"><input type="number" min="0" x-model.number="p.ancho" placeholder="A" class="{{ $campo }} text-right"><input type="number" min="0" x-model.number="p.alto" placeholder="H" class="{{ $campo }} text-right"></div></label></template>
                    </div>
                </template>
                <label class="{{ $etiqueta }}">Valor de lo que envías S/ (para el seguro, opcional)<input type="number" step="0.01" min="0" x-model.number="f.valor_declarado" class="{{ $campo }} text-right"></label>
            </div>

            <template x-if="precio">
                <div class="bg-orange-600 text-white rounded-2xl shadow-sm p-5">
                    <p class="text-sm text-orange-100">Precio referencial</p>
                    <p class="text-4xl font-black" x-text="'S/ ' + precio.total.toFixed(2)"></p>
                    <p class="text-sm text-orange-100" x-text="precio.piezas + ' pieza(s) · ' + precio.peso_cobrado.toFixed(2) + ' kg · llega en ' + precio.dias + ' día(s) aprox.' + (precio.seguro > 0 ? ' · incluye seguro S/ ' + precio.seguro.toFixed(2) : '')"></p>
                    <p class="text-xs text-orange-100 mt-1">El precio final se confirma al pesar en la agencia.</p>
                    <button type="button" x-show="paso !== 'registrar'" @click="paso = 'registrar'" class="mt-3 h-11 px-5 rounded-xl bg-white text-orange-700 font-black">Pre-registrar mi envío →</button>
                </div>
            </template>

            <template x-if="paso === 'registrar'">
                <div class="space-y-4">
                    @foreach (['rem' => ['Tus datos (remitente)', 'fa-user'], 'des' => ['Quién recibe', 'fa-user-check']] as $p => [$titulo, $icono])
                        <div class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-3 gap-3">
                            <p class="sm:col-span-3 font-black"><i class="fas {{ $icono }} text-orange-500"></i> {{ $titulo }}</p>
                            <label class="{{ $etiqueta }}">DNI o RUC<input x-model="f.{{ $p }}_doc" @if ($p === 'rem') required minlength="8" @endif maxlength="11" inputmode="numeric" class="{{ $campo }}"></label>
                            <label class="{{ $etiqueta }}">Nombre completo<input x-model="f.{{ $p }}_nombre" required maxlength="150" class="{{ $campo }} uppercase"></label>
                            <label class="{{ $etiqueta }}">Celular<input x-model="f.{{ $p }}_telefono" required maxlength="20" inputmode="tel" class="{{ $campo }}"></label>
                        </div>
                    @endforeach
                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                        <label class="{{ $etiqueta }}">¿Qué contiene?<input x-model="f.contenido" required maxlength="200" placeholder="Ropa, documentos, repuestos…" class="{{ $campo }}"></label>
                        <label x-show="f.entrega === 'DOMICILIO'" class="{{ $etiqueta }}">Dirección de entrega y referencia<input x-model="f.direccion_entrega" maxlength="200" class="{{ $campo }}"></label>
                    </div>
                </div>
            </template>

            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700"></p>
            <button :disabled="ocupado" class="w-full h-12 rounded-xl bg-slate-900 text-white font-black disabled:opacity-50"
                    x-text="ocupado ? 'Un momento…' : (paso === 'registrar' ? '✓ Obtener mi código de pre-registro' : 'Calcular precio')"></button>
        </form>

        @if ($telefono)<a href="tel:{{ preg_replace('/\D/', '', $telefono) }}" class="block text-center text-sm font-semibold text-orange-700"><i class="fas fa-phone"></i> ¿Dudas? Llámanos al {{ $telefono }}</a>@endif
    </main>

    <script>
        function webEnvios() {
            const token = document.querySelector('meta[name=csrf-token]').content;
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token } })
                .then(async r => ({ status: r.status, ...(await r.json().catch(() => ({}))) }));
            const paquete = tipo => ({ tipo, cantidad: 1, peso: tipo === 'SOBRE' ? 0 : '', largo: '', ancho: '', alto: '' });
            return {
                paso: 'cotizar', ocupado: false, error: '', precio: null, codigo: '', qr: '', paquete,
                f: { origen_id: '', destino_id: '', entrega: 'AGENCIA', valor_declarado: '', paquetes: [paquete('PAQUETE')], rem_doc: '', rem_nombre: '', rem_telefono: '',
                     des_doc: '', des_nombre: '', des_telefono: '', contenido: '', direccion_entrega: '' },
                problema(d) { return d.errors ? Object.values(d.errors).flat()[0] : (d.status === 429 ? 'Muchos intentos: espera unos minutos.' : 'No se pudo. Revisa los datos.'); },
                async cotizar() {
                    this.ocupado = true; this.error = '';
                    try { const d = await post(@json(route('envios.cotizar')), this.f); if (d.total === undefined) { this.error = this.problema(d); return; } this.precio = d; }
                    catch (e) { this.error = 'Sin conexión.'; } finally { this.ocupado = false; }
                },
                async registrar() {
                    this.ocupado = true; this.error = '';
                    try {
                        const d = await post(@json(route('envios.prerregistrar')), this.f);
                        if (!d.ok) { this.error = this.problema(d); return; }
                        this.codigo = d.codigo; this.qr = d.qr; window.scrollTo({ top: 0, behavior: 'smooth' });
                    } catch (e) { this.error = 'Sin conexión.'; } finally { this.ocupado = false; }
                },
            };
        }
    </script>
</body>
</html>
