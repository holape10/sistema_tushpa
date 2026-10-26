@extends('admin.layout')
@section('title', 'Tablero')
@section('ancho', 'max-w-screen-2xl')

@section('content')
    @use('App\Support\TableroComprobantes', 'Tablero')
    @php
        $n = fn ($v) => number_format($v);
        $soles = fn ($v) => 'S/ '.number_format($v, 2);
        $pct = fn ($v, $de) => $de ? round($v * 100 / $de) : 0;
        // Dona por tipo (conic-gradient): cada tipo ocupa su porcentaje
        $acum = 0;
        $tramos = [];
        foreach (Tablero::TIPOS as $t => [$nom, $clase, $color]) {
            if (! $tipos[$t]['n']) {
                continue;
            }
            $finTramo = $acum + $tipos[$t]['n'] * 100 / max($total, 1);
            $tramos[] = "{$color} {$acum}% {$finTramo}%";
            $acum = $finTramo;
        }
        $dona = $tramos ? 'conic-gradient('.implode(', ', $tramos).')' : '#e2e8f0';
        $maxSerie = max(1, max($serie ?: [0]));
        // Sin redondear hacia arriba: 99.8% no debe verse como 100% si hay alguno sin aceptar
        $aceptadosPct = $electronicos ? floor($sunat['aceptados'] * 1000 / $electronicos) / 10 : 0;
        $etiquetaCada = max(1, (int) ceil(count($serie) / 12));
        $iconos = ['01' => '🧾', '03' => '🎫', '07' => '↩️', '08' => '➕', '09' => '🚚', '13' => '📝'];
    @endphp

    {{-- Encabezado y periodo --}}
    <div class="flex flex-wrap items-end gap-3 mb-5">
        <div class="mr-auto">
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Tablero de comprobantes</p>
            <h1 class="text-2xl font-extrabold text-slate-900">{{ $nombre }}</h1>
            <p class="text-sm text-slate-500">Todas tus empresas ({{ $empresasActivas }}) en un solo lugar.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex bg-white rounded-xl shadow-sm p-1 text-sm">
                @foreach (Tablero::PERIODOS as $clave => $texto)
                    <a href="{{ route('admin.tablero', ['periodo' => $clave]) }}"
                       class="px-3 py-1.5 rounded-lg font-semibold {{ $periodo === $clave ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $texto }}</a>
                @endforeach
            </div>
            <form method="GET" class="w-full sm:w-auto flex items-center gap-1 bg-white rounded-xl shadow-sm p-1 text-sm {{ $periodo === 'rango' ? 'ring-2 ring-indigo-500' : '' }}">
                <input type="date" name="desde" value="{{ $desde }}" class="flex-1 min-w-0 border-0 rounded-lg text-sm py-1 px-2 focus:ring-indigo-500">
                <span class="text-slate-400">→</span>
                <input type="date" name="hasta" value="{{ $hasta }}" class="flex-1 min-w-0 border-0 rounded-lg text-sm py-1 px-2 focus:ring-indigo-500">
                <button class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold">Ver</button>
            </form>
        </div>
    </div>

    {{-- Precio por comprobante del PSE: lo escribe el dueño y se recuerda en este navegador --}}
    <div x-data="{
            precio: (() => { try { return parseFloat(localStorage.getItem('tablero_precio_pse')) || 0.01 } catch (e) { return 0.01 } })(),
            guardar() { try { localStorage.setItem('tablero_precio_pse', this.precio) } catch (e) {} },
            costo(n) { return 'S/ ' + (n * (parseFloat(this.precio) || 0)).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
         }">
    @if ($sinLeer)
        <p class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm">⚠️ No se pudo leer {{ $sinLeer }} empresa(s): no están sumadas.</p>
    @endif

    {{-- Números grandes --}}
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="relative overflow-hidden rounded-2xl p-5 text-white bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900 shadow-lg">
            <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-white/10"></div>
            <p class="text-sm text-indigo-100">Comprobantes emitidos</p>
            <p class="text-4xl font-extrabold mt-1">{{ $n($total) }}</p>
            <p class="text-xs text-indigo-200 mt-2">{{ count($empresas) }} empresa(s) emitieron en el periodo</p>
        </div>
        <div class="rounded-2xl p-5 bg-white shadow-sm">
            <p class="text-sm text-slate-500">Vendido con factura y boleta</p>
            <p class="text-3xl font-extrabold text-slate-900 mt-1">{{ $soles($vendido) }}</p>
            <p class="text-xs text-slate-400 mt-2">+ {{ $soles($tipos['13']['monto']) }} en notas de venta</p>
        </div>
        <div class="rounded-2xl p-5 bg-white shadow-sm">
            <p class="text-sm text-slate-500">Aceptados por SUNAT</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-extrabold text-emerald-600">{{ $aceptadosPct }}%</p>
                <p class="text-sm text-slate-400 pb-1">{{ $n($sunat['aceptados']) }} de {{ $n($electronicos) }}</p>
            </div>
            <div class="mt-3 h-2.5 rounded-full bg-slate-100 overflow-hidden flex">
                @foreach (Tablero::GRUPOS_SUNAT as $g => [$texto, $clase])
                    @if ($sunat[$g])<div class="{{ $clase }}" style="width: {{ $sunat[$g] * 100 / max($electronicos, 1) }}%" title="{{ $texto }}: {{ $n($sunat[$g]) }}"></div>@endif
                @endforeach
            </div>
        </div>
        <div class="rounded-2xl p-5 bg-white shadow-sm">
            <p class="text-sm text-slate-500">Necesitan atención</p>
            <p class="text-3xl font-extrabold mt-1 {{ $sunat['pendientes'] + $sunat['rechazados'] ? 'text-rose-600' : 'text-slate-900' }}">{{ $n($sunat['pendientes'] + $sunat['rechazados']) }}</p>
            <p class="text-xs text-slate-400 mt-2">{{ $n($sunat['pendientes']) }} por enviar · {{ $n($sunat['rechazados']) }} rechazados · {{ $n($anulados) }} anulados</p>
        </div>
    </div>

    {{-- Válidos para SUNAT: lo que cobraría un PSE --}}
    <div class="rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-lg p-5 mb-4 flex flex-wrap items-center gap-x-8 gap-y-4">
        <div>
            <p class="text-sm text-emerald-100">✅ Válidos para SUNAT</p>
            <p class="text-4xl font-extrabold">{{ $n($electronicos) }}</p>
            <p class="text-xs text-emerald-100 mt-1">Facturas, boletas, notas de crédito y débito y guías (sin anulados ni notas de venta)</p>
        </div>
        <div class="flex items-center gap-3 sm:ml-auto bg-white/10 rounded-xl px-4 py-3">
            <label class="text-sm text-emerald-50">Precio del PSE<br><span class="text-xs text-emerald-200">por comprobante</span></label>
            <div class="flex items-center bg-white rounded-lg text-slate-900 px-2">
                <span class="text-sm text-slate-400">S/</span>
                <input type="number" step="0.001" min="0" x-model="precio" @input="guardar()" class="w-24 border-0 focus:ring-0 text-sm font-bold">
            </div>
        </div>
        <div class="text-right">
            <p class="text-sm text-emerald-100">Pagarías al PSE</p>
            <p class="text-3xl font-extrabold" x-text="costo({{ $electronicos }})"></p>
            <p class="text-xs text-emerald-100 mt-1">en {{ \Illuminate\Support\Str::lower($nombre) }}</p>
        </div>
    </div>

    {{-- Por tipo --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
        @foreach (Tablero::TIPOS as $t => [$nom, $clase, $color])
            <div class="rounded-2xl bg-white shadow-sm p-4 relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 {{ $clase }}"></div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">{{ $iconos[$t] }}</span>
                    <p class="text-xs font-semibold text-slate-500 leading-tight">{{ $nom }}</p>
                </div>
                <p class="text-2xl font-extrabold text-slate-900 mt-2">{{ $n($tipos[$t]['n']) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $pct($tipos[$t]['n'], $total) }}% del total</p>
                <p class="text-xs font-semibold text-slate-600 h-4">@if ($t !== '09' && $tipos[$t]['monto']){{ $soles($tipos[$t]['monto']) }}@endif</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mb-4">
        {{-- Gráfico por día (o por mes) --}}
        <div class="lg:col-span-2 rounded-2xl bg-white shadow-sm p-5" x-data="{ punto: null }">
            <div class="flex items-center mb-4">
                <p class="font-bold text-slate-800 flex-1">Comprobantes por {{ $porMes ? 'mes' : 'día' }}</p>
                <p class="text-sm text-slate-500 h-5" x-text="punto ?? 'Pasa el mouse por las barras'"></p>
            </div>
            <div class="flex items-end gap-[3px] h-56 border-b border-slate-200">
                @foreach ($serie as $clave => $cant)
                    @php
                        $fecha = \Carbon\Carbon::parse($porMes ? $clave.'-01' : $clave);
                        $texto = ($porMes ? ucfirst($fecha->translatedFormat('F Y')) : $fecha->translatedFormat('D d/m')).': '.$n($cant).' comprobantes';
                    @endphp
                    <div class="flex-1 h-full flex items-end group cursor-default" @mouseenter="punto = @js($texto)" @mouseleave="punto = null">
                        <div class="w-full rounded-t-md bg-gradient-to-t from-indigo-600 to-indigo-400 group-hover:from-amber-500 group-hover:to-amber-300 transition-colors {{ $cant ? '' : 'opacity-20' }}"
                             style="height: {{ $cant ? max(2, $cant * 100 / $maxSerie) : 1 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-[3px] mt-1.5">
                @foreach (array_keys($serie) as $i => $clave)
                    <div class="flex-1 text-center text-[10px] text-slate-400 overflow-visible whitespace-nowrap">
                        @if ($i % $etiquetaCada === 0){{ $porMes ? \Carbon\Carbon::parse($clave.'-01')->translatedFormat('M') : substr($clave, 8, 2) }}@endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Reparto por tipo --}}
        <div class="rounded-2xl bg-white shadow-sm p-5">
            <p class="font-bold text-slate-800 mb-4">Reparto por tipo</p>
            <div class="flex items-center gap-5">
                <div class="relative w-36 h-36 shrink-0 rounded-full" style="background: {{ $dona }}">
                    <div class="absolute inset-5 rounded-full bg-white flex flex-col items-center justify-center">
                        <span class="text-xl font-extrabold text-slate-900">{{ $n($total) }}</span>
                        <span class="text-[11px] text-slate-400">en total</span>
                    </div>
                </div>
                <ul class="space-y-1.5 text-sm flex-1 min-w-0">
                    @foreach (Tablero::TIPOS as $t => [$nom, $clase, $color])
                        <li class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $clase }}"></span>
                            <span class="flex-1 truncate text-slate-600">{{ $nom }}</span>
                            <span class="font-semibold">{{ $pct($tipos[$t]['n'], $total) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-5 pt-4 border-t border-slate-100 space-y-2 text-sm">
                <p class="font-semibold text-slate-700">Estado en SUNAT</p>
                @foreach (Tablero::GRUPOS_SUNAT as $g => [$texto, $clase])
                    <div class="flex items-center gap-2">
                        <span class="w-24 text-slate-500">{{ $texto }}</span>
                        <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full {{ $clase }}" style="width: {{ $pct($sunat[$g], $electronicos) }}%"></div></div>
                        <span class="w-14 text-right font-semibold">{{ $n($sunat[$g]) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Empresas --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 flex items-center">
            <p class="font-bold text-slate-800 flex-1">Empresas que más emitieron</p>
            <span class="text-xs text-slate-400">Se actualiza cada 10 minutos</span>
        </div>
        <table class="w-full text-sm tabla-movil">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-2.5 text-left w-10">#</th>
                    <th class="px-3 py-2.5 text-left">Empresa</th>
                    <th class="px-3 py-2.5 text-left w-2/5">Comprobantes por tipo</th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5 text-right">Válidos SUNAT</th>
                    <th class="px-3 py-2.5 text-right">Costo PSE</th>
                    <th class="px-3 py-2.5 text-right">Vendido (F + B)</th>
                    <th class="px-5 py-2.5 text-right">SUNAT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($empresas as $i => $e)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-bold text-slate-400" data-label="Puesto">{{ $i + 1 }}</td>
                        <td class="px-3 py-3" data-label="Empresa">
                            <p class="font-semibold text-slate-800">{{ $e['cliente']->nombre_comercial ?: $e['cliente']->razon_social }}</p>
                            <p class="text-xs text-slate-400">{{ $e['cliente']->ruc }}</p>
                        </td>
                        <td class="px-3 py-3" data-label="Por tipo">
                            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden flex">
                                @foreach (Tablero::TIPOS as $t => [$nom, $clase, $color])
                                    @if ($e['tipos'][$t] ?? 0)<div class="{{ $clase }}" style="width: {{ $e['tipos'][$t] * 100 / max($e['total'], 1) }}%" title="{{ $nom }}: {{ $n($e['tipos'][$t]) }}"></div>@endif
                                @endforeach
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">
                                @foreach (Tablero::TIPOS as $t => [$nom])
                                    @if ($e['tipos'][$t] ?? 0)<span class="mr-2">{{ $nom }}: <b>{{ $n($e['tipos'][$t]) }}</b></span>@endif
                                @endforeach
                            </p>
                        </td>
                        <td class="px-3 py-3 text-right font-extrabold text-slate-900" data-label="Total">{{ $n($e['total']) }}</td>
                        <td class="px-3 py-3 text-right font-bold text-emerald-700" data-label="Válidos SUNAT">{{ $n($e['validos']) }}</td>
                        <td class="px-3 py-3 text-right text-slate-600" data-label="Costo PSE" x-text="costo({{ $e['validos'] }})"></td>
                        <td class="px-3 py-3 text-right" data-label="Vendido">{{ $soles($e['monto']) }}</td>
                        <td class="px-5 py-3 text-right" data-label="SUNAT">
                            @if ($e['pendientes'])
                                <a href="{{ route('admin.clientes.comprobantes', $e['cliente']) }}" class="inline-block px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-bold hover:bg-rose-200">{{ $n($e['pendientes']) }} por revisar</a>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">Todo al día</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">Ninguna empresa emitió comprobantes en este periodo.</td></tr>
                @endforelse
            </tbody>
            @if ($empresas)
                <tfoot class="bg-slate-50 font-bold">
                    <tr>
                        <td class="px-5 py-3" colspan="3">Total</td>
                        <td class="px-3 py-3 text-right" data-label="Total">{{ $n($total) }}</td>
                        <td class="px-3 py-3 text-right text-emerald-700" data-label="Válidos SUNAT">{{ $n($electronicos) }}</td>
                        <td class="px-3 py-3 text-right" data-label="Costo PSE" x-text="costo({{ $electronicos }})"></td>
                        <td class="px-3 py-3 text-right" data-label="Vendido">{{ $soles($vendido) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    </div>
@endsection
