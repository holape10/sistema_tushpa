{{--
    Conversación de un ticket de soporte (la usan la empresa y el panel admin).
    Variables: $ticket, $mensajes, $yo ('CLIENTE' | 'SOPORTE'), $urlArchivo (fn (mensaje, n) => url), $urlResponder
--}}
<div class="space-y-3" x-data="{ foto: null }">
    @foreach ($mensajes as $m)
        @php $mio = $m->autor === $yo; @endphp
        <div class="flex {{ $mio ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[85%] rounded-2xl px-4 py-3 shadow-sm {{ $mio ? 'bg-indigo-600 text-white rounded-br-md' : ($m->autor === 'SOPORTE' ? 'bg-emerald-50 border border-emerald-200 rounded-bl-md' : 'bg-white border border-slate-200 rounded-bl-md') }}">
                <p class="text-xs font-semibold {{ $mio ? 'text-indigo-200' : 'text-slate-500' }}">
                    {{ $m->autor === 'SOPORTE' ? '🛟 Soporte · ' : '' }}{{ $m->autor_nombre }} · {{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</p>
                @if ($m->texto)<p class="mt-1 text-sm whitespace-pre-line break-words">{{ $m->texto }}</p>@endif
                @if ($m->adjuntos)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($m->adjuntos as $n => $ruta)
                            <button type="button" @click="foto = @js($urlArchivo($m->id, $n))" class="block">
                                <img src="{{ $urlArchivo($m->id, $n) }}" alt="Foto adjunta" loading="lazy" class="w-24 h-24 rounded-xl object-cover ring-2 {{ $mio ? 'ring-indigo-400' : 'ring-slate-200' }} hover:opacity-90">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Foto en grande --}}
    <div x-show="foto" x-cloak @click="foto = null" @keydown.escape.window="foto = null" class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4 cursor-zoom-out">
        <img :src="foto" alt="" class="max-w-full max-h-full rounded-xl shadow-2xl">
        <a :href="foto" target="_blank" @click.stop class="absolute top-4 right-4 px-3 py-1.5 rounded-lg bg-white/90 text-sm font-semibold">Abrir original</a>
    </div>

    {{-- Responder --}}
    <form method="POST" action="{{ $urlResponder }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 space-y-3"
          x-data="{ fotos: [], enviando: false }" @submit="enviando = true">
        @csrf
        @if ($ticket->estado === 'CERRADO')
            <p class="text-xs text-slate-500">Este ticket está cerrado. Si escribes, se vuelve a abrir.</p>
        @endif
        <textarea name="texto" rows="3" maxlength="5000" placeholder="Escribe tu mensaje…" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        <div class="flex flex-wrap items-center gap-2">
            <label class="h-10 px-3 rounded-xl border border-slate-300 hover:border-indigo-400 cursor-pointer text-sm font-semibold text-slate-600 flex items-center gap-1.5">
                📷 <span x-text="fotos.length ? fotos.length + ' foto(s)' : 'Adjuntar fotos'"></span>
                <input type="file" name="fotos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only"
                       @change="if ($event.target.files.length > {{ \App\Support\Soporte::MAX_FOTOS }}) { alert('Hasta {{ \App\Support\Soporte::MAX_FOTOS }} fotos.'); $event.target.value = ''; fotos = []; } else { fotos = [...$event.target.files].map(f => URL.createObjectURL(f)); }">
            </label>
            <template x-for="f in fotos" :key="f"><img :src="f" alt="" class="w-10 h-10 rounded-lg object-cover"></template>
            {{ $extras ?? '' }}
            <button :disabled="enviando" class="ml-auto h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" x-text="enviando ? 'Enviando…' : 'Enviar'"></button>
        </div>
    </form>
</div>
