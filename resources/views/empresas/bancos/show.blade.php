@extends('layouts.app')
@section('title', $cuenta->banco.' '.$cuenta->numero)
@section('content')
@php
    $soles = fn ($n) => ($n < 0 ? '- ' : '').'S/ '.number_format(abs((float) $n), 2);
    $dia = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
    $mesTexto = \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::parse($desde)->locale('es')->isoFormat('MMMM YYYY'));
    $mesValor = substr($desde, 0, 7);
    $tab = fn ($v) => route('bancos.show', ['id' => $cuenta->id, 'mes' => $mesValor, 'vista' => $v]);
    $entran = collect($tipos)->filter(fn ($t) => $t[1] > 0)->map(fn ($t) => $t[0]);
    $salen = collect($tipos)->filter(fn ($t) => $t[1] < 0)->except('TRANSFERENCIA')->map(fn ($t) => $t[0]);
@endphp
<div class="max-w-7xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <a href="{{ route('bancos.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Bancos</a>
            <h1 class="text-xl font-extrabold text-gray-800">🏦 {{ $cuenta->banco }} · {{ $cuenta->numero }}</h1>
            <p class="text-xs text-gray-500">Cuenta contable {{ $cuenta->cuenta_contable }} · desde el {{ $dia($cuenta->fecha_inicio) }} con {{ $soles($cuenta->saldo_inicial) }}
                · <a href="{{ route('bancos.edit', $cuenta->id) }}" class="text-indigo-700 hover:underline">editar</a></p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="vista" value="{{ $vista }}">
            <input type="month" name="mes" value="{{ $mesValor }}" onchange="this.form.submit()" class="h-10 rounded-xl border-gray-300 text-sm font-semibold">
        </form>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
        @foreach (['conciliar' => '🔗 Conciliar', 'libro' => '📒 Libro bancos', 'reporte' => '📄 Reporte de conciliación'] as $v => $nombre)
            <a href="{{ $tab($v) }}" class="px-4 py-2 rounded-full font-semibold ring-1 {{ $vista === $v ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">{{ $nombre }}</a>
        @endforeach
    </div>

    {{-- Acciones del mes: subir extracto, automático, movimiento propio --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <form method="POST" action="{{ route('bancos.extracto', $cuenta->id) }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-4 space-y-2"
              x-data="{ subiendo: false }" @submit="subiendo = true">
            @csrf
            <p class="font-bold text-gray-700">1. Sube el estado de cuenta</p>
            <p class="text-xs text-gray-500">El Excel que descargas de tu banca por internet (xlsx, xls, csv). Volver a subirlo no duplica nada.
                <a href="{{ route('bancos.formato') }}" class="text-indigo-700 hover:underline">Formato de ejemplo</a></p>
            <div class="flex gap-2">
                <input type="file" name="archivo" accept="{{ \App\Support\ExcelLector::ACCEPT }}" required class="flex-1 min-w-0 text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2 file:py-1.5 file:font-semibold file:text-indigo-700">
                <button class="h-9 px-3 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-50" :disabled="subiendo" x-text="subiendo ? '…' : 'Subir'"></button>
            </div>
        </form>
        <form method="POST" action="{{ route('bancos.automatico', $cuenta->id) }}" class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
            @csrf <input type="hidden" name="mes" value="{{ $mesValor }}">
            <p class="font-bold text-gray-700">2. Concilia automático</p>
            <p class="text-xs text-gray-500">Junta lo que coincide sin dudas: N° de operación, mismo monto en fechas cercanas y el depósito del día (POS, Yape) contra la suma de sus cobros.</p>
            <button class="h-9 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">Conciliar {{ $mesTexto }}</button>
        </form>
        <details class="bg-white rounded-2xl shadow-sm p-4" @if (old('tipo')) open @endif>
            <summary class="cursor-pointer list-none font-bold text-gray-700">3. Movimiento del banco ▾</summary>
            <p class="text-xs text-gray-500 mt-1">Depósito de caja, retiro, comisión, ITF, intereses o transferencia entre tus cuentas. Se registra con su asiento contable.</p>
            <form method="POST" action="{{ route('bancos.movimiento', $cuenta->id) }}" class="mt-2 grid grid-cols-2 gap-2 text-sm" x-data="{ tipo: @js(old('tipo', 'COMISION')) }">
                @csrf
                <select name="tipo" x-model="tipo" class="col-span-2 h-9 rounded-xl border-gray-300 text-sm">
                    @foreach ($tipos as $clave => [$nombre])<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                </select>
                <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="h-9 rounded-xl border-gray-300 text-sm">
                <input type="number" step="0.01" min="0.01" name="monto" value="{{ old('monto') }}" placeholder="Monto S/" required class="h-9 rounded-xl border-gray-300 text-sm">
                <select name="cuenta_destino_id" x-show="tipo === 'TRANSFERENCIA'" x-cloak class="col-span-2 h-9 rounded-xl border-gray-300 text-sm">
                    <option value="">¿A qué cuenta?</option>
                    @foreach ($otras as $o)<option value="{{ $o->id }}">{{ $o->banco }} {{ $o->numero }}</option>@endforeach
                </select>
                <input name="descripcion" value="{{ old('descripcion') }}" maxlength="200" placeholder="Descripción" class="h-9 rounded-xl border-gray-300 text-sm">
                <input name="operacion" value="{{ old('operacion') }}" maxlength="40" placeholder="N° operación" class="h-9 rounded-xl border-gray-300 text-sm">
                <button class="col-span-2 h-9 rounded-xl bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold">Registrar con su asiento</button>
            </form>
        </details>
    </div>

    @if ($vista === 'conciliar')
        {{-- Conciliar a mano: se marcan líneas que sumen lo mismo de cada lado --}}
        <div x-data="conciliarBanco()" class="space-y-3">
            <div class="grid gap-3 lg:grid-cols-2">
                <section class="bg-white rounded-2xl shadow-sm">
                    <header class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p class="font-bold text-gray-700">En el banco, sin conciliar <span class="text-gray-400 font-semibold">({{ $banco->count() }})</span></p>
                        <span class="text-sm font-semibold" x-text="'Marcado: S/ ' + suma(selBanco, montosBanco)"></span>
                    </header>
                    <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
                        @forelse ($banco as $e)
                            <div class="px-4 py-2 text-sm flex items-start gap-3" :class="selBanco.includes({{ $e->id }}) ? 'bg-indigo-50' : ''">
                                <input type="checkbox" value="{{ $e->id }}" x-model.number="selBanco" class="mt-1 rounded text-indigo-600">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-700 break-words">{{ $e->descripcion ?: '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ $dia($e->fecha) }}{{ $e->operacion ? ' · Op. '.$e->operacion : '' }}</p>
                                    <form method="POST" action="{{ route('bancos.registrar', $cuenta->id) }}" class="mt-1 flex flex-wrap items-center gap-1">
                                        @csrf <input type="hidden" name="extracto_id" value="{{ $e->id }}">
                                        @php
                                            // El tipo que sugiere la descripción del banco
                                            $texto = mb_strtoupper((string) $e->descripcion);
                                            $sugerido = collect(['ITF' => 'ITF', 'COMISION' => 'COMISION', 'COMISIÓN' => 'COMISION', 'MANTENIMIENTO' => 'COMISION', 'INTERES' => 'INTERES',
                                                'INTERÉS' => 'INTERES', 'RETIRO' => 'RETIRO', 'DEPOSITO' => 'DEPOSITO', 'DEPÓSITO' => 'DEPOSITO'])
                                                ->first(fn ($tipo, $palabra) => str_contains($texto, $palabra) && isset(((float) $e->monto > 0 ? $entran : $salen)[$tipo]));
                                        @endphp
                                        <select name="tipo" class="h-8 py-0 pl-2 pr-8 rounded-lg border-gray-300 text-xs">
                                            @foreach ((float) $e->monto > 0 ? $entran : $salen as $clave => $nombre)<option value="{{ $clave }}" @selected($clave === $sugerido)>{{ $nombre }}</option>@endforeach
                                        </select>
                                        <button class="h-8 px-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold" title="No está en el sistema: se registra con su asiento y queda conciliado">Registrar en el sistema</button>
                                    </form>
                                </div>
                                <span class="shrink-0 font-bold {{ (float) $e->monto < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $soles($e->monto) }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-gray-400 text-sm">{{ $mesesExtracto->isEmpty() ? 'Sube el estado de cuenta para empezar.' : 'Todo lo del banco de este mes está conciliado ✔' }}</p>
                        @endforelse
                    </div>
                </section>
                <section class="bg-white rounded-2xl shadow-sm">
                    <header class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p class="font-bold text-gray-700">En el sistema, sin conciliar <span class="text-gray-400 font-semibold">({{ $sistema->count() }})</span></p>
                        <span class="text-sm font-semibold" x-text="'Marcado: S/ ' + suma(selSistema, montosSistema)"></span>
                    </header>
                    <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
                        @forelse ($sistema as $m)
                            @php $clave = $m['origen'].':'.$m['id']; @endphp
                            <label class="px-4 py-2 text-sm flex items-start gap-3 cursor-pointer" :class="selSistema.includes('{{ $clave }}') ? 'bg-indigo-50' : ''">
                                <input type="checkbox" value="{{ $clave }}" x-model="selSistema" class="mt-1 rounded text-indigo-600">
                                <span class="flex-1 min-w-0"><span class="block font-semibold text-gray-700 break-words">{{ $m['descripcion'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $dia($m['fecha']) }}{{ $m['operacion'] ? ' · Op. '.$m['operacion'] : '' }}</span></span>
                                <span class="shrink-0 font-bold {{ $m['monto'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $soles($m['monto']) }}</span>
                            </label>
                        @empty
                            <p class="px-4 py-8 text-center text-gray-400 text-sm">Nada pendiente del sistema en {{ $mesTexto }}.</p>
                        @endforelse
                    </div>
                </section>
            </div>
            <form method="POST" action="{{ route('bancos.conciliar', $cuenta->id) }}" class="sticky bottom-2 bg-gray-900 text-white rounded-2xl shadow-lg px-4 py-3 flex flex-wrap items-center gap-3"
                  x-show="selBanco.length || selSistema.length" x-cloak>
                @csrf
                <template x-for="id in selBanco" :key="'b' + id"><input type="hidden" name="extracto[]" :value="id"></template>
                <template x-for="k in selSistema" :key="'s' + k"><input type="hidden" name="sistema[]" :value="k"></template>
                <span class="text-sm">Banco <b x-text="'S/ ' + suma(selBanco, montosBanco)"></b> · Sistema <b x-text="'S/ ' + suma(selSistema, montosSistema)"></b></span>
                <span class="text-sm font-bold" :class="cuadra ? 'text-emerald-300' : 'text-amber-300'" x-text="cuadra ? '✔ Suman lo mismo' : 'Diferencia S/ ' + diferencia"></span>
                <button class="ml-auto h-9 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold disabled:opacity-40" :disabled="!cuadra || !selBanco.length || !selSistema.length">Conciliar marcados</button>
                <button type="button" @click="selBanco = []; selSistema = []" class="h-9 px-3 rounded-xl bg-white/10 text-sm">Limpiar</button>
            </form>

            @if ($grupos->isNotEmpty())
                <details class="bg-white rounded-2xl shadow-sm">
                    <summary class="cursor-pointer list-none px-4 py-3 font-bold text-gray-700">✔ Ya conciliado en {{ $mesTexto }} ({{ $grupos->count() }}) ▾</summary>
                    <div class="divide-y divide-gray-100">
                        @foreach ($grupos as $g)
                            <div class="px-4 py-2 text-sm flex flex-wrap items-center gap-3">
                                <span class="text-gray-500">{{ $dia($g->fecha) }}</span>
                                <span class="flex-1 min-w-0 font-semibold text-gray-700">{{ $g->banco }} <span class="text-gray-400 font-normal">↔ {{ $g->sistema }} movimiento(s) del sistema</span></span>
                                <span class="font-bold">{{ $soles($g->monto) }}</span>
                                <form method="POST" action="{{ route('bancos.deshacer', $cuenta->id) }}">@csrf <input type="hidden" name="grupo" value="{{ $g->grupo }}">
                                    <button class="text-xs font-semibold text-rose-600 hover:underline">Deshacer</button></form>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    @elseif ($vista === 'libro')
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Fecha</th><th class="text-left px-4 py-2">Movimiento</th><th class="text-right px-4 py-2">Entra</th>
                        <th class="text-right px-4 py-2">Sale</th><th class="text-right px-4 py-2">Saldo</th><th class="px-2 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr class="bg-slate-50/60"><td class="px-4 py-2" colspan="4">Saldo al {{ $dia(\Carbon\Carbon::parse($desde)->subDay()) }}</td>
                        <td class="px-4 py-2 text-right font-bold">{{ $soles($saldoAnterior) }}</td><td></td></tr>
                    @php $saldo = $saldoAnterior; @endphp
                    @forelse ($movimientos as $m)
                        @php $saldo += $m['monto']; $ok = isset($conciliados[$m['origen'].':'.$m['id']]); @endphp
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $dia($m['fecha']) }}</td>
                            <td class="px-4 py-2"><span class="font-semibold text-gray-700">{{ $m['descripcion'] }}</span>{{ $m['operacion'] ? ' · Op. '.$m['operacion'] : '' }}
                                @if ($ok)<span class="ml-1 text-xs font-bold text-emerald-600" title="Conciliado con el banco">✔</span>@endif</td>
                            <td class="px-4 py-2 text-right text-emerald-700 font-semibold whitespace-nowrap">{{ $m['monto'] > 0 ? $soles($m['monto']) : '' }}</td>
                            <td class="px-4 py-2 text-right text-rose-600 font-semibold whitespace-nowrap">{{ $m['monto'] < 0 ? $soles(-$m['monto']) : '' }}</td>
                            <td class="px-4 py-2 text-right font-bold whitespace-nowrap">{{ $soles($saldo) }}</td>
                            <td class="px-2 py-2 text-right">
                                @if ($m['origen'] === 'banco')
                                    <form method="POST" action="{{ route('bancos.movimiento.anular', $m['id']) }}" onsubmit="return confirm('¿Anular este movimiento? Se borra su asiento contable.')">
                                        @csrf <button class="text-xs font-semibold text-rose-600 hover:underline">Anular</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin movimientos en {{ $mesTexto }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        @php $r = $reporte; @endphp
        <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="flex-1 font-extrabold text-gray-800">Conciliación al {{ $dia($r['hasta']) }}</h2>
                <a href="{{ route('bancos.reporte.excel', ['id' => $cuenta->id, 'mes' => $mesValor]) }}" class="h-9 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Excel</a>
            </div>
            @if (! $r['hay_extracto'])
                <p class="rounded-xl bg-amber-50 text-amber-800 px-4 py-2 text-sm">Aún no subes el estado de cuenta de {{ $mesTexto }}: el saldo del banco no está completo.</p>
            @endif
            <table class="w-full max-w-2xl text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="py-2">Saldo según libros (el sistema)</td><td class="py-2 text-right font-bold">{{ $soles($r['saldo_libros']) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">(−) Del sistema que el banco aún no refleja ({{ $r['sistema_pendiente']->count() }})</td><td class="py-2 text-right">{{ $soles(-$r['total_sistema_pendiente']) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">(+) Del banco que el sistema aún no tiene ({{ $r['banco_pendiente']->count() }})</td><td class="py-2 text-right">{{ $soles($r['total_banco_pendiente']) }}</td></tr>
                    <tr class="font-bold"><td class="py-2">= Saldo calculado</td><td class="py-2 text-right">{{ $soles($r['calculado']) }}</td></tr>
                    <tr class="font-bold"><td class="py-2">Saldo según el banco {{ $r['saldo_banco_del_extracto'] ? '(del extracto)' : '(inicial + movimientos del extracto)' }}</td><td class="py-2 text-right">{{ $soles($r['saldo_banco']) }}</td></tr>
                    <tr class="text-lg font-extrabold {{ abs($r['diferencia']) < 0.005 ? 'text-emerald-700' : 'text-rose-600' }}">
                        <td class="py-2">{{ abs($r['diferencia']) < 0.005 ? '✔ Conciliado' : 'Diferencia sin explicar' }}</td><td class="py-2 text-right">{{ $soles($r['diferencia']) }}</td></tr>
                    <tr class="text-gray-500"><td class="py-2">Saldo de la cuenta {{ $cuenta->cuenta_contable }} en el Libro Mayor
                        <span class="block text-xs">Si difiere del saldo según libros: falta centralizar el mes, o la cuenta contable la comparten varias cuentas.</span></td>
                        <td class="py-2 text-right font-semibold">{{ $soles($r['saldo_mayor']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach (['Del sistema, pendientes en el banco' => $r['sistema_pendiente']->map(fn ($m) => (object) ['fecha' => $m['fecha'], 'descripcion' => $m['descripcion'], 'monto' => $m['monto']]),
                'Del banco, pendientes en el sistema' => $r['banco_pendiente']] as $titulo => $lista)
                <div class="bg-white rounded-2xl shadow-sm">
                    <p class="px-4 py-3 font-bold text-gray-700 border-b border-gray-100">{{ $titulo }}</p>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        @forelse ($lista as $x)
                            <div class="px-4 py-2 text-sm flex gap-3"><span class="text-gray-400 whitespace-nowrap">{{ $dia($x->fecha) }}</span>
                                <span class="flex-1 min-w-0 break-words">{{ $x->descripcion ?: '—' }}</span><span class="font-semibold whitespace-nowrap">{{ $soles($x->monto) }}</span></div>
                        @empty
                            <p class="px-4 py-6 text-center text-gray-400 text-sm">Nada pendiente.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@if ($vista === 'conciliar')
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('conciliarBanco', () => ({
            selBanco: [], selSistema: [],
            montosBanco: @json($banco->mapWithKeys(fn ($e) => [$e->id => (float) $e->monto])),
            montosSistema: @json($sistema->mapWithKeys(fn ($m) => [$m['origen'].':'.$m['id'] => $m['monto']])),
            total(sel, montos) { return sel.reduce((s, k) => s + (Number(montos[k]) || 0), 0); },
            suma(sel, montos) { return this.total(sel, montos).toFixed(2); },
            get diferencia() { return (this.total(this.selBanco, this.montosBanco) - this.total(this.selSistema, this.montosSistema)).toFixed(2); },
            get cuadra() { return Math.abs(this.total(this.selBanco, this.montosBanco) - this.total(this.selSistema, this.montosSistema)) < 0.005; },
        }));
    });
</script>
@endpush
@endif
