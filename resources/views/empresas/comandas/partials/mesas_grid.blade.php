@forelse ($mesas as $m)
    {{-- El estado sale del pedido abierto real, no del campo mes_est (que puede quedar desfasado) --}}
    @php
        $estado = $m->pedido_id ? 'Ocupado' : 'Libre';
        // Mesa junta a otra (grupo grande): al tocarla se trabaja con la mesa principal del grupo
        $principal = $m->principal ?? null;
    @endphp
    <button type="button"
        class="btn-mesa-comanda btn-mesa-kiosko {{ $principal ? 'junta' : ($estado == 'Libre' ? 'libre' : 'ocupado') }}"
        data-id="{{ $principal['mes_id'] ?? $m->mes_id }}" data-nombre="{{ $principal['nombre'] ?? $m->etiqueta }}" data-estado="{{ $estado }}"
        data-pedido-id="{{ $m->pedido_id }}" data-listos="{{ $m->listos ?? 0 }}" style="position:relative;">
        {{ $m->mes_nom }}<br>
        <span style="font-size: 0.75em; font-weight: normal;">
            @if ($principal)
                🔗 Junta con {{ $principal['nombre'] }}
            @else
                {{ $estado }}@if ($m->pedido_id) · S/ {{ number_format($m->ped_tot, 2) }}@endif
            @endif
        </span>
        @if ($m->pedido_id && $m->pedido_fecha_hora)
            <span class="mesa-timer" data-inicio="{{ \Carbon\Carbon::parse($m->pedido_fecha_hora)->toIso8601String() }}">00:00:00</span>
        @endif
        {{-- Cocina ya lo tiene listo: el mozo debe llevarlo --}}
        @if (($m->listos ?? 0) > 0)
            <span class="mesa-listo" title="Listo en cocina para servir">🔔 LISTO</span>
        @endif
        {{-- Reserva próxima --}}
        @if (!empty($m->reserva))
            <span class="mesa-reserva" title="Reserva de {{ $m->reserva->nombre_cliente }}">📅 {{ substr($m->reserva->hora_inicio, 0, 5) }}</span>
        @endif
    </button>
@empty
    <p style="width:100%; text-align:center; color:#888;">Sin mesas en este piso</p>
@endforelse
