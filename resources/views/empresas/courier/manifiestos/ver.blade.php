@extends('layouts.app')
@section('title', 'Manifiesto N° '.$m->numero)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $kg = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $cargando = $m->estado === 'ABIERTO' && $sale;
        // Para recibir: los que vienen a mi agencia en este viaje
        $porRecibir = $envios->where('estado', 'EN_TRANSITO')->where('miDestino', true)->values()
            ->map(fn ($e) => ['id' => $e->env_id, 'codigo' => $e->codigo, 'piezas' => (int) $e->piezas, 'para' => $e->des_nombre]);
    @endphp

    @include('empresas.courier.partials.escaner_js')
    <div class="space-y-5" x-data="mezclar(lectorGuias(), manifiesto())" x-init="iniciar()">
        @include('empresas.courier.partials.nav')

        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 flex flex-wrap items-start gap-4">
            <span class="h-16 w-16 shrink-0 rounded-2xl bg-gradient-to-br from-orange-500 to-rose-600 text-white flex items-center justify-center text-2xl"><i class="fas {{ $medios[$m->medio][1] }}"></i></span>
            <div class="flex-1 min-w-60">
                <a href="{{ route('courier.manifiestos.index') }}" class="text-sm text-gray-500">← Manifiestos</a>
                <h1 class="text-2xl font-black text-gray-800">Manifiesto N° {{ $m->numero }} <span class="text-sm font-bold rounded-full px-2.5 py-1 bg-orange-100 text-orange-700 align-middle">{{ $estados[$m->estado] }}</span></h1>
                <p class="text-gray-600"><b>{{ \App\Support\Courier::etiquetaAgencia($origen) }}</b> <i class="fas fa-arrow-right text-gray-400"></i> <b>{{ $destino ? \App\Support\Courier::etiquetaAgencia($destino) : 'Varias agencias' }}</b></p>
                <p class="text-sm text-gray-500">{{ $medios[$m->medio][0] }}{{ $m->transportista ? ' · '.$m->transportista : '' }}{{ $m->vehiculo ? ' · '.$m->vehiculo : '' }}{{ $m->conductor ? ' · '.$m->conductor : '' }}
                    @if ($m->salida) · salida {{ \Carbon\Carbon::parse($m->salida)->format('d/m/Y H:i') }}@endif</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('courier.manifiestos.imprimir', $m->man_id) }}" target="_blank" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold flex items-center gap-1"><i class="fas fa-print"></i> Imprimir</a>
                @if ($cargando)
                    <button type="button" @click="accion(@js(route('courier.manifiestos.despachar', $m->man_id)), '¿Sale el viaje con los ' + cargados + ' envío(s) cargados?')" :disabled="!cargados"
                            class="h-10 px-4 rounded-xl bg-orange-600 text-white text-sm font-bold disabled:opacity-40"><i class="fas fa-truck-fast"></i> Despachar viaje</button>
                    <button type="button" @click="accion(@js(route('courier.manifiestos.anular', $m->man_id)), '¿Anular el manifiesto? Sus envíos vuelven a estar por despachar.')"
                            class="h-10 px-3 rounded-xl bg-white ring-1 ring-rose-200 text-rose-600 text-sm font-semibold"><i class="fas fa-ban"></i></button>
                @endif
            </div>
        </div>

        @if ($cargando)
            {{-- Cargar --}}
            <div class="grid lg:grid-cols-2 gap-5 items-start">
                <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                    <h2 class="font-black text-gray-800"><i class="fas fa-barcode text-orange-500"></i> Cargar escaneando</h2>
                    @include('empresas.courier.partials.escaner')
                    @if ($porCargar->isNotEmpty())
                        <p class="text-xs font-semibold uppercase text-gray-400 pt-2">Esperan salir ({{ $porCargar->count() }})</p>
                        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            @foreach ($porCargar as $e)
                                <div class="py-2 flex items-center gap-3 text-sm">
                                    <div class="flex-1"><b class="font-mono">{{ $e->codigo }}</b> → {{ $e->destino->ciudad ?? '?' }} · {{ $e->piezas }} pza<br>
                                        <span class="text-xs text-gray-400">Para {{ $e->des_nombre }}</span></div>
                                    <button type="button" @click="leer(@js($e->codigo))" class="h-9 px-3 rounded-lg bg-orange-50 text-orange-700 font-bold">Cargar</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-black text-gray-800 mb-2">En este viaje: <span x-text="cargados"></span> envío(s)</h2>
                    <div class="divide-y divide-gray-100">
                        @foreach ($envios as $e)
                            <div class="py-2 flex items-center gap-3 text-sm">
                                <div class="flex-1"><b class="font-mono">{{ $e->codigo }}</b> → {{ $e->destino->ciudad ?? '?' }} · {{ $e->piezas }} pza · {{ $kg($e->peso_cobrado) }} kg<br>
                                    <span class="text-xs text-gray-400">Para {{ $e->des_nombre }}</span></div>
                                <button type="button" @click="accion(@js(route('courier.manifiestos.quitar', [$m->man_id, $e->env_id])))" class="h-9 w-9 rounded-lg text-rose-500 hover:bg-rose-50" title="Quitar"><i class="fas fa-xmark"></i></button>
                            </div>
                        @endforeach
                        <p x-show="nuevos.length" class="py-2 text-sm text-emerald-700 font-semibold" x-text="'+ ' + nuevos.join(', ') + ' (actualiza para verlos en la lista)'"></p>
                    </div>
                </div>
            </div>
        @elseif ($recibe)
            {{-- Recibir bulto por bulto --}}
            <div class="grid lg:grid-cols-2 gap-5 items-start">
                <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                    <h2 class="font-black text-gray-800"><i class="fas fa-barcode text-orange-500"></i> Recibir: escanea cada bulto</h2>
                    <p class="text-sm text-gray-500">Cada etiqueta dice "Bulto 1 de 3". Al terminar, confirma: lo completo queda listo para entregar y se avisa lo que falta.</p>
                    @include('empresas.courier.partials.escaner')
                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="button" @click="recibir(false)" :disabled="ocupado || !leidosTotal" class="h-11 px-5 rounded-xl bg-emerald-600 text-white font-extrabold disabled:opacity-40">✓ Confirmar recepción</button>
                        <button type="button" @click="recibir(true)" :disabled="ocupado" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 font-bold text-sm">Recibir todo sin escanear</button>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-black text-gray-800 mb-2">Vienen a tu agencia</h2>
                    <div class="divide-y divide-gray-100">
                        <template x-for="e in RECIBIR" :key="e.id">
                            <div class="py-2 flex items-center gap-3 text-sm">
                                <span class="h-9 w-9 rounded-full flex items-center justify-center text-white" :class="(leidos[e.id] || 0) >= e.piezas ? 'bg-emerald-500' : ((leidos[e.id] || 0) ? 'bg-amber-500' : 'bg-gray-300')">
                                    <i class="fas" :class="(leidos[e.id] || 0) >= e.piezas ? 'fa-check' : 'fa-box'"></i></span>
                                <div class="flex-1"><b class="font-mono" x-text="e.codigo"></b><br><span class="text-xs text-gray-400" x-text="'Para ' + e.para"></span></div>
                                <b x-text="(leidos[e.id] || 0) + ' / ' + e.piezas"></b>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800 mb-2">{{ $envios->count() }} envío(s)</h2>
                <div class="divide-y divide-gray-100">
                    @foreach ($envios as $e)
                        <a href="{{ route('courier.ver', $e->env_id) }}" class="py-2 flex items-center gap-3 text-sm hover:bg-orange-50/40">
                            <div class="flex-1"><b class="font-mono">{{ $e->codigo }}</b> → {{ $e->destino->ciudad ?? '?' }} · {{ $e->piezas }} pza<br><span class="text-xs text-gray-400">Para {{ $e->des_nombre }}</span></div>
                            <span class="text-xs font-bold">{{ \App\Support\Courier::ESTADOS[$e->estado][0] }}{{ $e->bultos_recibidos !== null && $e->bultos_recibidos < $e->piezas ? ' · llegó '.$e->bultos_recibidos.' de '.$e->piezas : '' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <script>
        function manifiesto() {
            const RECIBIR = @json($porRecibir);
            const CLAVE = 'manifiesto-{{ $m->man_id }}-leidos';
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data || {}),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
            return {
                RECIBIR, ocupado: false, cargados: {{ $envios->count() }}, nuevos: [],
                // Bultos leídos por envío y cuáles (para no contar dos veces la misma etiqueta); se guardan por si se recarga la página
                leidos: {}, bultos: {},
                get leidosTotal() { return Object.values(this.leidos).reduce((s, n) => s + n, 0); },
                iniciar() { try { const g = JSON.parse(localStorage.getItem(CLAVE) || '{}'); this.leidos = g.leidos || {}; this.bultos = g.bultos || {}; } catch (e) {} },
                guardar() { try { localStorage.setItem(CLAVE, JSON.stringify({ leidos: this.leidos, bultos: this.bultos })); } catch (e) {} },
                async leer(texto) {
                    const g = this.guiaDe(texto);
                    if (!g) return this.avisar('Eso no es una guía: ' + String(texto).slice(0, 30), false);
                    @if ($cargando)
                        const d = await post(@json(route('courier.manifiestos.cargar', $m->man_id)), { leido: g.codigo });
                        if (!d.ok) return this.avisar(d.mensaje, false);
                        this.avisar('✔ ' + d.mensaje, true);
                        if (!d.repetido) { this.cargados++; this.nuevos.push(d.codigo); }
                    @else
                        const e = RECIBIR.find(x => x.codigo === g.codigo);
                        if (!e) return this.avisar(g.codigo + ' no viene a tu agencia en este manifiesto.', false);
                        const ya = this.bultos[e.id] || [];
                        const bulto = g.bulto || ((this.leidos[e.id] || 0) + 1);
                        if (ya.includes(bulto)) return this.avisar(g.codigo + ': el bulto ' + bulto + ' ya se leyó.', false);
                        if ((this.leidos[e.id] || 0) >= e.piezas) return this.avisar(g.codigo + ': ya están sus ' + e.piezas + ' bultos.', false);
                        this.bultos[e.id] = [...ya, bulto]; this.leidos[e.id] = (this.leidos[e.id] || 0) + 1; this.guardar();
                        this.avisar('✔ ' + g.codigo + ' · bulto ' + this.leidos[e.id] + ' de ' + e.piezas, true);
                    @endif
                },
                async recibir(todo) {
                    if (todo && !confirm('¿Recibir todos los envíos sin escanear?')) return;
                    if (!todo && RECIBIR.some(e => (this.leidos[e.id] || 0) < e.piezas)
                        && !confirm('Faltan bultos por escanear. Lo que no se leyó quedará como incompleto o "no llegó". ¿Confirmar?')) return;
                    this.ocupado = true;
                    try {
                        const d = await post(@json(route('courier.manifiestos.recibir', $m->man_id)), todo ? { sin_escanear: true } : { escaneados: this.leidos });
                        (window.tushpaAviso || alert)(d.mensaje || 'Error', d.ok ? 'ok' : 'error');
                        if (d.ok) { try { localStorage.removeItem(CLAVE); } catch (e) {} setTimeout(() => location.reload(), 1200); }
                    } finally { this.ocupado = false; }
                },
                async accion(url, pregunta) {
                    if (pregunta && !confirm(pregunta)) return;
                    const d = await post(url);
                    (window.tushpaAviso || alert)(d.mensaje || (d.ok ? 'Listo' : 'Error'), d.ok ? 'ok' : 'error');
                    if (d.ok) setTimeout(() => location.href = d.url || location.href, 700);
                },
            };
        }
    </script>
@endsection
