@extends('admin.layout')
@section('title', 'Telegram')

@section('content')
    {{-- ✈️ Quién usa el bot: personas vinculadas, su empresa, sus avisos y cuánto le preguntan --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[14rem]">
            <h1 class="text-xl font-bold">✈️ Telegram</h1>
            <p class="text-sm text-slate-500">Quién tiene el bot vinculado, de qué empresa, qué avisos recibe y cuánto le pregunta.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="empresa" onchange="this.form.submit()" class="h-9 rounded-xl border-slate-300 text-sm max-w-[18rem]">
                <option value="">Todas las empresas</option>
                <option value="{{ $panel }}" @selected($empresa === $panel)>Usuarios de mi panel</option>
                @foreach ($empresas as $e)
                    <option value="{{ $e->id }}" @selected($empresa === (string) $e->id)>{{ $e->nombre_comercial ?: $e->razon_social }}{{ $e->subdominio ? ' · '.$e->subdominio : '' }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ $q }}" placeholder="Nombre, empresa o RUC" class="h-9 rounded-xl border-slate-300 text-sm w-48">
            <button class="h-9 px-3 rounded-xl bg-slate-900 text-white text-sm font-bold">Buscar</button>
            @if ($empresa !== '' || $q !== '')
                <a href="{{ route('admin.telegram.index') }}" class="h-9 px-3 inline-flex items-center rounded-xl bg-white shadow-sm text-sm font-semibold">Quitar filtros</a>
            @endif
        </form>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-4">
        @foreach ([['Personas', $totales['personas']], ['Empresas', $totales['empresas']], ['Preguntas este mes', $totales['preguntas_mes']]] as [$texto, $n])
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold text-slate-400 uppercase">{{ $texto }}</p><p class="text-2xl font-black">{{ $n }}</p></div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="px-3 py-2 text-left">Persona</th><th class="px-3 py-2 text-left">Empresa</th><th class="px-3 py-2 text-left">Vinculado</th>
                    <th class="px-3 py-2 text-left">Avisos que recibe</th><th class="px-3 py-2 text-right">Preguntas</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($vinculos as $v)
                    @php
                        $suyos = $avisos[$v->base.'|'.$v->IdUsuario] ?? null;
                        $p = $preguntas[$v->chat_id] ?? null;
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $v->nombre ?: 'Usuario '.$v->IdUsuario }}</td>
                        <td class="px-3 py-2">
                            @if ($v->base === $panel)
                                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold">Mi panel</span>
                            @elseif ($v->cliente_id)
                                <a href="{{ route('admin.clientes.diagnostico', $v->cliente_id) }}" class="font-semibold text-indigo-700 hover:underline">{{ $v->nombre_comercial ?: $v->razon_social }}</a>
                                <span class="block text-[11px] text-slate-400">RUC {{ $v->ruc }}{{ $v->subdominio ? ' · '.$v->subdominio : '' }}</span>
                            @else
                                <span class="text-slate-400">{{ $v->base }} (ya no es cliente)</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ $v->vinculado_at ? \Carbon\Carbon::parse($v->vinculado_at)->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($v->base === $panel)
                                <span class="text-xs text-slate-500">Alertas y parte de soporte del panel</span>
                            @elseif ($suyos === null)
                                <span class="text-xs text-slate-400">—</span>
                            @elseif (! $suyos['avisos'])
                                <span class="text-xs text-amber-700">Vinculado, pero con todos los avisos apagados</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($suyos['avisos'] as $tipo)
                                        <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[11px] font-semibold">{{ $tipos[$tipo][0] ?? $tipo }}{{ $tipo === 'telegram_resumen' && $suyos['hora'] ? ' · '.$suyos['hora'] : '' }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <b>{{ $p->total ?? 0 }}</b>
                            @if ($p)<span class="block text-[11px] text-slate-400">última {{ \Carbon\Carbon::parse($p->ultima)->format('d/m H:i') }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-10 text-center text-slate-400">Nadie tiene el bot vinculado{{ $empresa !== '' || $q !== '' ? ' con ese filtro' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
