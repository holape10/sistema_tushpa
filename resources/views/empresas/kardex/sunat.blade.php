@extends('layouts.app')
@section('title', 'Kardex SUNAT')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $n = fn ($x, $d = 2) => number_format((float) $x, $d);
        $q = fn ($x) => rtrim(rtrim(number_format((float) $x, 4), '0'), '.') ?: '0';
        $valorizado = $formato === '131';
        [$codigoFormato, $nombreFormato] = \App\Support\KardexSunat::FORMATOS[$formato];
        $avisos = $registro['avisos'];
        $consulta = request()->query();
    @endphp
    <style>@media print { aside, header, nav, .no-imprimir { display: none !important; } main, body { background: #fff !important; } .bloque { break-inside: avoid; } }</style>

    <div class="space-y-5">
        @include('empresas.partials.alert')
        <div class="flex flex-wrap items-start justify-between gap-3 no-imprimir">
            <div>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-file-contract text-red-700"></i> Kardex SUNAT</h1>
                <p class="text-sm text-gray-500 max-w-3xl">Registro de Inventario Permanente oficial, con su archivo para el PLE.
                    <b>12.1 en unidades físicas</b>: obligatorio con ingresos del año anterior de 500 a 1,500 UIT.
                    <b>13.1 valorizado</b>: obligatorio con más de 1,500 UIT.</p>
            </div>
            <a href="{{ route('kardex.index') }}" class="text-sm text-gray-500">← Kardex</a>
        </div>

        {{-- Filtros --}}
        <form class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3 no-imprimir">
            <label class="text-xs font-semibold text-gray-500">Periodo<input type="month" name="periodo" value="{{ $periodo }}" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-500">Formato
                <select name="formato" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm">
                    @foreach (\App\Support\KardexSunat::FORMATOS as $clave => [$cod, $nom])<option value="{{ $clave }}" @selected($formato === (string) $clave)>{{ $cod }} · {{ (string) $clave === '121' ? 'Unidades físicas' : 'Valorizado' }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-500">Método de valuación (tabla 14)
                <select name="metodo" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm">
                    @foreach (\App\Support\KardexSunat::METODOS as $clave => $nom)<option value="{{ $clave }}" @selected($metodo === (string) $clave)>{{ $nom }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-500">Almacén
                <select name="almacen" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected($almacen == $a->id_almacen)>{{ $a->descripcion }}</option>@endforeach
                </select></label>
            <button class="h-10 px-5 rounded-lg bg-gray-800 text-white text-sm font-bold">Ver</button>
            <div class="flex-1"></div>
            <a href="{{ route('kardex.sunat.txt', $consulta) }}" class="h-10 px-4 rounded-lg bg-red-700 text-white text-sm font-bold flex items-center gap-2" title="{{ $archivo }}"><i class="fas fa-file-arrow-down"></i> TXT para el PLE</a>
            <a href="{{ route('kardex.sunat.excel', $consulta) }}" class="h-10 px-4 rounded-lg bg-emerald-600 text-white text-sm font-bold flex items-center gap-2"><i class="fas fa-file-excel"></i> Excel</a>
            <button type="button" onclick="window.print()" class="h-10 px-3 rounded-lg bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-print"></i></button>
        </form>
        <p class="text-xs text-gray-400 no-imprimir">Archivo: <span class="font-mono">{{ $archivo }}</span> — se sube en el PLE de SUNAT (o en el SLE-PLE) en el libro {{ $codigoFormato }} del periodo.</p>

        {{-- Lo que SUNAT observaría --}}
        @if ($avisos['sin_unspsc'] || $avisos['sin_comprobante'] || $avisos['negativos'] || $avisos['sin_costo'])
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-amber-900 space-y-2 no-imprimir" x-data="{ abierto: false }">
                <p class="font-black"><i class="fas fa-triangle-exclamation"></i> Revisa antes de presentar</p>
                @if ($avisos['sin_unspsc'])
                    <p class="text-sm"><b>{{ count($avisos['sin_unspsc']) }} producto(s) sin código de existencia</b> (UNSPSC o CUBSO, campos 8 y 9 del PLE).
                        <button type="button" @click="abierto = !abierto" class="underline font-bold">Ponerles su código</button></p>
                @endif
                @if ($avisos['sin_comprobante'])
                    <p class="text-sm"><b>{{ $avisos['sin_comprobante'] }} venta(s) o compra(s) sin comprobante de pago</b> (notas de venta o documentos internos): van como operación 99 "Otros", porque SUNAT no acepta una venta sin comprobante.</p>
                @endif
                @if ($avisos['sin_costo'])
                    <p class="text-sm"><b>{{ $avisos['sin_costo'] }} compra(s) sin costo</b>: entran al último costo conocido. Corrige el costo en la compra para que el valorizado sea exacto.</p>
                @endif
                @if ($avisos['negativos'])
                    <p class="text-sm"><b>{{ count($avisos['negativos']) }} producto(s) terminan el mes con stock negativo</b> (se vendió sin stock): {{ collect($avisos['negativos'])->take(5)->implode(', ') }}{{ count($avisos['negativos']) > 5 ? '…' : '' }}. Regulariza con un ingreso o un ajuste de inventario.</p>
                @endif

                @if ($sinCodigo->isNotEmpty())
                    <form x-show="abierto" x-cloak method="POST" action="{{ route('kardex.sunat.codigos') }}" class="mt-3 rounded-xl bg-white p-4 space-y-3 text-gray-800">
                        @csrf
                        <datalist id="codigos-sunat">
                            @foreach ($codigosSunat['usados'] + $codigosSunat['catalogo'] as $codigo => $texto)<option value="{{ $codigo }}">{{ $texto }}</option>@endforeach
                        </datalist>
                        <p class="text-sm text-gray-600">Búscalo en el <a href="{{ \App\Support\KardexSunat::BUSCADOR_CUBSO }}" target="_blank" class="text-red-700 underline">buscador oficial del CUBSO</a> (OECE)
                            y pega su código de 16 dígitos, o escribe el UNSPSC de 8 dígitos (segmento, familia, clase y producto).
                            Lo más rápido: ponle el código a toda una categoría (si los productos son parecidos).</p>
                        <div class="flex flex-wrap items-end gap-2 rounded-lg bg-gray-50 p-3">
                            <label class="text-xs font-semibold text-gray-500">Todos los productos sin código de la categoría
                                <select name="categoria" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"><option value="">—</option>
                                    @foreach ($categorias as $id => $nombre)<option value="{{ $id }}">{{ $nombre }}</option>@endforeach</select></label>
                            <label class="text-xs font-semibold text-gray-500">llevan el código<input name="unspsc_categoria" maxlength="16" list="codigos-sunat" placeholder="Escribe: agua, lácteos…" class="mt-1 block h-9 w-44 rounded-lg border-gray-300 text-sm font-mono"></label>
                        </div>
                        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                            @foreach ($sinCodigo as $p)
                                <div class="py-2 flex flex-wrap items-center gap-2 text-sm">
                                    <span class="flex-1 min-w-48">{{ $p->pronom }} <span class="text-xs text-gray-400">{{ $p->procod }}</span></span>
                                    <input name="productos[{{ $p->IdProducto }}][unspsc]" value="{{ $p->unspsc }}" maxlength="16" list="codigos-sunat" placeholder="Código" class="h-9 w-44 rounded-lg border-gray-300 text-sm font-mono">
                                    <select name="productos[{{ $p->IdProducto }}][tipo_existencia]" class="h-9 rounded-lg border-gray-300 text-sm">
                                        <option value="">Tipo: {{ \App\Support\KardexSunat::TIPOS_EXISTENCIA[\App\Support\KardexSunat::tipoExistencia($p)] }} (automático)</option>
                                        @foreach (\App\Support\KardexSunat::TIPOS_EXISTENCIA as $cod => $nom)<option value="{{ $cod }}" @selected($p->tipo_existencia === $cod)>{{ $cod }} {{ $nom }}</option>@endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        @if (count($avisos['sin_unspsc']) > $sinCodigo->count())
                            <p class="text-xs text-gray-500">Se muestran {{ $sinCodigo->count() }} de {{ count($avisos['sin_unspsc']) }}: guarda y aparecerán los siguientes.</p>
                        @endif
                        <button class="h-10 px-5 rounded-lg bg-red-700 text-white text-sm font-bold">Guardar códigos</button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Encabezado del formato (como el impreso de SUNAT) --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="text-center font-black text-gray-800">FORMATO {{ $codigoFormato }}: "{{ mb_strtoupper($nombreFormato) }}"</p>
            <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-1 text-sm">
                <p><span class="text-gray-400">Periodo:</span> <b>{{ $registro['desde']->translatedFormat('F Y') }}</b></p>
                <p><span class="text-gray-400">RUC:</span> <b>{{ $empresa->IdEmpresa }}</b></p>
                <p class="lg:col-span-2"><span class="text-gray-400">Razón social:</span> <b>{{ $empresa->NomEmpresa }}</b></p>
                <p><span class="text-gray-400">Establecimiento:</span> <b>{{ $registro['establecimiento'] }}</b></p>
                @if ($valorizado)<p class="sm:col-span-2"><span class="text-gray-400">Método de valuación:</span> <b>{{ \App\Support\KardexSunat::METODOS[$metodo] }}</b></p>@endif
            </div>
            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3 no-imprimir">
                @foreach ([['Productos', $totales['productos']], ['Movimientos del mes', $totales['movimientos']], ['Valor inicial', 'S/ '.$n($totales['inicial'])], ['Valor final', 'S/ '.$n($totales['final'])]] as [$t, $v])
                    <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-semibold uppercase text-gray-400">{{ $t }}</p><p class="text-xl font-black text-gray-800">{{ $v }}</p></div>
                @endforeach
            </div>
        </div>

        @forelse ($grupos as $g)
            @php
                $p = $g['producto'];
                $tipo = \App\Support\KardexSunat::tipoExistencia($p);
                [$q0, $u0, $t0] = $g['inicial'];
            @endphp
            <div class="bloque bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b text-sm grid sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-0.5">
                    <p class="sm:col-span-2 font-black text-gray-800">{{ $p->pronom }}</p>
                    <p><span class="text-gray-400">Código:</span> {{ $p->procod ?: $p->IdProducto }} @if ($p->unspsc)<span class="text-gray-400">· UNSPSC</span> {{ $p->unspsc }}@endif</p>
                    <p><span class="text-gray-400">Establecimiento:</span> {{ $g['almacen']->codigo }} · {{ $g['almacen']->nombre }}</p>
                    <p><span class="text-gray-400">Tipo (tabla 5):</span> {{ $tipo }} {{ \App\Support\KardexSunat::TIPOS_EXISTENCIA[$tipo] ?? '' }}</p>
                    <p><span class="text-gray-400">Unidad (tabla 6):</span> {{ \App\Support\Unidades::sunat($p->umecod) }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="text-gray-500 uppercase">
                            <tr class="border-b">
                                <th class="p-2 text-left" colspan="4">Documento</th><th class="p-2 text-left">Operación (t. 12)</th>
                                @if ($valorizado)<th class="p-2 text-center bg-emerald-50" colspan="3">Entradas</th><th class="p-2 text-center bg-rose-50" colspan="3">Salidas</th><th class="p-2 text-center bg-gray-50" colspan="3">Saldo final</th>
                                @else<th class="p-2 text-right">Entradas</th><th class="p-2 text-right">Salidas</th><th class="p-2 text-right">Saldo</th>@endif
                            </tr>
                            <tr class="border-b text-[10px]">
                                <th class="p-2 text-left">Fecha</th><th class="text-left">Tipo</th><th class="text-left">Serie</th><th class="text-left">Número</th><th></th>
                                @if ($valorizado)
                                    @foreach (['bg-emerald-50', 'bg-rose-50', 'bg-gray-50'] as $fondo)<th class="p-2 text-right {{ $fondo }}">Cant.</th><th class="text-right {{ $fondo }}">C.U.</th><th class="pr-2 text-right {{ $fondo }}">Total</th>@endforeach
                                @else<th></th><th></th><th></th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-amber-50/40 font-semibold">
                                <td class="p-2">{{ $registro['desde']->format('d/m/Y') }}</td><td>00</td><td>0</td><td>0</td><td>16 SALDO INICIAL</td>
                                @if ($valorizado)
                                    <td class="p-2 text-right">{{ $q($q0) }}</td><td class="text-right">{{ $n($u0, 4) }}</td><td class="pr-2 text-right">{{ $n($t0) }}</td><td></td><td></td><td></td>
                                    <td class="p-2 text-right">{{ $q($q0) }}</td><td class="text-right">{{ $n($u0, 4) }}</td><td class="pr-2 text-right">{{ $n($t0) }}</td>
                                @else<td class="p-2 text-right">{{ $q($q0) }}</td><td></td><td class="p-2 text-right">{{ $q($q0) }}</td>@endif
                            </tr>
                            @foreach ($g['filas'] as $r)
                                @php $entra = $r['tipo'] === 'I'; @endphp
                                <tr>
                                    <td class="p-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r['fecha'])->format('d/m/Y') }}</td><td>{{ $r['tdoc'] }}</td><td>{{ $r['serie'] }}</td><td>{{ $r['numero'] }}</td>
                                    <td class="whitespace-nowrap">{{ $r['op'] }} {{ mb_strtoupper($operaciones[$r['op']] ?? '') }}</td>
                                    @if ($valorizado)
                                        <td class="p-2 text-right">{{ $entra ? $q($r['cantidad']) : '' }}</td><td class="text-right">{{ $entra ? $n($r['unitario'], 4) : '' }}</td><td class="pr-2 text-right">{{ $entra ? $n($r['total']) : '' }}</td>
                                        <td class="p-2 text-right">{{ $entra ? '' : $q($r['cantidad']) }}</td><td class="text-right">{{ $entra ? '' : $n($r['unitario'], 4) }}</td><td class="pr-2 text-right">{{ $entra ? '' : $n($r['total']) }}</td>
                                        <td class="p-2 text-right font-semibold">{{ $q($r['saldo'][0]) }}</td><td class="text-right">{{ $n($r['saldo'][1], 4) }}</td><td class="pr-2 text-right font-semibold">{{ $n($r['saldo'][2]) }}</td>
                                    @else
                                        <td class="p-2 text-right">{{ $entra ? $q($r['cantidad']) : '' }}</td><td class="p-2 text-right">{{ $entra ? '' : $q($r['cantidad']) }}</td><td class="p-2 text-right font-semibold">{{ $q($r['saldo'][0]) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">No hay existencias ni movimientos en {{ $registro['desde']->translatedFormat('F Y') }}.</div>
        @endforelse
        <div class="no-imprimir">{{ $grupos->links() }}</div>
    </div>
@endsection
