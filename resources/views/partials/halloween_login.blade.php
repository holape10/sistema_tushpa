{{-- Halloween en el ingreso móvil: noche morada, luna, telarañas y unos murciélagos y calabazas que flotan al fondo (no tapan nada) --}}
@if (\App\Support\Temporada::halloween())
    <style>
        body.halloween {
            background:
                radial-gradient(circle at 82% 78%, rgba(254, 243, 199, .9) 0 22px, rgba(253, 230, 138, .18) 23px 46px, transparent 47px),
                radial-gradient(circle at 10% 95%, rgba(234, 88, 12, .55) 0, transparent 45%),
                radial-gradient(circle at 95% 60%, rgba(124, 58, 237, .45) 0, transparent 40%),
                linear-gradient(180deg, #160b2e 0%, #24123f 55%, #2b1035 100%);
        }
        .halloween .usuario { background: rgba(255, 255, 255, .07); border-color: rgba(251, 146, 60, .25); }
        .halloween .usuario:active { background: rgba(251, 146, 60, .22); }
        .halloween .buscar input { background: rgba(255, 255, 255, .1); }
        .halloween .rol.promotor { background: #ea580c; }
        .halloween .tecla { background: rgba(251, 146, 60, .12); }
        .halloween .tecla:active { background: rgba(251, 146, 60, .35); }
        .halloween .ingresar { background: linear-gradient(90deg, #fb923c, #f59e0b); color: #1c0d2e; box-shadow: 0 12px 30px -10px rgba(251, 146, 60, .7); }
        .halloween .cambiar, .halloween .pie a { color: #fdba74; }
        .halloween h1 .calabaza { display: inline-block; animation: hw-balanceo 2.4s ease-in-out infinite; transform-origin: 50% 90%; }
        .halloween .saludo-hw { color: #fdba74; font-weight: 700; }

        /* Adornos: detrás del contenido y sin recibir toques */
        .hw-fondo { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .halloween .cab, .halloween .panel { position: relative; z-index: 1; }
        .hw-volador { position: absolute; font-size: 22px; opacity: .55; animation: hw-vuela 16s linear infinite; }
        .hw-calabaza { position: absolute; bottom: 10px; font-size: 26px; opacity: .45; animation: hw-flota 5s ease-in-out infinite; }
        .hw-telarana { position: absolute; width: 120px; height: 120px; opacity: .22; }
        @keyframes hw-vuela {
            0% { transform: translate(-60px, 0) scaleX(1); }
            25% { transform: translate(25vw, -18px) scaleX(1); }
            50% { transform: translate(50vw, 10px) scaleX(1); }
            75% { transform: translate(75vw, -12px) scaleX(1); }
            100% { transform: translate(calc(100vw + 60px), 0) scaleX(1); }
        }
        @keyframes hw-flota { 50% { transform: translateY(-8px) rotate(-6deg); } }
        @keyframes hw-balanceo { 50% { transform: rotate(-12deg); } }
        @media (prefers-reduced-motion: reduce) { .hw-volador, .hw-calabaza, .halloween h1 .calabaza { animation: none; } }
    </style>
    <div class="hw-fondo" aria-hidden="true">
        <svg class="hw-telarana" style="top:0; left:0" viewBox="0 0 100 100"><g fill="none" stroke="#fff" stroke-width="1">
            <path d="M0 0 L100 30 M0 0 L70 70 M0 0 L30 100"/><path d="M0 22 Q12 14 18 6"/><path d="M0 45 Q26 32 38 12"/><path d="M0 70 Q40 52 58 18"/></g></svg>
        <svg class="hw-telarana" style="top:0; right:0; transform: scaleX(-1)" viewBox="0 0 100 100"><g fill="none" stroke="#fff" stroke-width="1">
            <path d="M0 0 L100 30 M0 0 L70 70 M0 0 L30 100"/><path d="M0 22 Q12 14 18 6"/><path d="M0 45 Q26 32 38 12"/></g></svg>
        <span class="hw-volador" style="top: 18%; animation-delay: -2s">🦇</span>
        <span class="hw-volador" style="top: 42%; animation-delay: -9s; animation-duration: 21s; font-size: 16px">🦇</span>
        <span class="hw-volador" style="top: 70%; animation-delay: -14s; animation-duration: 25s; font-size: 18px">👻</span>
        <span class="hw-calabaza" style="left: 6%">🎃</span>
        <span class="hw-calabaza" style="right: 8%; animation-delay: -2.5s; font-size: 20px">🎃</span>
    </div>
    <script>document.body.classList.add('halloween');</script>
@endif
