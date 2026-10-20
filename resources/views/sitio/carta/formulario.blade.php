@extends('sitio.base')
@section('titulo', $carta ? 'Editar mi carta' : 'Crea tu carta digital gratis')
@section('robots', $carta ? 'noindex, nofollow' : 'index, follow')

@push('cabecera')
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <meta name="description" content="Crea gratis la carta digital de tu restaurante: escribe tus platos con su foto y precio, elige el diseño y obtén tu enlace y tu código QR.">
    <style>
        .diseno { cursor: pointer; border: 3px solid transparent; border-radius: 1rem; overflow: hidden; transition: .15s; }
        .btn-check:checked + .diseno { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .2); }
        .diseno .muestra { height: 92px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; }
        .foto-caja { border: 2px dashed #cbd5e1; border-radius: 1rem; min-height: 180px; cursor: pointer; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; }
        .foto-caja img { max-height: 320px; width: 100%; object-fit: contain; }
        .producto { border: 1px solid #e2e8f0; border-radius: 1rem; padding: .75rem; background: #fff; }
        .producto + .producto { margin-top: .75rem; }
        .prod-foto { width: 84px; height: 84px; flex: 0 0 84px; border: 2px dashed #cbd5e1; border-radius: .9rem; background: #f8fafc; cursor: pointer;
                     display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; color: #64748b; font-size: .7rem; text-align: center; }
        .prod-foto img { width: 100%; height: 100%; object-fit: cover; }
        .prod-foto .cargando { position: absolute; inset: 0; background: rgba(255,255,255,.8); display: flex; align-items: center; justify-content: center; }
        .prod-quitar-foto { font-size: .72rem; }
    </style>
@endpush

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="text-center text-white mb-4">
                @if ($carta)
                    <h1 class="fw-bold">Editar mi carta</h1>
                    <p class="mb-0 text-white-50">Cambia tus productos, el diseño o tus datos. Tu enlace y tu QR siguen siendo los mismos.</p>
                @else
                    <span class="badge rounded-pill text-bg-warning mb-2">GRATIS</span>
                    <h1 class="fw-bold">Crea la carta digital de tu negocio</h1>
                    <p class="mb-0 text-white-50">Escribe tus platos con su precio y su foto, elige el diseño y recibe tu enlace y tu código QR para las mesas. Solo con tu correo.</p>
                @endif
            </div>

            <form method="POST" action="{{ $carta ? route('sitio.carta.actualizar', $carta->slug) : route('sitio.carta.guardar') }}" enctype="multipart/form-data"
                  class="card tarjeta p-4 p-md-5" id="form_carta">
                @csrf
                @if ($carta)<input type="hidden" name="t" value="{{ $carta->token }}">@endif
                <input type="text" name="web" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

                @if ($errors->any())
                    <div class="alert alert-warning rounded-4">
                        <b>Revisa estos datos:</b>
                        <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- Productos: cada uno con su categoría, nombre, precio, descripción y una foto --}}
                <h2 class="h5 fw-bold mb-1"><span class="paso me-2">1</span>Tus productos</h2>
                <p class="small text-muted mb-3">Escribe cada plato o bebida con su precio. La foto es opcional (una por producto). Los que tengan la misma categoría salen juntos.</p>
                <div id="productos"></div>
                <datalist id="categorias_carta"></datalist>
                <button type="button" id="agregar_producto" class="btn btn-outline-primary rounded-pill w-100 mt-3"><i class="fas fa-plus me-1"></i> Agregar producto</button>
                <div class="form-text mb-4">Hasta {{ \App\Http\Controllers\CartaWebController::MAX_PRODUCTOS }} productos.</div>

                {{-- La foto de la carta impresa: opcional --}}
                <h2 class="h5 fw-bold mb-1"><span class="paso me-2">2</span>Foto de tu carta impresa <span class="text-muted fw-normal fs-6">(opcional)</span></h2>
                <p class="small text-muted mb-3">Si ya tienes tu carta en imagen, también puedes subirla: sale debajo de tus productos.</p>
                <label class="foto-caja mb-1" for="foto">
                    <img id="foto_vista" src="{{ $carta?->foto ? asset($carta->foto) : '' }}" alt="" style="{{ $carta?->foto ? '' : 'display:none' }}">
                    <span id="foto_texto" class="text-center text-muted p-4" style="{{ $carta?->foto ? 'display:none' : '' }}">
                        <i class="fas fa-camera fa-2x mb-2 d-block" style="color:#2563eb"></i>
                        <b>Toca para subir la foto</b><br><small>JPG, PNG o WEBP hasta 8 MB</small>
                    </span>
                </label>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" class="d-none">
                @if ($carta?->foto)
                    <label class="form-check small mt-1"><input type="checkbox" name="quitar_foto" value="1" class="form-check-input"> Quitar la foto de la carta</label>
                @endif
                <div class="mb-4"></div>

                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">3</span>Elige el diseño</h2>
                <div class="row g-3 mb-4">
                    @foreach ($disenos as $clave => [$nombre, $fondo, $acento, $texto, $letra])
                        <div class="col-6 col-md-4 col-lg">
                            <input type="radio" class="btn-check" name="diseno" id="d_{{ $clave }}" value="{{ $clave }}" @checked(old('diseno', $carta->diseno ?? 'clasico') === $clave)>
                            <label class="diseno d-block shadow-sm" for="d_{{ $clave }}">
                                <div class="muestra" style="background: {{ $fondo }}; color: {{ $texto }}">
                                    <span style="font-family: {{ $letra }}; font-weight: 800; font-size: 1.05rem">Mi Carta</span>
                                    <span style="width: 46px; height: 4px; border-radius: 4px; background: {{ $acento }}"></span>
                                </div>
                                <div class="bg-white text-center small fw-semibold py-1">{{ $nombre }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">4</span>Datos de tu negocio</h2>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="nombre">Nombre del negocio</label>
                        <input id="nombre" name="nombre" value="{{ old('nombre', $carta->nombre ?? '') }}" required maxlength="120" class="form-control" placeholder="Ej. Cevichería El Tiburón">
                        @unless ($carta)<div class="form-text">Tu enlace será {{ config('tenancy.dominio') }}/c/<b id="slug_vista">tu-negocio</b></div>@endunless
                    </div>
                    <div class="col-md-5">
                        @if ($carta)
                            <label class="form-label">Correo</label>
                            <input value="{{ $carta->email }}" disabled class="form-control">
                        @else
                            <label class="form-label" for="email">Tu correo</label>
                            <input id="email" type="email" name="email" value="{{ old('email', session('google.email')) }}" required maxlength="120" class="form-control" placeholder="tucorreo@gmail.com">
                            <div class="form-text">Ahí te enviamos el enlace para editarla.</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="whatsapp">WhatsApp <span class="text-muted fw-normal">(opcional)</span></label>
                        <input id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $carta->whatsapp ?? '') }}" maxlength="20" inputmode="tel" class="form-control" placeholder="987654321">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="direccion">Dirección <span class="text-muted fw-normal">(opcional)</span></label>
                        <input id="direccion" name="direccion" value="{{ old('direccion', $carta->direccion ?? '') }}" maxlength="200" class="form-control" placeholder="Jr. Próspero 123, Iquitos">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="horario">Horario <span class="text-muted fw-normal">(opcional)</span></label>
                        <input id="horario" name="horario" value="{{ old('horario', $carta->horario ?? '') }}" maxlength="120" class="form-control" placeholder="Lunes a domingo de 11am a 4pm">
                    </div>
                </div>

                @if ($turnstile)
                    <div class="cf-turnstile mt-4" data-sitekey="{{ $turnstile }}"></div>
                @endif

                <button class="btn btn-achiote btn-lg w-100 mt-4" id="btn_carta">
                    <i class="fas {{ $carta ? 'fa-floppy-disk' : 'fa-qrcode' }} me-2"></i>{{ $carta ? 'Guardar cambios' : 'Crear mi carta y mi QR' }}
                </button>
                @unless ($carta)
                    <p class="small text-muted text-center mt-3 mb-0">¿Quieres que tus clientes pidan desde la mesa, con fotos de cada plato, cocina y boletas SUNAT?
                        <a href="{{ route('sitio.registro') }}">Prueba el sistema TUSHPA gratis</a>.</p>
                @endunless
            </form>

            @unless ($carta)
                {{-- Recuperar el enlace para editar --}}
                <form method="POST" action="{{ route('sitio.carta.recuperar') }}" class="card tarjeta p-4 mt-4">
                    @csrf
                    @if (session('ok'))<div class="alert alert-success rounded-4 small">{{ session('ok') }}</div>@endif
                    <p class="fw-semibold mb-2">¿Ya tienes tu carta y perdiste el enlace para editarla?</p>
                    <div class="d-flex gap-2">
                        <input type="email" name="email" required maxlength="120" class="form-control" placeholder="El correo con el que la creaste">
                        <button class="btn btn-outline-primary rounded-pill px-4 text-nowrap">Enviarme el enlace</button>
                    </div>
                </form>
            @endunless
        </div>
    </div>
@endsection

@php
    // Filas para el formulario: lo que se escribió antes de un error, o los productos guardados
    $filasCarta = old('productos') !== null
        ? collect(old('productos'))->map(fn ($p) => ['id' => $p['id'] ?? null, 'categoria' => $p['categoria'] ?? '', 'nombre' => $p['nombre'] ?? '',
            'precio' => $p['precio'] ?? '', 'descripcion' => $p['descripcion'] ?? '', 'foto' => $p['foto'] ?? '', 'foto_actual' => $p['foto_actual'] ?? '',
            'url' => ! empty($p['foto']) ? asset('imagenes/cartas/tmp/'.$p['foto']) : (! empty($p['foto_actual']) ? asset($p['foto_actual']) : null)])->values()
        : $productos->map(fn ($p) => ['id' => $p->id, 'categoria' => $p->categoria, 'nombre' => $p->nombre, 'precio' => $p->precio !== null ? (float) $p->precio : '',
            'descripcion' => $p->descripcion, 'foto' => '', 'foto_actual' => $p->foto, 'url' => $p->foto ? asset($p->foto) : null])->values();
@endphp

@push('scripts')
    <template id="fila_producto">
        <div class="producto">
            <input type="hidden" data-campo="id"><input type="hidden" data-campo="foto"><input type="hidden" data-campo="foto_actual">
            <div class="d-flex gap-3">
                <div class="text-center">
                    <label class="prod-foto mb-1"><span class="prod-sin-foto"><i class="fas fa-camera fa-lg d-block mb-1"></i>Foto</span>
                        <input type="file" accept="image/*" class="d-none prod-archivo"></label>
                    <button type="button" class="btn btn-link p-0 text-danger prod-quitar-foto" style="display:none">Quitar foto</button>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="row g-2">
                        <div class="col-12 col-md-8"><input data-campo="nombre" maxlength="120" class="form-control" placeholder="Nombre (ej. Ceviche clásico)"></div>
                        <div class="col-5 col-md-4"><input data-campo="precio" type="number" step="0.01" min="0" inputmode="decimal" class="form-control text-end" placeholder="S/ 0.00"></div>
                        <div class="col-7 col-md-5"><input data-campo="categoria" maxlength="60" list="categorias_carta" class="form-control form-control-sm" placeholder="Categoría (ej. Ceviches)"></div>
                        <div class="col-12 col-md-7"><input data-campo="descripcion" maxlength="255" class="form-control form-control-sm" placeholder="Descripción (opcional)"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light align-self-start prod-quitar" title="Quitar producto"><i class="fas fa-trash text-danger"></i></button>
            </div>
        </div>
    </template>
    <script>
        (function () {
            // ---------- Productos ----------
            const lista = document.getElementById('productos'), plantilla = document.getElementById('fila_producto');
            const urlFoto = @json(route('sitio.carta.foto')), maximo = {{ \App\Http\Controllers\CartaWebController::MAX_PRODUCTOS }};
            const token = document.querySelector('#form_carta input[name=_token]').value;
            let indice = 0, subiendo = 0;

            const categorias = () => {
                const unicas = [...new Set([...lista.querySelectorAll('[data-campo=categoria]')].map(i => i.value.trim().toUpperCase()).filter(Boolean))];
                document.getElementById('categorias_carta').innerHTML = unicas.map(c => '<option value="' + c.replace(/"/g, '&quot;') + '">').join('');
            };
            const verFoto = (fila, url) => {
                const caja = fila.querySelector('.prod-foto');
                caja.querySelector('img')?.remove();
                fila.querySelector('.prod-sin-foto').style.display = url ? 'none' : '';
                fila.querySelector('.prod-quitar-foto').style.display = url ? '' : 'none';
                if (url) { const img = document.createElement('img'); img.src = url; img.alt = ''; caja.prepend(img); }
            };
            // La foto se achica en el navegador (máx. 900 px) y se sube sola; el formulario solo lleva su nombre
            const achicar = (archivo) => new Promise((ok, mal) => {
                const img = new Image();
                img.onload = () => {
                    const escala = Math.min(1, 900 / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.width * escala); c.height = Math.round(img.height * escala);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    c.toBlob(b => b ? ok(b) : mal(), 'image/jpeg', 0.82);
                };
                img.onerror = mal;
                img.src = URL.createObjectURL(archivo);
            });
            const subir = async (fila, archivo) => {
                const caja = fila.querySelector('.prod-foto');
                const espera = document.createElement('span'); espera.className = 'cargando'; espera.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                caja.append(espera); subiendo++;
                try {
                    const datos = new FormData(); datos.append('foto', await achicar(archivo), 'foto.jpg');
                    const r = await fetch(urlFoto, { method: 'POST', body: datos, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                    if (!r.ok) throw new Error();
                    const j = await r.json();
                    fila.querySelector('[data-campo=foto]').value = j.foto;
                    fila.querySelector('[data-campo=foto_actual]').value = '';
                    verFoto(fila, j.url);
                } catch (e) {
                    alert('No se pudo subir la foto. Prueba con otra imagen (JPG o PNG).');
                } finally { espera.remove(); subiendo--; }
            };
            const agregar = (p = {}) => {
                if (lista.children.length >= maximo) { alert('Tu carta puede tener hasta ' + maximo + ' productos.'); return null; }
                const fila = plantilla.content.firstElementChild.cloneNode(true), n = indice++;
                fila.querySelectorAll('[data-campo]').forEach(i => { i.name = 'productos[' + n + '][' + i.dataset.campo + ']'; i.value = p[i.dataset.campo] ?? ''; });
                verFoto(fila, p.url || null);
                fila.querySelector('.prod-archivo').addEventListener('change', function () { if (this.files[0]) subir(fila, this.files[0]); this.value = ''; });
                fila.querySelector('.prod-quitar-foto').addEventListener('click', () => {
                    fila.querySelector('[data-campo=foto]').value = ''; fila.querySelector('[data-campo=foto_actual]').value = ''; verFoto(fila, null);
                });
                fila.querySelector('.prod-quitar').addEventListener('click', () => { fila.remove(); categorias(); });
                fila.querySelector('[data-campo=categoria]').addEventListener('change', categorias);
                lista.append(fila);
                return fila;
            };
            const iniciales = @json($filasCarta);
            (iniciales.length ? iniciales : [{}, {}, {}]).forEach(p => agregar(p));
            categorias();
            document.getElementById('agregar_producto').addEventListener('click', () => {
                const ultima = lista.lastElementChild?.querySelector('[data-campo=categoria]')?.value || '';
                agregar({ categoria: ultima })?.querySelector('[data-campo=nombre]').focus();
            });

            const foto = document.getElementById('foto');
            foto.addEventListener('change', function () {
                const f = this.files[0];
                if (!f) return;
                if (f.size > 8 * 1024 * 1024) { alert('La foto pesa más de 8 MB. Elige una más liviana.'); this.value = ''; return; }
                document.getElementById('foto_vista').src = URL.createObjectURL(f);
                document.getElementById('foto_vista').style.display = '';
                document.getElementById('foto_texto').style.display = 'none';
            });
            const nombre = document.getElementById('nombre'), vista = document.getElementById('slug_vista');
            if (vista) nombre.addEventListener('input', () => {
                vista.textContent = nombre.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) || 'tu-negocio';
            });
            document.getElementById('form_carta').addEventListener('submit', (e) => {
                if (subiendo > 0) { e.preventDefault(); alert('Espera a que terminen de subir las fotos.'); return; }
                const b = document.getElementById('btn_carta');
                b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando…';
            });
        })();
    </script>
@endpush
