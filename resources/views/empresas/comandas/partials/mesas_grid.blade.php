@forelse ($mesas as $m)
    {{-- El estado sale del pedido abierto real, no del campo mes_est (que puede quedar desfasado) --}}
    @php
        $estado = $m->pedido_id ? 'Ocupado' : 'Libre';
        // Mesa junta a otra (grupo grande): al tocarla se trabaja con la mesa principal del grupo
        $principal = $m->principal ?? null;
        $color = $principal ? 'bg-violet-600' : ($estado === 'Libre' ? 'bg-emerald-700' : 'bg-rose-600');
    @endphp
    <button type="button"
        class="btn-mesa-comanda relative min-h-[88px] rounded-2xl px-2 py-2.5 text-white shadow-sm ring-1 ring-black/5 active:scale-95 transition-transform select-none {{ $color }}"
        data-id="{{ $principal['mes_id'] ?? $m->mes_id }}" data-nombre="{{ $principal['nombre'] ?? $m->etiqueta }}" data-estado="{{ $estado }}"
        data-pedido-id="{{ $m->pedido_id }}" data-listos="{{ $m->listos ?? 0 }}">
        <span class="block text-base font-black leading-tight">{{ $m->mes_nom }}</span>
        <span class="block text-xs font-semibold opacity-95 mt-0.5">
            @if ($principal)
                🔗 Junta con {{ $principal['nombre'] }}
            @else
                {{ $estado }}@if ($m->pedido_id) · S/ {{ number_format($m->ped_tot, 2) }}@endif
            @endif
        </span>
        @if ($m->pedido_id && $m->pedido_fecha_hora)
            <span class="mesa-timer block font-mono text-[11px] opacity-90" data-inicio="{{ \Carbon\Carbon::parse($m->pedido_fecha_hora)->toIso8601String() }}">00:00:00</span>
        @endif
        {{-- Quién atiende la mesa (solo en las sucursales con nombres cortos) --}}
        @if (! empty($m->mozo_nombre))
            <span class="mt-1 inline-flex max-w-full items-center justify-center gap-1 rounded-xl bg-black/25 px-2 py-0.5 text-[11px] font-bold leading-tight" title="Mozo: {{ $m->mozo_nombre }}">
                <svg class="h-3 w-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0H3z"/></svg>
                <span class="break-words">{{ $m->mozo_nombre }}</span>
            </span>
        @endif
        {{-- Cocina ya lo tiene listo: el mozo debe llevarlo --}}
        @if (($m->listos ?? 0) > 0)
            <span class="absolute -top-2 -right-1.5 rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-black text-black shadow animate-pulse" title="Listo en cocina para servir">🔔 LISTO</span>
        @endif
        {{-- Reserva próxima --}}
        @if (!empty($m->reserva))
            <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full border-2 border-violet-600 bg-white px-2 text-[10px] font-black text-violet-700"
                  title="Reserva de {{ $m->reserva->nombre_cliente }}">📅 {{ substr($m->reserva->hora_inicio, 0, 5) }}</span>
        @endif
    </button>
@empty
    <p class="col-span-full py-8 text-center text-gray-400">Sin mesas en este piso</p>
@endforelse
