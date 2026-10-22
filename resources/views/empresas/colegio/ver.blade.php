@extends('layouts.app')
@section('title', 'Alumno · '.$alumno->nombres)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $hoy = now()->toDateString();
        $saldo = fn ($c) => round((float) $c->monto - (float) $c->descuento - (float) $c->pagado, 2);
        $pendientes = $cargos->where('estado', 'PENDIENTE');
        $vencido = $pendientes->filter(fn ($c) => $c->vence && $c->vence < $hoy)->sum($saldo);
        $mesesVencidos = $pendientes->filter(fn ($c) => $c->vence && $c->vence < $hoy && $c->concepto === 'PENSION')->count();
        $pagado = $pagos->flatten(1)->sum('monto');
        $estados = ['PENDIENTE' => 'bg-amber-100 text-amber-800', 'PAGADO' => 'bg-emerald-100 text-emerald-700', 'BECADO' => 'bg-violet-100 text-violet-700', 'ANULADO' => 'bg-gray-100 text-gray-400'];
        $wa = fn ($tel) => ($n = preg_replace('/\D/', '', (string) $tel)) ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n) : null;
        $filasCargos = $cargos->map(fn ($c) => ['id' => $c->car_id, 'concepto' => $c->concepto, 'descripcion' => $c->descripcion, 'saldo' => $saldo($c),
            'vencido' => $c->estado === 'PENDIENTE' && $c->vence && $c->vence < $hoy, 'pendiente' => $c->estado === 'PENDIENTE'])->values();
        $pagadores = $responsables->map(fn ($r) => ['tipo' => $r->tipo, 'clinum' => $r->clinum, 'clinom' => $r->clinom, 'tdicod' => $r->tdicod, 'clidir' => $r->clidir, 'paga' => (bool) $r->paga])->values();
    @endphp

    <div class="space-y-5" x-data="fichaAlumno()">
        @include('empresas.partials.alert')

        {{-- Cabecera del alumno --}}
        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6">
            <a href="{{ route('colegio.index') }}" class="text-sm text-gray-500">← Alumnos</a>
            <div class="mt-2 flex flex-wrap items-start gap-4">
                <span class="h-16 w-16 shrink-0 rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center text-2xl font-black">
                    {{ collect(preg_split('/\s+/', $alumno->nombres))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</span>
                <div class="flex-1 min-w-60">
                    <h1 class="text-2xl font-black text-gray-800 leading-tight">{{ $alumno->nombres }}</h1>
                    <p class="text-sm text-gray-500">Cód. {{ $alumno->codigo }}@if ($alumno->dni) · DNI {{ $alumno->dni }}@endif
                        @if ($alumno->fecha_nac) · {{ \Carbon\Carbon::parse($alumno->fecha_nac)->age }} años @endif</p>
                    <p class="mt-1 text-sm font-semibold text-indigo-700">
                        @if ($matricula) {{ \App\Support\Colegio::etiquetaGrado($matricula) }} · {{ $matricula->anio }}
                            @if ((float) $matricula->dscto_pct > 0)<span class="ml-1 rounded bg-violet-50 px-1.5 text-violet-700">-{{ rtrim(rtrim(number_format($matricula->dscto_pct, 2), '0'), '.') }}% en la pensión ({{ $matricula->orden_grupo }}° del grupo familiar)</span>@endif
                        @else Sin matrícula en {{ $anio }} @endif
                        @if ($alumno->estado === 'RETIRADO')<span class="ml-1 rounded bg-gray-100 px-1.5 text-gray-600">RETIRADO</span>@endif
                    </p>
                    @if ($grupo->isNotEmpty())
                        <p class="mt-1 text-sm text-gray-600"><i class="fas fa-people-roof text-violet-500"></i> Grupo familiar:
                            @foreach ($grupo as $h)<a href="{{ route('colegio.ver', $h->alu_id) }}" class="font-semibold text-violet-700 hover:underline">{{ $h->nombres }}</a>@if (! $loop->last), @endif @endforeach</p>
                    @endif
                    @if ($alumno->observacion)<p class="mt-1 text-xs text-gray-500">{{ $alumno->observacion }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($matriculas->count() > 1)
                        <select onchange="location.href='?anio=' + this.value" class="h-10 rounded-xl border-gray-300 text-sm font-semibold">
                            @foreach ($matriculas as $m)<option value="{{ $m->anio }}" @selected($m->anio == $anio)>Año {{ $m->anio }}</option>@endforeach
                        </select>
                    @endif
                    <button type="button" @click="modal = 'matricula'" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-user-plus"></i> Matricular otro año</button>
                    <button type="button" @click="modal = 'cargo'" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-plus"></i> Otro cobro</button>
                    @if ($esAdmin && $matricula)
                        <button type="button" @click="cambiarDescuento()" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-percent"></i> Descuento</button>
                    @endif
                    @if ($esAdmin && $alumno->estado !== 'RETIRADO')
                        <button type="button" @click="retirar()" class="h-10 px-3 rounded-xl bg-white ring-1 ring-rose-200 text-rose-600 text-sm font-semibold"><i class="fas fa-user-minus"></i> Retirar</button>
                    @endif
                </div>
            </div>
            {{-- Resumen de su cuenta --}}
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-2xl {{ $vencido > 0 ? 'bg-rose-50' : 'bg-emerald-50' }} p-3"><p class="text-xs font-semibold {{ $vencido > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $vencido > 0 ? 'Debe (vencido)' : 'Al día' }}</p>
                    <p class="text-xl font-black {{ $vencido > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $soles($vencido) }}</p>
                    @if ($mesesVencidos)<p class="text-xs text-rose-600">{{ $mesesVencidos }} {{ $mesesVencidos === 1 ? 'pensión vencida' : 'pensiones vencidas' }}</p>@endif</div>
                <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Falta pagar en el año</p><p class="text-xl font-black text-gray-800">{{ $soles($pendientes->sum($saldo)) }}</p></div>
                <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Pagado</p><p class="text-xl font-black text-gray-800">{{ $soles($pagado) }}</p></div>
                <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Gratis (becas y sorteos)</p><p class="text-xl font-black text-violet-700">{{ $soles($cargos->where('estado', 'BECADO')->sum(fn ($c) => $c->monto - $c->descuento)) }}</p></div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Cuenta del alumno --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm">
                <div class="p-4 border-b border-gray-100 flex flex-wrap items-center gap-2">
                    <h2 class="font-black text-gray-800 mr-auto">Cuenta {{ $anio }}</h2>
                    <button type="button" @click="elegir('vencidos')" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-xs font-bold">Lo vencido</button>
                    <button type="button" @click="elegir('siguiente')" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold">Hasta el próximo mes</button>
                    <button type="button" @click="elegir('todo')" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold">Todo el año</button>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($cargos as $c)
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-x-3 gap-y-1 px-4 py-2.5 {{ $c->estado === 'PENDIENTE' && $c->vence && $c->vence < $hoy ? 'bg-rose-50/40' : '' }}">
                            <input type="checkbox" value="{{ $c->car_id }}" x-model.number="elegidos" @disabled($c->estado !== 'PENDIENTE') class="h-5 w-5 rounded text-indigo-600 disabled:opacity-20">
                            <div class="flex-1 min-w-44">
                                <p class="font-semibold text-gray-800 {{ $c->estado === 'ANULADO' ? 'line-through text-gray-400' : '' }}">{{ $c->descripcion }}</p>
                                <p class="text-xs text-gray-400">
                                    @if ($c->vence) Vence {{ \Carbon\Carbon::parse($c->vence)->format('d/m/Y') }} @endif
                                    @if ((float) $c->descuento > 0) · desc. grupo familiar {{ $soles($c->descuento) }} @endif
                                    @if ($c->motivo) · {{ $c->motivo }} @endif
                                    @foreach ($pagos[$c->car_id] ?? [] as $p)
                                        · <a href="{{ route('cobros.voucher', $p->IdCpe_cabecera) }}" target="_blank" class="text-indigo-600 hover:underline">{{ $p->serdoc }}-{{ ltrim($p->numdoc, '0') }}</a>
                                        {{ \Carbon\Carbon::parse($p->fecha)->format('d/m') }}@if ($p->pagador) ({{ mb_strtolower($p->pagador) }})@endif
                                        @if ((float) $p->descuento > 0) <span class="text-emerald-600">-{{ $soles($p->descuento) }} adelanto</span>@endif
                                    @endforeach
                                </p>
                            </div>
                            <p class="w-24 text-right font-bold {{ $c->estado === 'PENDIENTE' ? 'text-gray-800' : 'text-gray-400' }}">{{ $soles($c->estado === 'PENDIENTE' ? $saldo($c) : $c->monto - $c->descuento) }}</p>
                            <span class="w-24 text-center rounded-full px-2 py-0.5 text-[11px] font-black {{ $estados[$c->estado] ?? '' }}">{{ $c->estado === 'BECADO' ? 'GRATIS' : $c->estado }}</span>
                            @if ($esAdmin && $c->estado === 'PENDIENTE' && (float) $c->pagado == 0)
                                <div class="flex gap-1">
                                    <button type="button" @click="accion({{ $c->car_id }}, 'becar')" class="px-2 py-1 rounded-lg bg-violet-50 text-violet-700 text-xs font-bold" title="No se cobra (beca, convenio)">Gratis</button>
                                    <button type="button" @click="accion({{ $c->car_id }}, 'anular')" class="px-2 py-1 rounded-lg bg-gray-50 text-gray-500 text-xs font-bold" title="Anular">✕</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="p-8 text-center text-gray-400 text-sm">No tiene cobros en {{ $anio }}. Usa “Matricular otro año”.</p>
                    @endforelse
                </div>
            </div>

            {{-- Responsables --}}
            <div class="space-y-3">
                <h2 class="font-black text-gray-800">Padres y apoderado</h2>
                @forelse ($responsables as $r)
                    <div class="bg-white rounded-2xl shadow-sm p-4">
                        <p class="text-xs font-black uppercase text-indigo-600">{{ $r->tipo }} @if ($r->paga)<span class="ml-1 rounded bg-emerald-100 px-1.5 text-emerald-700">PAGA</span>@endif</p>
                        <p class="font-bold text-gray-800">{{ $r->clinom }}</p>
                        <p class="text-sm text-gray-500">{{ strlen($r->clinum) === 11 ? 'RUC' : 'DNI' }} {{ $r->clinum }}</p>
                        @if ($r->telefono)
                            <p class="text-sm mt-1"><i class="fas fa-phone text-gray-400"></i> {{ $r->telefono }}
                                @if ($w = $wa($r->telefono))<a href="{{ $w }}" target="_blank" class="ml-2 text-emerald-600 font-semibold"><i class="fab fa-whatsapp"></i> WhatsApp</a>@endif</p>
                        @endif
                        @if ($r->clicor)<p class="text-sm text-gray-500"><i class="fas fa-envelope text-gray-400"></i> {{ $r->clicor }}</p>@endif
                    </div>
                @empty
                    <p class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-400">Sin padres registrados.</p>
                @endforelse
            </div>
        </div>

        {{-- Barra para cobrar lo elegido --}}
        <div x-show="elegidos.length" x-cloak class="sticky bottom-3 z-20">
            <div class="mx-auto max-w-3xl rounded-2xl bg-gray-900 text-white shadow-2xl px-4 py-3 flex flex-wrap items-center gap-3">
                <p class="flex-1"><b x-text="elegidos.length"></b> concepto(s) · <b class="text-xl" x-text="soles(calculo.total)"></b>
                    <span x-show="calculo.ahorro > 0" class="ml-1 text-emerald-300 text-sm" x-text="'(ahorra ' + soles(calculo.ahorro) + ' por adelantado)'"></span></p>
                <button type="button" @click="abrirCobro()" class="h-11 px-6 rounded-xl bg-emerald-500 font-extrabold hover:bg-emerald-400"><i class="fas fa-cash-register"></i> Cobrar</button>
            </div>
        </div>

        {{-- Cobrar --}}
        <div x-show="modal === 'cobro'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
            <form @submit.prevent="cobrar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-xl my-4" @click.outside="modal = null">
                <div class="px-6 py-4 border-b"><h2 class="text-lg font-black text-gray-800"><i class="fas fa-cash-register text-emerald-600"></i> Cobrar</h2></div>
                <div class="p-6 space-y-4">
                    @unless ($turno)
                        <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Debes <a href="{{ route('turnos.index') }}" class="font-bold underline">aperturar tu turno de caja</a> antes de cobrar.</p>
                    @endunless
                    <div class="rounded-2xl bg-gray-50 p-3 space-y-1 text-sm max-h-48 overflow-y-auto">
                        <template x-for="l in calculo.lineas" :key="l.id">
                            <div class="flex justify-between gap-3"><span x-text="l.descripcion"></span>
                                <span class="whitespace-nowrap"><span x-show="l.descuento > 0" class="text-emerald-600 text-xs mr-1" x-text="'-' + soles(l.descuento)"></span><b x-text="soles(l.cobrar)"></b></span></div>
                        </template>
                        <p x-show="calculo.adelanto" class="text-emerald-700 text-xs font-semibold pt-1">✔ Paga {{ $cfg->adelanto_meses }} o más pensiones juntas: {{ rtrim(rtrim(number_format($cfg->adelanto_pct, 2), '0'), '.') }}% de descuento en ellas.</p>
                        <p class="flex justify-between border-t pt-2 text-base"><b>Total</b><b x-text="soles(calculo.total)"></b></p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="text-xs font-semibold text-gray-500">¿Quién paga?
                            <select x-model="cobro.pagador" @change="ponerPagador()" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                <template x-for="p in pagadores" :key="p.tipo"><option :value="p.tipo" x-text="p.tipo + ' · ' + p.clinom"></option></template>
                                <option value="ALUMNO">EL ALUMNO</option><option value="OTRO">OTRA PERSONA O EMPRESA</option>
                            </select></label>
                        <label class="text-xs font-semibold text-gray-500">Comprobante
                            <select x-model="cobro.tdocod" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                @foreach ($comprobantes as $t)<option value="{{ $t->tdocod }}">{{ $t->tdodes }}</option>@endforeach
                            </select></label>
                        <label class="text-xs font-semibold text-gray-500">DNI o RUC
                            <input x-model="cobro.clinum" maxlength="15" inputmode="numeric" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                        <label class="text-xs font-semibold text-gray-500">Nombre o razón social
                            <input x-model="cobro.clinom" maxlength="150" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                        <label class="sm:col-span-2 text-xs font-semibold text-gray-500" x-show="cobro.tdocod === '01'">Dirección fiscal
                            <input x-model="cobro.clidir" maxlength="200" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                        <label class="text-xs font-semibold text-gray-500">Medio de pago
                            <select x-model="cobro.medio" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                            </select></label>
                        <label class="text-xs font-semibold text-gray-500">Paga con (vuelto)
                            <input type="number" step="any" min="0" x-model.number="cobro.paga" placeholder="0.00" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm text-right">
                            <span x-show="cobro.paga > calculo.total" class="text-emerald-700" x-text="'Vuelto ' + soles(cobro.paga - calculo.total)"></span></label>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="cobro.imprimir" class="rounded"> Imprimir el comprobante</label>
                    <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                </div>
                <div class="px-6 py-4 border-t flex gap-3 justify-end">
                    <button type="button" @click="modal = null" class="px-5 h-11 rounded-xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                    <button :disabled="ocupado" class="px-6 h-11 rounded-xl bg-emerald-600 text-white font-extrabold disabled:opacity-60" x-text="ocupado ? 'Cobrando…' : '✓ Cobrar ' + soles(calculo.total)"></button>
                </div>
            </form>
        </div>

        {{-- Otro cobro --}}
        <div x-show="modal === 'cargo'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-center justify-center p-3">
            <form @submit.prevent="enviar(@js(route('colegio.cargo', $alumno->alu_id)), extra)" class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 space-y-3" @click.outside="modal = null">
                <h2 class="text-lg font-black text-gray-800">Otro cobro</h2>
                <p class="text-sm text-gray-500">Uniforme, libros, actividad, examen de recuperación…</p>
                <input x-model="extra.descripcion" required maxlength="150" placeholder="Concepto" class="w-full h-10 rounded-lg border-gray-300 text-sm uppercase">
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" step="0.01" min="0.01" x-model="extra.monto" required placeholder="Monto S/" class="h-10 rounded-lg border-gray-300 text-sm text-right">
                    <input type="date" x-model="extra.vence" class="h-10 rounded-lg border-gray-300 text-sm">
                </div>
                <div class="flex gap-3 justify-end"><button type="button" @click="modal = null" class="px-4 h-10 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                    <button class="px-5 h-10 rounded-xl bg-indigo-600 text-white font-bold">Agregar</button></div>
            </form>
        </div>

        {{-- Matricular otro año --}}
        <div x-show="modal === 'matricula'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-center justify-center p-3">
            <form @submit.prevent="enviar(@js(route('colegio.matricular', $alumno->alu_id)), mat)" class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 space-y-3" @click.outside="modal = null">
                <h2 class="text-lg font-black text-gray-800">Matricular otro año</h2>
                <p class="text-sm text-gray-500">Se le genera su matrícula y las pensiones del año. Como ya es alumno, no paga inscripción.</p>
                <div class="grid grid-cols-3 gap-3">
                    <input type="number" x-model.number="mat.anio" min="2020" max="2100" class="h-10 rounded-lg border-gray-300 text-sm">
                    <select x-model="mat.grado_id" required class="col-span-2 h-10 rounded-lg border-gray-300 text-sm">
                        <option value="">Grado…</option>
                        @foreach ($grados as $g)<option value="{{ $g->grado_id }}">{{ \App\Support\Colegio::etiquetaGrado($g) }}</option>@endforeach
                    </select>
                </div>
                <div class="flex gap-3 justify-end"><button type="button" @click="modal = null" class="px-4 h-10 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                    <button class="px-5 h-10 rounded-xl bg-indigo-600 text-white font-bold">Matricular</button></div>
            </form>
        </div>

        <iframe x-ref="ticket" class="hidden"></iframe>
    </div>

    <script>
        function fichaAlumno() {
            const soles = n => 'S/ ' + Number(n || 0).toFixed(2);
            const CARGOS = @json($filasCargos);
            const ADELANTO = { meses: {{ (int) $cfg->adelanto_meses }}, pct: {{ (float) $cfg->adelanto_pct }} };
            const PAGADORES = @json($pagadores);
            const ALUMNO = { clinum: @json($alumno->dni ?? ''), clinom: @json($alumno->nombres) };
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data || {}),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
            return {
                soles, elegidos: [], modal: null, error: '', ocupado: false, pagadores: PAGADORES,
                cobro: { pagador: (PAGADORES.find(p => p.paga) || PAGADORES[0] || { tipo: 'ALUMNO' }).tipo, tdocod: '03', tdicod: '1', clinum: '', clinom: '', clidir: '',
                         medio: @json((string) ($medios->first()->id_med_pag ?? '')), paga: null, imprimir: true },
                extra: { descripcion: '', monto: '', vence: @json(now()->toDateString()) },
                mat: { anio: {{ (int) ($matriculas->first()->anio ?? $cfg->anio) + 1 }}, grado_id: '' },
                // El mismo cálculo que hace el servidor: el descuento por adelantado sale al juntar N pensiones
                get calculo() {
                    const sel = CARGOS.filter(c => this.elegidos.includes(c.id));
                    const adelanto = ADELANTO.meses > 0 && ADELANTO.pct > 0 && sel.filter(c => c.concepto === 'PENSION').length >= ADELANTO.meses;
                    const lineas = sel.map(c => { const d = adelanto && c.concepto === 'PENSION' ? Math.round(c.saldo * ADELANTO.pct) / 100 : 0;
                        return { id: c.id, descripcion: c.descripcion, descuento: d, cobrar: Math.round((c.saldo - d) * 100) / 100 }; });
                    return { lineas, adelanto, total: lineas.reduce((s, l) => s + l.cobrar, 0), ahorro: lineas.reduce((s, l) => s + l.descuento, 0) };
                },
                elegir(que) {
                    const limite = new Date(); limite.setMonth(limite.getMonth() + 1);
                    const hasta = limite.toISOString().slice(0, 10);
                    this.elegidos = CARGOS.filter(c => c.pendiente && (que === 'todo' || (que === 'vencidos' && c.vencido)
                        || (que === 'siguiente' && (c.vencido || (@json($cargos->pluck('vence', 'car_id'))[c.id] || '') <= hasta)))).map(c => c.id);
                },
                ponerPagador() {
                    const p = this.cobro.pagador === 'ALUMNO' ? { ...ALUMNO, tdicod: '1', clidir: '' } : this.pagadores.find(x => x.tipo === this.cobro.pagador);
                    if (p) { this.cobro.clinum = p.clinum === '00000000' ? '' : (p.clinum || ''); this.cobro.clinom = p.clinom || ''; this.cobro.clidir = p.clidir || ''; this.cobro.tdicod = p.tdicod || '1'; }
                    else { this.cobro.clinum = ''; this.cobro.clinom = ''; this.cobro.clidir = ''; }
                    if (String(this.cobro.clinum).length === 11) this.cobro.tdocod = '01';
                },
                abrirCobro() { this.error = ''; this.ponerPagador(); this.modal = 'cobro'; },
                async cobrar() {
                    this.ocupado = true; this.error = '';
                    try {
                        const doc = String(this.cobro.clinum || '');
                        const d = await post(@json(route('colegio.cobrar', $alumno->alu_id)), { cargos: this.elegidos, tdocod: this.cobro.tdocod, pagador: this.cobro.pagador,
                            tdicod: doc.length === 11 ? '6' : '1', clinum: doc, clinom: this.cobro.clinom, clidir: this.cobro.clidir,
                            id_med_pag: this.cobro.medio ? [this.cobro.medio] : [], paga: this.cobro.paga || 0, imprimir: this.cobro.imprimir });
                        if (d.errors) { this.error = Object.values(d.errors).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje; return; }
                        (window.tushpaAviso || alert)('✔ ' + d.numero + ' · ' + soles(d.total) + (d.vuelto > 0 ? ' · vuelto ' + soles(d.vuelto) : ''), 'ok');
                        // Sin impresora directa: el ticket se imprime con el navegador
                        if (this.cobro.imprimir && !d.impreso) this.$refs.ticket.src = d.ticket + '&imprimir=1';
                        setTimeout(() => location.reload(), this.cobro.imprimir && !d.impreso ? 2500 : 900);
                    } catch (e) { this.error = 'Error de conexión. Revisa si se registró antes de volver a cobrar.'; } finally { this.ocupado = false; }
                },
                async enviar(url, datos) {
                    const d = await post(url, datos);
                    if (d.errors) return (window.tushpaAviso || alert)(Object.values(d.errors).flat()[0], 'error');
                    (window.tushpaAviso || alert)(d.mensaje, d.ok ? 'ok' : 'error');
                    if (d.ok) setTimeout(() => location.reload(), 700);
                },
                accion(id, tipo) {
                    const motivo = prompt(tipo === 'becar' ? '¿Por qué no se cobra? (beca, convenio, hijo de docente…)' : '¿Por qué se anula?');
                    if (motivo) this.enviar(@json(url('colegio/cargos')) + '/' + id + '/' + tipo, { motivo });
                },
                @if ($matricula)
                cambiarDescuento() {
                    const pct = prompt('Descuento en la pensión (%) para las pensiones que faltan pagar:', @json((float) $matricula->dscto_pct));
                    if (pct !== null && pct !== '') this.enviar(@json(route('colegio.descuento', $matricula->mat_id)), { pct: Number(pct) });
                },
                @endif
                retirar() {
                    const motivo = prompt('Motivo del retiro (ya no se le cobrarán las pensiones que aún no vencen):');
                    if (motivo) this.enviar(@json(route('colegio.retirar', $alumno->alu_id)), { motivo });
                },
            };
        }
    </script>
@endsection
