{{--
    Un comprobante de la propuesta tal como va al .dbf: si se lleva, sus montos, sus cuentas y (en compras) lo que se
    compró con la cuenta de cada línea y su PDF. Lo usan "Contabilidad (DBF)" y "Compras con detalle".
    Variables: $c (de SireDbf::delPeriodo), $compras, $tipos, $cfg (cuentas por defecto)
--}}
@php
    $m = $c['montos'];
    // En compras se edita la base y el IGV de cada destino (IGV_1, IGV_2, IGV_3); el ISC va en "otros"
    if ($compras) {
        $m = collect(\App\Support\Contabilidad\SireDbf::MONTOS_COMPRA)->mapWithKeys(fn ($k) => [$k => (float) ($m[$k] ?? 0)])->all();
        $m['otros'] = round($m['otros'] + $m['isc'], 2);
        $m['isc'] = 0;
    }
    $sinCuenta = collect($c['lineas'])->filter(fn ($l) => ! $l->cuenta)->count();
    $xmlId = $c['lineas'][0]->compra_xml_id ?? null;
    $datosFila = ['clave' => $c['clave'], 'excluido' => $c['excluido'], 'ctacargo' => $c['ctacargo'], 'ctaabono' => $c['ctaabono'],
        'montos' => $m, 'diferencia' => $c['diferencia'], 'alerta' => $c['alerta'] ?? null, 'editado' => $c['editado'], 'redondeo' => $c['redondeo'],
        'xml' => (bool) $c['lineas'], 'sinCuenta' => $sinCuenta];
    $soles = fn ($n) => number_format((float) $n, 2);
@endphp
<section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="filaDbf(@js($datosFila))" x-show="visible(fila)"
         :class="{ 'opacity-50': fila.excluido, 'ring-2 ring-rose-300': !fila.excluido && (Math.abs(fila.diferencia) >= 0.01 || fila.alerta) }">
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
                <span x-show="fila.sinCuenta" class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700" x-text="fila.sinCuenta + ' línea(s) sin cuenta'"></span>
                @if ($xmlId)
                    <a href="{{ route('sire.detalle.pdf', $xmlId) }}" target="_blank" class="text-xs font-bold text-slate-600 hover:text-indigo-700">📄 PDF</a>
                @endif
                <button type="button" @click="detalle = !detalle" class="text-xs font-bold text-indigo-700 hover:underline" x-text="detalle ? 'Ocultar detalle' : 'Ver detalle ({{ count($c['lineas']) }})'"></button>
            @else
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">sin XML</span>
            @endif
        @endif
        <span x-show="guardando" class="text-[11px] text-slate-400">Guardando…</span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 {{ $compras ? 'lg:grid-cols-[repeat(11,minmax(0,1fr))]' : 'lg:grid-cols-[repeat(8,minmax(0,1fr))]' }} gap-2 px-4 pb-3 text-xs">
        @if ($compras)
            @foreach (['base_dg' => ['B.I. gravadas', ''], 'igv_dg' => ['IGV 1', 'bg-indigo-50/60'], 'base_dgng' => ['B.I. grav. y no grav.', ''],
                'igv_dgng' => ['IGV 2', 'bg-indigo-50/60'], 'base_dng' => ['B.I. no gravadas', ''], 'igv_dng' => ['IGV 3', 'bg-indigo-50/60'],
                'no_gravado' => ['No gravado', ''], 'otros' => ['Otros / ISC', ''], 'total' => ['Total', '']] as $k => [$texto, $fondo])
                <label class="font-semibold text-slate-500 whitespace-nowrap">{{ $texto }}
                    <input type="number" step="0.01" x-model.number="fila.montos.{{ $k }}" @change="cambiarMontos()"
                           class="block mt-0.5 w-full h-9 rounded-lg border-slate-300 px-2 text-right text-sm {{ $fondo }} {{ $k === 'total' ? 'font-bold' : '' }}"></label>
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
    <div x-show="Math.abs(fila.diferencia) >= 0.01 || fila.alerta || fila.editado || fila.redondeo" class="flex flex-wrap items-center gap-3 px-4 pb-3 text-xs">
        <span x-show="fila.redondeo && !fila.editado && Math.abs(fila.diferencia) < 0.01" class="font-semibold text-sky-700"
              x-text="'Redondeo del proveedor corregido solo: ' + soles(fila.redondeo) + ' (su XML no cuadraba por céntimos)'"></span>
        <span x-show="Math.abs(fila.diferencia) >= 0.01" class="font-bold text-rose-600" x-text="'No cuadra: las partes suman ' + soles(fila.montos.total - fila.diferencia) + ' y el total es ' + soles(fila.montos.total)"></span>
        <span x-show="fila.alerta" class="font-bold text-rose-600" x-text="'⚠ ' + fila.alerta"></span>
        <button type="button" x-show="fila.editado" @click="restaurar()" class="font-semibold text-slate-500 hover:text-indigo-700 underline">Volver a los montos de SUNAT</button>
    </div>

    @if ($compras && $c['lineas'])
        <div x-show="detalle" x-cloak class="border-t border-slate-100">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr><th class="text-left px-4 py-1.5">Lo que se compró</th><th class="text-right px-3 py-1.5">Cant.</th><th class="text-right px-3 py-1.5">V. unit.</th>
                            <th class="text-right px-3 py-1.5">Base</th><th class="text-right px-3 py-1.5">IGV</th><th class="text-left px-3 py-1.5 w-36">Cuenta</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($c['lineas'] as $l)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-1.5">{{ $l->descripcion }} @if ($l->codigo)<span class="text-slate-400">{{ $l->codigo }}</span>@endif</td>
                                <td class="px-3 py-1.5 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($l->cantidad, 4, '.', ''), '0'), '.') }} {{ $l->unidad }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $soles($l->precio) }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $soles($l->base) }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $soles($l->igv) }}</td>
                                <td class="px-3 py-1">
                                    <input type="text" inputmode="numeric" maxlength="12" value="{{ $l->cuenta }}" placeholder="{{ $cfg['cta_compras'] }}" data-vacia="{{ $l->cuenta ? '0' : '1' }}"
                                           @keydown.enter.prevent="cuentaLinea([{{ $l->id }}], $event.target)" @change="cuentaLinea([{{ $l->id }}], $event.target)"
                                           class="w-full h-8 rounded-lg border-slate-300 text-xs font-mono {{ $l->cuenta ? '' : 'bg-rose-50' }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($c['lineas']) > 1)
                <div class="flex flex-wrap items-center gap-2 px-4 py-2 bg-slate-50 text-xs">
                    <span class="text-slate-600">Todas las líneas a la cuenta</span>
                    <input type="text" inputmode="numeric" maxlength="12" placeholder="Ej. 6563" class="w-28 h-8 rounded-lg border-slate-300 text-xs font-mono"
                           @keydown.enter.prevent="cuentaLinea(@js(collect($c['lineas'])->pluck('id')->all()), $event.target, true)">
                    <span class="text-slate-400">y presiona Enter</span>
                </div>
            @endif
        </div>
    @endif
</section>
