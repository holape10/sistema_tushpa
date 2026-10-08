<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Iniciar Sesión - Mozos</title>
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; min-height: 100vh; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
               display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #fff; width: 100%; max-width: 380px; border-radius: 22px; padding: 26px 22px 18px; box-shadow: 0 20px 50px rgba(0,0,0,.25); text-align: center; }
        .logo { width: 96px; height: 96px; object-fit: contain; }
        h1 { font-size: 22px; margin: 10px 0 4px; color: #2d2d2d; letter-spacing: .5px; }
        .sub { font-size: 14px; font-weight: bold; color: #444; margin: 8px 0 14px; }
        .usuarios { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 52vh; overflow-y: auto; padding: 2px; }
        .usuario { border: 1px solid #e3e3e8; background: #f7f7fa; border-radius: 12px; padding: 14px 8px; cursor: pointer; font-size: 12px; font-weight: bold;
                   color: #333; text-transform: uppercase; line-height: 1.25; transition: .15s; }
        .usuario i { display: block; color: #667eea; font-size: 18px; margin-bottom: 6px; }
        .usuario:active { transform: scale(.97); background: #eef0ff; }
        .codigo { width: 100%; border: 2px solid #667eea; border-radius: 12px; background: #f4f4f4; padding: 14px; font-size: 26px; text-align: center;
                  letter-spacing: 10px; margin-bottom: 12px; color: #333; }
        .codigo::placeholder { font-size: 14px; letter-spacing: 0; color: #999; font-weight: bold; }
        .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .tecla { border: none; border-radius: 12px; background: linear-gradient(180deg, #f1f1f1, #dedede); font-size: 24px; font-weight: bold; color: #333;
                 height: 62px; box-shadow: 0 2px 4px rgba(0,0,0,.12); cursor: pointer; }
        .tecla:active { transform: scale(.96); }
        .tecla.cero { grid-column: span 2; }
        .tecla.borrar { background: linear-gradient(180deg, #ff7b8a, #f0566a); color: #fff; }
        .ingresar { width: 100%; margin-top: 12px; border: none; border-radius: 12px; padding: 15px; font-size: 16px; font-weight: bold; color: #fff;
                    background: linear-gradient(90deg, #667eea, #764ba2); cursor: pointer; }
        .ingresar:disabled { background: #ccc; cursor: not-allowed; }
        .hola { font-size: 15px; font-weight: bold; color: #333; margin: 6px 0 2px; }
        .cambiar { font-size: 12px; color: #667eea; cursor: pointer; text-decoration: underline; background: none; border: none; margin-bottom: 12px; }
        .error { background: #fde8ea; color: #b4232f; border-radius: 10px; padding: 8px; font-size: 13px; margin-bottom: 10px; }
        .aviso { background: #fff8e1; color: #8a6d00; border-radius: 10px; padding: 12px; font-size: 13px; line-height: 1.4; }
        .pie { margin-top: 16px; font-size: 13px; }
        .pie a { color: #667eea; font-weight: bold; text-decoration: none; }
        .pie small { display: block; margin-top: 8px; color: #999; font-size: 10px; letter-spacing: .5px; }
        .oculto { display: none; }
    </style>
    @include('partials.pwa')
</head>
<body>
<div class="card">
    <img src="{{ asset('imagenes/512.png') }}" alt="Logo" class="logo">
    <h1>INICIAR SESIÓN</h1>

    @if (!$sucursal)
        <div class="aviso">
            <i class="fas fa-circle-info"></i> Este equipo aún no está asociado a una sucursal.<br>
            Pide al administrador o cajero que inicie sesión <strong>una vez</strong> desde aquí con su usuario y contraseña.
        </div>
    @elseif ($mozos->isEmpty())
        <div class="aviso">
            <i class="fas fa-circle-info"></i> No hay mozos activos con código móvil en <strong>{{ $sucursal->nombre_comercial }}</strong>.<br>
            Créalos en <em>Usuarios</em> con el rol Mozo y su código.
        </div>
    @else
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        {{-- Paso 1: elegir el mozo --}}
        <div id="paso_usuario">
            <p class="sub">Selecciona tu usuario</p>
            <div class="usuarios">
                @foreach ($mozos as $m)
                    <button type="button" class="usuario" data-id="{{ $m->IdUsuario }}" data-nombre="{{ $m->apeusu }}">
                        <i class="fas fa-user"></i>{{ $m->apeusu }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Paso 2: escribir el código --}}
        <form id="paso_codigo" class="oculto" method="POST" action="{{ route('login.movil') }}">
            @csrf
            <input type="hidden" name="usuario" id="usuario" value="{{ old('usuario') }}">
            <p class="hola">Hola, <span id="nombre_mozo"></span></p>
            <button type="button" class="cambiar" id="cambiar"><i class="fas fa-arrow-left"></i> Cambiar usuario</button>
            <input type="password" name="codigo" id="codigo" class="codigo" placeholder="Ingrese su código" inputmode="numeric" maxlength="6" readonly autocomplete="off">
            <div class="teclado">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                    <button type="button" class="tecla" data-n="{{ $n }}">{{ $n }}</button>
                @endforeach
                <button type="button" class="tecla cero" data-n="0">0</button>
                <button type="button" class="tecla borrar" id="borrar"><i class="fas fa-arrow-left"></i></button>
            </div>
            <button class="ingresar" id="ingresar" disabled><i class="fas fa-right-to-bracket"></i> INGRESAR</button>
        </form>
    @endif

    <div class="pie">
        <a href="{{ route('login', ['escritorio' => 1]) }}"><i class="fas fa-desktop"></i> Ir a Login Escritorio</a>
        <small>SISTEMA DE GESTIÓN COMERCIAL</small>
    </div>
</div>

<script>
    (function () {
        const pasoUsuario = document.getElementById('paso_usuario');
        const pasoCodigo = document.getElementById('paso_codigo');
        if (!pasoCodigo) return;
        const codigo = document.getElementById('codigo');
        const btnIngresar = document.getElementById('ingresar');

        function elegir(id, nombre) {
            document.getElementById('usuario').value = id;
            document.getElementById('nombre_mozo').textContent = nombre;
            codigo.value = '';
            actualizar();
            pasoUsuario.classList.add('oculto');
            pasoCodigo.classList.remove('oculto');
        }
        function actualizar() { btnIngresar.disabled = codigo.value.length < 3; }

        document.querySelectorAll('.usuario').forEach(b => b.addEventListener('click', () => elegir(b.dataset.id, b.dataset.nombre)));
        document.getElementById('cambiar').addEventListener('click', () => { pasoCodigo.classList.add('oculto'); pasoUsuario.classList.remove('oculto'); });
        document.querySelectorAll('.tecla[data-n]').forEach(b => b.addEventListener('click', () => {
            if (codigo.value.length < 6) { codigo.value += b.dataset.n; actualizar(); }
        }));
        document.getElementById('borrar').addEventListener('click', () => { codigo.value = codigo.value.slice(0, -1); actualizar(); });
        // También funciona con teclado físico
        document.addEventListener('keydown', e => {
            if (pasoCodigo.classList.contains('oculto')) return;
            if (/^\d$/.test(e.key) && codigo.value.length < 6) { codigo.value += e.key; actualizar(); }
            else if (e.key === 'Backspace') { codigo.value = codigo.value.slice(0, -1); actualizar(); }
            else if (e.key === 'Enter' && !btnIngresar.disabled) pasoCodigo.submit();
        });
        pasoCodigo.addEventListener('submit', () => { btnIngresar.disabled = true; btnIngresar.textContent = 'INGRESANDO...'; });

        // Si volvió con error, se queda en el teclado del mismo mozo
        const previo = document.getElementById('usuario').value;
        if (previo) {
            const b = document.querySelector(`.usuario[data-id="${previo}"]`);
            if (b) elegir(b.dataset.id, b.dataset.nombre);
        }
    })();
</script>
</body>
</html>
