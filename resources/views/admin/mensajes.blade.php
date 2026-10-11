@extends('admin.layout')
@section('title', 'Mensajes web')

@section('content')
    <div class="flex items-center gap-3 mb-4">
        <h1 class="text-xl font-bold">Mensajes de la web</h1>
        <span class="text-sm text-slate-500">Formulario de contacto de {{ config('tenancy.dominio') }}</span>
    </div>

    <div class="space-y-3">
        @forelse ($mensajes as $m)
            <div class="bg-white rounded-2xl shadow-sm p-4 {{ $m->atendido ? 'opacity-60' : 'ring-2 ring-indigo-100' }}">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <p class="font-semibold">{{ $m->nombre }}
                            @if ($m->empresa)<span class="text-slate-400 font-normal">· {{ $m->empresa }}</span>@endif
                            @if ($m->servicio)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700">{{ $m->servicio }}</span>@endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}
                            @if ($m->correo) · <a href="mailto:{{ $m->correo }}" class="text-indigo-700">{{ $m->correo }}</a>@endif
                            @if ($m->telefono) · {{ $m->telefono }}@endif
                        </p>
                        <p class="mt-2 text-sm whitespace-pre-line">{{ $m->mensaje }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($tel = preg_replace('/\D/', '', (string) $m->telefono))
                            <a href="https://wa.me/{{ strlen($tel) === 9 ? '51'.$tel : $tel }}?text={{ rawurlencode('Hola '.$m->nombre.', te escribimos de TUSHPA por tu mensaje en nuestra web.') }}"
                               target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">WhatsApp</a>
                        @endif
                        @unless ($m->atendido)
                            <form method="POST" action="{{ route('admin.mensajes.atendido', $m->id) }}">
                                @csrf
                                <button class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold">✔ Atendido</button>
                            </form>
                        @else
                            <span class="text-xs text-emerald-600 font-semibold">✔ Atendido</span>
                        @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-slate-400">Aún no llegan mensajes desde la web.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $mensajes->links() }}</div>
@endsection
