@extends('admin.layout')
@section('title', 'Ticket N° '.$ticket->id)

@section('content')
    @php
        $empresa = $ticket->nombre_comercial ?: $ticket->razon_social;
        $celular = preg_replace('/\D/', '', (string) ($ticket->telefono ?: $ticket->contacto_telefono));
    @endphp
    <div class="max-w-4xl mx-auto space-y-4">
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ route('admin.tickets.index') }}" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold flex items-center">← Tickets</a>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-400">Ticket N° {{ $ticket->id }} · {{ \App\Support\Soporte::TIPOS[$ticket->tipo] ?? $ticket->tipo }} · {{ \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}
                        @if ($ticket->prioridad === 'URGENTE')<span class="ml-1 px-1.5 rounded bg-rose-100 text-rose-700 font-bold">URGENTE</span>@endif</p>
                    <h1 class="text-lg font-bold">{{ $ticket->asunto }}</h1>
                </div>
                <form method="POST" action="{{ route('admin.tickets.estado', $ticket->id) }}" class="flex items-center gap-2">@csrf
                    <select name="estado" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm font-semibold">
                        @foreach (\App\Support\Soporte::ESTADOS as $valor => $nombre)<option value="{{ $valor }}" @selected($ticket->estado === $valor)>{{ $nombre }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="mt-4 grid sm:grid-cols-3 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-xs text-slate-400">Empresa</p><p class="font-semibold">{{ $empresa }}</p><p class="text-xs text-slate-500">RUC {{ $ticket->ruc }}</p></div>
                <div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-xs text-slate-400">Lo envió</p><p class="font-semibold">{{ $ticket->usuario }}</p><p class="text-xs text-slate-500">{{ $ticket->sucursal }}</p></div>
                <div class="rounded-xl bg-slate-50 px-3 py-2 flex items-center gap-2">
                    <div class="flex-1"><p class="text-xs text-slate-400">Contacto</p><p class="font-semibold">{{ $celular ?: '—' }}</p></div>
                    @if (strlen($celular) >= 9)
                        <a href="https://wa.me/{{ strlen($celular) === 9 ? '51'.$celular : $celular }}?text={{ rawurlencode('Hola, te escribimos del soporte de TUSHPA por tu ticket N° '.$ticket->id.': '.$ticket->asunto) }}"
                           target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold">WhatsApp</a>
                    @endif
                </div>
            </div>
        </div>

        @include('partials.soporte_conversacion', [
            'yo' => 'SOPORTE',
            'urlArchivo' => fn ($mensaje, $n) => route('admin.tickets.archivo', [$ticket->id, $mensaje, $n]),
            'urlResponder' => route('admin.tickets.responder', $ticket->id),
            'extras' => new \Illuminate\Support\HtmlString('<label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="checkbox" name="cerrar" value="1" class="rounded"> Responder y cerrar</label>'),
        ])
    </div>
@endsection
