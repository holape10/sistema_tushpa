@extends('layouts.app')
@section('title', 'Reportes del colegio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>@media print { aside, nav, header, .no-print { display: none !important; } body { background: #fff; } }</style>
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $wa = fn ($tel) => ($n = preg_replace('/\D/', '', (string) $tel)) ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n) : null;
        $mensaje = fn ($a) => 'Estimado(a) '.($a->responsable->clinom ?? 'padre de familia').', le recordamos que '.$a->nombre.' tiene '
            .($a->meses === 1 ? '1 pensión vencida' : $a->meses.' pensiones vencidas').' por '.$soles($a->vencido).'. Gracias por su puntualidad.';
        $tabs = ['morosos' => ['Deben', 'fa-triangle-exclamation', $morosos->count()], 'aldia' => ['Al día', 'fa-circle-check', $alDia->count()],
            'meses' => ['Por mes', 'fa-calendar', null], 'grados' => ['Por grado', 'fa-layer-group', null],
            'pagos' => ['Pagos', 'fa-receipt', $pagos->count()], 'descuentos' => ['Descuentos y becas', 'fa-gift', null]];
    @endphp

    <div class="space-y-5" x-data="{ ver: @js(array_key_exists($ver, $tabs) ? $ver : 'morosos') }">
        <div class="flex flex-wrap items-end gap-3">
            <div class="mr-auto">
                <a href="{{ route('colegio.index') }}" class="no-print text-sm text-gray-500">← Alumnos</a>
                <h1 class="text-2xl font-black text-gray-800">Reportes del colegio · {{ $anio }}</h1>
                <p class="text-sm text-gray-500">Al {{ now()->format('d/m/Y H:i') }}</p>
            </div>
            <form class="no-print flex flex-wrap items-end gap-2 text-xs">
                <input type="hidden" name="ver" :value="ver">
                <label class="font-semibold text-gray-500">Año<input type="number" name="anio" value="{{ $anio }}" class="mt-1 block w-24 h-9 rounded-lg border-gray-300 text-sm"></label>
                <label class="font-semibold text-gray-500">Debe desde<select name="min" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm">
                    @foreach ([1, 2, 3, 4] as $n)<option value="{{ $n }}" @selected($min === $n)>{{ $n }} {{ $n === 1 ? 'mes' : 'meses' }}</option>@endforeach</select></label>
                <label class="font-semibold text-gray-500">Pagos del<input type="date" name="desde" value="{{ $desde }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
                <label class="font-semibold text-gray-500">al<input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
                <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white font-bold">Ver</button>
                <button type="button" onclick="window.print()" class="h-9 px-3 rounded-lg bg-white ring-1 ring-gray-200 font-bold"><i class="fas fa-print"></i></button>
            </form>
        </div>

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Alumnos matriculados</p><p class="text-2xl font-black text-gray-800">{{ $resumen->alumnos }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-emerald-600">Al día</p><p class="text-2xl font-black text-emerald-700">{{ $resumen->alDia }}</p>
                <p class="text-xs text-gray-400">{{ $resumen->alumnos ? round($resumen->alDia * 100 / $resumen->alumnos) : 0 }}% de los alumnos</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-rose-600">Deben (1 mes o más)</p><p class="text-2xl font-black text-rose-700">{{ $resumen->morosos }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-rose-600">Deuda vencida</p><p class="text-2xl font-black text-rose-700">{{ $soles($resumen->vencido) }}</p>
                <p class="text-xs text-gray-400">Por cobrar en el año {{ $soles($resumen->pendiente) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4 col-span-2 lg:col-span-1"><p class="text-xs font-semibold text-indigo-600">Cobrado del {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m') }}</p>
                <p class="text-2xl font-black text-indigo-700">{{ $soles($resumen->cobradoRango) }}</p></div>
        </div>

        <div class="no-print flex gap-2 overflow-x-auto pb-1">
            @foreach ($tabs as $k => [$nombre, $icono, $n])
                <button type="button" @click="ver = '{{ $k }}'" :class="ver === '{{ $k }}' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700'"
                        class="shrink-0 h-10 px-4 rounded-xl shadow-sm text-sm font-bold"><i class="fas {{ $icono }}"></i> {{ $nombre }}@if ($n !== null) <span class="opacity-60">({{ $n }})</span>@endif</button>
            @endforeach
        </div>

        {{-- Deben --}}
        <div x-show="ver === 'morosos'" class="solo-tab bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="p-4 font-black text-gray-800">Alumnos que deben {{ $min }} {{ $min === 1 ? 'mes' : 'meses' }} o más</h2>
            <table class="w-full text-sm min-w-[720px]">
                <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Alumno</th><th class="px-3 py-2">Grado</th><th class="px-3 py-2 text-center">Meses</th>
                    <th class="px-3 py-2 text-right">Vencido</th><th class="px-3 py-2">Último pago</th><th class="px-3 py-2">Responsable</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($morosos as $a)
                        <tr>
                            <td class="px-3 py-2"><a href="{{ route('colegio.ver', $a->id) }}" class="font-semibold text-gray-800 hover:text-indigo-600">{{ $a->nombre }}</a></td>
                            <td class="px-3 py-2 text-gray-600">{{ $a->grado }}</td>
                            <td class="px-3 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-xs font-black {{ $a->meses >= 3 ? 'bg-rose-600 text-white' : ($a->meses === 2 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">{{ $a->meses }}</span></td>
                            <td class="px-3 py-2 text-right font-bold text-rose-700">{{ $soles($a->vencido) }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $a->ultimo ? \Carbon\Carbon::parse($a->ultimo)->format('d/m/Y') : 'Nunca' }}</td>
                            <td class="px-3 py-2">
                                @if ($a->responsable)
                                    <span class="text-xs text-gray-400">{{ $a->responsable->tipo }}</span> {{ $a->responsable->clinom }}
                                    @if ($a->responsable->telefono)
                                        <span class="text-gray-500">· {{ $a->responsable->telefono }}</span>
                                        @if ($w = $wa($a->responsable->telefono))<a href="{{ $w }}?text={{ rawurlencode($mensaje($a)) }}" target="_blank" class="no-print ml-1 text-emerald-600 font-bold"><i class="fab fa-whatsapp"></i> Recordar</a>@endif
                                    @endif
                                @else <span class="text-gray-400">—</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-emerald-600 font-semibold">¡Nadie debe {{ $min }} {{ $min === 1 ? 'mes' : 'meses' }} o más! 🎉</td></tr>
                    @endforelse
                </tbody>
                @if ($morosos->isNotEmpty())
                    <tfoot class="font-black"><tr class="bg-gray-50"><td class="px-3 py-2" colspan="3">{{ $morosos->count() }} alumnos</td><td class="px-3 py-2 text-right text-rose-700">{{ $soles($morosos->sum('vencido')) }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>

        {{-- Al día --}}
        <div x-show="ver === 'aldia'" x-cloak class="solo-tab bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="p-4 font-black text-gray-800">Alumnos al día</h2>
            <table class="w-full text-sm min-w-[600px]">
                <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Alumno</th><th class="px-3 py-2">Grado</th><th class="px-3 py-2 text-right">Falta pagar (aún no vence)</th><th class="px-3 py-2">Último pago</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($alDia as $a)
                        <tr><td class="px-3 py-2"><a href="{{ route('colegio.ver', $a->id) }}" class="font-semibold text-gray-800 hover:text-indigo-600">{{ $a->nombre }}</a></td>
                            <td class="px-3 py-2 text-gray-600">{{ $a->grado }}</td><td class="px-3 py-2 text-right">{{ $soles($a->pendiente) }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $a->ultimo ? \Carbon\Carbon::parse($a->ultimo)->format('d/m/Y') : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-gray-400">Ningún alumno al día.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Por mes --}}
        <div x-show="ver === 'meses'" x-cloak class="solo-tab bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="p-4 font-black text-gray-800">Pensiones de cada mes</h2>
            <table class="w-full text-sm min-w-[720px]">
                <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Mes</th><th class="px-3 py-2 text-center">Alumnos</th><th class="px-3 py-2 text-right">Esperado</th>
                    <th class="px-3 py-2 text-right">Cobrado</th><th class="px-3 py-2 text-right">Gratis</th><th class="px-3 py-2 text-right">Falta</th><th class="px-3 py-2 w-40">Avance</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($porMes as $m)
                        @php($pct = $m->esperado > 0 ? min(100, round(($m->esperado - $m->pendiente) * 100 / $m->esperado)) : 100)
                        <tr>
                            <td class="px-3 py-2 font-semibold">{{ $m->mes }} @if ($m->vencio && $m->deben)<span class="ml-1 text-xs text-rose-600">{{ $m->deben }} deben</span>@endif</td>
                            <td class="px-3 py-2 text-center">{{ $m->alumnos }}</td><td class="px-3 py-2 text-right">{{ $soles($m->esperado) }}</td>
                            <td class="px-3 py-2 text-right font-bold text-emerald-700">{{ $soles($m->cobrado) }}</td><td class="px-3 py-2 text-right text-violet-700">{{ $soles($m->becado) }}</td>
                            <td class="px-3 py-2 text-right {{ $m->vencio && $m->pendiente > 0 ? 'text-rose-700 font-bold' : 'text-gray-500' }}">{{ $soles($m->pendiente) }}</td>
                            <td class="px-3 py-2"><div class="h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full {{ $pct >= 90 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-amber-400' : 'bg-rose-500') }}" style="width: {{ $pct }}%"></div></div>
                                <span class="text-xs text-gray-500">{{ $pct }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-gray-400">Sin pensiones en {{ $anio }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($porMes->isNotEmpty())
                    <tfoot class="font-black bg-gray-50"><tr><td class="px-3 py-2" colspan="2">Total</td><td class="px-3 py-2 text-right">{{ $soles($porMes->sum('esperado')) }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ $soles($porMes->sum('cobrado')) }}</td><td class="px-3 py-2 text-right text-violet-700">{{ $soles($porMes->sum('becado')) }}</td>
                        <td class="px-3 py-2 text-right">{{ $soles($porMes->sum('pendiente')) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>

        {{-- Por grado --}}
        <div x-show="ver === 'grados'" x-cloak class="solo-tab bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="p-4 font-black text-gray-800">Pensiones por grado</h2>
            <table class="w-full text-sm min-w-[640px]">
                <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Grado</th><th class="px-3 py-2 text-center">Alumnos</th><th class="px-3 py-2 text-center">Deben</th>
                    <th class="px-3 py-2 text-right">Esperado del año</th><th class="px-3 py-2 text-right">Cobrado</th><th class="px-3 py-2 text-right">Falta</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($porGrado as $g)
                        <tr><td class="px-3 py-2 font-semibold">{{ $g->grado }}</td><td class="px-3 py-2 text-center">{{ $g->alumnos }}</td>
                            <td class="px-3 py-2 text-center {{ $g->morosos ? 'text-rose-700 font-bold' : 'text-gray-400' }}">{{ $g->morosos }}</td>
                            <td class="px-3 py-2 text-right">{{ $soles($g->esperado) }}</td><td class="px-3 py-2 text-right text-emerald-700 font-bold">{{ $soles($g->cobrado) }}</td>
                            <td class="px-3 py-2 text-right">{{ $soles($g->pendiente) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-gray-400">Sin matrículas en {{ $anio }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($porGrado->isNotEmpty())
                    <tfoot class="font-black bg-gray-50"><tr><td class="px-3 py-2">Total</td><td class="px-3 py-2 text-center">{{ $porGrado->sum('alumnos') }}</td><td class="px-3 py-2 text-center">{{ $porGrado->sum('morosos') }}</td>
                        <td class="px-3 py-2 text-right">{{ $soles($porGrado->sum('esperado')) }}</td><td class="px-3 py-2 text-right text-emerald-700">{{ $soles($porGrado->sum('cobrado')) }}</td>
                        <td class="px-3 py-2 text-right">{{ $soles($porGrado->sum('pendiente')) }}</td></tr></tfoot>
                @endif
            </table>
        </div>

        {{-- Pagos --}}
        <div x-show="ver === 'pagos'" x-cloak class="solo-tab space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @forelse ($porConcepto as $c)
                    <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">{{ $c->concepto }} · {{ $c->pagos }}</p><p class="text-xl font-black text-gray-800">{{ $soles($c->monto) }}</p>
                        @if ($c->descuento > 0)<p class="text-xs text-emerald-600">{{ $soles($c->descuento) }} de descuento por adelantado</p>@endif</div>
                @empty
                    <p class="col-span-full bg-white rounded-2xl shadow-sm p-6 text-center text-gray-400 text-sm">Sin pagos del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}.</p>
                @endforelse
            </div>
            @if ($pagos->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                    <table class="w-full text-sm min-w-[760px]">
                        <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Alumno</th><th class="px-3 py-2">Concepto</th>
                            <th class="px-3 py-2">Pagó</th><th class="px-3 py-2">Comprobante</th><th class="px-3 py-2 text-right">Desc.</th><th class="px-3 py-2 text-right">Monto</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($pagos as $p)
                                <tr><td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($p->fecha)->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2"><a href="{{ route('colegio.ver', $p->alu_id) }}" class="font-semibold text-gray-800 hover:text-indigo-600">{{ $p->nombres }}</a></td>
                                    <td class="px-3 py-2">{{ $p->descripcion }}</td>
                                    <td class="px-3 py-2 text-gray-600"><span class="text-xs text-gray-400">{{ $p->pagador }}</span> {{ $p->ccanom }}</td>
                                    <td class="px-3 py-2"><a href="{{ route('cobros.voucher', $p->IdCpe_cabecera) }}" target="_blank" class="text-indigo-600 hover:underline">{{ $p->serdoc }}-{{ ltrim($p->numdoc, '0') }}</a></td>
                                    <td class="px-3 py-2 text-right text-emerald-600">{{ (float) $p->descuento > 0 ? $soles($p->descuento) : '' }}</td>
                                    <td class="px-3 py-2 text-right font-bold">{{ $soles($p->monto) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="font-black bg-gray-50"><tr><td class="px-3 py-2" colspan="5">{{ $pagos->count() }} pagos</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $soles($pagos->sum('descuento')) }}</td><td class="px-3 py-2 text-right">{{ $soles($pagos->sum('monto')) }}</td></tr></tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Descuentos y becas --}}
        <div x-show="ver === 'descuentos'" x-cloak class="solo-tab space-y-4">
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-violet-600">Descuento por hermanos o referidos</p><p class="text-2xl font-black text-violet-700">{{ $soles($resumen->dsctoGrupo) }}</p><p class="text-xs text-gray-400">en las pensiones del año</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-emerald-600">Descuento por pagar adelantado</p><p class="text-2xl font-black text-emerald-700">{{ $soles($resumen->dsctoAdelanto) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-indigo-600">Gratis (becas y sorteos)</p><p class="text-2xl font-black text-indigo-700">{{ $soles($resumen->becas) }}</p><p class="text-xs text-gray-400">{{ $resumen->nBecas }} conceptos</p></div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm min-w-[600px]">
                    <thead class="text-xs text-gray-500 text-left bg-gray-50"><tr><th class="px-3 py-2">Alumno</th><th class="px-3 py-2">Concepto</th><th class="px-3 py-2">Motivo</th><th class="px-3 py-2 text-right">Monto</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($becas as $b)
                            <tr><td class="px-3 py-2"><a href="{{ route('colegio.ver', $b->alu_id) }}" class="font-semibold text-gray-800 hover:text-indigo-600">{{ $b->nombres }}</a></td>
                                <td class="px-3 py-2">{{ $b->descripcion }}</td><td class="px-3 py-2 text-gray-600">{{ $b->motivo }}</td><td class="px-3 py-2 text-right font-bold">{{ $soles($b->monto) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-gray-400">Sin becas ni sorteos en {{ $anio }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
