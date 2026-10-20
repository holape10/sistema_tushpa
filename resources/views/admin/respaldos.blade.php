@extends('admin.layout')
@section('title', 'Copias de seguridad')

@section('content')
    @php $tamano = fn ($b) => $b > 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB'; @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h1 class="text-xl font-bold">Copias de seguridad</h1>
        <form method="GET" class="ml-auto">
            <select name="base" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm min-w-[280px]">
                @foreach ($bases as $clave => $nombre)
                    <option value="{{ $clave }}" @selected($clave === $base)>{{ $nombre }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        {{-- Sacar copia --}}
        <form method="POST" action="{{ route('admin.respaldos.crear') }}" class="bg-white rounded-2xl shadow-sm p-5" x-data="{ enviando: false }" @submit="enviando = true">
            @csrf
            <input type="hidden" name="base" value="{{ $base }}">
            <p class="font-bold">💾 Sacar una copia ahora</p>
            <p class="text-sm text-slate-500 mt-1 mb-4">Guarda toda la información de <b>{{ $bases[$base] }}</b> (ventas, productos, clientes, configuración). Queda en el servidor y puedes descargarla.</p>
            <button class="px-4 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" :disabled="enviando"
                    x-text="enviando ? 'Sacando la copia…' : 'Sacar copia'"></button>
        </form>

        {{-- Subir y restaurar --}}
        <form method="POST" action="{{ route('admin.respaldos.restaurar') }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-5 border-2 border-rose-100"
              x-data="{ archivo: '', confirmacion: '', enviando: false }" @submit="enviando = true">
            @csrf
            <input type="hidden" name="base" value="{{ $base }}">
            <p class="font-bold text-rose-700">⬆ Subir una copia y restaurar</p>
            <p class="text-sm text-slate-500 mt-1 mb-3">Reemplaza la información de <b>{{ $bases[$base] }}</b> por la del archivo (.sql o .sql.gz). Antes se guarda una copia de cómo está ahora.</p>
            <input type="file" name="subido" accept=".sql,.gz" required class="block w-full text-sm mb-3" @change="archivo = $event.target.files.length ? $event.target.files[0].name : ''">
            <div class="flex flex-wrap items-center gap-2">
                <input name="confirmacion" x-model="confirmacion" placeholder="Escribe RESTAURAR" autocomplete="off" class="rounded-xl border-slate-300 text-sm w-44">
                <button class="px-4 h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold disabled:opacity-40"
                        :disabled="!archivo || confirmacion !== 'RESTAURAR' || enviando" x-text="enviando ? 'Restaurando… no cierres' : 'Restaurar'"></button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm tabla-movil">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-3">Copia</th><th class="text-left px-4 py-3">Fecha</th><th class="text-left px-4 py-3">Tipo</th><th class="text-right px-4 py-3">Tamaño</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($copias as $c)
                    <tr x-data="{ restaurar: false, confirmacion: '' }">
                        <td class="px-4 py-3 font-mono text-xs">{{ $c['archivo'] }}</td>
                        <td class="px-4 py-3">{{ $c['fecha']->format('d/m/Y H:i') }} <span class="text-xs text-slate-400">({{ $c['fecha']->diffForHumans() }})</span></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">{{ $c['motivo'] ?: 'manual' }}</span></td>
                        <td class="px-4 py-3 text-right">{{ $tamano($c['tamano']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.respaldos.descargar', ['base' => $base, 'archivo' => $c['archivo']]) }}" class="text-xs font-semibold text-indigo-700 hover:underline mr-3">Descargar</a>
                            <button type="button" @click="restaurar = !restaurar" class="text-xs font-semibold text-rose-600 hover:underline mr-3">Restaurar</button>
                            <form method="POST" action="{{ route('admin.respaldos.eliminar') }}" class="inline" onsubmit="return confirm('¿Borrar esta copia del servidor?')">
                                @csrf
                                <input type="hidden" name="base" value="{{ $base }}"><input type="hidden" name="archivo" value="{{ $c['archivo'] }}">
                                <button class="text-xs font-semibold text-slate-400 hover:text-rose-700">Borrar</button>
                            </form>
                            <form x-show="restaurar" x-cloak method="POST" action="{{ route('admin.respaldos.restaurar') }}" class="mt-2 flex justify-end gap-2" x-data="{ enviando: false }" @submit="enviando = true">
                                @csrf
                                <input type="hidden" name="base" value="{{ $base }}"><input type="hidden" name="archivo" value="{{ $c['archivo'] }}">
                                <input name="confirmacion" x-model="confirmacion" placeholder="Escribe RESTAURAR" autocomplete="off" class="rounded-lg border-slate-300 text-xs w-36">
                                <button class="px-3 rounded-lg bg-rose-600 text-white text-xs font-bold disabled:opacity-40" :disabled="confirmacion !== 'RESTAURAR' || enviando"
                                        x-text="enviando ? 'Restaurando…' : 'Confirmar'"></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Todavía no hay copias de esta base. Saca la primera arriba.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Las copias se guardan en el servidor en <span class="font-mono">storage/app/respaldos</span>. Descárgalas de vez en cuando para tener una copia fuera del servidor.</p>
@endsection
