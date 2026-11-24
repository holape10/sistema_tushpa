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

    {{-- Manuales: los tutoriales en PDF que el asistente usa para explicar cómo se hace cada cosa --}}
    <div class="rounded-2xl bg-white shadow-sm mb-6" id="manuales">
        <div class="p-4 border-b border-slate-100">
            <p class="font-bold">📚 Manuales del asistente</p>
            <p class="text-xs text-slate-500">Sube los tutoriales en PDF: la IA los lee <b>una sola vez</b> (cuesta centavos) y los deja en secciones. Cuando un usuario pregunta
                «¿cómo emito una factura?» o «¿por qué no imprime la comanda?», el asistente responde con lo que dicen estos manuales.</p>
        </div>
        @if ($errors->has('pdf') || $errors->has('titulo') || $errors->has('ruc'))
            <p class="mx-4 mt-3 rounded-lg bg-rose-50 text-rose-700 text-sm px-3 py-2">{{ $errors->first('pdf') ?: ($errors->first('titulo') ?: $errors->first('ruc')) }}</p>
        @endif
        <form method="POST" action="{{ route('admin.asistente.manuales.subir') }}" enctype="multipart/form-data" class="p-4 grid sm:grid-cols-12 gap-3 items-end border-b border-slate-100">
            @csrf
            <label class="sm:col-span-4 text-xs text-slate-500">PDF (máx. 20 MB y 100 páginas)
                <input type="file" name="pdf" accept="application/pdf" required class="mt-1 block w-full text-sm"></label>
            <label class="sm:col-span-3 text-xs text-slate-500">Título
                <input name="titulo" value="{{ old('titulo') }}" maxlength="150" placeholder="Igual que el archivo" class="mt-1 w-full h-9 rounded-lg border-slate-300 text-sm"></label>
            <label class="sm:col-span-2 text-xs text-slate-500">Rubro
                <select name="rubro" class="mt-1 w-full h-9 rounded-lg border-slate-300 text-sm">
                    @foreach ($rubros as $clave => $r)<option value="{{ $clave }}" @selected(old('rubro') === $clave)>{{ $r['nombre'] }}</option>@endforeach
                </select></label>
            <label class="sm:col-span-2 text-xs text-slate-500" title="Si el manual es solo para una empresa (trae sus impresoras, cajas, etc.)">Solo para el RUC
                <input name="ruc" value="{{ old('ruc') }}" maxlength="11" inputmode="numeric" placeholder="Todas" class="mt-1 w-full h-9 rounded-lg border-slate-300 text-sm"></label>
            <button class="sm:col-span-1 h-9 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Subir</button>
        </form>
        <div class="divide-y divide-slate-100 text-sm" @if ($manuales->contains('estado', 'procesando')) x-data x-init="setTimeout(() => location.reload(), 15000)" @endif>
            @forelse ($manuales as $m)
                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                    <div class="flex-1 min-w-56">
                        <p class="font-semibold">{{ $m->titulo }}
                            <span class="text-xs font-normal text-slate-400">· {{ $rubros[$m->rubro]['nombre'] ?? $m->rubro }}{{ $m->cliente_id ? ' · solo '.($m->cliente_nombre ?? 'una empresa') : '' }}</span></p>
                        <p class="text-xs text-slate-500">
                            @if ($m->estado === 'procesando')⏳ La IA lo está leyendo (puede tardar 1 a 3 minutos)…
                            @elseif ($m->estado === 'error')<span class="text-rose-600">✕ {{ $m->error }}</span>
                            @else ✓ {{ $m->secciones }} secciones · {{ $usd($m->costo_usd) }}@if ($m->error) · <span class="text-amber-700">{{ $m->error }}</span>@endif
                            @endif
                            · {{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</p>
                    </div>
                    @if ($m->estado !== 'procesando')
                        <form method="POST" action="{{ route('admin.asistente.manuales.leer', $m->id) }}">@csrf
                            <button class="h-8 px-3 rounded-lg bg-white border border-slate-200 text-xs font-semibold" title="La IA vuelve a leer el PDF">Volver a leer</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.asistente.manuales.eliminar', $m->id) }}" onsubmit="return confirm('¿Quitar este manual? El asistente ya no lo usará.')">@csrf
                        <button class="h-8 px-3 rounded-lg bg-white border border-rose-200 text-rose-600 text-xs font-semibold">Quitar</button></form>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-slate-400">Aún no hay manuales. Sube tus tutoriales en PDF para que el asistente los conozca.</p>
            @endforelse
        </div>
    </div>

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
