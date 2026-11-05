{{--
    Lo que se va a descargar al .dbf (y, debajo de cada monto, lo que no se lleva y lo que dice SUNAT). Usa `totales`
    y `filtro` del x-data de la pantalla. Variables: $compras, $comprobantes
--}}
@php
    $tarjetas = [['base', $compras ? 'Base imponible' : 'Gravada', ''], ['igv', 'IGV', ''],
        ['no_gravado', $compras ? 'No gravado' : 'Exonerado / inafecto', ''], ['total', 'Total', 'text-indigo-700']];
@endphp
<div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs font-semibold text-slate-500">Registros a descargar</p>
        <p class="text-2xl font-extrabold" x-text="totales.registros"></p>
        <p class="text-[11px]" :class="totales.excluidos ? 'font-semibold text-amber-700' : 'text-slate-400'"
           x-text="totales.excluidos ? totales.excluidos + ' no se llevan' : 'de {{ count($comprobantes) }}'"></p>
    </div>
    @foreach ($tarjetas as [$campo, $texto, $color])
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">{{ $texto }}</p>
            <p class="text-xl font-extrabold {{ $color }}" x-text="soles(totales.{{ $campo }})"></p>
            <template x-if="totales.no_llevan && totales.no_llevan.{{ $campo }}">
                <div class="text-[11px] leading-tight mt-0.5">
                    <p class="font-semibold text-amber-700" x-text="'− ' + soles(totales.no_llevan.{{ $campo }}) + ' no se llevan'"></p>
                    <p class="text-slate-400" x-text="'SUNAT: ' + soles(totales.{{ $campo }} + totales.no_llevan.{{ $campo }})"></p>
                </div>
            </template>
        </div>
    @endforeach
    <button type="button" @click="filtro = filtro === 'descuadre' ? 'todos' : 'descuadre'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2"
            :class="filtro === 'descuadre' ? 'ring-rose-500' : 'ring-transparent'">
        <p class="text-xs font-semibold text-slate-500">No cuadran / IGV mal</p>
        <p class="text-2xl font-extrabold" :class="totales.descuadrados ? 'text-rose-600' : 'text-emerald-600'" x-text="totales.descuadrados || '✔ 0'"></p>
    </button>
</div>
