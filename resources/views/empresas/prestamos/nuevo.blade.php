@extends('layouts.app')
@section('title', 'Nuevo préstamo')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500'; @endphp

    <div x-data="nuevoPrestamo()" class="space-y-4 pb-24">
        <div>
            <a href="{{ route('prestamos.index') }}" class="text-sm text-gray-500">← Préstamos</a>
            <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-hand-holding-dollar text-emerald-600"></i> Nuevo préstamo</h1>
        </div>

        <div class="grid lg:grid-cols-5 gap-5">
            <div class="lg:col-span-3 space-y-4 min-w-0">
                {{-- 1. Cliente --}}
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-bold uppercase text-gray-400 mb-2">1. Cliente</p>
                    <template x-if="!cliente">
                        <div class="relative">
                            <input x-model="q" @input.debounce.250ms="buscar()" placeholder="Busca por nombre o DNI" class="{{ $in }} text-base" autofocus>
                            <div x-show="sugerencias.length" class="absolute z-20 inset-x-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden">
                                <template x-for="s in sugerencias" :key="s.pcl_id">
                                    <button type="button" @click="elegir(s.pcl_id)" class="w-full text-left px-4 py-2.5 hover:bg-emerald-50 border-b last:border-0">
                                        <b x-text="s.clinom"></b> <span class="text-xs text-gray-500" x-text="s.clinum + (s.zona ? ' · ' + s.zona : '') + (s.estado !== 'ACTIVO' ? ' · ' + s.estado : '')"></span></button>
                                </template>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">¿Cliente nuevo? <a href="{{ route('prestamos.clientes') }}" class="text-emerald-700 font-bold underline">Regístralo aquí</a> (con su DNI) y vuelve.</p>
                        </div>
                    </template>
                    <template x-if="cliente">
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-black" x-text="cliente.cliente.clinom.slice(0, 1)"></div>
                                <div class="flex-1 min-w-0"><p class="font-bold text-gray-800" x-text="cliente.cliente.clinom"></p>
                                    <p class="text-xs text-gray-500" x-text="['DNI ' + cliente.cliente.clinum, cliente.cliente.zona, cliente.cliente.negocio].filter(Boolean).join(' · ')"></p></div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold" :class="cal[cliente.calificacion.color]" x-text="cliente.calificacion.texto"></span>
                                <button type="button" @click="cliente = null; renovar = null" class="text-xs text-gray-500 underline">Cambiar</button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2" x-text="cliente.calificacion.detalle"></p>
                            <p x-show="cliente.calificacion.sugerido" class="text-xs font-bold text-emerald-700" x-text="'Se le puede prestar hasta ' + soles(cliente.calificacion.sugerido)"></p>
                            <p x-show="cliente.cliente.limite" class="text-xs text-gray-500" x-text="'Límite del cliente: ' + soles(cliente.cliente.limite)"></p>
                            <div x-show="cliente.calificacion.nivel === 'MORA'" class="mt-2 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold px-3 py-2"><i class="fas fa-triangle-exclamation"></i> Está en mora: mejor no prestarle hasta que se ponga al día.</div>
                            {{-- Renovación --}}
                            <template x-for="v in cliente.vigentes" :key="v.pre_id">
                                <label class="mt-3 flex items-start gap-3 rounded-xl border-2 p-3 cursor-pointer" :class="renovar === v.pre_id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'">
                                    <input type="checkbox" :checked="renovar === v.pre_id" @change="renovar = $event.target.checked ? v.pre_id : null" class="mt-1 rounded text-emerald-600">
                                    <span class="text-sm"><b x-text="'Renovar el préstamo N° ' + String(v.numero).padStart(5, '0')"></b>
                                        <span class="block text-gray-500" x-text="'Aún debe ' + soles(v.saldo) + ' (' + v.avance + '% pagado). Ese saldo se descuenta de lo que se le entrega.'"></span></span>
                                </label>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- 2. Plan y monto --}}
                <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                    <p class="text-xs font-bold uppercase text-gray-400">2. Plan y monto</p>
                    <div class="grid sm:grid-cols-3 gap-2">
                        <template x-for="p in planes" :key="p.plan_id">
                            <button type="button" @click="elegirPlan(p)" class="rounded-2xl border-2 p-3 text-left transition" :class="plan === p.plan_id ? 'border-emerald-500 bg-emerald-50 shadow' : 'border-gray-200 hover:border-gray-300'">
                                <p class="font-extrabold text-gray-800 text-sm" x-text="p.nombre"></p>
                                <p class="text-xs text-gray-500" x-text="Number(p.interes) + '% · ' + p.cuotas + ' ' + unidad(p.frecuencia, p.cuotas)"></p>
                                <p x-show="Number(p.primer_prestamo)" class="mt-1 inline-block px-1.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">PRIMERA VEZ</p>
                            </button>
                        </template>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500">Monto que presta S/</label>
                        <input type="number" min="1" step="10" x-model.number="monto" class="{{ $in }} mt-1 text-3xl font-black text-emerald-700 py-3 text-right">
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <template x-for="m in [100, 150, 200, 300, 500, 1000, 2000]" :key="m">
                                <button type="button" @click="monto = m" class="px-3 py-1.5 rounded-lg text-sm font-bold" :class="monto === m ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700'" x-text="'S/ ' + m"></button>
                            </template>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <label class="block text-xs font-semibold text-gray-500">Interés %<input type="number" min="0" step="1" x-model.number="interes" class="{{ $in }} mt-1 text-right font-bold"></label>
                        <label class="block text-xs font-semibold text-gray-500">N° de cuotas<input type="number" min="1" max="400" x-model.number="cuotas" class="{{ $in }} mt-1 text-right font-bold"></label>
                        <label class="block text-xs font-semibold text-gray-500">Frecuencia
                            <select x-model="frecuencia" class="{{ $in }} mt-1">@foreach (\App\Support\Prestamos::FRECUENCIAS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
                        <label class="block text-xs font-semibold text-gray-500">Fecha del préstamo<input type="date" x-model="fecha" max="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                    </div>
                    <p class="text-xs text-gray-500"><i class="fas fa-calendar-days"></i> Se cobra: <b>{{ collect($cfg->dias)->map(fn ($d) => \App\Support\Prestamos::DIAS[$d])->implode(', ') }}</b>
                        @if ($cfg->lista_feriados) · sin feriados @endif · la primera cuota es el siguiente día de cobro.</p>
                </div>

                {{-- 3. Entrega --}}
                <div class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-3 gap-3">
                    <p class="sm:col-span-3 text-xs font-bold uppercase text-gray-400">3. Entrega y cobrador</p>
                    <label class="block text-xs font-semibold text-gray-500">Se entrega en
                        <select x-model="medio" class="{{ $in }} mt-1">@foreach (\App\Support\Prestamos::MEDIOS as $m)<option>{{ $m }}</option>@endforeach</select></label>
                    <label class="block text-xs font-semibold text-gray-500">Cobrador
                        <select x-model="cobrador" class="{{ $in }} mt-1"><option value="">— El del cliente —</option>
                            @foreach ($cobradores as $u)<option value="{{ $u->IdUsuario }}">{{ $u->nombre }}</option>@endforeach</select></label>
                    <label class="block text-xs font-semibold text-gray-500">Nota (opcional)<input x-model="obs" maxlength="255" class="{{ $in }} mt-1"></label>
                </div>
            </div>

            {{-- Resumen --}}
            <div class="lg:col-span-2">
                <div class="lg:sticky lg:top-20 space-y-4">
                    <div class="rounded-3xl p-6 text-white bg-gradient-to-br from-emerald-600 to-teal-800 shadow-lg">
                        <p class="text-sm text-emerald-100">El cliente devuelve</p>
                        <p class="text-5xl font-black" x-text="soles(sim.total)"></p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-white/15 p-3"><p class="text-emerald-100 text-xs">Cuota</p><p class="text-2xl font-black" x-text="soles(sim.cuota)"></p>
                                <p class="text-xs text-emerald-100" x-text="sim.cuotas + ' ' + unidad(frecuencia, sim.cuotas)"></p></div>
                            <div class="rounded-2xl bg-white/15 p-3"><p class="text-emerald-100 text-xs">Ganancia</p><p class="text-2xl font-black text-lime-200" x-text="soles(sim.interes)"></p>
                                <p class="text-xs text-emerald-100" x-text="interes + '% de ' + soles(monto)"></p></div>
                            <div class="rounded-2xl bg-white/15 p-3"><p class="text-emerald-100 text-xs">Primera cuota</p><p class="font-bold" x-text="largo(sim.primera)"></p></div>
                            <div class="rounded-2xl bg-white/15 p-3"><p class="text-emerald-100 text-xs">Termina</p><p class="font-bold" x-text="largo(sim.ultima)"></p></div>
                        </div>
                        <div x-show="renovar" class="mt-3 rounded-2xl bg-amber-300/90 text-amber-950 p-3 text-sm">
                            <p>Debe del préstamo anterior: <b x-text="soles(saldoRenovar)"></b></p>
                            <p class="text-lg font-black" x-text="'Se le entrega: ' + soles(monto - saldoRenovar)"></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-4">
                        <p class="text-xs font-bold uppercase text-gray-400 mb-2">Cronograma</p>
                        <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-4 xl:grid-cols-6 gap-1.5 max-h-72 overflow-y-auto">
                            <template x-for="c in sim.fechas" :key="c.numero">
                                <div class="rounded-lg border text-center py-1">
                                    <p class="text-[10px] text-gray-400" x-text="'#' + c.numero + ' ' + dia(c.fecha)"></p>
                                    <p class="text-xs font-bold" x-text="corto(c.fecha)"></p>
                                    <p class="text-[10px] text-emerald-700 font-semibold" x-text="c.monto.toFixed(2)"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                    <button type="button" @click="guardar()" :disabled="ocupado || !cliente || !(monto > 0)"
                            class="w-full py-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-black shadow-lg disabled:opacity-40">
                        <i class="fas fa-check"></i> <span x-text="cliente ? 'Prestar ' + soles(renovar ? monto - saldoRenovar : monto) : 'Elige el cliente'"></span></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function nuevoPrestamo() {
            const CSRF = '{{ csrf_token() }}';
            const DIAS = @js($cfg->dias), FERIADOS = @js($cfg->lista_feriados);
            const planes = @js($planes);
            const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const inicial = @js($cliente);
            const plan0 = planes.find(p => inicial?.esPrimero && Number(p.primer_prestamo)) || planes.find(p => !Number(p.primer_prestamo) && p.frecuencia === 'DIARIA') || planes.find(p => !Number(p.primer_prestamo)) || planes[0] || { plan_id: null, interes: 20, cuotas: 24, frecuencia: 'DIARIA', monto_min: 100 };
            return {
                planes, cliente: inicial, q: '', sugerencias: [], renovar: null, ocupado: false,
                plan: plan0.plan_id, monto: Number(plan0.monto_min) || 100, interes: Number(plan0.interes), cuotas: Number(plan0.cuotas), frecuencia: plan0.frecuencia,
                fecha: @js(now()->toDateString()), medio: 'EFECTIVO', cobrador: '', obs: '',
                cal: { emerald: 'bg-emerald-100 text-emerald-700', sky: 'bg-sky-100 text-sky-700', amber: 'bg-amber-100 text-amber-800', rose: 'bg-rose-100 text-rose-700', slate: 'bg-slate-100 text-slate-600' },
                soles: n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                unidad: (f, n) => ({ DIARIA: 'días', SEMANAL: 'semanas', QUINCENAL: 'quincenas', MENSUAL: 'meses' })[f] || 'cuotas',
                dia: f => ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][new Date(f + 'T00:00').getDay()],
                corto: f => f ? f.slice(8, 10) + '/' + f.slice(5, 7) : '',
                largo: f => f ? new Date(f + 'T00:00').toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' }) : '—',
                get saldoRenovar() { const v = (this.cliente?.vigentes || []).find(x => x.pre_id === this.renovar); return v ? Number(v.saldo) : 0; },
                // Mismo cálculo que el sistema: cuota redondeada a 10 céntimos, la última ajusta; solo días de cobro y sin feriados
                get sim() {
                    const monto = Math.round((Number(this.monto) || 0) * 100) / 100, n = Math.max(1, Number(this.cuotas) || 1);
                    const interes = Math.round(monto * (Number(this.interes) || 0)) / 100, total = Math.round((monto + interes) * 100) / 100;
                    let cuota = Math.ceil(total / n * 10 - 0.0001) / 10;
                    if (cuota * (n - 1) >= total) cuota = Math.round(total / n * 100) / 100;
                    const fechas = []; let f = new Date((this.fecha || iso(new Date())) + 'T00:00');
                    const base = new Date(f);
                    for (let i = 1; i <= n; i++) {
                        if (this.frecuencia === 'SEMANAL') f.setDate(f.getDate() + 7);
                        else if (this.frecuencia === 'QUINCENAL') f.setDate(f.getDate() + 15);
                        else if (this.frecuencia === 'MENSUAL') { f = new Date(base); const d = f.getDate(); f.setDate(1); f.setMonth(f.getMonth() + i); f.setDate(Math.min(d, new Date(f.getFullYear(), f.getMonth() + 1, 0).getDate())); }
                        else f.setDate(f.getDate() + 1);
                        let v = 0;
                        while ((!DIAS.includes(f.getDay() || 7) || FERIADOS.includes(iso(f))) && v++ < 15) f.setDate(f.getDate() + 1);
                        fechas.push({ numero: i, fecha: iso(f), monto: i < n ? cuota : Math.round((total - cuota * (n - 1)) * 100) / 100 });
                    }
                    return { total, interes, cuota, cuotas: n, fechas, primera: fechas[0]?.fecha, ultima: fechas[fechas.length - 1]?.fecha };
                },
                elegirPlan(p) {
                    this.plan = p.plan_id; this.interes = Number(p.interes); this.cuotas = Number(p.cuotas); this.frecuencia = p.frecuencia;
                    if (Number(p.monto_min) && this.monto < Number(p.monto_min)) this.monto = Number(p.monto_min);
                    if (Number(p.monto_max) && this.monto > Number(p.monto_max)) this.monto = Number(p.monto_max);
                },
                async buscar() {
                    if (this.q.trim().length < 2) { this.sugerencias = []; return; }
                    this.sugerencias = await fetch('{{ route('prestamos.clientes.buscar') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                async elegir(id) {
                    this.sugerencias = []; this.q = '';
                    this.cliente = await fetch('{{ url('prestamos/clientes') }}/' + id, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (this.cliente?.esPrimero) { const p = planes.find(x => Number(x.primer_prestamo)); if (p) { this.elegirPlan(p); this.monto = Number(p.monto_min) || this.monto; } }
                },
                async guardar() {
                    if (this.renovar && this.monto - this.saldoRenovar <= 0) return tushpaAviso('El nuevo monto debe ser mayor que lo que aún debe.', 'error');
                    if (!confirm('¿Prestar ' + this.soles(this.renovar ? this.monto - this.saldoRenovar : this.monto) + ' a ' + this.cliente.cliente.clinom + '? Devolverá ' + this.soles(this.sim.total) + ' en ' + this.sim.cuotas + ' cuotas de ' + this.soles(this.sim.cuota) + '.')) return;
                    this.ocupado = true;
                    const r = await fetch('{{ route('prestamos.guardar') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ pcl_id: this.cliente.cliente.pcl_id, plan_id: this.plan, monto: this.monto, interes_pct: this.interes, cuotas: this.cuotas, frecuencia: this.frecuencia,
                            fecha: this.fecha, medio_entrega: this.medio, cobrador_id: this.cobrador || null, renueva_pre_id: this.renovar, obs: this.obs || null }) });
                    const j = await r.json().catch(() => ({}));
                    this.ocupado = false;
                    const ok = r.ok && j.ok;
                    tushpaAviso(j.mensaje || Object.values(j.errors || {}).flat()[0] || 'No se pudo guardar.', ok ? 'ok' : 'error');
                    if (ok) location.href = j.url;
                },
            };
        }
    </script>
@endsection
