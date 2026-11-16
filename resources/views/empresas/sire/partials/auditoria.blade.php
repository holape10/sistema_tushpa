{{--
    Auditoría del mes: sumas por tipo de documento (lo que se lleva al .dbf, lo que dice SUNAT y lo que dicen los XML)
    y, comprobante por comprobante, por qué no cuadra. Al tocar una fila o un comprobante, filtra la lista de abajo (usa
    `tipoDoc`, `busca` y `filtro` del x-data de la pantalla). Variables: $auditoria (SireAuditoria::calcular), $compras
--}}
@php
    $a = $auditoria;
    $nombre = fn (string $t) => \App\Support\Contabilidad\SireDbf::NOMBRES_TIPO[$t] ?? 'Tipo '.$t;
    $n = fn ($v) => number_format((float) $v, 2);
    $graves = collect($a['motivos'])->where('grave', true);
    $avisos = collect($a['motivos'])->where('grave', false);
    $cuadra = abs($a['todos']['dif_sunat']) < 0.01 && (! $compras || abs($a['todos']['dif_xml']) < 0.01) && $graves->isEmpty();
@endphp
<section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{ abierta: {{ $cuadra ? 'false' : 'true' }} }">
    <button type="button" @click="abierta = !abierta" class="w-full flex flex-wrap items-center gap-3 px-4 py-3 text-left">
        <span class="font-bold text-slate-800">🧮 Sumas y cuadre (auditoría)</span>
        @if ($cuadra)
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700">✔ Todo cuadra</span>
        @else
            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700">{{ $graves->count() }} cosa(s) por revisar</span>
        @endif
        <span class="text-xs text-slate-400 flex-1">Todo en soles (los de dólares con su TC). Las notas de crédito restan.</span>
        <a href="{{ request()->fullUrl() }}" @click.stop class="text-xs font-semibold text-indigo-700 hover:underline">↻ Recalcular</a>
        <span x-text="abierta ? '−' : '+'" class="text-slate-400 text-lg w-4 text-center"></span>
    </button>
    <div x-show="abierta" x-cloak class="border-t border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Documento</th>
                        <th class="text-right px-3 py-2" title="Se llevan / del mes">Cant.</th>
                        <th class="text-right px-3 py-2">Base</th>
                        <th class="text-right px-3 py-2">IGV</th>
                        <th class="text-right px-3 py-2">{{ $compras ? 'No gravado' : 'Exon./Inaf.' }}</th>
                        <th class="text-right px-3 py-2">Otros/ISC</th>
                        <th class="text-right px-3 py-2 bg-indigo-50 text-indigo-700">Total .dbf</th>
                        <th class="text-right px-3 py-2">SUNAT</th>
                        <th class="text-right px-3 py-2">Dif. SUNAT</th>
                        @if ($compras)
                            <th class="text-right px-3 py-2" title="Suma de los XML (de los que se llevan)">XML</th>
                            <th class="text-right px-3 py-2">Dif. XML</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ([...array_values($a['filas']), $a['todos']] as $f)
                        @php $todos = $f['tipo'] === 'todos'; @endphp
                        <tr class="{{ $todos ? 'bg-slate-50 font-extrabold' : 'cursor-pointer hover:bg-indigo-50/50' }}"
                            @unless ($todos) @click="tipoDoc = @js($f['tipo']); filtro = 'todos'; busca = ''" title="Ver solo estos abajo" @endunless>
                            <td class="px-3 py-1.5 whitespace-nowrap">{{ $todos ? 'TODOS' : $nombre($f['tipo']) }}
                                @if ($f['excluidos'])<span class="ml-1 text-[11px] font-semibold text-amber-700">({{ $f['excluidos'] }} no se llevan)</span>@endif
                                @if ($f['manuales'])<span class="ml-1 text-[11px] font-semibold text-indigo-600">({{ $f['manuales'] }} a mano)</span>@endif</td>
                            <td class="px-3 py-1.5 text-right">{{ $f['llevan'] }}<span class="text-slate-400">/{{ $f['cantidad'] }}</span></td>
                            <td class="px-3 py-1.5 text-right">{{ $n($f['base']) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $n($f['igv']) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $n($f['no_gravado']) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $n($f['otros']) }}</td>
                            <td class="px-3 py-1.5 text-right bg-indigo-50/60 font-bold text-indigo-800">{{ $n($f['total']) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $n($f['sunat']) }}</td>
                            <td class="px-3 py-1.5 text-right font-bold {{ abs($f['dif_sunat']) >= 0.01 ? 'text-rose-600' : 'text-emerald-600' }}">{{ abs($f['dif_sunat']) >= 0.01 ? $n($f['dif_sunat']) : '✔' }}</td>
                            @if ($compras)
                                <td class="px-3 py-1.5 text-right">{{ $n($f['xml_de_llevan']) }}
                                    @if ($f['con_xml'] < $f['cantidad'])<span class="block text-[10px] text-amber-700">{{ $f['cantidad'] - $f['con_xml'] }} sin XML</span>@endif</td>
                                <td class="px-3 py-1.5 text-right font-bold {{ abs($f['dif_xml']) >= 0.01 ? 'text-rose-600' : 'text-emerald-600' }}">{{ abs($f['dif_xml']) >= 0.01 ? $n($f['dif_xml']) : '✔' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-[11px] text-slate-400 border-t border-slate-100">
            <b>Total .dbf</b>: lo que se descarga. <b>SUNAT</b>: lo de la propuesta (los agregados a mano no están). <b>Dif. SUNAT</b> sale por lo que no se lleva
            y por los montos cambiados a mano.@if ($compras) <b>XML</b>: lo que dicen las facturas de los proveedores (solo de las que tienen XML).@endif
            Toca un documento para ver solo esos abajo.
        </p>

        {{-- Por qué no cuadra --}}
        <div class="border-t border-slate-100 px-4 py-3 space-y-2">
            <p class="text-sm font-bold text-slate-700">¿Por qué no cuadra?</p>
            @forelse ($graves as $m)
                <button type="button" @click="busca = @js($m['comprobante']); tipoDoc = ''; filtro = 'todos'; $nextTick(() => document.getElementById('lista-dbf')?.scrollIntoView({ behavior: 'smooth' }))"
                        class="w-full flex flex-wrap items-baseline gap-x-2 rounded-lg bg-rose-50 px-3 py-1.5 text-left text-xs hover:bg-rose-100">
                    <span class="font-bold text-slate-800 whitespace-nowrap">{{ $nombre($m['tipo']) }} {{ $m['comprobante'] }}</span>
                    <span class="text-slate-500">{{ \Illuminate\Support\Str::limit($m['nombre'], 40) }}</span>
                    <span class="flex-1 min-w-[200px] font-semibold text-rose-700">{{ $m['texto'] }}</span>
                    @if (abs($m['efecto']) >= 0.01)<span class="font-bold text-rose-700 whitespace-nowrap">{{ $m['efecto'] > 0 ? '+' : '' }}{{ $n($m['efecto']) }}</span>@endif
                </button>
            @empty
                <p class="text-xs font-semibold text-emerald-700">✔ Ningún comprobante con problemas.</p>
            @endforelse
            @if ($avisos->isNotEmpty())
                <details class="text-xs">
                    <summary class="cursor-pointer font-semibold text-slate-500">Avisos para revisar ({{ $avisos->count() }}): no se llevan, a mano, sin XML, en dólares…</summary>
                    <div class="mt-2 space-y-1">
                        @foreach ($avisos as $m)
                            <button type="button" @click="busca = @js($m['comprobante']); tipoDoc = ''; filtro = 'todos'; $nextTick(() => document.getElementById('lista-dbf')?.scrollIntoView({ behavior: 'smooth' }))"
                                    class="w-full flex flex-wrap items-baseline gap-x-2 rounded-lg bg-slate-50 px-3 py-1 text-left hover:bg-slate-100">
                                <span class="font-bold text-slate-700 whitespace-nowrap">{{ $nombre($m['tipo']) }} {{ $m['comprobante'] }}</span>
                                <span class="text-slate-500">{{ \Illuminate\Support\Str::limit($m['nombre'], 40) }}</span>
                                <span class="flex-1 min-w-[200px] text-slate-600">{{ $m['texto'] }}</span>
                                @if (abs($m['efecto']) >= 0.01)<span class="font-semibold text-slate-700 whitespace-nowrap">{{ $m['efecto'] > 0 ? '+' : '' }}{{ $n($m['efecto']) }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>
</section>
