@extends('layouts.app')
@section('title', 'Caja - Turno ' . $turno->turno)
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <span class="inline-block px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">TURNO ABIERTO</span>
            <span class="text-sm text-gray-600 ml-2">N° {{ $turno->turno }} · desde {{ $turno->apertura->format('d/m/Y H:i') }} · {{ auth()->user()->apeusu }}</span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('comandas.seleccion') }}" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Ir a Comandas</a>
            <a href="{{ route('turnos.show', $turno->id_turno) }}" target="_blank" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Ver reporte</a>
        </div>
    </div>

    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ([
            ['Fondo inicial', $turno->monto, 'text-gray-800'],
            ['Ventas del turno', $r['ventasTotal'], 'text-indigo-700'],
            ['Otros ingresos', $r['ingresos'], 'text-green-700'],
            ['Egresos / gastos', $r['egresos'], 'text-red-600'],
            ['Efectivo esperado', $r['efectivoEsperado'], 'text-emerald-700'],
        ] as [$label, $valor, $color])
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                <p class="text-xl font-bold {{ $color }}">S/ {{ number_format($valor, 2) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Ventas por medio de pago --}}
            @php
                // Color del botón según el medio de pago
                $colorMedio = function ($nombre) {
                    $n = strtoupper((string) $nombre);
                    return match (true) {
                        str_contains($n, 'EFECTIVO') => 'bg-emerald-600',
                        str_contains($n, 'YAPE') => 'bg-purple-600',
                        str_contains($n, 'PLIN') => 'bg-cyan-600',
                        str_contains($n, 'TARJETA'), str_contains($n, 'VISA'), str_contains($n, 'POS') => 'bg-blue-600',
                        str_contains($n, 'TRANSF'), str_contains($n, 'DEPOS') => 'bg-amber-600',
                        default => 'bg-slate-600',
                    };
                };
            @endphp
            <div class="bg-white rounded-2xl shadow-sm p-4" x-data="{ medio: null }">
                <h3 class="font-semibold text-gray-700 mb-3">Ventas por medio de pago</h3>

                @if ($r['ventasPorMedio']->isEmpty())
                    <p class="text-sm text-gray-400">Aún no hay ventas en este turno.</p>
                @else
                    {{-- Solo aparecen los medios que se cobraron en este turno --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                        @foreach ($r['ventasPorMedio'] as $v)
                            <button type="button" @click="medio = (medio === {{ (int) $v->id_med_pag }} ? null : {{ (int) $v->id_med_pag }})"
                                    :class="medio === {{ (int) $v->id_med_pag }} ? 'ring-4 ring-offset-2 ring-indigo-300' : ''"
                                    class="{{ $colorMedio($v->nom_med_pag) }} text-white rounded-xl p-3 text-left shadow-sm hover:opacity-90 transition">
                                <span class="block text-xs font-semibold uppercase opacity-90">{{ $v->nom_med_pag ?? 'SIN MEDIO' }}</span>
                                <span class="block text-xl font-bold">S/ {{ number_format($v->monto, 2) }}</span>
                                <span class="block text-xs opacity-80">{{ $v->operaciones }} {{ $v->operaciones == 1 ? 'cobro' : 'cobros' }}</span>
                            </button>
                        @endforeach
                        @if ($r['ventasCredito'] > 0)
                            <div class="bg-amber-100 text-amber-800 rounded-xl p-3">
                                <span class="block text-xs font-semibold uppercase">Crédito (no entra a caja)</span>
                                <span class="block text-xl font-bold">S/ {{ number_format($r['ventasCredito'], 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-gray-500" x-text="medio === null ? 'Todos los cobros del turno' : 'Cobros con el medio seleccionado'"></p>
                        <button type="button" x-show="medio !== null" x-cloak @click="medio = null" class="text-xs text-indigo-600 hover:underline">Ver todos</button>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">Hora</th><th class="text-left">Comprobante</th><th class="text-left">Mesa / Cliente</th><th class="text-left">Medio</th><th class="text-right">Monto</th></tr></thead>
                            <tbody>
                                @foreach ($r['ventas'] as $vt)
                                    <tr class="border-t border-gray-100" x-show="medio === null || medio === {{ (int) $vt->id_med_pag }}">
                                        <td class="py-2">{{ \Carbon\Carbon::parse($vt->fecha_hora)->format('H:i') }}</td>
                                        <td><a href="{{ route('cobros.voucher', $vt->IdCpe_cabecera) }}" target="_blank" class="text-indigo-600 hover:underline">{{ $vt->serdoc }}-{{ str_pad($vt->numdoc, 8, '0', STR_PAD_LEFT) }}</a></td>
                                        <td>{{ $vt->mes_nom ?? strtoupper($vt->ped_tip ?? '') }} <span class="text-xs text-gray-400">{{ $vt->ccanom }}</span></td>
                                        <td><span class="px-2 py-0.5 rounded-full text-xs text-white {{ $colorMedio($vt->nom_med_pag) }}">{{ $vt->nom_med_pag }}</span></td>
                                        <td class="text-right font-semibold">
                                            S/ {{ number_format($vt->monto, 2) }}
                                            @if ((float) $vt->monto != (float) $vt->ccaitv)
                                                <span class="block text-xs font-normal text-gray-400">de {{ number_format($vt->ccaitv, 2) }} (mixto)</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($r['comprobantes']->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @foreach ($r['comprobantes'] as $c)
                            <span class="px-2 py-1 rounded-lg bg-gray-100 text-gray-600">{{ $c->tdodes }}: {{ $c->cantidad }} · S/ {{ number_format($c->total, 2) }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Movimientos de caja --}}
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-3">Ingresos y egresos de caja</h3>
                <form method="POST" action="{{ route('turnos.movimiento') }}" class="grid sm:grid-cols-6 gap-2 mb-4">
                    @csrf
                    <select name="tip_caj_id" required class="sm:col-span-2 rounded-lg border-gray-300 text-sm">
                        @foreach ($tiposCaja as $t)
                            <option value="{{ $t->tip_caj_id }}">{{ $t->tipo == 'ENTRADA' ? '➕' : '➖' }} {{ $t->tip_caj_nom }}</option>
                        @endforeach
                    </select>
                    <input name="mov_com" required maxlength="255" placeholder="Descripción (ej. compra de gas)" class="sm:col-span-2 rounded-lg border-gray-300 text-sm">
                    <input name="importe" type="number" step="0.01" min="0.01" required placeholder="Importe" class="rounded-lg border-gray-300 text-sm">
                    <button class="rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 py-2">Registrar</button>
                </form>
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">Hora</th><th class="text-left">Tipo</th><th class="text-left">Descripción</th><th class="text-right">Importe</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($r['movimientos'] as $m)
                        <tr class="border-t border-gray-100 {{ $m->estado == 'ANULADO' ? 'line-through text-gray-400' : '' }}">
                            <td class="py-2">{{ \Carbon\Carbon::parse($m->registro)->format('H:i') }}</td>
                            <td>{{ $m->tip_caj_nom }}</td>
                            <td>{{ $m->mov_com }}</td>
                            <td class="text-right font-semibold {{ $m->tipo == 'ENTRADA' ? 'text-green-700' : 'text-red-600' }}">{{ $m->tipo == 'ENTRADA' ? '+' : '-' }} {{ number_format($m->importe, 2) }}</td>
                            <td class="text-right">
                                @if ($m->estado == 'ACTIVO')
                                    <form method="POST" action="{{ route('turnos.anular_movimiento', $m->mov_caj_id) }}" onsubmit="return confirm('¿Anular este movimiento?')">
                                        @csrf<button class="text-xs text-red-600 hover:underline">Anular</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-center text-gray-400">Sin movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cierre de turno --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 h-fit" x-data="cierre({{ $r['efectivoEsperado'] }})">
            <h3 class="font-semibold text-gray-700 mb-3">Cerrar turno (arqueo)</h3>
            @if ($pedidosAbiertos > 0)
                <div class="mb-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-xs">
                    ⚠️ Hay {{ $pedidosAbiertos }} pedido(s) sin cobrar en la sucursal.
                </div>
            @endif
            <form method="POST" action="{{ route('turnos.cerrar') }}" onsubmit="return confirm('¿Cerrar el turno? Ya no podrás cobrar hasta abrir otro.')">
                @csrf
                <label class="block text-sm text-gray-700 mb-1">Efectivo contado (S/)</label>
                <input type="number" step="0.01" min="0" name="montocierre" x-model="contado" required placeholder="0.00"
                       :readonly="verArqueo" :class="verArqueo ? 'bg-gray-50' : ''"
                       class="w-full rounded-lg border-gray-300 text-lg font-bold text-center mb-2">

                {{-- Conteo de billetes y monedas: opcional, oculto por defecto --}}
                <button type="button" @click="alternar()" class="text-sm text-indigo-600 hover:underline mb-2">
                    <span x-text="verArqueo ? '▾ No contar billetes y monedas' : '▸ Contar billetes y monedas (opcional)'"></span>
                </button>
                <div x-show="verArqueo" x-cloak class="grid grid-cols-2 gap-2 mb-3">
                    @foreach ($denominaciones as $campo => $valor)
                        <label class="block">
                            <span class="text-xs text-gray-500">{{ $valor < 1 ? (int) round($valor * 100) . ' cént.' : 'S/ ' . $valor }}</span>
                            {{-- deshabilitados cuando el conteo está oculto, así no se envían --}}
                            <input type="number" min="0" step="1" name="cierre_{{ $campo }}" value="0" data-valor="{{ $valor }}"
                                   :disabled="!verArqueo" @input="sumar()" class="den-cierre w-full rounded-lg border-gray-300 text-sm">
                        </label>
                    @endforeach
                </div>
                <div class="text-sm space-y-1 mb-4">
                    <div class="flex justify-between"><span>Esperado</span><span class="font-semibold">S/ <span x-text="esperado.toFixed(2)"></span></span></div>
                    <div class="flex justify-between"><span>Diferencia</span>
                        <span class="font-bold" :class="dif() < 0 ? 'text-red-600' : (dif() > 0 ? 'text-amber-600' : 'text-green-700')">
                            S/ <span x-text="dif().toFixed(2)"></span>
                        </span>
                    </div>
                </div>
                <button class="w-full py-3 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700">CERRAR TURNO</button>
            </form>
        </div>
    </div>

    <script>
        function cierre(esperado) {
            return {
                esperado, contado: '', verArqueo: false,
                alternar() {
                    this.verArqueo = !this.verArqueo;
                    if (this.verArqueo) this.sumar();
                },
                sumar() {
                    let t = 0;
                    document.querySelectorAll('.den-cierre').forEach(i => t += (parseInt(i.value) || 0) * parseFloat(i.dataset.valor));
                    this.contado = t.toFixed(2);
                },
                dif() { return (parseFloat(this.contado) || 0) - this.esperado; }
            };
        }
    </script>
@endsection
