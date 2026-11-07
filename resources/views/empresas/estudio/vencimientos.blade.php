@extends('layouts.app')
@section('title', 'Vencimientos SUNAT')
@section('content')
@php
    use App\Support\Contabilidad\Contabilidad;
    use App\Support\Vencimientos;
    $nombre = Contabilidad::nombrePeriodo($periodo);
    $anterior = $mes->copy()->subMonth()->format('Ym');
    $siguiente = $mes->copy()->addMonth()->format('Ym');
    $declaradas = $lista->filter(fn ($e) => $e->declarado)->count();
    $vencidas = $lista->filter(fn ($e) => ! $e->declarado && $e->dias < 0)->count();
    $urgentes = $lista->filter(fn ($e) => ! $e->declarado && $e->dias >= 0 && $e->dias <= 3)->count();
    $grupos = $lista->groupBy(fn ($e) => $e->vence->toDateString());
    $nombresGrupo = ['0' => 'Dígito 0', '1' => 'Dígito 1', '2-3' => 'Dígitos 2 y 3', '4-5' => 'Dígitos 4 y 5', '6-7' => 'Dígitos 6 y 7', '8-9' => 'Dígitos 8 y 9', 'BC' => 'Buenos contribuyentes'];
    $esAdmin = auth()->user()->esAdmin();
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold text-gray-800">📅 Vencimientos SUNAT</h1>
                <p class="text-sm text-gray-500">Declaraciones mensuales (IGV-Renta, PLAME…) del periodo <b class="text-gray-700">{{ $nombre }}</b>, según el último dígito del RUC.</p>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('estudio.vencimientos', ['periodo' => $anterior]) }}" class="h-10 px-3 inline-flex items-center rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold text-sm" title="Periodo anterior">←</a>
                <span class="h-10 px-4 inline-flex items-center rounded-xl bg-indigo-50 text-indigo-800 font-bold text-sm">{{ $nombre }}</span>
                @if ($mes->copy()->addMonth()->lte(now()->addYear()))
                    <a href="{{ route('estudio.vencimientos', ['periodo' => $siguiente]) }}" class="h-10 px-3 inline-flex items-center rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold text-sm" title="Periodo siguiente">→</a>
                @endif
                @if ($periodo !== $pendiente)
                    <a href="{{ route('estudio.vencimientos') }}" class="h-10 px-3 inline-flex items-center rounded-xl text-indigo-700 hover:bg-indigo-50 font-semibold text-sm">Este mes</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-2xl font-extrabold text-gray-800">{{ $lista->count() }}</p><p class="text-xs text-gray-500">empresas</p></div>
            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-2xl font-extrabold text-emerald-700">{{ $declaradas }}</p><p class="text-xs text-emerald-700">ya declaradas</p></div>
            <div class="rounded-xl bg-amber-50 p-3"><p class="text-2xl font-extrabold text-amber-700">{{ $urgentes }}</p><p class="text-xs text-amber-700">vencen en 3 días o menos</p></div>
            <div class="rounded-xl bg-rose-50 p-3"><p class="text-2xl font-extrabold text-rose-700">{{ $vencidas }}</p><p class="text-xs text-rose-700">vencidas sin declarar</p></div>
        </div>

        @unless ($cronograma['oficial'])
            <p class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
                ⚠️ La SUNAT aún no publica el cronograma {{ $mes->year }}. Estas fechas están <b>calculadas</b> con su regla de siempre (días hábiles del mes siguiente);
                el sistema las cambia solo por las oficiales apenas salgan. Confírmalas en
                <a href="{{ sprintf(Vencimientos::URL, $mes->year, $mes->year) }}" target="_blank" rel="noopener" class="underline font-semibold">la web de la SUNAT</a>.
            </p>
        @endunless
    </div>

    @forelse ($grupos as $fecha => $empresas)
        @php
            $primera = $empresas->first();
            $pendientes = $empresas->filter(fn ($e) => ! $e->declarado)->count();
            $color = $pendientes === 0 ? 'text-emerald-700 bg-emerald-50' : ($primera->dias < 0 ? 'text-rose-700 bg-rose-50' : ($primera->dias <= 3 ? 'text-amber-800 bg-amber-50' : 'text-gray-700 bg-gray-50'));
            $cuando = match (true) {
                $primera->dias < 0 => 'venció hace '.abs($primera->dias).' día(s)',
                $primera->dias === 0 => 'vence HOY',
                $primera->dias === 1 => 'vence mañana',
                default => 'en '.$primera->dias.' días',
            };
        @endphp
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 sm:px-5 py-3 {{ $color }}">
                <p class="font-extrabold">{{ ucfirst($primera->vence->locale('es')->isoFormat('dddd D [de] MMMM')) }}
                    <span class="font-semibold text-sm opacity-80">· {{ $empresas->pluck('grupo')->unique()->map(fn ($g) => $nombresGrupo[$g])->join(', ') }}</span></p>
                <p class="text-sm font-bold">{{ $pendientes === 0 ? '✔ todas declaradas' : $cuando.' · faltan '.$pendientes }}</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($empresas as $e)
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 sm:px-5 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-800 truncate">{{ $e->propia ? '🏛️ ' : '' }}{{ $e->NomEmpresa }}</p>
                            <p class="text-sm font-mono text-gray-500">{{ substr($e->IdEmpresa, 0, -1) }}<b class="text-indigo-700">{{ substr($e->IdEmpresa, -1) }}</b>
                                @if ($e->buen_contribuyente)<span class="font-sans text-xs text-emerald-700"> · buen contribuyente</span>@endif
                                @if ($e->declarado)<span class="font-sans text-xs text-emerald-700"> · declaró {{ $e->declarado->apeusu ?? '' }} el {{ \Illuminate\Support\Carbon::parse($e->declarado->created_at)->format('d/m H:i') }}</span>@endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($esAdmin)
                                <form method="POST" action="{{ route('estudio.buen_contribuyente', $e->IdEmpresa) }}">
                                    @csrf
                                    <button class="h-9 px-3 rounded-xl text-xs font-semibold ring-1 {{ $e->buen_contribuyente ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-white text-gray-500 ring-gray-200 hover:bg-gray-50' }}"
                                            title="Los buenos contribuyentes vencen después que los demás">⭐ Buen contrib.</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('estudio.declarar') }}">
                                @csrf
                                <input type="hidden" name="ruc" value="{{ $e->IdEmpresa }}">
                                <input type="hidden" name="periodo" value="{{ $periodo }}">
                                <input type="hidden" name="declarado" value="{{ $e->declarado ? 0 : 1 }}">
                                @if ($e->declarado)
                                    <button class="h-9 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold" title="Toca para quitar la marca si fue un error">✔ Declarado</button>
                                @else
                                    <button class="h-9 px-4 rounded-xl bg-white ring-1 ring-gray-300 hover:bg-indigo-50 text-gray-700 text-sm font-bold">Marcar declarado</button>
                                @endif
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm px-5 py-10 text-center text-gray-400">No tienes empresas asignadas.</div>
    @endforelse

    <details class="bg-white rounded-2xl shadow-sm">
        <summary class="cursor-pointer px-5 py-4 font-bold text-gray-700">Cronograma {{ $mes->year }} completo {{ $cronograma['oficial'] ? '(oficial SUNAT)' : '(calculado)' }}</summary>
        <div class="overflow-x-auto px-5 pb-5">
            <table class="w-full text-sm text-center">
                <thead class="text-xs text-gray-500"><tr>
                    <th class="py-2 text-left">Periodo</th>
                    @foreach (['0', '1', '2-3', '4-5', '6-7', '8-9'] as $g)<th>{{ str_replace('-', ' y ', $g) }}</th>@endforeach
                    <th>Buenos contrib.</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($cronograma['periodos'] as $p => $fechas)
                        <tr class="{{ $p === $periodo ? 'bg-indigo-50 font-bold' : '' }}">
                            <td class="py-2 text-left whitespace-nowrap">{{ Contabilidad::nombrePeriodo($p) }}</td>
                            @foreach ($fechas as $f)<td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($f)->locale('es')->isoFormat('D MMM') }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
@endsection
