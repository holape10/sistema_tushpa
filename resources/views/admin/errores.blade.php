@extends('admin.layout')
@section('title', 'Errores del sistema')

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold">🚨 Errores del sistema</h1>
            <p class="text-sm text-slate-500">Cuando algo falla en cualquier empresa queda aquí (una sola vez, con cuántas veces pasó) y te avisa por Telegram.</p>
        </div>
        <form method="POST" action="{{ route('admin.errores.probar') }}">@csrf
            <button class="px-3 h-9 rounded-xl bg-sky-50 text-sky-700 text-sm font-semibold">📲 Probar aviso a Telegram</button></form>
        @if (! $verResueltos && $pendientes)
            <form method="POST" action="{{ route('admin.errores.resolver_todos') }}" onsubmit="return confirm('¿Marcar todos como resueltos?')">@csrf
                <button class="px-3 h-9 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-semibold">✔ Marcar todos resueltos</button></form>
        @endif
    </div>

    @unless ($telegram)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            <b>Los avisos a Telegram están apagados.</b> Los errores igual se anotan aquí. Para recibirlos en tu celular, pon
            <code>TELEGRAM_BOT_TOKEN</code> y <code>TELEGRAM_CHAT_ID</code> en el archivo <code>.env</code> del servidor.
        </div>
    @endunless

    <div class="flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm w-fit mb-3">
        <a href="{{ route('admin.errores.index') }}" class="px-3 py-1.5 rounded-lg font-semibold {{ $verResueltos ? 'text-slate-600 hover:bg-slate-100' : 'bg-slate-900 text-white' }}">Sin resolver ({{ $pendientes }})</a>
        <a href="{{ route('admin.errores.index', ['resueltos' => 1]) }}" class="px-3 py-1.5 rounded-lg font-semibold {{ $verResueltos ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Resueltos</a>
    </div>

    <div class="space-y-3">
        @forelse ($errores as $e)
            <div class="bg-white rounded-2xl shadow-sm p-4" x-data="{ ver: false }">
                <div class="flex flex-wrap items-start gap-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-black {{ $e->veces > 10 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $e->veces }}×</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-800 break-words">{{ \Illuminate\Support\Str::limit($e->mensaje, 220) }}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            @if ($e->empresa)🏢 {{ $e->empresa }} · @endif
                            @if ($e->usuario)👤 {{ $e->usuario }} · @endif
                            Última vez {{ \Carbon\Carbon::parse($e->ultima_vez)->format('d/m/Y H:i') }}
                            @if ($e->veces > 1) · primera {{ \Carbon\Carbon::parse($e->primera_vez)->format('d/m/Y H:i') }}@endif
                            @if ($e->avisado_el) · 📲 avisado @endif
                        </p>
                    </div>
                    <button type="button" @click="ver = !ver" class="px-3 h-8 rounded-lg bg-slate-100 text-xs font-semibold" x-text="ver ? 'Ocultar' : 'Detalle'"></button>
                    @unless ($e->resuelto)
                        <form method="POST" action="{{ route('admin.errores.resolver', $e->id) }}">@csrf
                            <button class="px-3 h-8 rounded-lg bg-emerald-600 text-white text-xs font-bold">✔ Resuelto</button></form>
                    @endunless
                </div>
                <div x-show="ver" x-cloak class="mt-3 rounded-xl bg-slate-50 p-3 text-xs font-mono text-slate-700 space-y-1 break-words">
                    <p><b>Tipo:</b> {{ $e->tipo }}</p>
                    <p><b>Archivo:</b> {{ $e->archivo }}:{{ $e->linea }}</p>
                    @if ($e->url)<p><b>Dónde:</b> {{ $e->url }}</p>@endif
                    @if ($e->base_datos)<p><b>Base:</b> {{ $e->base_datos }}</p>@endif
                    <p class="whitespace-pre-wrap"><b>Mensaje:</b> {{ $e->mensaje }}</p>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-slate-400">{{ $verResueltos ? 'Aún no hay errores resueltos.' : 'Sin errores pendientes 🎉' }}</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $errores->links() }}</div>
@endsection
