@extends('layouts.app')
@section('title', 'Requerimientos del día')
@section('content')
@use('App\Support\Requerimientos')
@php
    $chip = ['amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'slate' => 'bg-slate-100 text-slate-500 ring-slate-200', 'sky' => 'bg-sky-50 text-sky-700 ring-sky-200'];
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">📝 Requerimientos del día</h1>
            <p class="text-sm text-gray-500">Cocina o almacén pide lo que falta; {{ $atiende ? 'tú lo compras, marcas lo comprado y lo registras como compra' : 'el administrador lo compra' }}.</p>
        </div>
        @if (\App\Support\Requerimientos::sedes(auth()->user())->isNotEmpty())
            <a href="{{ route('requerimientos.enviar') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-sky-50 text-sky-800 ring-1 ring-sky-200 text-sm font-bold">🚚 Enviar a otra sede</a>
        @endif
        <a href="{{ route('requerimientos.produccion') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 text-sm font-bold">🍳 Producción del día</a>
        <a href="{{ route('requerimientos.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo requerimiento</a>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('requerimientos.index') }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ ! $estado ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">Todos</a>
        @foreach (Requerimientos::ESTADOS as $clave => [$nombre, $color])
            <a href="{{ route('requerimientos.index', ['estado' => $clave]) }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ $estado === $clave ? 'bg-gray-800 text-white ring-gray-800' : $chip[$color] }}">
                {{ $nombre }} <span class="opacity-70">{{ $conteo[$clave] ?? 0 }}</span></a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        @forelse ($requerimientos as $r)
            @php [$nombreEstado, $color] = Requerimientos::ESTADOS[$r->estado]; @endphp
            <a href="{{ route('requerimientos.show', $r->id) }}" class="flex flex-wrap items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50">
                <span class="font-mono font-bold text-indigo-700">{{ Requerimientos::codigo($r) }}</span>
                @if ($r->urgente)<span class="rounded-md bg-rose-600 px-1.5 text-xs font-black text-white">URGENTE</span>@endif
                @if ((int) $r->sucursal_origen === (int) $sucursal)<span class="rounded-md bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-800">🏬 te lo pide {{ $r->sede_pide }}</span>
                @elseif ($r->sucursal_origen)<span class="rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">pedido a {{ $r->sede_envia }}</span>@endif
                <span class="flex-1 min-w-48 text-sm text-gray-700">Para el <b>{{ \Carbon\Carbon::parse($r->fecha_para)->locale('es')->isoFormat('ddd D MMM') }}</b>
                    · {{ $r->lineas }} producto(s) · {{ $r->almacen }} · pidió {{ $r->pidio }}
                    <span class="text-gray-400">{{ \Carbon\Carbon::parse($r->created_at)->format('d/m H:i') }}</span></span>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $chip[$color] }}">{{ $nombreEstado }}</span>
            </a>
        @empty
            <p class="px-5 py-10 text-center text-gray-400">Aún no hay requerimientos.</p>
        @endforelse
    </div>
    {{ $requerimientos->links() }}

    {{-- Pedidos por Telegram: cada administrador vincula su Telegram y elige si le llegan los pedidos (gratis) --}}
    @if ($telegram ?? null)
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3" x-data="pedidosTelegram(@js($telegram->map(fn ($a) => ['id' => (int) $a->IdUsuario,
                'nombre' => trim((string) ($a->apeusu ?: $a->name)), 'vinculado' => (bool) $a->telegram_chat_id, 'recibir' => (bool) $a->telegram_pedidos, 'enlace' => null])->values()))">
            <div>
                <h2 class="font-extrabold text-gray-800">📲 Pedidos por Telegram</h2>
                <p class="text-sm text-gray-500">Cada pedido nuevo o cambiado llega completo (productos y cantidades) al Telegram de los administradores que lo activen.
                    Primero vincula el Telegram: abre el enlace en el celular de esa persona y toca <b>Iniciar</b>.</p>
            </div>
            <p x-show="aviso" x-text="aviso" x-cloak class="rounded-xl bg-indigo-50 text-indigo-800 px-3 py-2 text-sm"></p>
            <div class="divide-y divide-gray-100">
                <template x-for="a in admins" :key="a.id">
                    <div class="py-3 flex flex-wrap items-center gap-2">
                        <p class="flex-1 min-w-40 font-semibold text-gray-800" x-text="a.nombre"></p>
                        <template x-if="!a.vinculado && !a.enlace">
                            <button type="button" @click="vincular(a)" class="h-9 px-3 rounded-xl bg-sky-600 text-white text-sm font-bold">Vincular Telegram</button>
                        </template>
                        <template x-if="!a.vinculado && a.enlace">
                            <span class="flex flex-wrap items-center gap-2 text-sm">
                                <a :href="a.enlace" target="_blank" class="h-9 px-3 inline-flex items-center rounded-xl bg-sky-50 text-sky-700 font-bold">Abrir en Telegram ↗</a>
                                <span class="text-gray-500">esperando que toque <b>Iniciar</b>…</span>
                            </span>
                        </template>
                        <template x-if="a.vinculado">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-emerald-700">✔ Vinculado</span>
                                <label class="flex items-center gap-2 rounded-xl px-3 h-9 ring-1 text-sm font-semibold cursor-pointer" :class="a.recibir ? 'ring-emerald-300 bg-emerald-50 text-emerald-800' : 'ring-gray-200 text-gray-600'">
                                    <input type="checkbox" :checked="a.recibir" @change="recibir(a, $event.target.checked)" class="rounded text-emerald-600"> Recibir los pedidos</label>
                                <button type="button" @click="probar(a)" class="h-9 px-3 rounded-xl bg-gray-100 text-sm font-bold">Probar</button>
                                <button type="button" @click="quitar(a)" class="h-9 px-3 rounded-xl text-rose-600 text-sm font-bold hover:bg-rose-50">Quitar</button>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('pedidosTelegram', (admins) => ({
            admins, aviso: '',
            async post(url, datos = {}) {
                const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(datos) });
                return r.json();
            },
            ruta(a, accion) { return @js(url('requerimientos/telegram')) + '/' + a.id + '/' + accion; },
            async vincular(a) {
                const r = await this.post(this.ruta(a, 'enlace'));
                if (!r.ok) { this.aviso = r.mensaje; return; }
                a.enlace = r.enlace;
                window.open(r.enlace, '_blank');
                this.esperar(45);
            },
            // Cada 4 s pregunta si ya tocó Iniciar en el bot
            async esperar(vueltas) {
                const r = await this.post(@js(route('requerimientos.telegram.verificar')));
                this.admins.forEach(a => { if (!a.vinculado && (r.vinculados || []).includes(a.id)) { a.vinculado = true; a.enlace = null; a.recibir = true; this.recibir(a, true); this.aviso = '✔ ' + a.nombre + ' quedó vinculado: le llegarán los pedidos.'; } });
                if (this.admins.some(a => a.enlace && !a.vinculado) && vueltas > 1) setTimeout(() => this.esperar(vueltas - 1), 4000);
            },
            async recibir(a, valor) { a.recibir = valor; await this.post(this.ruta(a, 'recibir'), { recibir: valor }); },
            async probar(a) { const r = await this.post(this.ruta(a, 'probar')); this.aviso = r.mensaje; },
            async quitar(a) {
                if (!confirm('¿Quitar el Telegram de ' + a.nombre + '? Ya no le llegarán los pedidos.')) return;
                await this.post(this.ruta(a, 'quitar'));
                a.vinculado = false; a.recibir = false; a.enlace = null;
            },
        }));
    });
</script>
@endpush
