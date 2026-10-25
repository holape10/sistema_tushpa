@php
    $soles = fn ($n) => 'S/ '.number_format($n, 2);
    $productos = collect($categorias)->flatMap(fn ($c) => collect($c['productos'])->map(fn ($p) => $p + ['cat' => $c['id']]))->values();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="{{ $color }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Autoservicio · {{ $nombre }}</title>
    @if ($logo)<link rel="icon" href="{{ asset($logo) }}">@endif
    <style>
        [x-cloak]{display:none!important}
        :root { --acento: {{ $color }}; }
        .bg-acento { background: var(--acento); }
        .text-acento { color: var(--acento); }
        .ring-acento { box-shadow: 0 0 0 4px var(--acento); }
        .chip-activo { background: var(--acento); color: #fff; }
        html, body { overscroll-behavior: none; -webkit-user-select: none; user-select: none; touch-action: manipulation; }
        .no-scroll::-webkit-scrollbar { display: none; } .no-scroll { scrollbar-width: none; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased h-screen overflow-hidden" x-data="kiosko()" @pointerdown.window="tocar()">

    {{-- 1. Bienvenida --}}
    <section x-show="paso === 'inicio'" class="h-screen bg-acento text-white flex flex-col items-center justify-center text-center px-6">
        @if ($logo)<img src="{{ asset($logo) }}" alt="" class="h-36 w-36 rounded-[2rem] object-contain bg-white p-2 shadow-2xl">@endif
        <h1 class="text-4xl sm:text-5xl font-black mt-6">{{ $nombre }}</h1>
        <p class="text-xl text-white/85 mt-2">Pide aquí y paga en caja</p>
        <div class="grid sm:grid-cols-2 gap-5 mt-10 w-full max-w-2xl">
            <button type="button" @click="empezar(false)" class="h-40 rounded-[2rem] bg-white text-slate-800 shadow-2xl active:scale-95 transition">
                <span class="block text-6xl">🍽️</span><span class="block text-2xl font-black mt-2">Comer aquí</span></button>
            <button type="button" @click="empezar(true)" class="h-40 rounded-[2rem] bg-white text-slate-800 shadow-2xl active:scale-95 transition">
                <span class="block text-6xl">🥡</span><span class="block text-2xl font-black mt-2">Para llevar</span></button>
        </div>
        <a href="{{ route('comandas.seleccion') }}" class="absolute bottom-3 right-4 text-xs text-white/40" @click.prevent="salir()">Salir del kiosko</a>
    </section>

    {{-- 2. Carta --}}
    <section x-show="paso === 'menu'" x-cloak class="h-screen flex flex-col">
        <header class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
            @if ($logo)<img src="{{ asset($logo) }}" alt="" class="h-12 w-12 rounded-xl object-contain">@endif
            <div class="flex-1 min-w-0">
                <p class="font-black text-lg truncate">{{ $nombre }}</p>
                <p class="text-sm text-slate-500" x-text="llevar ? '🥡 Para llevar' : '🍽️ Para comer aquí'"></p>
            </div>
            <button type="button" @click="reiniciar()" class="h-12 px-5 rounded-2xl bg-slate-100 font-bold text-slate-600">Cancelar</button>
        </header>
        <nav class="no-scroll flex gap-2 overflow-x-auto px-4 py-3 bg-white border-t">
            @foreach ($categorias as $c)
                <button type="button" @click="cat = {{ $c['id'] }}" :class="cat === {{ $c['id'] }} ? 'chip-activo' : 'bg-slate-100 text-slate-700'"
                        class="shrink-0 h-12 px-6 rounded-2xl text-base font-black">{{ $c['nombre'] }}</button>
            @endforeach
        </nav>
        <main class="flex-1 overflow-y-auto p-4 pb-32">
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach ($productos as $p)
                    <button type="button" x-show="cat === {{ $p['cat'] }}" @click="agregar({{ $p['id'] }})"
                            class="relative bg-white rounded-3xl shadow-sm overflow-hidden text-left active:scale-95 transition">
                        @if ($p['img'])
                            <img src="{{ $p['img'] }}" alt="" loading="lazy" class="w-full aspect-[4/3] object-cover bg-slate-100">
                        @else
                            <div class="w-full aspect-[4/3] bg-slate-100 flex items-center justify-center text-5xl">🍽️</div>
                        @endif
                        <div class="p-3">
                            <p class="font-black leading-tight">{{ $p['nombre'] }}</p>
                            <p class="text-lg font-black text-acento mt-1">{{ $soles($p['precio']) }}</p>
                        </div>
                        <span x-show="cantidadDe({{ $p['id'] }})" x-text="cantidadDe({{ $p['id'] }})"
                              class="absolute top-2 right-2 w-10 h-10 rounded-full bg-acento text-white text-lg font-black flex items-center justify-center shadow-lg"></span>
                    </button>
                @endforeach
            </div>
            @if ($productos->isEmpty())
                <p class="text-center text-slate-500 py-20">No hay productos en la carta. Revisa que las categorías estén visibles.</p>
            @endif
        </main>
        <div x-show="carrito.length" class="fixed bottom-0 inset-x-0 p-4 bg-gradient-to-t from-slate-100">
            <button type="button" @click="paso = 'revisar'" class="w-full max-w-3xl mx-auto h-20 rounded-3xl bg-acento text-white shadow-2xl flex items-center px-6 text-2xl font-black">
                <span class="w-12 h-12 rounded-full bg-white/25 flex items-center justify-center" x-text="unidades()"></span>
                <span class="flex-1 text-left ml-4">Ver mi pedido</span>
                <span x-text="soles(total())"></span>
            </button>
        </div>
    </section>

    {{-- Elegir presentación --}}
    <div x-show="eligiendo" x-cloak @click="eligiendo = null" class="fixed inset-0 z-40 bg-slate-900/60 flex items-center justify-center p-6">
        <div @click.stop class="bg-white rounded-[2rem] w-full max-w-lg p-6 space-y-3">
            <p class="text-2xl font-black" x-text="eligiendo && eligiendo.nombre"></p>
            <template x-for="op in (eligiendo ? eligiendo.opciones : [])" :key="op.id ?? 0">
                <button type="button" @click="sumar(eligiendo.id, op.id); eligiendo = null" class="w-full flex justify-between items-center h-16 px-5 rounded-2xl ring-2 ring-slate-200 text-xl font-bold">
                    <span x-text="op.nombre"></span><span class="text-acento" x-text="soles(op.precio)"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- 3. Revisar y confirmar --}}
    <section x-show="paso === 'revisar'" x-cloak class="h-screen overflow-y-auto">
        <div class="max-w-2xl mx-auto p-5 space-y-4">
            <div class="flex items-center gap-3">
                <button type="button" @click="paso = 'menu'" class="h-14 px-5 rounded-2xl bg-white shadow-sm font-black text-lg">← Seguir pidiendo</button>
                <h2 class="flex-1 text-right text-2xl font-black">Tu pedido</h2>
            </div>
            <div class="bg-white rounded-3xl shadow-sm divide-y">
                <template x-for="(it, i) in carrito" :key="it.clave">
                    <div class="p-4 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-lg font-black leading-tight" x-text="it.nombre"></p>
                            <p class="text-slate-500" x-text="soles(it.precio * it.cantidad)"></p>
                        </div>
                        <button type="button" @click="cambiar(i, -1)" class="w-14 h-14 rounded-2xl bg-slate-100 text-3xl font-black">−</button>
                        <span class="w-10 text-center text-2xl font-black" x-text="it.cantidad"></span>
                        <button type="button" @click="cambiar(i, 1)" class="w-14 h-14 rounded-2xl bg-slate-100 text-3xl font-black">+</button>
                    </div>
                </template>
            </div>
            <label class="block bg-white rounded-3xl shadow-sm p-5">
                <span class="text-lg font-black">¿A nombre de quién?</span>
                <input x-model="nombre" maxlength="40" placeholder="Tu nombre" class="mt-2 w-full h-16 rounded-2xl border-slate-300 text-2xl font-bold" autocomplete="off">
            </label>
            <div class="bg-white rounded-3xl shadow-sm p-5 flex justify-between text-3xl font-black"><span>Total</span><span x-text="soles(total())"></span></div>
            <p x-show="error" x-text="error" class="text-center text-lg font-bold text-rose-600"></p>
            <button type="button" @click="confirmar()" :disabled="enviando" class="w-full h-20 rounded-3xl bg-acento text-white text-2xl font-black shadow-xl disabled:opacity-50"
                    x-text="enviando ? 'Enviando…' : 'Confirmar pedido'"></button>
        </div>
    </section>

    {{-- 4. Listo: su número --}}
    <section x-show="paso === 'listo'" x-cloak class="h-screen bg-acento text-white flex flex-col items-center justify-center text-center px-6">
        <p class="text-2xl font-bold">¡Gracias, <span x-text="nombre"></span>!</p>
        <p class="text-xl mt-6 text-white/85">Tu número es</p>
        <p class="text-[9rem] leading-none font-black" x-text="listo.numero"></p>
        <p class="text-3xl font-black mt-6">Acércate a caja a pagar <span x-text="soles(listo.total)"></span></p>
        <p class="text-lg text-white/80 mt-3" x-text="listo.alCobrar ? 'Al pagar, tu pedido pasa a cocina.' : 'Tu pedido ya está en cocina.'"></p>
        <button type="button" @click="reiniciar()" class="mt-10 h-16 px-10 rounded-2xl bg-white text-slate-800 text-xl font-black">Listo</button>
    </section>

    <script>
        function kiosko() {
            const PRODUCTOS = @js($productos->mapWithKeys(fn ($p) => [$p['id'] => ['nombre' => $p['nombre'], 'precio' => $p['precio'], 'presentaciones' => $p['presentaciones']]]));
            const PRIMERA = @js($categorias[0]['id'] ?? null);
            let reloj = null;
            return {
                paso: 'inicio', llevar: false, cat: PRIMERA, carrito: [], eligiendo: null, nombre: '', error: '', enviando: false, listo: {},
                soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
                unidades() { return this.carrito.reduce((s, i) => s + i.cantidad, 0); },
                total() { return Math.round(this.carrito.reduce((s, i) => s + i.precio * i.cantidad, 0) * 100) / 100; },
                cantidadDe(id) { return this.carrito.filter(i => i.id === id).reduce((s, i) => s + i.cantidad, 0); },
                empezar(llevar) { this.llevar = llevar; this.paso = 'menu'; },
                agregar(id) {
                    const p = PRODUCTOS[id];
                    if (p.presentaciones.length) {
                        this.eligiendo = { id, nombre: p.nombre, opciones: [{ id: null, nombre: 'Normal', precio: p.precio }, ...p.presentaciones] };
                        return;
                    }
                    this.sumar(id, null);
                },
                sumar(id, presentacion) {
                    const p = PRODUCTOS[id];
                    const pr = presentacion ? p.presentaciones.find(x => x.id === presentacion) : null;
                    const clave = id + '-' + (presentacion || 0);
                    const fila = this.carrito.find(i => i.clave === clave);
                    if (fila) fila.cantidad++;
                    else this.carrito.push({ clave, id, presentacion, nombre: p.nombre + (pr ? ' (' + pr.nombre + ')' : ''), precio: pr ? pr.precio : p.precio, cantidad: 1 });
                },
                cambiar(i, n) {
                    this.carrito[i].cantidad += n;
                    if (this.carrito[i].cantidad <= 0) this.carrito.splice(i, 1);
                    if (!this.carrito.length) this.paso = 'menu';
                },
                confirmar() {
                    this.error = '';
                    if (this.nombre.trim().length < 2) { this.error = 'Escribe tu nombre para llamarte.'; return; }
                    this.enviando = true;
                    fetch(@js(route('autoservicio.pedir')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ nombre: this.nombre, llevar: this.llevar, items: this.carrito.map(i => ({ id: i.id, cantidad: i.cantidad, presentacion: i.presentacion })) }),
                    }).then(async r => {
                        const d = await r.json().catch(() => ({}));
                        if (r.status === 419) { this.error = 'Se venció la sesión del kiosko. Avisa al personal.'; return; }
                        if (!r.ok) { this.error = d.message || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo enviar el pedido.'); return; }
                        this.listo = d;
                        this.paso = 'listo';
                        clearTimeout(reloj);
                        reloj = setTimeout(() => this.reiniciar(), 20000);
                    }).catch(() => this.error = 'Sin conexión. Avisa al personal.').finally(() => this.enviando = false);
                },
                reiniciar() {
                    Object.assign(this, { paso: 'inicio', carrito: [], nombre: '', error: '', eligiendo: null, cat: PRIMERA, listo: {} });
                },
                // Si nadie toca la pantalla por 2 minutos, vuelve a la bienvenida
                tocar() {
                    clearTimeout(this._inactivo);
                    this._inactivo = setTimeout(() => { if (this.paso !== 'inicio') this.reiniciar(); }, 120000);
                },
                salir() { if (confirm('¿Salir del kiosko?')) location.href = @js(route('comandas.seleccion')); },
            };
        }
    </script>
</body>
</html>
