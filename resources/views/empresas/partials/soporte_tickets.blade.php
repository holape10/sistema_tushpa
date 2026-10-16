{{-- Tickets de soporte de la empresa (solo administrador): nuevo ticket con fotos y la lista con su estado --}}
@php
    $colorEstado = ['ABIERTO' => 'bg-amber-100 text-amber-800', 'EN_PROCESO' => 'bg-sky-100 text-sky-800', 'RESPONDIDO' => 'bg-emerald-100 text-emerald-800', 'CERRADO' => 'bg-slate-100 text-slate-500'];
    $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp
<section class="bg-white rounded-2xl shadow-sm" x-data="{ nuevo: @js($errors->any() || $tickets->isEmpty()), fotos: [] }">
    <header class="flex flex-wrap items-center gap-3 px-5 py-4 border-b border-slate-100">
        <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl">🎫</span>
        <div class="flex-1 min-w-0">
            <h2 class="font-bold text-slate-800">Tickets de soporte</h2>
            <p class="text-xs text-slate-500">Cuéntanos la falla con fotos o capturas. Te respondemos aquí y queda todo el historial.</p>
        </div>
        <button type="button" @click="nuevo = !nuevo" class="h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold" x-text="nuevo ? 'Ver mis tickets' : '+ Nuevo ticket'"></button>
    </header>

    {{-- Nuevo ticket --}}
    <form x-show="nuevo" x-cloak method="POST" action="{{ route('soporte.store') }}" enctype="multipart/form-data" class="p-5 grid sm:grid-cols-2 gap-4"
          x-data="{ enviando: false }" @submit="enviando = true">
        @csrf
        <label class="sm:col-span-2 text-sm font-medium text-slate-700">Asunto *
            <input name="asunto" value="{{ old('asunto') }}" required maxlength="150" placeholder="Ej.: No imprime la boleta en la caja 2" class="{{ $in }}"></label>
        <label class="text-sm font-medium text-slate-700">Tipo
            <select name="tipo" class="{{ $in }}">
                @foreach (\App\Support\Soporte::TIPOS as $valor => $nombre)<option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $nombre }}</option>@endforeach
            </select></label>
        <div class="text-sm font-medium text-slate-700">Prioridad
            <div class="mt-1 grid grid-cols-2 gap-2">
                @foreach (['NORMAL' => '🟢 Normal', 'URGENTE' => '🔴 Urgente (no puedo vender)'] as $valor => $nombre)
                    <label class="flex items-center gap-2 h-10 px-3 rounded-xl border border-slate-300 cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 text-xs">
                        <input type="radio" name="prioridad" value="{{ $valor }}" @checked(old('prioridad', 'NORMAL') === $valor) class="text-indigo-600"> {{ $nombre }}</label>
                @endforeach
            </div>
        </div>
        <label class="sm:col-span-2 text-sm font-medium text-slate-700">¿Qué pasó? *
            <textarea name="texto" rows="5" required maxlength="5000" placeholder="Qué estabas haciendo, qué mensaje salió, en qué comprobante o pantalla…" class="{{ $in }}">{{ old('texto') }}</textarea></label>
        <label class="text-sm font-medium text-slate-700">Celular de contacto <span class="text-slate-400 font-normal">(opcional)</span>
            <input name="telefono" value="{{ old('telefono') }}" maxlength="20" inputmode="tel" class="{{ $in }}"></label>
        <div class="text-sm font-medium text-slate-700">Fotos o capturas <span class="text-slate-400 font-normal">(hasta {{ \App\Support\Soporte::MAX_FOTOS }})</span>
            <label class="mt-1 flex items-center justify-center gap-2 h-11 rounded-xl border-2 border-dashed border-slate-300 hover:border-indigo-400 cursor-pointer text-indigo-600 font-semibold">
                📷 <span x-text="fotos.length ? fotos.length + ' foto(s) elegida(s)' : 'Elegir fotos'"></span>
                <input type="file" name="fotos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only"
                       @change="if ($event.target.files.length > {{ \App\Support\Soporte::MAX_FOTOS }}) { alert('Elige hasta {{ \App\Support\Soporte::MAX_FOTOS }} fotos.'); $event.target.value = ''; fotos = []; } else { fotos = [...$event.target.files].map(f => URL.createObjectURL(f)); }">
            </label>
        </div>
        <div x-show="fotos.length" class="sm:col-span-2 flex flex-wrap gap-2">
            <template x-for="f in fotos" :key="f"><img :src="f" alt="" class="w-20 h-20 rounded-xl object-cover ring-1 ring-slate-200"></template>
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button :disabled="enviando" class="h-11 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" x-text="enviando ? 'Enviando…' : 'Enviar ticket'"></button>
        </div>
    </form>

    {{-- Mis tickets --}}
    <div x-show="!nuevo" class="divide-y divide-slate-100">
        @forelse ($tickets as $t)
            <a href="{{ route('soporte.ticket', $t->id) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 {{ $t->leido_cliente ? '' : 'bg-emerald-50/60' }}">
                <span class="text-xs font-mono text-slate-400 w-12">#{{ $t->id }}</span>
                <span class="flex-1 min-w-0">
                    <span class="block font-semibold text-slate-800 truncate">{{ $t->asunto }}
                        @unless ($t->leido_cliente)<span class="ml-1 px-1.5 rounded bg-emerald-500 text-white text-[10px] font-bold">RESPUESTA NUEVA</span>@endunless</span>
                    <span class="block text-xs text-slate-400">{{ \App\Support\Soporte::TIPOS[$t->tipo] ?? $t->tipo }} · {{ $t->usuario }} · {{ \Carbon\Carbon::parse($t->updated_at)->diffForHumans() }}</span>
                </span>
                @if ($t->prioridad === 'URGENTE')<span class="text-[10px] font-bold text-rose-600">URGENTE</span>@endif
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $colorEstado[$t->estado] ?? '' }}">{{ \App\Support\Soporte::ESTADOS[$t->estado] ?? $t->estado }}</span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-400">Aún no has enviado tickets.</p>
        @endforelse
    </div>
</section>
