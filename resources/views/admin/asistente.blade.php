@extends('admin.layout')
@section('title', 'Asistente IA')

@section('content')
    @php
        $usd = fn ($n) => 'US$ '.number_format((float) $n, 2);
        $avance = $tope > 0 ? min(100, round($gasto / $tope * 100)) : 0;
        $nombres = $clientes->pluck('razon_social', 'id');
    @endphp

    <h1 class="text-xl font-bold mb-1">✨ Asistente IA</h1>
    <p class="text-sm text-slate-500 mb-4">Cuántas preguntas se hicieron este mes, cuánto costaron y a qué empresas se les activa. Modelo: <b>{{ $modelo }}</b>.</p>

    @unless ($configurado)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Falta la clave: pon <code>ANTHROPIC_API_KEY=...</code> en el <code>.env</code> del servidor y corre <code>php artisan config:cache</code>.
        </div>
    @endunless

    <div class="grid sm:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-slate-500">Gastado este mes</p><p class="text-2xl font-extrabold">{{ $usd($gasto) }}</p>
            <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $avance >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $avance }}%"></div></div>
            <p class="mt-1 text-xs text-slate-500">{{ $avance }}% del tope de {{ $usd($tope) }}</p></div>
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-slate-500">Preguntas este mes</p><p class="text-2xl font-extrabold">{{ number_format($preguntas) }}</p>
            <p class="mt-1 text-xs text-slate-500">≈ {{ $preguntas ? $usd($gasto / $preguntas) : '—' }} por pregunta</p></div>
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-slate-500">Si sigue así, a fin de mes</p><p class="text-2xl font-extrabold {{ $proyeccion > $tope ? 'text-rose-600' : '' }}">{{ $usd($proyeccion) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $proyeccion > $tope ? 'Pasaría el tope: el asistente se pausaría antes.' : 'Dentro del tope.' }}</p></div>
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-slate-500">Errores este mes</p><p class="text-2xl font-extrabold {{ $errores ? 'text-amber-600' : '' }}">{{ $errores }}</p>
            <p class="mt-1 text-xs text-slate-500">No cuentan como preguntas.</p></div>
    </div>
    <p class="mb-6 text-xs text-slate-500">🛡️ Al llegar al tope, el asistente se pausa solo hasta el 1 del mes siguiente. El tope se cambia con <code>ASISTENTE_TOPE_USD</code> en el <code>.env</code>.
        Pon también un límite de gasto en platform.claude.com (Gasto › Límites) como segunda protección.</p>

    <div class="rounded-2xl bg-white shadow-sm mb-6">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
            <p class="flex-1 font-bold">Empresas</p>
            <form method="GET"><input type="search" name="q" value="{{ $q }}" placeholder="Buscar RUC o razón social" class="h-9 rounded-xl border-slate-300 text-sm"></form>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($clientes as $c)
                @php $u = $porCliente[$c->id] ?? null; $limite = $c->asistente_limite ?: $limitePorDefecto; @endphp
                <form method="POST" action="{{ route('admin.asistente.guardar', $c->id) }}" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                    @csrf
                    <div class="flex-1 min-w-56">
                        <p class="font-semibold text-sm">{{ $c->razon_social }} <span class="font-mono text-xs text-slate-400">{{ $c->ruc }}</span></p>
                        <p class="text-xs text-slate-500">{{ $u ? $u->preguntas.' de '.$limite.' preguntas · '.$usd($u->costo).' · última '.\Carbon\Carbon::parse($u->ultima)->format('d/m H:i') : 'Sin preguntas este mes' }}</p>
                    </div>
                    <label class="text-xs text-slate-500">Límite al mes
                        <input type="number" name="limite" min="1" value="{{ $c->asistente_limite }}" placeholder="{{ $limitePorDefecto }}" class="ml-1 w-20 h-9 rounded-lg border-slate-300 text-sm"></label>
                    <select name="activo" class="h-9 rounded-lg border-slate-300 text-sm">
                        <option value="1" @selected($c->asistente_activo)>✅ Activo</option>
                        <option value="0" @selected(! $c->asistente_activo)>Apagado</option>
                    </select>
                    <button class="h-9 px-3 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Guardar</button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-sm">
        <p class="p-4 border-b border-slate-100 font-bold">Últimas preguntas</p>
        <div class="divide-y divide-slate-100 text-sm">
            @forelse ($ultimas as $p)
                <div class="flex flex-wrap items-center gap-3 px-4 py-2">
                    <span class="text-xs text-slate-400 w-24">{{ \Carbon\Carbon::parse($p->created_at)->format('d/m H:i') }}</span>
                    <span class="flex-1 min-w-56">{{ \Illuminate\Support\Str::limit($p->pregunta, 120) }}
                        <span class="text-xs text-slate-400">· {{ $nombres[$p->cliente_id] ?? 'Sistema principal' }} · {{ $p->usuario }}</span>
                        @if ($p->error)<span class="text-xs text-amber-700">· {{ $p->error }}</span>@endif</span>
                    <span class="text-xs text-slate-500">{{ $p->consultas }} consulta(s)</span>
                    <span class="w-20 text-right font-semibold">{{ $usd($p->costo_usd) }}</span>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-slate-400">Aún no hay preguntas este mes.</p>
            @endforelse
        </div>
    </div>
@endsection
