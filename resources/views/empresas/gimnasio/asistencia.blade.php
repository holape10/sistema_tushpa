@extends('layouts.app')
@section('title', 'Ingresos del día')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $dia = \Carbon\Carbon::parse($fecha);
        $titulo = ucfirst($dia->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'));
        $promedio = $resumen['promedio'] !== null ? \App\Support\Gimnasio::permanencia(now()->startOfDay(), now()->startOfDay()->addMinutes((int) round($resumen['promedio']))) : '—';
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } }</style>

    <div class="max-w-6xl space-y-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[240px]">
                <a href="{{ route('gimnasio.index') }}" class="no-print text-sm text-gray-500">← Gimnasio</a>
                <h1 class="text-2xl font-extrabold text-gray-800 mt-1"><i class="fas fa-clipboard-list text-orange-500"></i> Ingresos del {{ $titulo }}</h1>
                <p class="text-sm text-gray-500">Quiénes entraron, a qué hora entraron y salieron, y cuánto estuvieron.</p>
            </div>
            <form method="GET" class="no-print flex items-end gap-2">
                <a href="{{ route('gimnasio.asistencia', ['fecha' => $dia->copy()->subDay()->toDateString()]) }}" class="h-10 px-3 rounded-lg bg-white border border-gray-300 flex items-center" title="Día anterior"><i class="fas fa-chevron-left"></i></a>
                <input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-300 text-sm">
                <a href="{{ route('gimnasio.asistencia', ['fecha' => $dia->copy()->addDay()->toDateString()]) }}" class="h-10 px-3 rounded-lg bg-white border border-gray-300 flex items-center {{ $fecha >= now()->toDateString() ? 'pointer-events-none opacity-40' : '' }}" title="Día siguiente"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ route('gimnasio.asistencia.excel', ['fecha' => $fecha]) }}" class="h-10 px-4 rounded-lg bg-emerald-600 text-white text-sm font-bold flex items-center gap-2"><i class="fas fa-file-excel"></i> Excel</a>
                <button type="button" onclick="window.print()" class="h-10 px-4 rounded-lg bg-white border border-gray-300 text-sm font-semibold"><i class="fas fa-print"></i></button>
            </form>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach ([['Personas', $resumen['personas'], 'text-orange-600'], ['Ingresos', $resumen['ingresos'], 'text-gray-800'], ['Marcaron salida', $resumen['salidas'], 'text-sky-700'],
                ['Estuvieron en promedio', $promedio, 'text-gray-800'], ['No pudieron pasar', $resumen['denegados'], 'text-rose-600']] as [$t, $v, $c])
                <div class="bg-white rounded-2xl shadow-sm px-4 py-3"><p class="text-xs text-gray-500">{{ $t }}</p><p class="text-2xl font-black {{ $c }}">{{ $v }}</p></div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                    <th class="px-3 py-2 text-left">Código</th><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-left">Membresía</th><th class="px-3 py-2 text-left">Vence</th>
                    <th class="px-3 py-2 text-center">Ingreso</th><th class="px-3 py-2 text-center">Salida</th><th class="px-3 py-2 text-left">Estuvo</th><th class="px-3 py-2 text-left">Marcó con</th></tr></thead>
                <tbody class="divide-y">
                    @forelse ($filas as $a)
                        <tr class="{{ $a->permitido ? '' : 'bg-rose-50' }}">
                            <td class="px-3 py-1.5 font-mono font-bold">{{ $a->codigo }}</td>
                            <td class="px-3 py-1.5 font-semibold">{{ $a->nombre }}
                                @unless ($a->permitido)<span class="block text-xs font-normal text-rose-700">No pasó: {{ $a->motivo }}</span>@endunless</td>
                            <td class="px-3 py-1.5">{{ $a->plan ?: '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-500">{{ $a->vence ? \Carbon\Carbon::parse($a->vence)->format('d/m/Y') : '—' }}</td>
                            <td class="px-3 py-1.5 text-center font-bold">{{ $a->entro }}</td>
                            <td class="px-3 py-1.5 text-center {{ $a->salio ? 'font-bold text-sky-700' : 'text-gray-300' }}">{{ $a->salio ?: '—' }}</td>
                            <td class="px-3 py-1.5">{{ $a->estuvo ?: '' }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-500">{{ $a->metodo }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">Nadie marcó su ingreso este día.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="no-print text-xs text-gray-400">La hora de salida queda cuando el cliente vuelve a marcar al irse (en Control de ingreso). Si no marcó, sale "—".</p>
    </div>
@endsection
