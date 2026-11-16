{{--
    Filtros de la lista de comprobantes del .dbf y el buscador (número, RUC o nombre). Usa `filtro`, `busca` y `totales`
    del x-data de la pantalla. Variables: $compras
--}}
@php
    $filtros = ['todos' => 'Todos', 'descuadre' => 'No cuadran / IGV mal', 'excluidos' => 'No se llevan']
        + ($compras ? ['sinxml' => 'Sin detalle (XML)', 'sincuenta' => 'Sin Cta. cargo', 'xml' => 'Distinto al XML'] : [])
        + ['dolares' => 'En dólares'];
@endphp
<div class="flex flex-wrap items-center gap-2 text-sm">
    @foreach ($filtros as $valor => $texto)
        <button type="button" @click="filtro = '{{ $valor }}'" class="px-3 h-8 rounded-full font-semibold"
                :class="filtro === '{{ $valor }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'">{{ $texto }}
            @if ($valor === 'dolares')<span x-show="totales.en_dolares" x-text="'(' + totales.en_dolares + ')'"></span>@endif
            @if ($valor === 'xml')<span x-show="totales.distinto_xml" class="text-rose-500" :class="filtro === 'xml' && 'text-white'" x-text="'(' + totales.distinto_xml + ')'"></span>@endif
        </button>
    @endforeach
    <input type="search" x-model.debounce.200ms="busca" placeholder="🔍 Buscar número, RUC o nombre"
           class="h-8 w-60 rounded-full border-slate-300 text-sm">
    @include('empresas.sire.partials.agregar_comprobante', ['libro' => $compras ? 'compras' : 'ventas'])
    <span class="text-xs text-slate-400 ml-auto">Los cambios se guardan solos · desmarca «Llevar» lo que no va al .dbf.</span>
</div>
