@php
    $soles = fn ($n) => 'S/ '.number_format($n, 2);
    // "CHILCANO DE MARACUYÁ" → "Chilcano de Maracuyá"
    $titulo = fn ($t) => preg_replace_callback('/(?<=\s)(De|Del|La|Las|Los|El|Con|Y|A|Al|En|Por)(?=\s)/u', fn ($m) => mb_strtolower($m[1]), \Illuminate\Support\Str::title(mb_strtolower($t)));
    $total = collect($categorias)->sum(fn ($c) => count($c['productos']));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $color }}">
    <title>Carta · {{ $nombre }}</title>
    <meta name="description" content="Carta digital de {{ $nombre }}: platos, bebidas y precios.">
    @if ($logo)<link rel="icon" href="{{ asset($logo) }}">@endif
    <style>
        [x-cloak]{display:none!important}
        :root { --acento: {{ $color }}; }
        .acento { color: var(--acento); }
        .bg-acento { background: var(--acento); }
        .hero { background: radial-gradient(120% 90% at 100% 0%, color-mix(in srgb, var(--acento) 55%, #a855f7) 0%, transparent 60%),
                            linear-gradient(160deg, var(--acento) 0%, color-mix(in srgb, var(--acento) 60%, #000) 100%); }
        .chip-activo { background: var(--acento); color: #fff; box-shadow: 0 6px 14px -6px var(--acento); }
        .precio { background: color-mix(in srgb, var(--acento) 10%, #fff); color: color-mix(in srgb, var(--acento) 85%, #000); }
        .no-scroll::-webkit-scrollbar { display: none; } .no-scroll { scrollbar-width: none; }
        section { scroll-margin-top: 76px; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="carta()" x-init="iniciar()">

    {{-- Portada --}}
    <header class="hero relative overflow-hidden text-white rounded-b-[2rem] shadow-lg">
        <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/10"></div>
        <div class="absolute -left-14 bottom-0 w-40 h-40 rounded-full bg-white/5"></div>
        <div class="relative max-w-3xl mx-auto px-5 pt-8 pb-7 text-center">
            @if ($logo)
                <img src="{{ asset($logo) }}" alt="{{ $nombre }}" class="mx-auto h-24 w-24 rounded-3xl object-contain bg-white p-1.5 shadow-xl ring-4 ring-white/30">
            @else
                <div class="mx-auto h-20 w-20 rounded-3xl bg-white/20 ring-4 ring-white/30 flex items-center justify-center text-4xl font-black">{{ mb_substr($nombre, 0, 1) }}</div>
            @endif
            <h1 class="mt-4 text-2xl sm:text-3xl font-black tracking-tight">{{ $nombre }}</h1>
            <p class="text-white/75 text-sm mt-0.5">Nuestra carta · {{ $total }} {{ $total === 1 ? 'opción' : 'opciones' }}</p>
            @if ($mesa)
                <span class="inline-flex items-center gap-1.5 mt-3 rounded-full bg-white text-slate-800 px-4 py-1.5 text-sm font-extrabold shadow">🍽️ {{ $mesa }}</span>
            @endif
            @if ($negocio->carta_mensaje)
                <p class="mt-3 text-sm text-white/90 whitespace-pre-line">{{ $negocio->carta_mensaje }}</p>
            @endif

            <label class="relative block mt-5">
                <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" x-model="q" placeholder="¿Qué se te antoja hoy?"
                       class="w-full h-12 pl-12 pr-4 rounded-2xl border-0 text-slate-800 shadow-lg placeholder-slate-400 focus:ring-4 focus:ring-white/40">
            </label>
        </div>
    </header>

    {{-- Categorías (se quedan arriba al bajar) --}}
    @if (count($categorias) > 1)
        <nav class="sticky top-0 z-20 bg-slate-100/90 backdrop-blur">
            <div class="max-w-3xl mx-auto flex gap-2 overflow-x-auto no-scroll px-4 py-3" x-ref="chips">
                @foreach ($categorias as $c)
                    <a href="#cat-{{ $c['id'] }}" data-chip="{{ $c['id'] }}" @click.prevent="ir({{ $c['id'] }})"
                       :class="activa === {{ $c['id'] }} ? 'chip-activo' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                       class="shrink-0 h-9 px-4 rounded-full text-sm font-bold whitespace-nowrap flex items-center transition">{{ $titulo($c['nombre']) }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    <main class="max-w-3xl mx-auto px-4 pb-10 pt-2">
        @forelse ($categorias as $c)
            <section id="cat-{{ $c['id'] }}" data-cat="{{ $c['id'] }}" class="pt-5" x-show="hay(@js(collect($c['productos'])->map(fn ($p) => $p['nombre'].' '.$p['descripcion'].' '.$c['nombre'])))">
                <h2 class="flex items-center gap-3 mb-3">
                    <span class="w-1.5 h-7 rounded-full bg-acento"></span>
                    <span class="text-xl font-black text-slate-800">{{ $titulo($c['nombre']) }}</span>
                    <span class="text-xs font-semibold text-slate-400">{{ count($c['productos']) }}</span>
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($c['productos'] as $p)
                        <article x-show="ver(@js($p['nombre'].' '.$p['descripcion'].' '.$c['nombre']))"
                                 class="bg-white rounded-2xl {{ $p['img'] ? 'p-3.5' : 'px-3.5 py-3' }} shadow-sm ring-1 ring-slate-200/70 flex gap-3 {{ $p['img'] ? 'cursor-pointer active:scale-[.99] transition' : '' }}"
                                 @if ($p['img']) @click="foto = @js(['img' => $p['img'], 'nombre' => $p['nombre'], 'descripcion' => $p['descripcion'], 'precio' => $soles($p['precio'])])" @endif>
                            <div class="flex-1 min-w-0 flex flex-col">
                                <h3 class="font-extrabold text-slate-800 leading-snug">{{ $titulo($p['nombre']) }}</h3>
                                @if ($p['descripcion'])
                                    <p class="text-[13px] text-slate-500 leading-snug mt-0.5 line-clamp-2">{{ $p['descripcion'] }}</p>
                                @endif
                                <div class="mt-auto flex flex-wrap items-center gap-1.5 {{ $p['img'] || $p['precio'] < $p['normal'] || $p['presentaciones'] ? 'pt-2' : '' }}">
                                    @if ($p['img'])<span class="precio rounded-lg px-2.5 py-1 text-sm font-black">{{ $soles($p['precio']) }}</span>@endif
                                    @if ($p['precio'] < $p['normal'])
                                        <span class="text-xs text-slate-400 line-through">{{ $soles($p['normal']) }}</span>
                                        <span class="text-[10px] font-black uppercase text-rose-600 bg-rose-50 rounded px-1.5 py-0.5">Oferta</span>
                                    @endif
                                    @foreach ($p['presentaciones'] as $pr)
                                        <span class="rounded-lg bg-slate-100 text-slate-600 px-2 py-1 text-xs font-bold">{{ $titulo($pr['nombre']) }} {{ $soles($pr['precio']) }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @if ($p['img'])
                                <img src="{{ $p['img'] }}" alt="" loading="lazy" class="w-24 h-24 rounded-xl object-cover shrink-0 bg-slate-100">
                            @else
                                <span class="precio self-start rounded-lg px-2.5 py-1 text-sm font-black shrink-0">{{ $soles($p['precio']) }}</span>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="mt-8 bg-white rounded-2xl p-10 text-center text-slate-500 shadow-sm">Muy pronto publicaremos nuestra carta. 🍽️</div>
        @endforelse

        <p x-show="q.trim() && !todo.some(t => ver(t))" x-cloak class="text-center text-slate-500 py-10">
            No encontramos “<span x-text="q"></span>”. Prueba con otra palabra.
        </p>
    </main>

    <footer class="text-center text-xs text-slate-400 pb-8 px-4 space-y-1">
        @if ($negocio->direccion)<p>📍 {{ $negocio->direccion }}</p>@endif
        @if ($negocio->telefono)<p>📞 <a href="tel:{{ preg_replace('/\D/', '', $negocio->telefono) }}" class="underline">{{ $negocio->telefono }}</a></p>@endif
        <p class="pt-2">Precios en soles, incluyen impuestos · Carta digital por <b class="text-slate-500">TUSHPA</b></p>
    </footer>

    {{-- Foto ampliada --}}
    <div x-show="foto" x-cloak x-transition.opacity @click="foto = null" @keydown.escape.window="foto = null"
         class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
        <div @click.stop class="bg-white rounded-3xl overflow-hidden w-full max-w-md shadow-2xl">
            <img :src="foto?.img" alt="" class="w-full max-h-[60vh] object-cover">
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-lg font-black text-slate-800" x-text="foto?.nombre"></h3>
                    <span class="precio rounded-lg px-2.5 py-1 text-sm font-black shrink-0" x-text="foto?.precio"></span>
                </div>
                <p class="text-sm text-slate-500 mt-1" x-text="foto?.descripcion"></p>
                <button type="button" @click="foto = null" class="mt-4 w-full h-11 rounded-xl bg-slate-100 font-bold text-slate-600">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        function carta() {
            const n = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            return {
                q: '', activa: null, foto: null,
                todo: @js(collect($categorias)->flatMap(fn ($c) => collect($c['productos'])->map(fn ($p) => $p['nombre'].' '.$p['descripcion'].' '.$c['nombre']))->values()),
                ver(t) { return !this.q.trim() || n(t).includes(n(this.q.trim())); },
                hay(lista) { return lista.some((t) => this.ver(t)); },
                ir(id) {
                    this.q = '';
                    this.$nextTick(() => document.getElementById('cat-' + id)?.scrollIntoView({ behavior: 'smooth' }));
                },
                // Marca la categoría que se está viendo y mueve su botón a la vista
                iniciar() {
                    const secciones = [...document.querySelectorAll('section[data-cat]')];
                    if (!secciones.length) return;
                    this.activa = +secciones[0].dataset.cat;
                    const obs = new IntersectionObserver((entradas) => {
                        entradas.filter((e) => e.isIntersecting).forEach((e) => {
                            this.activa = +e.target.dataset.cat;
                            const chip = this.$refs.chips?.querySelector(`[data-chip="${this.activa}"]`);
                            chip?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                        });
                    }, { rootMargin: '-80px 0px -70% 0px' });
                    secciones.forEach((s) => obs.observe(s));
                },
            };
        }
    </script>
</body>
</html>
