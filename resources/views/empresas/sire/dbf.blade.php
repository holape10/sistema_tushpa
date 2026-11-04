@extends('layouts.app')
@section('title', 'SIRE a FoxPro (DBF)')

@php
    $tipos = ['01' => 'Factura', '02' => 'Rec. honorarios', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito', '12' => 'Ticket', '14' => 'Serv. público'];
    $nombrePeriodo = \Carbon\Carbon::createFromFormat('Ym', $periodo)->locale('es')->translatedFormat('F Y');
    $compras = $libro === 'compras';
    $tabla = $compras ? 'almaingr' : 'tienfact';
    $soles = fn ($n) => number_format((float) $n, 2);
    $sinXml = $compras ? collect($comprobantes)->filter(fn ($c) => ! $c['lineas'])->count() : 0;
    // Lo que se edita de cada fila: en compras la base y el IGV de su destino (gravada, mixta o no gravada)
    $fila = function (array $c) use ($compras) {
        $m = $c['montos'];
        if ($compras) {
            $destino = collect(['dg', 'dgng', 'dng'])->first(fn ($d) => ($m["base_{$d}"] ?? 0) != 0 || ($m["igv_{$d}"] ?? 0) != 0) ?? 'dg';
            $m = ['destino' => $destino, 'base' => round(($m['base_dg'] ?? 0) + ($m['base_dgng'] ?? 0) + ($m['base_dng'] ?? 0), 2),
                'igv' => round(($m['igv_dg'] ?? 0) + ($m['igv_dgng'] ?? 0) + ($m['igv_dng'] ?? 0), 2), 'no_gravado' => $m['no_gravado'] ?? 0,
                'otros' => round(($m['isc'] ?? 0) + ($m['otros'] ?? 0), 2), 'total' => $m['total'] ?? 0];
        }

        return ['clave' => $c['clave'], 'excluido' => $c['excluido'], 'ctacargo' => $c['ctacargo'], 'ctaabono' => $c['ctaabono'],
            'montos' => $m, 'diferencia' => $c['diferencia'], 'editado' => $c['editado'], 'xml' => (bool) $c['lineas']];
    };
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="sireDbf(@js($totales))">

    {{-- Pestañas del SIRE --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas (RVIE)</a>
        <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras (RCE)</a>
        <a href="{{ route('sire.detalle', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras con detalle</a>
        <span class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-indigo-600 text-white">Contabilidad (DBF)</span>
    </div>

    {{-- Periodo, libro y descarga --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="libro" value="{{ $libro }}">
            <label class="text-xs font-semibold text-slate-500">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>
        <div class="inline-flex rounded-xl bg-slate-100 p-1">
            @foreach (['compras' => 'Compras · almaingr', 'ventas' => 'Ventas · tienfact'] as $valor => $texto)
                <a href="{{ route('sire.dbf', ['libro' => $valor, 'periodo' => $periodo]) }}"
                   class="px-4 h-9 inline-flex items-center rounded-lg text-sm font-bold {{ $libro === $valor ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' }}">{{ $texto }}</a>
            @endforeach
        </div>
        <div class="flex-1"></div>
        <a href="{{ route('sire.dbf.descargar', [$libro, 'periodo' => $periodo]) }}" @click="if (totales.descuadrados) { $event.preventDefault(); tushpaAviso('Hay ' + totales.descuadrados + ' comprobante(s) que no cuadran: corrígelos o no los lleves.', false); }"
           class="h-10 px-5 inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold {{ $propuesta ? '' : 'pointer-events-none opacity-50' }}">
            ⬇ Descargar {{ $tabla }}.dbf
        </a>
    </section>

    @unless ($propuesta)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Primero trae la propuesta de {{ $compras ? 'compras' : 'ventas' }} de <strong>{{ $nombrePeriodo }}</strong> desde SUNAT.
            <a href="{{ route('sire.index', [$libro, 'periodo' => $periodo]) }}" class="font-bold underline">Ir a {{ $compras ? 'Compras (RCE)' : 'Ventas (RVIE)' }}</a>
        </div>
    @else
        @if ($compras && $sinXml)
            <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm flex flex-wrap items-center gap-2">
                <span class="flex-1">A <strong>{{ $sinXml }}</strong> compra(s) les falta el detalle (su XML). Con el detalle sabes qué se compró y eliges mejor la cuenta.</span>
                <a href="{{ route('sire.detalle', ['periodo' => $periodo]) }}" class="font-bold underline">Traer XML de SUNAT</a>
            </div>
        @endif

        {{-- Lo que se va a descargar --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Registros a descargar</p>
                <p class="text-2xl font-extrabold" x-text="totales.registros"></p>
                <p class="text-[11px] text-slate-400" x-text="totales.excluidos ? totales.excluidos + ' no se llevan' : 'de {{ count($comprobantes) }}'"></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">{{ $compras ? 'Base imponible' : 'Gravada' }}</p>
                <p class="text-xl font-extrabold" x-text="soles(totales.base)"></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">IGV</p>
                <p class="text-xl font-extrabold" x-text="soles(totales.igv)"></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">{{ $compras ? 'No gravado' : 'Exonerado / inafecto' }}</p>
                <p class="text-xl font-extrabold" x-text="soles(totales.no_gravado)"></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Total</p>
                <p class="text-xl font-extrabold text-indigo-700" x-text="soles(totales.total)"></p>
            </div>
            <button type="button" @click="filtro = filtro === 'descuadre' ? 'todos' : 'descuadre'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2"
                    :class="filtro === 'descuadre' ? 'ring-rose-500' : 'ring-transparent'">
                <p class="text-xs font-semibold text-slate-500">No cuadran</p>
                <p class="text-2xl font-extrabold" :class="totales.descuadrados ? 'text-rose-600' : 'text-emerald-600'" x-text="totales.descuadrados || '✔ 0'"></p>
            </button>
        </div>

        {{-- Las mismas cuentas a todos --}}
        <form method="POST" action="{{ route('sire.dbf.aplicar') }}" class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3 text-sm">
            @csrf
            <input type="hidden" name="libro" value="{{ $libro }}"><input type="hidden" name="periodo" value="{{ $periodo }}">
            <p class="w-full sm:w-auto sm:flex-1 text-slate-600"><strong class="text-slate-800">Mismas cuentas a todos</strong>
                <span class="block text-xs text-slate-500">{{ $compras ? 'Luego cambias las de cada compra según lo que se compró. El sistema recuerda la cuenta de cada proveedor para el próximo mes.' : 'Por ejemplo: cargo 1212 y abono 70001 para todas las ventas.' }}</span></p>
            <label class="text-xs font-semibold text-slate-500">Cta. cargo
                <input name="ctacargo" maxlength="12" inputmode="numeric" class="block mt-1 w-28 h-10 rounded-xl border-slate-300 text-sm font-mono" placeholder="{{ $compras ? '63141' : '1212' }}"></label>
            <label class="text-xs font-semibold text-slate-500">Cta. abono
                <input name="ctaabono" maxlength="12" inputmode="numeric" class="block mt-1 w-28 h-10 rounded-xl border-slate-300 text-sm font-mono" placeholder="{{ $compras ? '42121' : '70001' }}"></label>
            <button class="h-10 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold">Poner a todos</button>
        </form>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @foreach (['todos' => 'Todos', 'descuadre' => 'No cuadran', 'excluidos' => 'No se llevan'] + ($compras ? ['sinxml' => 'Sin detalle (XML)'] : []) as $valor => $texto)
                <button type="button" @click="filtro = '{{ $valor }}'" class="px-3 h-8 rounded-full font-semibold"
                        :class="filtro === '{{ $valor }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'">{{ $texto }}</button>
            @endforeach
            <span class="text-xs text-slate-400 ml-auto">Los cambios se guardan solos.</span>
        </div>

        {{-- Comprobantes --}}
        <div class="space-y-2">
            @forelse ($comprobantes as $c)
                <section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="filaDbf(@js($fila($c)))" x-show="visible(fila)"
                         :class="{ 'opacity-50': fila.excluido, 'ring-2 ring-rose-300': !fila.excluido && Math.abs(fila.diferencia) >= 0.01 }">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 cursor-pointer" title="Llevar al .dbf">
                            <input type="checkbox" :checked="!fila.excluido" @change="fila.excluido = !$event.target.checked; guardar({ excluido: fila.excluido })" class="rounded text-indigo-600">
                            Llevar
                        </label>
                        <span class="font-bold whitespace-nowrap">{{ $c['serie'] }}-{{ $c['numero'] }}</span>
                        <span class="text-xs text-slate-400 whitespace-nowrap">{{ $tipos[$c['tipo']] ?? 'Tipo '.$c['tipo'] }} · {{ $c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '' }}@if ($c['moneda'] !== 'PEN') · {{ $c['moneda'] }} TC {{ $c['tc'] }}@endif</span>
                        <span class="flex-1 min-w-[180px] text-sm text-slate-700">{{ $c['nombre'] }} <span class="text-xs text-slate-400">{{ $c['ruc'] }}</span>
                            @if ($c['anulado'])<span class="ml-1 rounded bg-slate-200 px-1.5 text-[11px] font-bold text-slate-600">ANULADO</span>@endif</span>
                        @if ($compras)
                            @if ($c['lineas'])
                                <button type="button" @click="detalle = !detalle" class="text-xs font-bold text-indigo-700 hover:underline" x-text="detalle ? 'Ocultar detalle' : 'Ver detalle ({{ count($c['lineas']) }})'"></button>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">sin XML</span>
                            @endif
                        @endif
                        <span x-show="guardando" class="text-[11px] text-slate-400">Guardando…</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-[repeat(8,minmax(0,1fr))] gap-2 px-4 pb-3 text-xs">
                        @if ($compras)
                            <label class="font-semibold text-slate-500">Destino
                                <select x-model="fila.montos.destino" @change="cambiarMontos()" class="block mt-0.5 w-full h-9 rounded-lg border-slate-300 text-xs">
                                    @foreach (\App\Support\Contabilidad\SireDbf::DESTINOS as $d => $texto)<option value="{{ $d }}">{{ $texto }}</option>@endforeach
                                </select></label>
                            @foreach (['base' => 'Base', 'igv' => 'IGV', 'no_gravado' => 'No gravado', 'otros' => 'Otros / ISC', 'total' => 'Total'] as $k => $texto)
                                <label class="font-semibold text-slate-500">{{ $texto }}
                                    <input type="number" step="0.01" x-model.number="fila.montos.{{ $k }}" @change="cambiarMontos()"
                                           class="block mt-0.5 w-full h-9 rounded-lg border-slate-300 text-right text-sm {{ $k === 'total' ? 'font-bold' : '' }}"></label>
                            @endforeach
                        @else
                            @foreach (['gravada' => 'Gravada', 'igv' => 'IGV', 'exonerada' => 'Exonerada', 'inafecta' => 'Inafecta', 'total' => 'Total'] as $k => $texto)
                                <div class="font-semibold text-slate-500">{{ $texto }}
                                    <p class="mt-0.5 h-9 flex items-center justify-end rounded-lg bg-slate-50 px-2 text-sm text-slate-800 {{ $k === 'total' ? 'font-bold' : '' }}">{{ $soles($c['montos'][$k] ?? 0) }}</p></div>
                            @endforeach
                            <div></div>
                        @endif
                        <label class="font-semibold text-slate-500">Cta. cargo
                            <input type="text" maxlength="12" inputmode="numeric" x-model="fila.ctacargo" @change="guardar({ ctacargo: fila.ctacargo })"
                                   class="block mt-0.5 w-full h-9 rounded-lg border-slate-300 text-sm font-mono"></label>
                        <label class="font-semibold text-slate-500">Cta. abono
                            <input type="text" maxlength="12" inputmode="numeric" x-model="fila.ctaabono" @change="guardar({ ctaabono: fila.ctaabono })"
                                   class="block mt-0.5 w-full h-9 rounded-lg border-slate-300 text-sm font-mono"></label>
                    </div>
                    <div x-show="Math.abs(fila.diferencia) >= 0.01 || fila.editado" class="flex flex-wrap items-center gap-3 px-4 pb-3 text-xs">
                        <span x-show="Math.abs(fila.diferencia) >= 0.01" class="font-bold text-rose-600" x-text="'No cuadra: las partes suman ' + soles(fila.montos.total - fila.diferencia) + ' y el total es ' + soles(fila.montos.total)"></span>
                        <button type="button" x-show="fila.editado" @click="restaurar()" class="font-semibold text-slate-500 hover:text-indigo-700 underline">Volver a los montos de SUNAT</button>
                    </div>

                    @if ($compras && $c['lineas'])
                        <div x-show="detalle" x-cloak class="border-t border-slate-100 overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500 uppercase">
                                    <tr><th class="text-left px-4 py-1.5">Lo que se compró</th><th class="text-right px-3 py-1.5">Cant.</th><th class="text-right px-3 py-1.5">V. unit.</th>
                                        <th class="text-right px-3 py-1.5">Base</th><th class="text-right px-3 py-1.5">IGV</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($c['lineas'] as $l)
                                        <tr class="border-t border-slate-100">
                                            <td class="px-4 py-1.5">{{ $l->descripcion }}</td>
                                            <td class="px-3 py-1.5 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($l->cantidad, 4, '.', ''), '0'), '.') }} {{ $l->unidad }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $soles($l->precio) }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $soles($l->base) }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $soles($l->igv) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">La propuesta de {{ $nombrePeriodo }} no tiene comprobantes.</div>
            @endforelse
        </div>
    @endunless
</div>

<script>
    const SIRE_DBF = { libro: @json($libro), periodo: @json($periodo), url: @json(route('sire.dbf.guardar')) };
    const solesDbf = n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function sireDbf(totales) {
        return {
            totales, filtro: 'todos', soles: solesDbf,
            visible(f) {
                return this.filtro === 'todos' || (this.filtro === 'descuadre' && !f.excluido && Math.abs(f.diferencia) >= 0.01)
                    || (this.filtro === 'excluidos' && f.excluido) || (this.filtro === 'sinxml' && !f.xml);
            },
        };
    }

    function filaDbf(fila) {
        return {
            fila, detalle: false, guardando: false, soles: solesDbf,
            async guardar(cambios) {
                this.guardando = true;
                try {
                    const r = await fetch(SIRE_DBF.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ libro: SIRE_DBF.libro, periodo: SIRE_DBF.periodo, clave: this.fila.clave, ...cambios }),
                    });
                    const d = await r.json();
                    if (!r.ok) {
                        tushpaAviso(d.message || 'No se pudo guardar.', false);
                        return;
                    }
                    this.fila.diferencia = d.diferencia;
                    this.fila.editado = d.editado;
                    this.totales = d.totales;
                } catch (e) {
                    tushpaAviso('Se cortó la conexión: el cambio no se guardó.', false);
                } finally {
                    this.guardando = false;
                }
            },
            /** Al tocar un monto se ve al instante si cuadra; se guarda igual */
            cambiarMontos() {
                const m = this.fila.montos;
                this.fila.diferencia = Math.round((m.total - (m.base + m.igv + m.no_gravado + m.otros)) * 100) / 100;
                this.guardar({ montos: m });
            },
            async restaurar() {
                await this.guardar({ montos: null });
                location.reload();
            },
        };
    }
</script>
@endsection
