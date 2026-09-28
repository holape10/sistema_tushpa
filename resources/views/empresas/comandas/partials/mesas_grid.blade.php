@forelse ($mesas as $m)
    <button type="button"
        class="btn-mesa-comanda btn-mesa-kiosko {{ $m->mes_est == 'Libre' ? 'libre' : 'ocupado' }}"
        data-id="{{ $m->mes_id }}" data-nombre="{{ $m->mes_nom }}" data-estado="{{ $m->mes_est }}"
        data-pedido-id="{{ $m->pedido_id }}">
        {{ $m->mes_nom }}<br>
        <span style="font-size: 0.75em; font-weight: normal;">{{ $m->mes_est }}</span>
        @if ($m->mes_est != 'Libre' && $m->pedido_fecha_hora)
            <span class="mesa-timer" data-inicio="{{ \Carbon\Carbon::parse($m->pedido_fecha_hora)->toIso8601String() }}">00:00:00</span>
        @endif
    </button>
@empty
    <p style="width:100%; text-align:center; color:#888;">Sin mesas en este piso</p>
@endforelse