@extends('layouts.app')
@section('title', 'Embarque')
@section('content')
    <div class="space-y-4" x-data="embarque(@js($elegida->id ?? null), @js(collect($pasajeros)->map(fn ($p) => ['codigo' => $p->codigo, 'asiento' => $p->asiento, 'nombre' => $p->nombre, 'doc' => $p->doc, 'subio' => (bool) $p->embarcado_at])->values()))">
        @include('empresas.transportes.partials.nav')

        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <label class="text-xs font-semibold text-gray-500 flex-1 min-w-[14rem]">Salida
                    <select name="salida" onchange="this.form.submit()" class="mt-1 w-full h-11 rounded-lg border-gray-300 text-sm">
                        @forelse ($salidas as $s)
                            <option value="{{ $s->id }}" @selected($elegida && $elegida->id === $s->id)>{{ \Carbon\Carbon::parse($s->salida)->format('d/m H:i') }} · {{ $s->origen }} → {{ $s->destino }} · {{ $s->unidad }}</option>
                        @empty
                            <option value="">No hay salidas hoy</option>
                        @endforelse
                    </select></label>
            </form>
            @if ($elegida)
                <div class="flex flex-wrap gap-2">
                    <input x-model="codigo" x-ref="codigo" @keydown.enter.prevent="leer(codigo)" placeholder="Código del boleto" maxlength="200" autofocus
                           class="flex-1 min-w-[12rem] h-12 rounded-xl border-gray-300 text-lg font-bold uppercase tracking-widest">
                    <button type="button" @click="leer(codigo)" class="h-12 px-5 rounded-xl bg-indigo-600 font-bold text-white">Validar</button>
                    <button type="button" @click="camara()" class="h-12 px-5 rounded-xl bg-slate-800 font-bold text-white"><i class="fas fa-camera"></i> Escanear QR</button>
                </div>
                <div x-show="resultado" x-cloak class="rounded-2xl p-4 text-center" :class="resultado?.ok ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'">
                    <p class="text-2xl font-black" x-text="resultado?.mensaje"></p>
                    <p class="font-bold" x-show="resultado?.pasajero" x-text="resultado?.pasajero ? resultado.pasajero.nombre + ' · ' + resultado.pasajero.doc : ''"></p>
                </div>
            @endif
        </div>

        @if ($elegida)
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b flex items-center gap-2">
                    <h2 class="flex-1 font-black text-gray-800">Pasajeros</h2>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-sm font-bold" x-text="subieron + ' subieron'"></span>
                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-sm font-bold" x-text="(lista.length - subieron) + ' faltan'"></span>
                </div>
                <div class="divide-y divide-gray-100">
                    <template x-for="p in lista" :key="p.codigo">
                        <div class="px-4 py-2 flex items-center gap-3 text-sm">
                            <span class="w-10 text-center font-black text-indigo-700" x-text="p.asiento"></span>
                            <span class="flex-1"><b x-text="p.nombre"></b> <span class="text-gray-500" x-text="p.doc"></span></span>
                            <span x-show="p.subio" class="text-emerald-600 font-bold"><i class="fas fa-check"></i> Subió</span>
                            <button x-show="!p.subio" type="button" @click="leer(p.codigo)" class="h-8 px-3 rounded-lg bg-slate-100 text-xs font-bold text-slate-700">Subió</button>
                        </div>
                    </template>
                    <p x-show="!lista.length" class="p-6 text-center text-sm text-gray-400">Aún no hay pasajes vendidos para esta salida.</p>
                </div>
            </div>
        @endif
    </div>

    <script src="{{ asset('js/escaner-barras.js') }}?v={{ @filemtime(public_path('js/escaner-barras.js')) }}"></script>
    <script>
        function embarque(salida, lista) {
            return {
                codigo: '', resultado: null, lista,
                get subieron() { return this.lista.filter((p) => p.subio).length; },
                camara() { window.EscanerBarras.abrir((texto) => this.leer(texto)); },
                async leer(texto) {
                    texto = String(texto || '').trim();
                    if (!texto) return;
                    try {
                        const r = await fetch(@js(route('transportes.embarcar')), { method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ codigo: texto, salida_id: salida }) });
                        if (r.status === 419 || r.status === 401) { location.reload(); return; }
                        this.resultado = await r.json();
                    } catch (e) {
                        this.resultado = { ok: false, mensaje: 'Sin conexión. Intenta otra vez.' };
                    }
                    if (this.resultado.ok) {
                        const m = texto.toUpperCase().match(/([A-Z0-9]{10})\s*$/);
                        const p = m && this.lista.find((x) => x.codigo === m[1]);
                        if (p) p.subio = true;
                    }
                    this.codigo = '';
                    this.$nextTick(() => this.$refs.codigo?.focus());
                },
            };
        }
    </script>
@endsection
