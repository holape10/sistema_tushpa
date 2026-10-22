@extends('admin.layout')
@section('title', 'Anuncios')

@section('content')
    @use('App\Support\Anuncios')
    @php
        $e = $editar;
        $hoy = now()->format('d/m/Y');
        $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm';
        $plantillas = [
            ['AVISO', "Estimado cliente: hoy {$hoy} SUNAT presenta fallas en la recepción de comprobantes electrónicos. Tus comprobantes se guardan y se enviarán automáticamente cuando SUNAT se normalice."],
            ['INFO', "Estimado cliente: el sistema estará en mantenimiento hoy {$hoy} de 11:00 p. m. a 12:00 a. m. Te recomendamos no emitir comprobantes en ese horario."],
            ['URGENTE', 'Estimado cliente: estamos presentando lentitud en el sistema. Ya lo estamos solucionando, gracias por tu paciencia.'],
            ['OFERTA', 'Novedad: ya puedes … Escríbenos por WhatsApp si quieres que te expliquemos cómo usarlo.'],
        ];
        $rubrosSel = old('rubros', $e && $e->rubros ? json_decode($e->rubros, true) : []);
        $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    @endphp
    <div class="mb-4">
        <h1 class="text-xl font-bold">Anuncios para tus clientes</h1>
        <p class="text-sm text-slate-500">Un mensaje que sale arriba de todas las pantallas del sistema de tus empresas (como una franja), mientras esté activo.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $er)<p>{{ $er }}</p>@endforeach</div>
    @endif

    <div class="grid lg:grid-cols-5 gap-4 items-start">
        <form method="POST" action="{{ $e ? route('admin.anuncios.guardar', $e->id) : route('admin.anuncios.guardar') }}"
              class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5 space-y-3"
              x-data="{ mensaje: @js(old('mensaje', $e->mensaje ?? '')), tipo: @js(old('tipo', $e->tipo ?? 'AVISO')), cerrar: @js((bool) old('se_puede_cerrar', $e->se_puede_cerrar ?? true)),
                        tipos: @js(Anuncios::TIPOS) }">
            @csrf
            <div class="flex items-center gap-2">
                <p class="font-bold flex-1">{{ $e ? 'Editar anuncio' : '📢 Nuevo anuncio' }}</p>
                @if ($e)<a href="{{ route('admin.anuncios.index') }}" class="text-xs text-slate-500 underline">Nuevo</a>@endif
            </div>
            @unless ($e)
                <div>
                    <p class="text-xs font-semibold text-slate-500 mb-1">Mensajes listos (luego lo ajustas):</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($plantillas as [$t, $texto])
                            <button type="button" @click="mensaje = @js($texto); tipo = '{{ $t }}'" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs">
                                {{ ['AVISO' => 'Falla de SUNAT', 'INFO' => 'Mantenimiento', 'URGENTE' => 'Lentitud', 'OFERTA' => 'Novedad'][$t] }}</button>
                        @endforeach
                    </div>
                </div>
            @endunless
            <label class="block text-sm">Mensaje *
                <textarea name="mensaje" x-model="mensaje" required minlength="5" maxlength="600" rows="4" class="{{ $in }}" placeholder="Estimado cliente: …"></textarea>
                <span class="text-[11px] text-slate-400" x-text="mensaje.length + ' / 600'"></span></label>
            <div>
                <p class="text-sm mb-1">Color</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (Anuncios::TIPOS as $valor => [$nombre, $fondo, $color, $icono])
                        <label class="rounded-xl border-2 px-3 py-2 cursor-pointer text-sm font-semibold" style="background: {{ $fondo }}; color: {{ $color }}"
                               :class="tipo === '{{ $valor }}' ? 'border-slate-900' : 'border-transparent'">
                            <input type="radio" name="tipo" value="{{ $valor }}" x-model="tipo" class="sr-only">{{ $icono }} {{ $nombre }}</label>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">Desde <span class="text-slate-400 text-xs">(vacío = ya)</span>
                    <input type="datetime-local" name="desde" value="{{ old('desde', $fecha($e->desde ?? null)) }}" class="{{ $in }}"></label>
                <label class="block text-sm">Hasta <span class="text-slate-400 text-xs">(vacío = hasta quitarlo)</span>
                    <input type="datetime-local" name="hasta" value="{{ old('hasta', $fecha($e->hasta ?? null) ?: ($e ? '' : now()->endOfDay()->format('Y-m-d\TH:i'))) }}" class="{{ $in }}"></label>
            </div>
            <details class="text-sm" @if ($rubrosSel) open @endif>
                <summary class="cursor-pointer font-semibold text-slate-600">¿A quiénes? <span class="font-normal text-slate-400">{{ $rubrosSel ? count($rubrosSel).' rubro(s)' : 'Todas las empresas' }}</span></summary>
                <p class="text-xs text-slate-400 mt-1">Si no marcas ninguno, lo ven todas.</p>
                <div class="mt-2 grid grid-cols-2 gap-1">
                    @foreach ($rubros as $clave => $nombre)
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="rubros[]" value="{{ $clave }}" @checked(in_array($clave, $rubrosSel, true)) class="rounded border-slate-300">
                            {{ $nombre }} <span class="text-slate-400">({{ $empresas[$clave] ?? 0 }})</span></label>
                    @endforeach
                </div>
            </details>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="se_puede_cerrar" value="1" x-model="cerrar" class="rounded border-slate-300"> El cliente lo puede cerrar con la ✕</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" @checked(old('activo', $e->activo ?? true)) class="rounded border-slate-300"> Activo</label>

            <div>
                <p class="text-xs font-semibold text-slate-500 mb-1">Así lo verán:</p>
                <div class="rounded-lg px-10 py-2 text-center text-sm relative" :style="'background:' + tipos[tipo][1] + ';color:' + tipos[tipo][2]">
                    <span x-text="tipos[tipo][3]"></span> <span x-text="mensaje || 'Tu mensaje…'"></span>
                    <span x-show="cerrar" class="absolute right-3 top-1/2 -translate-y-1/2 text-lg opacity-70">&times;</span>
                </div>
            </div>
            <button class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">{{ $e ? 'Guardar cambios' : 'Enviar a las empresas' }}</button>
        </form>

        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
            @forelse ($anuncios as $a)
                @php
                    [$nombre, $fondo, $color, $icono] = Anuncios::TIPOS[$a->tipo] ?? Anuncios::TIPOS['INFO'];
                    $vencido = $a->hasta && $a->hasta < now()->toDateTimeString();
                    $porVenir = $a->desde && $a->desde > now()->toDateTimeString();
                    $rs = $a->rubros ? json_decode($a->rubros, true) : [];
                @endphp
                <div class="p-4 {{ $a->activo && ! $vencido ? '' : 'opacity-60' }}">
                    <div class="rounded-lg px-3 py-2 text-sm" style="background: {{ $fondo }}; color: {{ $color }}">{{ $icono }} {{ $a->mensaje }}</div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                        @if (! $a->activo)<span class="px-2 py-0.5 rounded-full bg-slate-200 font-semibold">Pausado</span>
                        @elseif ($vencido)<span class="px-2 py-0.5 rounded-full bg-slate-200 font-semibold">Terminó</span>
                        @elseif ($porVenir)<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">Programado</span>
                        @else<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold">Se está viendo</span>@endif
                        <span>{{ $a->desde ? \Illuminate\Support\Carbon::parse($a->desde)->format('d/m/Y H:i') : \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}
                            → {{ $a->hasta ? \Illuminate\Support\Carbon::parse($a->hasta)->format('d/m/Y H:i') : 'sin fin' }}</span>
                        <span>{{ $rs ? implode(', ', array_map(fn ($r) => $rubros[$r] ?? $r, $rs)) : 'Todas las empresas' }}</span>
                        @unless ($a->se_puede_cerrar)<span>· no se puede cerrar</span>@endunless
                        <span class="ml-auto flex gap-3">
                            <a href="{{ route('admin.anuncios.index', ['editar' => $a->id]) }}" class="font-semibold text-slate-600 hover:text-indigo-700">Editar</a>
                            <form method="POST" action="{{ route('admin.anuncios.estado', $a->id) }}">@csrf
                                <button class="font-semibold {{ $a->activo ? 'text-amber-600' : 'text-emerald-600' }}">{{ $a->activo ? 'Quitar' : 'Activar' }}</button></form>
                            <form method="POST" action="{{ route('admin.anuncios.eliminar', $a->id) }}" onsubmit="return confirm('¿Eliminar este anuncio?')">@csrf
                                <button class="font-semibold text-rose-600">Eliminar</button></form>
                        </span>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-slate-400">Aún no enviaste anuncios.</p>
            @endforelse
        </div>
    </div>
@endsection
