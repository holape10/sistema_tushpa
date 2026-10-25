@extends('layouts.app')
@section('title', 'Importar clientes desde Excel')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—'; @endphp

    <div class="max-w-6xl space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1">
                <a href="{{ route('gimnasio.index') }}" class="text-sm text-gray-500">← Gimnasio</a>
                <h1 class="text-2xl font-extrabold text-gray-800 mt-1"><i class="fas fa-file-excel text-emerald-600"></i> Importar clientes desde Excel</h1>
                <p class="text-sm text-gray-500">Para pasar al sistema los clientes que llevabas en Excel, con su mismo código, sin escribirlos uno por uno.</p>
            </div>
        </div>

        @if (! $revision)
            {{-- Paso 1: subir el Excel --}}
            <div class="grid lg:grid-cols-2 gap-5">
                <form method="POST" action="{{ route('gimnasio.importar.subir') }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-5 space-y-3"
                      x-data="{ enviando: false, nombre: '' }" @submit="enviando = true">
                    @csrf
                    <p class="font-bold text-gray-800">1. Sube tu Excel tal como lo tienes</p>
                    <label class="block rounded-2xl border-2 border-dashed border-gray-300 hover:border-emerald-400 p-8 text-center cursor-pointer">
                        <i class="fas fa-cloud-arrow-up text-3xl text-emerald-500"></i>
                        <span class="block mt-2 text-sm font-semibold text-gray-700" x-text="nombre || 'Toca para elegir el archivo (.xlsx)'"></span>
                        <input type="file" name="excel" accept=".xlsx,.csv" required class="hidden" @change="nombre = $event.target.files[0]?.name || ''">
                    </label>
                    @error('excel')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    @if ($error)<p class="text-sm text-rose-600">{{ $error }}</p>@endif
                    <button class="w-full h-11 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-50" :disabled="!nombre || enviando"
                            x-text="enviando ? 'Revisando…' : 'Revisar el Excel'"></button>
                    <p class="text-xs text-gray-400">Primero se revisa: no se guarda nada hasta que confirmes.</p>
                </form>
                <div class="bg-white rounded-2xl shadow-sm p-5 text-sm text-gray-600 space-y-2">
                    <p class="font-bold text-gray-800">¿Qué Excel sirve?</p>
                    <p>La lista de clientes con una fila por persona y sus títulos: <b>CÓDIGO, NOMBRE, DNI, TELÉFONO, MEMBRESÍA, FECHA DE INICIO, FECHA DE VENCIMIENTO</b>
                        (y si los tiene: DIRECCIÓN, MONTO PAGADO, CUMPLEAÑOS, OBSERVACIÓN).</p>
                    <p>Si tu Excel tiene una hoja por cada día y otra llamada <b>"BASE DE DATOS"</b>, se usa esa.</p>
                    <p class="font-bold text-gray-800 pt-2">¿Qué hace?</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Registra a cada cliente <b>con su mismo código</b>: sigue marcando su ingreso con ese número.</li>
                        <li>Le pone su <b>última membresía con sus fechas</b>: si está vigente, entra desde hoy; si venció, sale en "clientes para recuperar".</li>
                        <li>Las observaciones (NEW, RENOV, P-Y, pendientes…) quedan como nota del cliente.</li>
                        <li>Si alguien ya estaba en el sistema (mismo DNI), no se duplica: solo se completan sus datos.</li>
                    </ul>
                </div>
            </div>
        @else
            {{-- Paso 2: revisar y confirmar --}}
            @php $r = $revision['resumen']; @endphp
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <p class="text-sm text-gray-500">Archivo: <b>{{ $archivo }}</b></p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
                    @foreach ([['Clientes en el Excel', $r['total'], 'text-gray-800'], ['Nuevos para el sistema', $r['nuevos'], 'text-emerald-700'],
                        ['Ya estaban (mismo DNI)', $r['existentes'], 'text-sky-700'], ['Con membresía vigente hoy', $r['vigentes'], 'text-orange-600'],
                        ['Vencidos en los últimos 60 días', $r['vencidos_60'], 'text-rose-600'], ['Con membresía (fechas)', $r['con_membresia'], 'text-gray-700'],
                        ['Sin DNI', $r['sin_dni'], 'text-gray-700'], ['Con cumpleaños', $r['con_cumple'], 'text-gray-700']] as [$t, $v, $c])
                        <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs text-gray-500">{{ $t }}</p><p class="text-2xl font-black {{ $c }}">{{ $v }}</p></div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <form method="POST" action="{{ route('gimnasio.importar.confirmar') }}" x-data="{ enviando: false }" @submit="enviando = true"
                          onsubmit="return confirm('¿Importar {{ $r['total'] }} clientes al sistema?')">
                        @csrf
                        <button class="h-11 px-6 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-50" :disabled="enviando"
                                x-text="enviando ? 'Importando…' : 'Confirmar e importar {{ $r['total'] }} clientes'"></button>
                    </form>
                    <form method="POST" action="{{ route('gimnasio.importar.cancelar') }}">@csrf
                        <button class="h-11 px-5 rounded-xl bg-gray-100 text-gray-700 font-semibold">Cancelar y subir otro</button>
                    </form>
                </div>
            </div>

            @if ($revision['problemas'])
                <details class="bg-amber-50 border border-amber-200 rounded-2xl p-4" open>
                    <summary class="font-bold text-amber-900 cursor-pointer">{{ count($revision['problemas']) }} avisos (se importan igual, revisa si quieres corregir algo en el Excel)</summary>
                    <div class="mt-2 max-h-64 overflow-y-auto text-sm divide-y divide-amber-100">
                        @foreach ($revision['problemas'] as $p)
                            <p class="py-1"><span class="text-amber-700">Fila {{ $p['fila'] }}</span> · <b>{{ $p['nombre'] }}</b>: {{ $p['detalle'] }}</p>
                        @endforeach
                    </div>
                </details>
            @endif

            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <p class="px-4 pt-4 text-sm font-bold text-gray-700">Así quedarán (primeros 200)</p>
                <table class="w-full text-sm mt-2">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-3 py-2 text-left">Código</th><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-left">DNI</th><th class="px-3 py-2 text-left">Teléfono</th>
                        <th class="px-3 py-2 text-left">Membresía</th><th class="px-3 py-2 text-left">Desde</th><th class="px-3 py-2 text-left">Vence</th><th class="px-3 py-2 text-right">Pagó</th><th class="px-3 py-2 text-left">Nota</th></tr></thead>
                    <tbody class="divide-y">
                        @foreach (array_slice($revision['clientes'], 0, 200) as $c)
                            <tr class="{{ $c['vigente'] ? 'bg-emerald-50/40' : '' }}">
                                <td class="px-3 py-1.5 font-mono font-bold">{{ $c['codigo'] ?: 'nuevo' }}</td>
                                <td class="px-3 py-1.5 font-semibold">{{ $c['nombre'] }} @if ($c['existe'])<span class="text-[10px] px-1.5 rounded bg-sky-100 text-sky-700">ya estaba</span>@endif</td>
                                <td class="px-3 py-1.5">{{ $c['dni'] ?: '—' }}</td>
                                <td class="px-3 py-1.5">{{ $c['telefono'] ?: '—' }}</td>
                                <td class="px-3 py-1.5">{{ $c['plan'] }}</td>
                                <td class="px-3 py-1.5">{{ $f($c['inicio']) }}</td>
                                <td class="px-3 py-1.5 {{ $c['vigente'] ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">{{ $f($c['fin']) }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $c['monto'] > 0 ? number_format($c['monto'], 2) : '' }}</td>
                                <td class="px-3 py-1.5 text-xs text-gray-500 max-w-[16rem] truncate" title="{{ $c['nota'] }}">{{ $c['nota'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
