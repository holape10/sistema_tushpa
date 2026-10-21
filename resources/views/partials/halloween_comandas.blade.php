{{-- Halloween en Comandas: solo adornos suaves (guirnalda y una telaraña en la esquina); las mesas no cambian de color --}}
@if (\App\Support\Temporada::halloween())
    <style>
        .hw-guirnalda { display: flex; justify-content: space-between; align-items: flex-start; height: 26px; margin: -6px 0 2px; overflow: hidden;
                        background: radial-gradient(ellipse 60% 12px at 50% 0, transparent 70%, rgba(124, 58, 237, .18) 72%, transparent 76%) 0 0 / 70px 26px repeat-x; }
        .hw-guirnalda span { font-size: 15px; opacity: .8; animation: hw-colgar 3s ease-in-out infinite; transform-origin: 50% 0; }
        .hw-guirnalda span:nth-child(even) { animation-delay: -1.5s; font-size: 13px; }
        .hw-esquina { position: fixed; right: 0; bottom: 0; width: 140px; height: 140px; opacity: .12; pointer-events: none; z-index: 0; transform: rotate(180deg); }
        @keyframes hw-colgar { 50% { transform: rotate(8deg); } }
        @media (prefers-reduced-motion: reduce) { .hw-guirnalda span { animation: none; } }
    </style>
    <div class="hw-guirnalda" aria-hidden="true">
        @foreach (['🎃', '🦇', '🕸️', '🎃', '👻', '🦇', '🎃', '🕸️', '🦇', '🎃'] as $i => $adorno)
            <span @class(['hidden sm:inline' => $i >= 6])>{{ $adorno }}</span>
        @endforeach
    </div>
    <svg class="hw-esquina" viewBox="0 0 100 100" aria-hidden="true"><g fill="none" stroke="#4c1d95" stroke-width="1.2">
        <path d="M0 0 L100 30 M0 0 L70 70 M0 0 L30 100"/><path d="M0 22 Q12 14 18 6"/><path d="M0 45 Q26 32 38 12"/><path d="M0 70 Q40 52 58 18"/></g></svg>
@endif
