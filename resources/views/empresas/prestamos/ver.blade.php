@extends('layouts.app')
@section('title', 'Préstamo '.str_pad((string) $prestamo->numero, 5, '0', STR_PAD_LEFT))
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $s = $situacion;
        $num = str_pad((string) $prestamo->numero, 5, '0', STR_PAD_LEFT);
        [$estadoTxt, $estadoColor] = \App\Support\Prestamos::ESTADOS[$s['estado']];
        $fondo = ['emerald' => 'from-emerald-600 to-teal-800', 'amber' => 'from-amber-500 to-orange-700', 'rose' => 'from-rose-600 to-red-800', 'sky' => 'from-sky-600 to-indigo-700', 'slate' => 'from-slate-500 to-slate-700'][$estadoColor];
        $vigente = $prestamo->estado === 'VIGENTE';
        $wa = \App\Support\Prestamos::whatsapp($cliente->telefono, "Hola {$cliente->clinom}, le saludamos de {$negocio->nombre_comercial}. "
            .($s['cobrar_hoy'] > 0 ? 'Le recordamos su pago de hoy: S/ '.number_format($s['cobrar_hoy'], 2).($s['vencidas'] ? ' ('.$s['vencidas'].' cuota(s) pendiente(s))' : '').'. ' : '')
            .'Saldo de su préstamo: S/ '.number_format($s['saldo'], 2).'. ¡Gracias!');
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
    @endphp

    <div x-data="verPrestamo()" class="space-y-5">
        <div class="relative overflow-hidden rounded-3xl p-6 text-white bg-gradient-to-br {{ $fondo }} shadow-lg">
            <div class="absolute -right-12 -top-12 w-52 h-52 rounded-full bg-white/10"></div>
            <div class="relative flex flex-wrap items-start gap-4">
                <div class="flex-1 min-w-[240px]">
                    <a href="{{ route('prestamos.index') }}" class="text-sm text-white/80">← Préstamos</a>
                    <p class="mt-1 text-xs uppercase tracking-widest text-white/70">Préstamo N° {{ $num }} · {{ \App\Support\Prestamos::FRECUENCIAS[$prestamo->frecuencia] }}</p>
                    <h1 class="text-2xl sm:text-3xl font-black">{{ $cliente->clinom }}</h1>
                    <p class="text-sm text-white/85">DNI {{ $cliente->clinum }}@if ($cliente->telefono) · {{ $cliente->telefono }}@endif @if ($cliente->negocio) · {{ $cliente->negocio }}@endif</p>
                    <p class="text-xs text-white/75">{{ collect([$cliente->direccion, $cliente->referencia, $cliente->zona])->filter()->implode(' · ') }}@if ($cobrador) · Cobra: {{ $cobrador }}@endif</p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-3 py-1 rounded-full bg-white/20 font-extrabold text-sm">{{ $estadoTxt }}{{ $s['vencidas'] && $vigente ? ' · '.$s['vencidas'].' cuota(s) sin pagar' : '' }}</span>
                    <p class="mt-2 text-sm text-white/80">Saldo</p>
                    <p class="text-4xl font-black">{{ $soles($s['saldo']) }}</p>
                </div>
            </div>
            <div class="relative mt-4">
                <div class="flex justify-between text-xs text-white/80"><span>{{ $s['pagadas'] }} de {{ $prestamo->cuotas }} cuotas · pagado {{ $soles($prestamo->pagado) }}</span><span>{{ $s['avance'] }}%</span></div>
                <div class="mt-1 h-3 rounded-full bg-white/20"><div class="h-3 rounded-full bg-white" style="width: {{ $s['avance'] }}%"></div></div>
            </div>
            <div class="relative mt-4 flex flex-wrap gap-2">
                @if ($vigente)
                    <button type="button" @click="abrirCobro()" class="px-5 py-3 rounded-xl bg-white text-gray-900 font-black shadow"><i class="fas fa-coins text-amber-500"></i> Cobrar{{ $s['cobrar_hoy'] > 0 ? ' '.$soles($s['cobrar_hoy']) : '' }}</button>
                    <button type="button" @click="gestion = { resultado: 'NO_PAGO', promesa: '', nota: '' }" class="px-4 py-3 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-user-clock"></i> No pagó / promesa</button>
                @endif
                <a href="{{ route('prestamos.tarjeta', $prestamo->pre_id) }}" target="_blank" class="px-4 py-3 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-print"></i> Tarjeta de pagos</a>
                @if ($wa)<a href="{{ $wa }}" target="_blank" class="px-4 py-3 rounded-xl bg-green-500 font-bold text-sm"><i class="fab fa-whatsapp"></i> Recordar</a>@endif
                @if ($gestiona && $vigente && $s['avance'] >= 50)
                    <a href="{{ route('prestamos.nuevo', ['cliente' => $prestamo->pcl_id]) }}" class="px-4 py-3 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-rotate"></i> Renovar</a>
                @endif
                @if ($gestiona && ! $vigente)
                    <a href="{{ route('prestamos.nuevo', ['cliente' => $prestamo->pcl_id]) }}" class="px-4 py-3 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-plus"></i> Nuevo préstamo</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach ([['Prestó', $soles($prestamo->monto), $prestamo->entregado != $prestamo->monto ? 'entregado '.$soles($prestamo->entregado) : \Carbon\Carbon::parse($prestamo->fecha)->format('d/m/Y')],
                ['Interés', $soles($prestamo->interes), (float) $prestamo->interes_pct.'%'], ['Total', $soles($prestamo->total), $prestamo->cuotas.' cuotas'],
                ['Cuota', $soles($prestamo->cuota), \App\Support\Prestamos::FRECUENCIAS[$prestamo->frecuencia]],
                ['Debe a hoy', $soles($s['cobrar_hoy']), $s['vencidas'].' cuota(s) atrasada(s)'],
                ['Termina', \Carbon\Carbon::parse($prestamo->ultima_cuota)->format('d/m/Y'), $prestamo->mora_pagada > 0 ? 'mora cobrada '.$soles($prestamo->mora_pagada) : 'última cuota']] as [$t, $v, $sub])
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-[11px] uppercase font-semibold text-gray-400">{{ $t }}</p><p class="text-xl font-black text-gray-800">{{ $v }}</p><p class="text-[11px] text-gray-400">{{ $sub }}</p></div>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <div class="bg-white rounded-2xl shadow-sm p-5 lg:col-span-2 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <h2 class="font-bold text-gray-800 mr-auto"><i class="fas fa-calendar-check text-emerald-600"></i> Cronograma</h2>
                    <span class="text-[11px] text-gray-500"><span class="inline-block w-2.5 h-2.5 rounded bg-emerald-500"></span> pagada
                        <span class="inline-block w-2.5 h-2.5 rounded bg-rose-500 ml-1"></span> atrasada <span class="inline-block w-2.5 h-2.5 rounded bg-amber-400 ml-1"></span> hoy
                        <span class="inline-block w-2.5 h-2.5 rounded bg-gray-200 ml-1"></span> por venir</span>
                </div>
                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-1.5">
                    @foreach ($s['cuotas'] as $c)
                        @php $cls = ['PAGADA' => 'bg-emerald-500 text-white border-emerald-500', 'VENCIDA' => 'bg-rose-500 text-white border-rose-500', 'HOY' => 'bg-amber-400 text-amber-950 border-amber-400', 'PARCIAL' => 'bg-emerald-50 text-emerald-800 border-emerald-300', 'PENDIENTE' => 'bg-white text-gray-600 border-gray-200'][$c['estado']]; @endphp
                        <div class="rounded-xl border-2 text-center py-1.5 {{ $cls }}" title="Cuota {{ $c['numero'] }} · {{ \Carbon\Carbon::parse($c['fecha'])->format('d/m/Y') }} · pagado {{ $soles($c['pagado']) }} de {{ $soles($c['monto']) }}">
                            <p class="text-[10px] opacity-80">#{{ $c['numero'] }} {{ \App\Support\Prestamos::DIAS[\Carbon\Carbon::parse($c['fecha'])->dayOfWeekIso] }}</p>
                            <p class="text-sm font-black">{{ \Carbon\Carbon::parse($c['fecha'])->format('d/m') }}</p>
                            <p class="text-[10px] font-semibold">{{ $c['estado'] === 'PAGADA' ? '✔' : number_format($c['monto'] - $c['pagado'], 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="space-y-5">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-receipt text-emerald-600"></i> Pagos</h2>
                    <div class="divide-y max-h-96 overflow-y-auto">
                        @forelse ($pagos as $g)
                            <div class="py-2 text-sm {{ $g->anulado ? 'opacity-50' : '' }}">
                                <div class="flex justify-between gap-2">
                                    <span><b class="{{ $g->anulado ? 'line-through' : '' }}">{{ $soles($g->monto + $g->mora) }}</b>
                                        <span class="text-xs text-gray-500">{{ $g->medio }}{{ $g->operacion ? ' · '.$g->operacion : '' }}{{ $g->mora > 0 ? ' · mora '.$soles($g->mora) : '' }}</span></span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($g->fecha_hora)->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-[11px] text-gray-400">{{ $g->tipo === 'RENOVACION' ? 'Renovación · ' : '' }}{{ $g->usuario }}{{ $g->obs ? ' · '.$g->obs : '' }}{{ $g->anulado ? ' · ANULADO: '.$g->motivo_anulacion : '' }}</p>
                                @if (! $g->anulado && $g->tipo === 'PAGO')
                                    <div class="flex gap-2 mt-0.5">
                                        <a href="{{ route('prestamos.recibo', $g->pag_id) }}" target="_blank" class="text-[11px] text-emerald-700 underline">Recibo</a>
                                        @if ($esAdmin)<button type="button" @click="anularPago({{ $g->pag_id }})" class="text-[11px] text-rose-600 underline">Anular</button>@endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-gray-400">Aún no paga.</p>
                        @endforelse
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-person-walking text-amber-500"></i> Visitas sin pago</h2>
                    @forelse ($gestiones as $x)
                        <p class="text-sm border-b last:border-0 py-1.5"><b>{{ \App\Support\Prestamos::RESULTADOS_GESTION[$x->resultado] }}</b>{{ $x->promesa ? ' · para el '.\Carbon\Carbon::parse($x->promesa)->format('d/m') : '' }}
                            <span class="block text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($x->fecha_hora)->format('d/m H:i') }} · {{ $x->usuario }}{{ $x->nota ? ' · '.$x->nota : '' }}</span></p>
                    @empty
                        <p class="text-sm text-gray-400">Ninguna.</p>
                    @endforelse
                </div>
                @if ($otros->isNotEmpty())
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-800 mb-2">Otros préstamos del cliente</h2>
                        @foreach ($otros as $o)
                            <a href="{{ route('prestamos.ver', $o->pre_id) }}" class="flex justify-between text-sm border-b last:border-0 py-1.5 hover:bg-gray-50">
                                <span>N° {{ str_pad((string) $o->numero, 5, '0', STR_PAD_LEFT) }} · {{ $soles($o->monto) }}</span><span class="text-xs text-gray-500">{{ $o->estado }} · {{ \Carbon\Carbon::parse($o->fecha)->format('d/m/Y') }}</span></a>
                        @endforeach
                    </div>
                @endif
                @if ($esAdmin && $prestamo->estado !== 'ANULADO')
                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-2">
                        <h2 class="font-bold text-gray-800">Administración</h2>
                        <p class="text-xs text-gray-500">{{ $prestamo->obs }}</p>
                        <button type="button" @click="anular()" class="text-sm text-rose-600 underline">Anular préstamo (solo si se registró por error y no tiene pagos)</button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Cobrar --}}
        <div x-show="cobro" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center sm:p-4">
            <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-xl p-5 space-y-3" @click.outside="cobro = null">
                <template x-if="cobro">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between"><h3 class="font-black text-lg">Cobrar</h3><button type="button" @click="cobro = null" class="text-gray-400 text-xl">✕</button></div>
                        <p class="text-sm text-gray-500">Saldo {{ $soles($s['saldo']) }} · cuota {{ $soles($prestamo->cuota) }}</p>
                        <input type="number" step="0.10" min="0" x-model.number="cobro.monto" class="block w-full rounded-2xl border-2 border-emerald-500 text-4xl font-black text-center py-4 text-emerald-700" autofocus>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (array_unique(array_filter([(float) $prestamo->cuota, $s['cobrar_hoy'], (float) $prestamo->cuota * 2, $s['saldo']])) as $sug)
                                <button type="button" @click="cobro.monto = {{ round($sug, 2) }}" class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 text-sm font-bold">{{ $sug == $s['saldo'] ? 'Todo ' : '' }}{{ $soles($sug) }}</button>
                            @endforeach
                        </div>
                        @if ((float) $cfg->mora_diaria > 0)
                            <label class="block text-xs font-semibold text-gray-500">Mora S/ <span class="font-normal">(sugerida {{ $soles($s['mora_sugerida']) }})</span>
                                <input type="number" step="0.10" min="0" x-model.number="cobro.mora" class="{{ $in }} mt-1 text-right"></label>
                        @endif
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach (\App\Support\Prestamos::MEDIOS as $m)
                                <button type="button" @click="cobro.medio = '{{ $m }}'" class="py-2 rounded-xl text-xs font-bold border-2" :class="cobro.medio === '{{ $m }}' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-200 text-gray-600'">{{ $m }}</button>
                            @endforeach
                        </div>
                        <input x-show="cobro.medio !== 'EFECTIVO'" x-model="cobro.operacion" placeholder="N° de operación *" maxlength="30" class="{{ $in }} uppercase">
                        <input x-model="cobro.obs" placeholder="Nota (opcional)" maxlength="200" class="{{ $in }}">
                        <button type="button" @click="pagar()" :disabled="ocupado || !(cobro.monto > 0 || cobro.mora > 0)" class="w-full py-4 rounded-2xl bg-emerald-600 text-white text-lg font-black disabled:opacity-40"
                                x-text="'Registrar ' + soles((Number(cobro.monto) || 0) + (Number(cobro.mora) || 0))"></button>
                    </div>
                </template>
            </div>
        </div>
        {{-- Recibo después de cobrar --}}
        <div x-show="hecho" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-sm rounded-3xl shadow-xl p-6 text-center space-y-3">
                <p class="text-5xl">✅</p>
                <p class="text-lg font-black" x-text="hecho?.mensaje"></p>
                <div class="grid gap-2">
                    <a x-show="hecho?.whatsapp" :href="hecho?.whatsapp" target="_blank" class="py-3 rounded-xl bg-green-600 text-white font-bold"><i class="fab fa-whatsapp"></i> Enviar comprobante por WhatsApp</a>
                    <a :href="hecho?.recibo" target="_blank" class="py-3 rounded-xl bg-gray-100 font-bold"><i class="fas fa-print"></i> Imprimir recibo</a>
                    <button type="button" @click="location.reload()" class="py-3 rounded-xl bg-emerald-600 text-white font-bold">Listo</button>
                </div>
            </div>
        </div>
        {{-- No pagó --}}
        <div x-show="gestion" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center sm:p-4">
            <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-xl p-5 space-y-3" @click.outside="gestion = null">
                <template x-if="gestion">
                    <div class="space-y-3">
                        <h3 class="font-black text-lg">Visita sin pago</h3>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Support\Prestamos::RESULTADOS_GESTION as $k => $v)
                                <button type="button" @click="gestion.resultado = '{{ $k }}'" class="py-2.5 rounded-xl text-sm font-bold border-2" :class="gestion.resultado === '{{ $k }}' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">{{ $v }}</button>
                            @endforeach
                        </div>
                        <label x-show="gestion.resultado === 'PROMESA'" class="block text-xs font-semibold text-gray-500">¿Cuándo paga?<input type="date" x-model="gestion.promesa" min="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                        <input x-model="gestion.nota" placeholder="Nota (opcional)" maxlength="200" class="{{ $in }}">
                        <button type="button" @click="guardarGestion()" :disabled="ocupado" class="w-full py-3 rounded-2xl bg-amber-500 text-white font-black">Anotar visita</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function verPrestamo() {
            const CSRF = '{{ csrf_token() }}';
            const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                .then(async r => { const j = await r.json().catch(() => ({})); return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j; })
                .catch(() => ({ ok: false, mensaje: 'Sin conexión.' }));
            return {
                cobro: null, gestion: null, hecho: null, ocupado: false,
                soles: n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                abrirCobro() { this.cobro = { monto: {{ $s['cobrar_hoy'] > 0 ? $s['cobrar_hoy'] : min((float) $prestamo->cuota, $s['saldo']) }}, mora: {{ $s['mora_sugerida'] }}, medio: 'EFECTIVO', operacion: '', obs: '' }; },
                async pagar() {
                    this.ocupado = true;
                    const pos = await new Promise(ok => navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => ok(p.coords), () => ok(null), { timeout: 3000 }) : ok(null));
                    const r = await post('{{ route('prestamos.pagar', $prestamo->pre_id) }}', { ...this.cobro, lat: pos?.latitude ?? null, lng: pos?.longitude ?? null });
                    this.ocupado = false;
                    if (!r.ok) return tushpaAviso(r.mensaje || 'No se pudo registrar.', 'error');
                    this.cobro = null; this.hecho = r;
                },
                async guardarGestion() {
                    this.ocupado = true;
                    const r = await post('{{ route('prestamos.gestion', $prestamo->pre_id) }}', { ...this.gestion, promesa: this.gestion.promesa || null });
                    this.ocupado = false;
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) setTimeout(() => location.reload(), 600);
                },
                async anularPago(id) {
                    const motivo = prompt('¿Por qué se anula este pago?');
                    if (!motivo) return;
                    const r = await post('{{ url('prestamos/pagos') }}/' + id + '/anular', { motivo });
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) setTimeout(() => location.reload(), 600);
                },
                async anular() {
                    const motivo = prompt('¿Por qué se anula el préstamo?');
                    if (!motivo) return;
                    const r = await post('{{ route('prestamos.anular', $prestamo->pre_id) }}', { motivo });
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) setTimeout(() => location.reload(), 600);
                },
            };
        }
    </script>
@endsection
