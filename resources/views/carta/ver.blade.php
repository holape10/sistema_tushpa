@php
    $soles = fn ($n) => 'S/ '.number_format($n, 2);
    // "CHILCANO DE MARACUYÁ" → "Chilcano de Maracuyá"
    $titulo = fn ($t) => preg_replace_callback('/(?<=\s)(De|Del|La|Las|Los|El|Con|Y|A|Al|En|Por)(?=\s)/u', fn ($m) => mb_strtolower($m[1]), \Illuminate\Support\Str::title(mb_strtolower($t)));
    $total = collect($categorias)->sum(fn ($c) => count($c['productos']));
    // Se arma un pedido: delivery (carta general) o desde el QR de la mesa
    $pide = $delivery || $pedidoMesa;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $color }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($delivery)<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">@endif
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

    @if ($pedidoMesa)
    {{-- ============ Modo mesa: como la pantalla del mozo (categorías, botones y tocar para sumar) ============ --}}
    <header class="sticky top-0 z-30 hero text-white shadow-lg">
        <div class="max-w-3xl mx-auto px-3 pt-3 pb-2">
            <div class="flex items-center gap-2.5">
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="" class="h-11 w-11 shrink-0 rounded-xl object-contain bg-white p-0.5">
                @else
                    <span class="h-11 w-11 shrink-0 rounded-xl bg-white/20 flex items-center justify-center text-xl font-black">{{ mb_substr($nombre, 0, 1) }}</span>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="font-black leading-tight truncate">{{ $nombre }}</p>
                    <p class="text-xs font-bold text-white/85">🍽️ {{ $mesa }}</p>
                </div>
            </div>
            {{-- Ver lo consumido (solo lectura), llamar al mozo y pedir la cuenta --}}
            <div class="grid grid-cols-3 gap-2 mt-2.5">
                <button type="button" @click="verConsumo()" class="h-10 rounded-xl bg-white/15 ring-1 ring-white/30 text-xs font-black active:scale-95 transition">📋 Mi consumo</button>
                <button type="button" @click="enviarMesa('MOZO')" :disabled="enviando" class="h-10 rounded-xl bg-white/15 ring-1 ring-white/30 text-xs font-black active:scale-95 transition">🙋 Mozo</button>
                <button type="button" @click="enviarMesa('CUENTA')" :disabled="enviando" class="h-10 rounded-xl bg-white/15 ring-1 ring-white/30 text-xs font-black active:scale-95 transition">🧾 Cuenta</button>
            </div>
            <label class="relative block mt-2.5">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" x-model="q" placeholder="Buscar plato o bebida…" class="w-full h-10 pl-9 pr-3 rounded-xl border-0 text-sm text-slate-800 placeholder-slate-400">
            </label>
            {{-- Una categoría a la vez, como el mozo --}}
            @if (count($categorias) > 1)
                <div class="flex gap-2 overflow-x-auto no-scroll mt-2.5 pb-1">
                    @foreach ($categorias as $c)
                        <button type="button" @click="catMesa = {{ $c['id'] }}; q = ''"
                                :class="catMesa === {{ $c['id'] }} && !q.trim() ? 'bg-white text-slate-900 shadow' : 'bg-white/15 text-white'"
                                class="shrink-0 h-9 px-4 rounded-full text-sm font-bold whitespace-nowrap transition">{{ $titulo($c['nombre']) }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-3 pt-3 pb-4">
        <p x-show="!carrito.length && !q.trim()" class="text-xs text-slate-500 mb-2.5">👆 Toca un plato para agregarlo. Al terminar, toca <b>Ver mi pedido</b> y envíalo al mozo.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
            @foreach ($categorias as $c)
                @foreach ($c['productos'] as $p)
                    <button type="button" x-show="enMesa({{ $c['id'] }}, @js($p['nombre'].' '.$p['descripcion'].' '.$c['nombre']))" @click="agregar({{ $p['id'] }})"
                            :class="cantidadDe({{ $p['id'] }}) ? 'ring-2 ring-[color:var(--acento)]' : 'ring-1 ring-slate-200'"
                            class="relative flex min-h-[104px] flex-col rounded-2xl bg-white p-3 text-left shadow-sm active:scale-[.97] transition-transform select-none">
                        <span class="flex items-start gap-2">
                            <span class="flex-1 min-w-0 text-sm font-bold leading-snug text-slate-800 line-clamp-3">{{ $titulo($p['nombre']) }}</span>
                            @if ($p['img'])
                                <img src="{{ $p['img'] }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-xl object-cover bg-slate-100"
                                     @click.stop="foto = @js(['img' => $p['img'], 'nombre' => $p['nombre'], 'descripcion' => $p['descripcion'], 'precio' => $soles($p['precio'])])">
                            @endif
                        </span>
                        <span class="mt-auto pt-1.5 flex items-center gap-1.5">
                            <span class="text-base font-black acento">{{ $soles($p['precio']) }}</span>
                            @if ($p['precio'] < $p['normal'])<span class="text-[10px] text-slate-400 line-through">{{ $soles($p['normal']) }}</span>@endif
                        </span>
                        @if ($p['presentaciones'])
                            <span class="mt-1 self-start rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 line-clamp-1">📦 {{ collect($p['presentaciones'])->pluck('nombre')->implode(' · ') }}</span>
                        @endif
                        {{-- Cuántos van y quitar uno --}}
                        <span x-show="cantidadDe({{ $p['id'] }})" x-cloak x-text="cantidadDe({{ $p['id'] }})"
                              class="absolute -top-2 -right-2 min-w-[28px] h-7 px-1.5 rounded-full bg-acento text-white text-sm font-black flex items-center justify-center shadow"></span>
                        <span x-show="cantidadDe({{ $p['id'] }})" x-cloak @click.stop="quitarUno({{ $p['id'] }})" role="button" aria-label="Quitar uno"
                              class="absolute bottom-2 right-2 w-8 h-8 rounded-full bg-slate-100 text-slate-700 text-lg font-black flex items-center justify-center active:scale-90">−</span>
                    </button>
                @endforeach
            @endforeach
        </div>
        <p x-show="!hayEnMesa()" x-cloak class="text-center text-slate-500 py-10">
            <span x-show="q.trim()">No encontramos “<span x-text="q"></span>”. Prueba con otra palabra.</span>
            <span x-show="!q.trim()">Aún no hay productos en esta categoría.</span>
        </p>
    </main>
    @else
    {{-- Portada --}}
    <header class="hero relative overflow-hidden text-white rounded-b-[2rem] shadow-lg isolate">
        <x-kene-adorno />
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
            @if ($pedidoMesa)
                {{-- Desde la mesa: llamar al mozo o pedir la cuenta --}}
                <div class="grid grid-cols-2 gap-2 mt-4 max-w-sm mx-auto">
                    <button type="button" @click="enviarMesa('MOZO')" :disabled="enviando" class="h-12 rounded-2xl bg-white/15 ring-1 ring-white/30 font-black text-sm active:scale-95 transition">🙋 Llamar al mozo</button>
                    <button type="button" @click="enviarMesa('CUENTA')" :disabled="enviando" class="h-12 rounded-2xl bg-white/15 ring-1 ring-white/30 font-black text-sm active:scale-95 transition">🧾 Pedir la cuenta</button>
                </div>
                <button type="button" @click="verConsumo()" class="mt-2 h-11 w-full max-w-sm mx-auto block rounded-2xl bg-white/15 ring-1 ring-white/30 font-black text-sm active:scale-95 transition">📋 Ver lo que voy consumiendo</button>
                <p class="text-white/80 text-xs mt-2">Toca <b>+ Agregar</b> en lo que quieras: tu pedido le llega al mozo.</p>
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
                            <div class="shrink-0 flex flex-col items-end gap-2">
                                @if ($p['img'])
                                    <img src="{{ $p['img'] }}" alt="" loading="lazy" class="w-24 h-24 rounded-xl object-cover bg-slate-100">
                                @else
                                    <span class="precio rounded-lg px-2.5 py-1 text-sm font-black">{{ $soles($p['precio']) }}</span>
                                @endif
                                @if ($pide)
                                    <button type="button" @click.stop="agregar({{ $p['id'] }})"
                                            class="h-9 px-3 rounded-xl bg-acento text-white text-sm font-black shadow active:scale-95 transition">
                                        <span x-show="!cantidadDe({{ $p['id'] }})">+ Agregar</span>
                                        <span x-show="cantidadDe({{ $p['id'] }})" x-cloak x-text="'+ Otro (' + cantidadDe({{ $p['id'] }}) + ')'"></span>
                                    </button>
                                @endif
                            </div>
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
    @endif

    <footer class="text-center text-xs text-slate-400 {{ $pide ? 'pb-28' : 'pb-8' }} px-4 space-y-1">
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

    @if ($pide)
        {{-- Barra del pedido --}}
        <div x-show="carrito.length && !abierto" x-cloak x-transition class="fixed bottom-0 inset-x-0 z-30 p-3 pb-[max(.75rem,env(safe-area-inset-bottom))]">
            <button type="button" @click="abrirPedido()" class="max-w-3xl mx-auto w-full h-14 rounded-2xl bg-acento text-white shadow-2xl flex items-center px-5 font-black">
                <span class="w-8 h-8 rounded-full bg-white/25 flex items-center justify-center text-sm" x-text="unidades()"></span>
                <span class="flex-1 text-left ml-3">{{ $pedidoMesa ? '🍽️ Ver mi pedido' : '🛵 Ver mi pedido delivery' }}</span>
                <span x-text="soles(subtotal())"></span>
            </button>
        </div>

        {{-- Elegir presentación --}}
        <div x-show="eligiendo" x-cloak @click="eligiendo = null" class="fixed inset-0 z-40 bg-slate-900/60 flex items-end sm:items-center justify-center p-4">
            <div @click.stop class="bg-white rounded-3xl w-full max-w-md p-5 space-y-2">
                <p class="font-black text-slate-800" x-text="eligiendo && eligiendo.nombre"></p>
                <template x-for="op in (eligiendo ? eligiendo.opciones : [])" :key="op.id ?? 0">
                    <button type="button" @click="sumar(eligiendo.id, op.id); eligiendo = null" class="w-full flex justify-between items-center h-12 px-4 rounded-xl ring-1 ring-slate-200 font-bold text-slate-700">
                        <span x-text="op.nombre"></span><span class="acento" x-text="soles(op.precio)"></span>
                    </button>
                </template>
            </div>
        </div>
    @endif

    @if ($pedidoMesa)
        {{-- Lo que pidió desde la mesa y si el mozo ya lo aceptó --}}
        <div x-show="solicitudes.length" x-cloak class="fixed top-3 inset-x-0 z-30 px-3 pointer-events-none">
            <div class="max-w-md mx-auto space-y-2">
                <template x-for="s in solicitudes.slice(-3)" :key="s.token">
                    <div class="pointer-events-auto rounded-2xl shadow-xl px-4 py-3 text-sm font-bold flex items-center gap-3"
                         :class="{ 'bg-amber-50 text-amber-900 ring-1 ring-amber-200': s.estado === 'PENDIENTE', 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200': s.estado === 'ACEPTADA' || s.estado === 'ATENDIDA', 'bg-rose-50 text-rose-900 ring-1 ring-rose-200': s.estado === 'RECHAZADA' || s.estado === 'VENCIDA' }">
                        <span class="text-2xl" x-text="s.estado === 'PENDIENTE' ? '⏳' : ((s.estado === 'ACEPTADA' || s.estado === 'ATENDIDA') ? '✅' : '❌')"></span>
                        <span class="flex-1" x-text="textoSolicitud(s)"></span>
                        <button type="button" @click="quitarSolicitud(s.token)" class="text-xl leading-none opacity-50">&times;</button>
                    </div>
                </template>
            </div>
        </div>

        {{-- Lo que va consumiendo la mesa: solo para ver (lo aceptado no se cambia desde aquí) --}}
        <div x-show="consumo" x-cloak class="fixed inset-0 z-40 bg-slate-900/60 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="consumo = null">
            <div @click.outside="consumo = null" class="bg-slate-50 w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white px-5 py-4 flex items-center border-b">
                    <h2 class="flex-1 text-lg font-black text-slate-800">📋 Mi consumo · {{ $mesa }}</h2>
                    <button type="button" @click="consumo = null" class="text-3xl leading-none text-slate-400">&times;</button>
                </div>
                <div class="p-4 space-y-3">
                    <p x-show="consumo && consumo.cargando" class="text-center text-sm text-slate-500">Cargando…</p>
                    <template x-if="consumo && !consumo.cargando">
                        <div class="space-y-3">
                            <div x-show="!consumo.items.length" class="bg-white rounded-2xl ring-1 ring-slate-200 p-5 text-center text-sm text-slate-500">
                                Todavía no hay nada confirmado en tu mesa. Lo que pidas aparece aquí cuando el mozo lo acepte.</div>
                            <div x-show="consumo.items.length" class="bg-white rounded-2xl ring-1 ring-slate-200 divide-y">
                                <template x-for="(it, i) in consumo.items" :key="i">
                                    <div class="px-4 py-2.5 flex items-center gap-3 text-sm">
                                        <span class="w-10 shrink-0 font-black text-slate-700" x-text="Number(it.cantidad) + '×'"></span>
                                        <span class="flex-1 text-slate-800" x-text="it.nombre"></span>
                                        <span class="font-bold text-slate-700" x-text="soles(it.total)"></span>
                                    </div>
                                </template>
                            </div>
                            <div x-show="consumo.items.length" class="bg-white rounded-2xl ring-1 ring-slate-200 p-4 flex justify-between text-lg font-black text-slate-900"><span>Total</span><span x-text="soles(consumo.total)"></span></div>
                            <p class="text-xs text-slate-500 text-center">Solo para ver. Si algo no está bien, llama al mozo.</p>
                            <button type="button" @click="enviarMesa('CUENTA'); consumo = null" :disabled="enviando || !consumo.items.length" class="w-full h-12 rounded-2xl bg-acento text-white font-black disabled:opacity-40">🧾 Pedir la cuenta</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Mi pedido en la mesa --}}
        <div x-show="abierto" x-cloak class="fixed inset-0 z-40 bg-slate-900/60 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="abierto = false">
            <div class="bg-slate-50 w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white px-5 py-4 flex items-center border-b">
                    <h2 class="flex-1 text-lg font-black text-slate-800">🍽️ Mi pedido · {{ $mesa }}</h2>
                    <button type="button" @click="abierto = false" class="text-3xl leading-none text-slate-400">&times;</button>
                </div>
                <form @submit.prevent="enviarMesa('PEDIDO')" class="p-4 space-y-3">
                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 divide-y">
                        <template x-for="(it, i) in carrito" :key="it.clave">
                            <div class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-slate-800 leading-tight" x-text="it.nombre"></p>
                                        <p class="text-xs text-slate-500" x-text="soles(it.precio) + ' c/u'"></p>
                                    </div>
                                    <button type="button" @click="cambiar(i, -1)" class="w-8 h-8 rounded-full bg-slate-100 font-black">−</button>
                                    <span class="w-6 text-center font-black" x-text="it.cantidad"></span>
                                    <button type="button" @click="cambiar(i, 1)" class="w-8 h-8 rounded-full bg-slate-100 font-black">+</button>
                                </div>
                                <input x-model="it.nota" maxlength="80" placeholder="Indicación (ej.: sin cebolla)" class="mt-2 w-full h-9 rounded-lg border-slate-200 text-sm">
                            </div>
                        </template>
                    </div>
                    <input x-model="nombreMesa" maxlength="60" placeholder="Tu nombre (opcional)" class="w-full h-11 rounded-xl border-slate-300 text-sm" autocomplete="given-name">
                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-4 flex justify-between text-lg font-black text-slate-900"><span>Total</span><span x-text="soles(subtotal())"></span></div>
                    <p class="text-xs text-slate-500 text-center">El mozo confirma tu pedido y lo pasa a cocina. Pagas al final, como siempre.</p>
                    <p x-show="error" x-text="error" class="text-sm text-rose-600 font-semibold"></p>
                    <button :disabled="enviando" class="w-full h-14 rounded-2xl bg-acento text-white text-lg font-black shadow-lg disabled:opacity-50"
                            x-text="enviando ? 'Enviando…' : 'Enviar pedido · ' + soles(subtotal())"></button>
                </form>
            </div>
        </div>
    @endif

    @if ($delivery)
        {{-- Mi pedido: productos y datos de entrega --}}
        <div x-show="abierto" x-cloak class="fixed inset-0 z-40 bg-slate-900/60 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="abierto = false">
            <div class="bg-slate-50 w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white px-5 py-4 flex items-center border-b">
                    <h2 class="flex-1 text-lg font-black text-slate-800">🛵 Mi pedido delivery</h2>
                    <button type="button" @click="abierto = false" class="text-3xl leading-none text-slate-400">&times;</button>
                </div>
                <form @submit.prevent="enviar()" class="p-4 space-y-3">
                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 divide-y">
                        <template x-for="(it, i) in carrito" :key="it.clave">
                            <div class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-slate-800 leading-tight" x-text="it.nombre"></p>
                                        <p class="text-xs text-slate-500" x-text="soles(it.precio) + ' c/u'"></p>
                                    </div>
                                    <button type="button" @click="cambiar(i, -1)" class="w-8 h-8 rounded-full bg-slate-100 font-black">−</button>
                                    <span class="w-6 text-center font-black" x-text="it.cantidad"></span>
                                    <button type="button" @click="cambiar(i, 1)" class="w-8 h-8 rounded-full bg-slate-100 font-black">+</button>
                                </div>
                                <input x-model="it.nota" maxlength="80" placeholder="Indicación (ej.: sin cebolla)" class="mt-2 w-full h-9 rounded-lg border-slate-200 text-sm">
                            </div>
                        </template>
                    </div>

                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-4 space-y-3 text-sm">
                        <p class="font-black text-slate-800">¿A dónde te lo llevamos?</p>
                        <input x-model="datos.cliente" required minlength="2" maxlength="100" placeholder="Tu nombre" class="w-full h-11 rounded-xl border-slate-300" autocomplete="name">
                        <input x-model="datos.telefono" required inputmode="tel" maxlength="15" placeholder="Tu WhatsApp (ej.: 987654321)" class="w-full h-11 rounded-xl border-slate-300" autocomplete="tel">
                        <input x-model="datos.direccion" required minlength="5" maxlength="200" placeholder="Dirección (calle, número, distrito)" class="w-full h-11 rounded-xl border-slate-300" autocomplete="street-address">
                        <input x-model="datos.referencia" maxlength="200" placeholder="Referencia (ej.: frente al parque, puerta verde)" class="w-full h-11 rounded-xl border-slate-300">
                        <div class="space-y-2">
                            <button type="button" @click="miUbicacion()" class="w-full h-11 rounded-xl ring-2 font-bold" :class="datos.lat ? 'ring-emerald-500 text-emerald-700 bg-emerald-50' : 'ring-slate-300 text-slate-700'"
                                    x-text="ubicando ? 'Buscando tu ubicación…' : (datos.lat ? '✓ Ubicación marcada (puedes mover el pin)' : '📍 Marcar mi ubicación en el mapa')"></button>
                            <div x-show="mapaVisible" id="mapa-cliente" class="h-56 rounded-xl overflow-hidden"></div>
                            <p x-show="mapaVisible" class="text-xs text-slate-500">Toca el mapa o arrastra el pin hasta tu puerta.</p>
                            <p x-show="errorUbicacion" x-text="errorUbicacion" class="text-xs text-rose-600"></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-4 space-y-2 text-sm">
                        <p class="font-black text-slate-800">¿Cómo pagarás al recibir?</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($mediosPago as $clave => $nombreMedio)
                                <label class="flex items-center gap-2 h-11 px-3 rounded-xl ring-1 cursor-pointer" :class="datos.pago === @js($clave) ? 'ring-2 ring-[color:var(--acento)] bg-white font-bold' : 'ring-slate-200'">
                                    <input type="radio" value="{{ $clave }}" x-model="datos.pago" class="text-[color:var(--acento)]"> {{ $nombreMedio }}
                                </label>
                            @endforeach
                        </div>
                        <label x-show="datos.pago === 'EFECTIVO'" class="block">¿Con cuánto pagas? (para llevarte el vuelto)
                            <input type="number" x-model="datos.paga_con" min="0" step="0.10" placeholder="Ej.: 100" class="mt-1 w-full h-11 rounded-xl border-slate-300"></label>
                        <textarea x-model="datos.nota" maxlength="150" rows="2" placeholder="Nota para el local (opcional)" class="w-full rounded-xl border-slate-300"></textarea>
                    </div>

                    <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-4 text-sm space-y-1">
                        <div class="flex justify-between"><span>Productos</span><span x-text="soles(subtotal())"></span></div>
                        <div class="flex justify-between"><span>Envío</span>
                            <span x-text="envio.costo !== null ? soles(envio.costo) + (envio.km !== null ? ' (' + envio.km.toFixed(1) + ' km)' : '') : (conTarifas ? 'Marca tu ubicación' : 'Por confirmar')"></span></div>
                        <div class="flex justify-between text-lg font-black text-slate-900 pt-1 border-t"><span>Total</span><span x-text="soles(subtotal() + (envio.costo || 0))"></span></div>
                        @if ((float) $negocio->delivery_minimo > 0)
                            <p class="text-xs text-slate-500">Pedido mínimo: {{ $soles($negocio->delivery_minimo) }}</p>
                        @endif
                        <p x-show="envio.fuera" class="text-rose-600 font-semibold">Tu ubicación está fuera de nuestra zona de reparto.</p>
                    </div>

                    <p x-show="error" x-text="error" class="text-sm text-rose-600 font-semibold"></p>
                    <button :disabled="enviando || envio.fuera" class="w-full h-14 rounded-2xl bg-acento text-white text-lg font-black shadow-lg disabled:opacity-50"
                            x-text="enviando ? 'Enviando tu pedido…' : 'Pedir delivery · ' + soles(subtotal() + (envio.costo || 0))"></button>
                </form>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    @endif

    <script>
        function carta() {
            const n = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            const PRODUCTOS = @js($pide ? collect($categorias)->flatMap(fn ($c) => $c['productos'])->mapWithKeys(fn ($p) => [$p['id'] => ['nombre' => $p['nombre'], 'precio' => $p['precio'], 'presentaciones' => $p['presentaciones']]]) : []);
            const LOCAL = @js($local ?? null);
            // Pedido desde la mesa: { id, k } (el QR firmado de la mesa)
            const MESA = @js($pedidoMesa);
            const CLAVE = 'carta-pedido-{{ $negocio->id_empresa_negocio }}' + (MESA ? '-mesa-' + MESA.id : '');
            const leer = (k) => { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } };
            const guardar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
            let mapa = null, pin = null;
            return {
                // Pedido delivery
                carrito: (leer(CLAVE) || []).filter(i => PRODUCTOS[i.id]), abierto: false, eligiendo: null, enviando: false, error: '', ubicando: false, errorUbicacion: '', mapaVisible: false,
                conTarifas: @js((bool) ($conTarifas ?? false)), envio: { costo: null, km: null, fuera: false },
                datos: Object.assign({ cliente: '', telefono: '', direccion: '', referencia: '', lat: null, lng: null, pago: 'EFECTIVO', paga_con: '', nota: '' }, leer(CLAVE + '-datos') || {}, { nota: '', paga_con: '' }),
                soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
                unidades() { return this.carrito.reduce((s, i) => s + i.cantidad, 0); },
                subtotal() { return Math.round(this.carrito.reduce((s, i) => s + i.precio * i.cantidad, 0) * 100) / 100; },
                cantidadDe(id) { return this.carrito.filter(i => i.id === id).reduce((s, i) => s + i.cantidad, 0); },
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
                    else this.carrito.push({ clave, id, presentacion, nombre: p.nombre + (pr ? ' (' + pr.nombre + ')' : ''), precio: pr ? pr.precio : p.precio, cantidad: 1, nota: '' });
                    guardar(CLAVE, this.carrito);
                },
                cambiar(i, n) {
                    this.carrito[i].cantidad += n;
                    if (this.carrito[i].cantidad <= 0) this.carrito.splice(i, 1);
                    if (!this.carrito.length) this.abierto = false;
                    guardar(CLAVE, this.carrito);
                },
                abrirPedido() {
                    this.abierto = true;
                    this.error = '';
                    if (!MESA && this.datos.lat) this.$nextTick(() => this.mostrarMapa(this.datos.lat, this.datos.lng));
                },
                miUbicacion() {
                    this.errorUbicacion = '';
                    if (!navigator.geolocation) { this.mostrarMapa(null, null); return; }
                    this.ubicando = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => { this.ubicando = false; this.mostrarMapa(pos.coords.latitude, pos.coords.longitude); },
                        () => { this.ubicando = false; this.errorUbicacion = 'No pudimos leer tu ubicación. Marca tu puerta tocando el mapa.'; this.mostrarMapa(null, null); },
                        { enableHighAccuracy: true, timeout: 15000 }
                    );
                },
                mostrarMapa(lat, lng) {
                    this.mapaVisible = true;
                    setTimeout(() => {
                        const centro = lat !== null ? [lat, lng] : (LOCAL ? [LOCAL.lat, LOCAL.lng] : [-9.19, -75.02]);
                        if (!mapa) {
                            mapa = L.map('mapa-cliente').setView(centro, lat !== null || LOCAL ? 16 : 5);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapa);
                            mapa.on('click', e => this.ponerPin(e.latlng.lat, e.latlng.lng));
                        } else {
                            mapa.invalidateSize();
                            mapa.setView(centro, 16);
                        }
                        if (lat !== null) this.ponerPin(lat, lng);
                    }, 80);
                },
                ponerPin(lat, lng) {
                    if (!pin) {
                        pin = L.marker([lat, lng], { draggable: true }).addTo(mapa);
                        pin.on('dragend', () => { const c = pin.getLatLng(); this.ponerPin(c.lat, c.lng); });
                    } else {
                        pin.setLatLng([lat, lng]);
                    }
                    this.datos.lat = lat;
                    this.datos.lng = lng;
                    this.calcularEnvio();
                },
                calcularEnvio() {
                    fetch(@js(route('carta.envio', $negocio->id_empresa_negocio)), { method: 'POST', headers: this.cabeceras(), body: JSON.stringify({ lat: this.datos.lat, lng: this.datos.lng }) })
                        .then(r => r.json()).then(d => this.envio = d).catch(() => {});
                },
                cabeceras() {
                    return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content };
                },
                enviar() {
                    this.error = '';
                    if (this.conTarifas && !this.datos.lat) { this.error = 'Marca tu ubicación en el mapa para calcular el envío.'; return; }
                    this.enviando = true;
                    const cuerpo = Object.assign({}, this.datos, { items: this.carrito.map(i => ({ id: i.id, cantidad: i.cantidad, presentacion: i.presentacion, nota: i.nota })) });
                    if (cuerpo.paga_con === '') delete cuerpo.paga_con;
                    fetch(@js(route('carta.pedir', $negocio->id_empresa_negocio)), { method: 'POST', headers: this.cabeceras(), body: JSON.stringify(cuerpo) })
                        .then(async r => {
                            const d = await r.json().catch(() => ({}));
                            if (r.status === 419) { this.error = 'La página estuvo abierta mucho tiempo. Recárgala y vuelve a enviar (tu pedido sigue guardado).'; return; }
                            if (r.status === 429) { this.error = 'Muchos intentos seguidos. Espera unos minutos.'; return; }
                            if (!r.ok) { this.error = d.message || 'No se pudo enviar el pedido.'; return; }
                            guardar(CLAVE, []);
                            guardar(CLAVE + '-datos', { cliente: this.datos.cliente, telefono: this.datos.telefono, direccion: this.datos.direccion, referencia: this.datos.referencia, lat: this.datos.lat, lng: this.datos.lng, pago: this.datos.pago });
                            location.href = d.seguimiento;
                        })
                        .catch(() => this.error = 'Sin conexión. Revisa tu internet e intenta de nuevo.')
                        .finally(() => this.enviando = false);
                },

                // Desde la mesa: pedido, llamar al mozo o pedir la cuenta; el cliente ve si ya lo atendieron
                nombreMesa: leer('carta-nombre') || '',
                solicitudes: (leer(CLAVE + '-sol') || []).filter(s => Date.now() - s.hora < 3 * 3600 * 1000),
                enviarMesa(tipo) {
                    if (!MESA || this.enviando) return;
                    this.error = '';
                    this.enviando = true;
                    const cuerpo = { k: MESA.k, tipo, nombre: this.nombreMesa };
                    if (tipo === 'PEDIDO') cuerpo.items = this.carrito.map(i => ({ id: i.id, cantidad: i.cantidad, presentacion: i.presentacion, nota: i.nota }));
                    fetch(@js(url('/carta/'.$negocio->id_empresa_negocio.'/mesa')) + '/' + MESA.id, { method: 'POST', headers: this.cabeceras(), body: JSON.stringify(cuerpo) })
                        .then(async r => {
                            const d = await r.json().catch(() => ({}));
                            const aviso = r.status === 419 ? 'La página estuvo abierta mucho tiempo. Recárgala y vuelve a intentarlo.'
                                : (r.status === 429 ? 'Muchos intentos seguidos. Espera un momento.' : (!r.ok ? (d.message || 'No se pudo enviar. Llama al mozo, por favor.') : null));
                            if (aviso) { if (tipo === 'PEDIDO') this.error = aviso; else alert(aviso); return; }
                            guardar('carta-nombre', this.nombreMesa);
                            if (!this.solicitudes.some(s => s.token === d.token)) this.solicitudes.push({ token: d.token, tipo, estado: 'PENDIENTE', total: d.total, hora: Date.now() });
                            guardar(CLAVE + '-sol', this.solicitudes);
                            if (tipo === 'PEDIDO') { this.carrito = []; guardar(CLAVE, []); this.abierto = false; }
                        })
                        .catch(() => { const m = 'Sin conexión. Revisa tu internet e intenta de nuevo.'; if (tipo === 'PEDIDO') this.error = m; else alert(m); })
                        .finally(() => this.enviando = false);
                },
                textoSolicitud(s) {
                    const t = { PEDIDO: 'Tu pedido' + (s.total ? ' de ' + this.soles(s.total) : ''), MOZO: 'Llamaste al mozo', CUENTA: 'Pediste la cuenta' }[s.tipo];
                    if (s.estado === 'PENDIENTE') return t + ': ' + (s.tipo === 'PEDIDO' ? 'esperando que el mozo lo confirme…' : 'ya le avisamos…');
                    if (s.estado === 'ACEPTADA') return t + (s.corregido ? ': el mozo lo ajustó y ya va a cocina 👨‍🍳 (mira 📋 tu consumo)' : ': confirmado, ya va a cocina 👨‍🍳');
                    if (s.estado === 'ATENDIDA') return t + (s.tipo === 'CUENTA' ? ': ya te la llevan' : ': ya viene');
                    if (s.estado === 'RECHAZADA') return t + ': no se pudo aceptar' + (s.motivo ? ' (' + s.motivo.toLowerCase() + ')' : '') + '. Habla con el mozo.';
                    return t + ': no se atendió a tiempo. Llama al mozo, por favor.';
                },
                consumo: null,
                verConsumo() {
                    if (!MESA) return;
                    this.consumo = { cargando: true, items: [], total: 0 };
                    fetch(@js(url('/carta/'.$negocio->id_empresa_negocio.'/mesa')) + '/' + MESA.id + '/consumo?k=' + encodeURIComponent(MESA.k), { headers: { Accept: 'application/json' } })
                        .then(r => r.ok ? r.json() : Promise.reject())
                        .then(d => { if (this.consumo) this.consumo = { cargando: false, items: d.items || [], total: d.total || 0 }; })
                        .catch(() => { this.consumo = null; alert('No se pudo cargar tu consumo. Revisa tu internet.'); });
                },
                quitarSolicitud(token) {
                    this.solicitudes = this.solicitudes.filter(s => s.token !== token);
                    guardar(CLAVE + '-sol', this.solicitudes);
                },
                seguirSolicitudes() {
                    setInterval(() => {
                        if (document.hidden) return;
                        this.solicitudes.filter(s => s.estado === 'PENDIENTE').forEach(s => {
                            fetch(@js(url('/carta/'.$negocio->id_empresa_negocio.'/solicitud')) + '/' + s.token, { headers: { Accept: 'application/json' } })
                                .then(r => r.ok ? r.json() : null).then(d => {
                                    if (!d || d.estado === s.estado) return;
                                    Object.assign(s, { estado: d.estado, motivo: d.motivo, corregido: d.total !== null && s.total !== null && Math.abs(d.total - s.total) > 0.001, total: d.total ?? s.total });
                                    if (navigator.vibrate) navigator.vibrate(200);
                                    guardar(CLAVE + '-sol', this.solicitudes);
                                }).catch(() => {});
                        });
                    }, 6000);
                },

                // Modo mesa: una categoría a la vez (como el mozo); al buscar se ve todo lo que coincide
                catMesa: @js(collect($categorias)->first()['id'] ?? null),
                todoMesa: @js(collect($categorias)->flatMap(fn ($c) => collect($c['productos'])->map(fn ($p) => [$c['id'], $p['nombre'].' '.$p['descripcion'].' '.$c['nombre']]))->values()),
                enMesa(cat, t) { return this.q.trim() ? this.ver(t) : cat === this.catMesa; },
                hayEnMesa() { return this.todoMesa.some(([c, t]) => this.enMesa(c, t)); },
                quitarUno(id) {
                    const i = this.carrito.map(x => x.id).lastIndexOf(id);
                    if (i >= 0) this.cambiar(i, -1);
                },

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
                    if (MESA) this.seguirSolicitudes();
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
