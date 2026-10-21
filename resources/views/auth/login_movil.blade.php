<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b4b">
    <title>Ingresar · Mozos, promotores y cobradores</title>
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; font-family: Poppins, system-ui, sans-serif; color: #fff;
               background: radial-gradient(circle at 15% 10%, #6366f1 0, transparent 45%), radial-gradient(circle at 90% 90%, #db2777 0, transparent 40%), #1e1b4b;
               display: flex; flex-direction: column; padding: calc(18px + env(safe-area-inset-top)) 16px calc(16px + env(safe-area-inset-bottom)); }
        .cab { display: flex; align-items: center; gap: 12px; max-width: 460px; width: 100%; margin: 0 auto 18px; }
        .cab img { width: 46px; height: 46px; border-radius: 14px; background: #fff; padding: 5px; }
        .cab small { display: block; font-size: 11px; letter-spacing: .15em; text-transform: uppercase; opacity: .7; }
        .cab b { font-size: 17px; line-height: 1.2; }
        .hora { margin-left: auto; text-align: right; font-size: 22px; font-weight: 700; line-height: 1; }
        .hora span { display: block; font-size: 11px; font-weight: 500; opacity: .7; }
        .panel { max-width: 460px; width: 100%; margin: 0 auto; flex: 1; display: flex; flex-direction: column; }
        h1 { font-size: 24px; margin: 0 0 2px; font-weight: 800; }
        .sub { margin: 0 0 14px; opacity: .75; font-size: 14px; }
        .buscar { position: relative; margin-bottom: 12px; }
        .buscar input { width: 100%; height: 48px; border-radius: 14px; border: 0; padding: 0 16px 0 44px; font: inherit; font-size: 15px; background: rgba(255,255,255,.12); color: #fff; }
        .buscar input::placeholder { color: rgba(255,255,255,.6); }
        .buscar i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); opacity: .6; }
        .usuarios { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; overflow-y: auto; padding-bottom: 8px; }
        .usuario { border: 1px solid rgba(255,255,255,.14); background: rgba(255,255,255,.08); border-radius: 18px; padding: 16px 8px 14px; cursor: pointer;
                   color: #fff; font: inherit; text-align: center; transition: transform .12s, background .12s; }
        .usuario:active { transform: scale(.96); background: rgba(255,255,255,.18); }
        .avatar { width: 58px; height: 58px; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center;
                  font-weight: 800; font-size: 20px; color: #fff; box-shadow: 0 8px 20px -6px rgba(0,0,0,.5); }
        .usuario .nombre { display: block; font-size: 13px; font-weight: 600; line-height: 1.25; text-transform: uppercase; }
        .rol { display: inline-block; margin-top: 6px; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .05em; background: rgba(255,255,255,.15); }
        .rol.promotor { background: #db2777; }
        .pin { display: flex; flex-direction: column; align-items: center; }
        .pin .avatar { width: 84px; height: 84px; font-size: 30px; margin-bottom: 10px; }
        .pin h2 { margin: 0; font-size: 20px; text-align: center; text-transform: uppercase; }
        .cambiar { margin: 6px 0 18px; background: none; border: 0; color: #c7d2fe; font: inherit; font-size: 13px; cursor: pointer; }
        .puntos { display: flex; gap: 12px; height: 22px; margin-bottom: 8px; }
        .puntos span { width: 16px; height: 16px; border-radius: 50%; background: #fff; animation: entra .15s ease-out; }
        .puntos .vacio { background: transparent; border: 2px solid rgba(255,255,255,.35); animation: none; }
        @keyframes entra { from { transform: scale(.3); } }
        .ayuda { font-size: 12px; opacity: .65; margin-bottom: 14px; }
        .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; width: 100%; max-width: 320px; }
        .tecla { height: 68px; border-radius: 50%; border: 0; background: rgba(255,255,255,.1); color: #fff; font: inherit; font-size: 28px; font-weight: 600; cursor: pointer;
                 aspect-ratio: 1; justify-self: center; width: 68px; transition: background .1s, transform .1s; }
        .tecla:active { background: rgba(255,255,255,.28); transform: scale(.94); }
        .tecla.accion { background: transparent; font-size: 20px; }
        .ingresar { width: 100%; max-width: 320px; margin-top: 20px; height: 56px; border: 0; border-radius: 18px; font: inherit; font-size: 17px; font-weight: 700; color: #1e1b4b;
                    background: linear-gradient(90deg, #a5b4fc, #f9a8d4); cursor: pointer; box-shadow: 0 12px 30px -10px rgba(249,168,212,.6); }
        .ingresar:disabled { opacity: .4; box-shadow: none; }
        .error { background: rgba(244,63,94,.18); border: 1px solid rgba(244,63,94,.5); color: #fecdd3; border-radius: 14px; padding: 10px 12px; font-size: 13px; margin-bottom: 12px;
                 animation: temblar .35s; }
        @keyframes temblar { 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
        .aviso { background: rgba(255,255,255,.1); border-radius: 18px; padding: 18px; font-size: 14px; line-height: 1.5; }
        .pie { text-align: center; margin-top: 18px; font-size: 13px; }
        .pie a { color: #c7d2fe; text-decoration: none; font-weight: 600; }
        .oculto { display: none !important; }
        .vacio-lista { text-align: center; opacity: .6; font-size: 14px; padding: 20px 0; grid-column: 1 / -1; }
    </style>
    @include('partials.pwa')
</head>
<body>
    @include('partials.halloween_login')
    @php
        $colores = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6', '#ef4444', '#14b8a6'];
        $iniciales = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
        $hora = (int) now()->format('H');
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
        $halloween = \App\Support\Temporada::halloween();
    @endphp
    <header class="cab">
        <img src="{{ asset('imagenes/192.png') }}" alt="">
        <div><small @class(['saludo-hw' => $halloween])>{{ $halloween ? '🎃 Feliz Halloween' : $saludo }}</small><b>{{ $sucursal->nombre_comercial ?? 'TUSHPA' }}</b></div>
        <div class="hora" id="reloj">{{ now()->format('H:i') }}<span>{{ now()->locale('es')->isoFormat('ddd D MMM') }}</span></div>
    </header>

    <main class="panel">
        @if (! $sucursal)
            <div class="aviso"><i class="fas fa-circle-info"></i> Este equipo aún no está asociado a una sucursal.<br>
                Pide al administrador o cajero que inicie sesión <b>una vez</b> desde aquí con su usuario y contraseña.</div>
        @elseif ($mozos->isEmpty())
            <div class="aviso"><i class="fas fa-circle-info"></i> No hay mozos, promotores ni cobradores con código móvil en <b>{{ $sucursal->nombre_comercial }}</b>.<br>
                Créalos en <em>Usuarios</em> con el rol Mozo, Promotor o Cobrador y su código.</div>
        @else
            @if ($errors->any())
                <div class="error"><i class="fas fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
            @endif

            {{-- Paso 1: ¿quién eres? --}}
            <section id="paso_usuario">
                <h1>¿Quién eres?@if ($halloween) <span class="calabaza">🎃</span>@endif</h1>
                <p class="sub">Toca tu nombre para entrar</p>
                @if ($mozos->count() > 8)
                    <div class="buscar"><i class="fas fa-magnifying-glass"></i><input id="buscar" type="search" placeholder="Buscar mi nombre" autocomplete="off"></div>
                @endif
                <div class="usuarios">
                    @foreach ($mozos as $i => $m)
                        @php $promotor = (int) $m->role_id === 13; @endphp
                        <button type="button" class="usuario" data-id="{{ $m->IdUsuario }}" data-nombre="{{ $m->apeusu }}" data-color="{{ $colores[$m->IdUsuario % count($colores)] }}"
                                data-iniciales="{{ $iniciales($m->apeusu) }}">
                            <span class="avatar" style="background: {{ $colores[$m->IdUsuario % count($colores)] }}">{{ $iniciales($m->apeusu) }}</span>
                            <span class="nombre">{{ $m->apeusu }}</span>
                            <span class="rol {{ $promotor || (int) $m->role_id === 14 ? 'promotor' : '' }}">{{ ['13' => 'PROMOTOR', '14' => 'COBRADOR'][(string) $m->role_id] ?? 'MOZO' }}</span>
                        </button>
                    @endforeach
                    <p class="vacio-lista oculto" id="sin_resultados">No encontramos ese nombre.</p>
                </div>
            </section>

            {{-- Paso 2: código --}}
            <form id="paso_codigo" class="pin oculto" method="POST" action="{{ route('login.movil') }}">
                @csrf
                <input type="hidden" name="usuario" id="usuario" value="{{ old('usuario') }}">
                <input type="hidden" name="codigo" id="codigo">
                <span class="avatar" id="avatar"></span>
                <h2 id="nombre_mozo"></h2>
                <button type="button" class="cambiar" id="cambiar"><i class="fas fa-arrow-left"></i> No soy yo</button>
                <div class="puntos" id="puntos"></div>
                <p class="ayuda">Escribe tu código (de 1 a 8 números)</p>
                <div class="teclado">
                    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                        <button type="button" class="tecla" data-n="{{ $n }}">{{ $n }}</button>
                    @endforeach
                    <button type="button" class="tecla accion" id="limpiar" aria-label="Borrar todo"><i class="fas fa-xmark"></i></button>
                    <button type="button" class="tecla" data-n="0">0</button>
                    <button type="button" class="tecla accion" id="borrar" aria-label="Borrar"><i class="fas fa-delete-left"></i></button>
                </div>
                <button class="ingresar" id="ingresar" disabled><i class="fas fa-right-to-bracket"></i> Ingresar</button>
            </form>
        @endif

        <div class="pie"><a href="{{ route('login', ['escritorio' => 1]) }}"><i class="fas fa-desktop"></i> Entrar con usuario y contraseña</a></div>
    </main>

<script>
    (function () {
        const MAX = 8;
        const reloj = document.getElementById('reloj');
        setInterval(() => { const d = new Date(); reloj.firstChild.nodeValue = d.toTimeString().slice(0, 5); }, 15000);

        const pasoUsuario = document.getElementById('paso_usuario');
        const pasoCodigo = document.getElementById('paso_codigo');
        if (!pasoCodigo) return;
        const codigo = document.getElementById('codigo');
        const btn = document.getElementById('ingresar');
        let valor = '';

        const pintar = () => {
            codigo.value = valor;
            document.getElementById('puntos').innerHTML = Array.from({ length: Math.max(4, valor.length) }, (_, i) => `<span class="${i < valor.length ? '' : 'vacio'}"></span>`).join('');
            btn.disabled = valor.length < 1;
        };
        const elegir = (b) => {
            document.getElementById('usuario').value = b.dataset.id;
            document.getElementById('nombre_mozo').textContent = b.dataset.nombre;
            const av = document.getElementById('avatar');
            av.textContent = b.dataset.iniciales; av.style.background = b.dataset.color;
            valor = ''; pintar();
            pasoUsuario.classList.add('oculto'); pasoCodigo.classList.remove('oculto');
        };
        const tecla = (n) => { if (valor.length < MAX) { valor += n; pintar(); navigator.vibrate?.(10); } };

        document.querySelectorAll('.usuario').forEach(b => b.addEventListener('click', () => elegir(b)));
        document.getElementById('cambiar').addEventListener('click', () => { pasoCodigo.classList.add('oculto'); pasoUsuario.classList.remove('oculto'); });
        document.querySelectorAll('.tecla[data-n]').forEach(b => b.addEventListener('click', () => tecla(b.dataset.n)));
        document.getElementById('borrar').addEventListener('click', () => { valor = valor.slice(0, -1); pintar(); });
        document.getElementById('limpiar').addEventListener('click', () => { valor = ''; pintar(); });
        document.addEventListener('keydown', e => {
            if (pasoCodigo.classList.contains('oculto')) return;
            if (/^\d$/.test(e.key)) tecla(e.key);
            else if (e.key === 'Backspace') { valor = valor.slice(0, -1); pintar(); }
            else if (e.key === 'Enter' && !btn.disabled) { e.preventDefault(); pasoCodigo.requestSubmit(); }
        });
        pasoCodigo.addEventListener('submit', () => { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ingresando…'; });

        // Buscar por nombre cuando hay muchos
        const buscar = document.getElementById('buscar');
        buscar?.addEventListener('input', () => {
            const q = buscar.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            let hay = 0;
            document.querySelectorAll('.usuario').forEach(b => {
                const ok = b.dataset.nombre.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(q);
                b.classList.toggle('oculto', !ok); hay += ok;
            });
            document.getElementById('sin_resultados').classList.toggle('oculto', hay > 0);
        });

        // Si volvió con error, se queda en el teclado del mismo usuario
        const previo = document.getElementById('usuario').value;
        const b = previo && document.querySelector(`.usuario[data-id="${previo}"]`);
        if (b) elegir(b);
    })();
</script>
</body>
</html>
