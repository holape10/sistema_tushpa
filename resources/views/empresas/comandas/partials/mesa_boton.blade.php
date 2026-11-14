{{-- Una mesa en Comandas (cuadrícula o plano del local). Usa $m (de ComandasController::mesasDelPiso) y $plano --}}
{{-- El estado sale del pedido abierto real, no del campo mes_est (que puede quedar desfasado) --}}
@php
    $estado = $m->pedido_id ? 'Ocupado' : 'Libre';
    // Mesa junta a otra (grupo grande): al tocarla se trabaja con la mesa principal del grupo
    $principal = $m->principal ?? null;
    $color = $principal ? 'bg-violet-600' : ($estado === 'Libre' ? 'bg-emerald-700' : 'bg-rose-600');
@endphp
@php
    $enPlano = ($plano ?? false) && $m->plano_x !== null;
    $forma = $enPlano ? ['REDONDA' => 'rounded-full', 'BOX' => 'rounded-2xl border-2 border-amber-300'][$m->plano_forma] ?? 'rounded-xl' : 'rounded-2xl';
    // En el plano la letra va según el ancho del plano (cqw): se lee igual en el celular y en la PC
    $letra = fn (int $min, float $cqw, int $max) => $enPlano ? 'style="font-size: clamp('.$min.'px, '.$cqw.'cqw, '.$max.'px)"' : '';
@endphp
<button type="button" @if ($enPlano) style="{{ ($girar ?? false) ? \App\Support\PlanoMesas::variables($m->plano_x, $m->plano_y, $m->plano_w, $m->plano_h, $alto, $ancho) : \App\Support\PlanoMesas::estiloMesa($m->plano_x, $m->plano_y, $m->plano_w, $m->plano_h, $alto ?? \App\Support\PlanoMesas::ALTO, $ancho ?? \App\Support\PlanoMesas::ANCHO) }}" @endif
    class="btn-mesa-comanda {{ $enPlano ? 'absolute '.($m->pedido_id ? 'z-30' : 'z-20').' flex flex-col items-center justify-center overflow-visible px-2 py-1 leading-tight whitespace-nowrap' : 'relative min-h-[88px] px-2 py-2.5' }} {{ $forma }} text-white shadow-sm ring-1 ring-black/5 active:scale-95 transition-transform select-none {{ $color }}"
    data-id="{{ $principal['mes_id'] ?? $m->mes_id }}" data-nombre="{{ $principal['nombre'] ?? $m->etiqueta }}" data-estado="{{ $estado }}"
    data-pedido-id="{{ $m->pedido_id }}" data-listos="{{ $m->listos ?? 0 }}" data-listos-texto="{{ $m->listos_texto ?? '' }}">
    @if ($enPlano && $m->plano_forma === 'BOX')<span class="block font-black tracking-widest text-amber-200" {!! $letra(8, 1.2, 11) !!}>VIP</span>@endif
    <span class="block {{ $enPlano ? '' : 'text-base' }} font-black leading-tight" {!! $letra(12, 2.4, 19) !!}>{{ $m->mes_nom }}</span>
    <span class="block {{ $enPlano ? '' : 'text-xs' }} font-semibold opacity-95 mt-0.5" {!! $letra(10, 1.7, 14) !!}>
        @if ($principal)
            🔗 Junta con {{ $principal['nombre'] }}
        @else
            {{ $estado }}@if ($m->pedido_id) · S/ {{ number_format($m->ped_tot, 2) }}@endif
        @endif
    </span>
    @if ($m->pedido_id && $m->pedido_fecha_hora)
        <span class="mesa-timer block font-mono {{ $enPlano ? '' : 'text-[11px]' }} opacity-90" {!! $letra(10, 1.6, 13) !!} data-inicio="{{ \Carbon\Carbon::parse($m->pedido_fecha_hora)->toIso8601String() }}">00:00:00</span>
    @endif
    {{-- Quién atiende la mesa (solo en las sucursales con nombres cortos) --}}
    @if (! empty($m->mozo_nombre))
        <span class="mt-1 inline-flex {{ $enPlano ? '' : 'max-w-full' }} items-center justify-center gap-1 rounded-xl bg-black/25 px-2 py-0.5 {{ $enPlano ? '' : 'text-[11px]' }} font-bold leading-tight" {!! $letra(10, 1.6, 13) !!} title="Mozo: {{ $m->mozo_nombre }}">
            <svg class="h-3 w-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0H3z"/></svg>
            <span class="break-words">{{ $m->mozo_nombre }}</span>
        </span>
    @endif
    {{-- Cocina ya lo tiene listo: el mozo debe llevarlo --}}
    @if (($m->listos ?? 0) > 0)
        <span class="absolute -top-2 -right-1.5 rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-black text-black shadow animate-pulse" title="Listo para llevar: {{ $m->listos_texto ?? '' }}">🔔 LISTO{{ ($m->listos ?? 0) > 1 ? ' ×'.$m->listos : '' }}</span>
    @endif
    {{-- Reserva próxima --}}
    @if (!empty($m->reserva))
        <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full border-2 border-violet-600 bg-white px-2 text-[10px] font-black text-violet-700"
              title="Reserva de {{ $m->reserva->nombre_cliente }}">📅 {{ substr($m->reserva->hora_inicio, 0, 5) }}</span>
    @endif
</button>
