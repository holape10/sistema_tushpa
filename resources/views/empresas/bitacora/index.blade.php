@extends('layouts.app')
@section('title', 'Bitácora de cambios')
@section('content')
@use('App\Support\Bitacora')
@php
    $nombresDatos = ['motivo' => 'Motivo', 'productos' => 'Productos', 'diferencias' => 'Diferencias del conteo', 'precios' => 'Precios (antes o de lista → cobrado o nuevo)', 'autorizo' => 'Autorizó',
        'correo' => 'Usuario escrito', 'archivo' => 'Archivo', 'nuevas' => 'Empresas creadas', 'omitidas' => 'Ya existían', 'turno' => 'Turno',
        'stock_devuelto' => 'Movimientos de stock devueltos', 'tamano_mb' => 'Tamaño (MB)', 'respaldo' => 'Respaldo previo', 'reinicia_electronicos' => 'Reinició series electrónicas'];
    // Un dato de la bitácora en texto: listas una por línea; [nombre, antes, después] como "nombre: antes → después"
    $texto = function ($v) {
        if (is_bool($v)) {
            return $v ? 'Sí' : 'No';
        }
        if (! is_array($v)) {
            return (string) $v;
        }
        return collect($v)->map(fn ($x) => is_array($x) ? (count($x) === 3 ? $x[0].': '.$x[1].' → '.$x[2] : implode(' · ', array_map('strval', $x))) : (string) $x)->implode("\n");
    };
    $equipo = function (?string $agente) {
        $a = (string) $agente;
        $sistema = match (true) {
            str_contains($a, 'Android') => 'Android', str_contains($a, 'iPhone') || str_contains($a, 'iPad') => 'iPhone/iPad',
            str_contains($a, 'Windows') => 'Windows', str_contains($a, 'Mac OS') => 'Mac', str_contains($a, 'Linux') => 'Linux', default => '',
        };
        $navegador = match (true) {
            str_contains($a, 'Edg/') => 'Edge', str_contains($a, 'OPR/') => 'Opera', str_contains($a, 'Chrome/') => 'Chrome',
            str_contains($a, 'Firefox/') => 'Firefox', str_contains($a, 'Safari/') => 'Safari', default => '',
        };
        return trim($navegador.($navegador && $sistema ? ' en ' : '').$sistema);
    };
    $url = fn (array $cambios) => route('bitacora.index', array_filter(array_merge($f, $cambios), fn ($v) => $v !== null && $v !== '' && $v !== false));
@endphp

<div class="max-w-6xl mx-auto space-y-4">
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">🛡️ Bitácora de cambios</h1>
            <p class="text-sm text-gray-500">Quién cambió qué, cuándo y qué había antes. Nadie puede editar ni borrar lo que queda aquí.</p>
        </div>
        @if ($disponible)
            <a href="{{ route('bitacora.exportar', array_filter($f, fn ($v) => $v !== null && $v !== '' && $v !== false)) }}"
               class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Exportar Excel</a>
        @endif
    </div>

    @if (! $disponible)
        <div class="bg-amber-50 text-amber-800 rounded-2xl p-5 text-sm">La bitácora se activa al terminar de actualizar el sistema. Vuelve a entrar en unos minutos.</div>
    @else
        {{-- Filtros --}}
        <form method="GET" action="{{ route('bitacora.index') }}" class="bg-white rounded-2xl shadow-sm p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
            <label class="text-xs font-semibold text-gray-600">Desde
                <input type="date" name="desde" value="{{ $f['desde'] }}" max="{{ now()->toDateString() }}" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600">Hasta
                <input type="date" name="hasta" value="{{ $f['hasta'] }}" max="{{ now()->toDateString() }}" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600">Persona
                <select name="usuario" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach ($usuarios as $id => $nombre)<option value="{{ $id }}" @selected($f['usuario'] === (int) $id)>{{ $nombre }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-600">Qué
                <select name="modulo" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                    <option value="">Todo</option>
                    @foreach ($modulos as $clave => $nombre)<option value="{{ $clave }}" @selected($f['modulo'] === $clave)>{{ $nombre }}</option>@endforeach
                </select></label>
            @if ($sucursales->count() > 1)
                <label class="text-xs font-semibold text-gray-600">Sucursal
                    <select name="sucursal" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($sucursales as $id => $nombre)<option value="{{ $id }}" @selected($f['sucursal'] === (int) $id)>{{ $nombre }}</option>@endforeach
                    </select></label>
            @endif
            <label class="col-span-2 text-xs font-semibold text-gray-600 {{ $sucursales->count() > 1 ? 'md:col-span-1' : 'md:col-span-2' }}">Buscar
                <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Ej.: inca precio, anuló, F001-25" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="col-span-2 md:col-span-3 flex items-center gap-2 text-sm font-semibold text-rose-700">
                <input type="checkbox" name="alertas" value="1" @checked($f['alertas']) class="rounded border-gray-300 text-rose-600"> Solo lo que hay que revisar ⚠</label>
            <div class="col-span-2 md:col-span-3 flex justify-end gap-2">
                <a href="{{ route('bitacora.index') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-semibold">Limpiar</a>
                <button class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Filtrar</button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-600">{{ number_format($filas->total()) }} registro(s) del {{ \Carbon\Carbon::parse($f['desde'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($f['hasta'])->format('d/m/Y') }}</span>
            @if ($alertas && ! $f['alertas'])
                <a href="{{ $url(['alertas' => true]) }}" class="rounded-full bg-rose-100 text-rose-700 px-3 py-1 font-bold">⚠ {{ $alertas }} para revisar</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($filas as $b)
                @php
                    $cambios = json_decode((string) $b->cambios, true) ?: [];
                    $datos = json_decode((string) $b->datos, true) ?: [];
                    $hayDetalle = $cambios || $datos || $b->ip;
                @endphp
                <details class="group {{ $b->alerta ? 'bg-rose-50/60' : '' }}">
                    <summary class="list-none cursor-pointer px-4 py-3 flex items-start gap-3 hover:bg-gray-50 {{ $hayDetalle ? '' : 'pointer-events-none' }}">
                        <span class="h-9 w-9 shrink-0 rounded-full {{ $b->alerta ? 'bg-rose-100' : 'bg-gray-100' }} flex items-center justify-center">{{ Bitacora::ICONOS[$b->modulo] ?? '📝' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2 text-xs">
                                <span class="font-mono text-gray-500">{{ \Carbon\Carbon::parse($b->fecha)->format('d/m/Y H:i') }}</span>
                                <span class="font-bold text-gray-700">{{ $b->usuario ?: 'Sin usuario' }}</span>
                                @if ($b->rol)<span class="text-gray-400">{{ $b->rol }}</span>@endif
                                @if ($sucursales->count() > 1 && $b->id_empresa_negocio)<span class="text-gray-400">· {{ $sucursales[$b->id_empresa_negocio] ?? '' }}</span>@endif
                                <span class="rounded-full bg-gray-100 text-gray-600 px-2 font-semibold">{{ $modulos[$b->modulo] ?? $b->modulo }}</span>
                                @if ($b->alerta)<span class="font-black uppercase text-rose-600">⚠ revisar</span>@endif
                            </div>
                            <p class="font-semibold text-gray-800 leading-snug break-words">{{ $b->descripcion }}</p>
                            @if ($cambios)<p class="text-sm text-gray-500 leading-snug break-words">{{ Bitacora::resumen($cambios) }}</p>@endif
                        </div>
                        @if ($hayDetalle)<span class="shrink-0 text-gray-400 text-sm group-open:rotate-180 transition">▾</span>@endif
                    </summary>
                    @if ($hayDetalle)
                        <div class="px-4 pb-4 pl-16 space-y-3 text-sm">
                            @if ($cambios)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left">
                                        <thead class="text-xs text-gray-500"><tr><th class="py-1 pr-3">Campo</th><th class="py-1 pr-3">Antes</th><th class="py-1">Después</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($cambios as $c)
                                                <tr class="align-top">
                                                    <td class="py-1.5 pr-3 font-semibold text-gray-700 whitespace-nowrap">{{ $c['etiqueta'] ?? '' }}</td>
                                                    @if (isset($c['agrego']) || isset($c['quito']))
                                                        <td class="py-1.5 pr-3 text-rose-700">@foreach ($c['quito'] ?? [] as $x)<div>− {{ $x }}</div>@endforeach</td>
                                                        <td class="py-1.5 text-emerald-700">@foreach ($c['agrego'] ?? [] as $x)<div>+ {{ $x }}</div>@endforeach</td>
                                                    @elseif (($c['despues'] ?? null) === '(cambió)' && ($c['antes'] ?? null) === null)
                                                        <td class="py-1.5 pr-3 text-gray-400" colspan="2">🔒 Cambió (por seguridad no se guarda la clave)</td>
                                                    @else
                                                        <td class="py-1.5 pr-3 text-gray-500 break-words">{{ Bitacora::mostrar($c['antes'] ?? null) }}</td>
                                                        <td class="py-1.5 text-gray-900 font-medium break-words">{{ Bitacora::mostrar($c['despues'] ?? null) }}</td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            @foreach ($datos as $clave => $valor)
                                @continue($valor === [] || $valor === null || $valor === '')
                                <div><span class="font-semibold text-gray-700">{{ $nombresDatos[$clave] ?? $clave }}:</span>
                                    <span class="text-gray-700 whitespace-pre-line break-words">{{ $texto($valor) }}</span></div>
                            @endforeach
                            @if ($b->ip)
                                <p class="text-xs text-gray-400" title="{{ $b->agente }}">Desde {{ $b->ip }}{{ $equipo($b->agente) ? ' · '.$equipo($b->agente) : '' }}</p>
                            @endif
                        </div>
                    @endif
                </details>
            @empty
                <p class="text-center text-gray-400 py-10">No hay registros con estos filtros.</p>
            @endforelse
        </div>
        <div>{{ $filas->links() }}</div>
    @endif
</div>
@endsection
