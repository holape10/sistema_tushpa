@extends('layouts.app')
@section('title', 'Ticket N° '.$ticket->id)

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    @include('empresas.partials.alert')

    <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-wrap items-start gap-3">
        <a href="{{ route('soporte') }}" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold flex items-center">← Soporte</a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-slate-400">Ticket N° {{ $ticket->id }} · {{ \App\Support\Soporte::TIPOS[$ticket->tipo] ?? $ticket->tipo }} · {{ \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}</p>
            <h1 class="text-lg font-bold text-slate-800">{{ $ticket->asunto }}</h1>
        </div>
        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100">{{ \App\Support\Soporte::ESTADOS[$ticket->estado] ?? $ticket->estado }}</span>
        @if ($ticket->estado !== 'CERRADO')
            <form method="POST" action="{{ route('soporte.cerrar', $ticket->id) }}" onsubmit="return confirm('¿Ya se solucionó? El ticket se cerrará.')">@csrf
                <button class="h-9 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">✔ Ya se solucionó</button>
            </form>
        @endif
    </div>

    @include('partials.soporte_conversacion', [
        'yo' => 'CLIENTE',
        'urlArchivo' => fn ($mensaje, $n) => route('soporte.archivo', [$ticket->id, $mensaje, $n]),
        'urlResponder' => route('soporte.responder', $ticket->id),
    ])
</div>
@endsection
