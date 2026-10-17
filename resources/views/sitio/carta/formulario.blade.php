@extends('sitio.base')
@section('titulo', $carta ? 'Editar mi carta' : 'Crea tu carta digital gratis')
@section('robots', $carta ? 'noindex, nofollow' : 'index, follow')

@push('cabecera')
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <meta name="description" content="Crea gratis la carta digital de tu restaurante: sube una foto de tu carta, elige el diseño y obtén tu enlace y tu código QR.">
    <style>
        .diseno { cursor: pointer; border: 3px solid transparent; border-radius: 1rem; overflow: hidden; transition: .15s; }
        .btn-check:checked + .diseno { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .2); }
        .diseno .muestra { height: 92px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; }
        .foto-caja { border: 2px dashed #cbd5e1; border-radius: 1rem; min-height: 180px; cursor: pointer; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; }
        .foto-caja img { max-height: 320px; width: 100%; object-fit: contain; }
    </style>
@endpush

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="text-center text-white mb-4">
                @if ($carta)
                    <h1 class="fw-bold">Editar mi carta</h1>
                    <p class="mb-0 text-white-50">Cambia la foto, el diseño o tus datos. Tu enlace y tu QR siguen siendo los mismos.</p>
                @else
                    <span class="badge rounded-pill text-bg-warning mb-2">GRATIS</span>
                    <h1 class="fw-bold">Crea la carta digital de tu negocio</h1>
                    <p class="mb-0 text-white-50">Sube una foto de tu carta, elige el diseño y recibe tu enlace y tu código QR para las mesas. Solo con tu correo.</p>
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

                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">1</span>La foto de tu carta</h2>
                <label class="foto-caja mb-1" for="foto">
                    <img id="foto_vista" src="{{ $carta ? asset($carta->foto) : '' }}" alt="" style="{{ $carta ? '' : 'display:none' }}">
                    <span id="foto_texto" class="text-center text-muted p-4" style="{{ $carta ? 'display:none' : '' }}">
                        <i class="fas fa-camera fa-2x mb-2 d-block" style="color:#2563eb"></i>
                        <b>Toca para subir la foto</b><br><small>Tómale una foto a tu carta impresa o sube su imagen (JPG, PNG o WEBP hasta 8 MB)</small>
                    </span>
                </label>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" class="d-none" @if (! $carta) required @endif>
                <div class="form-text mb-4">{{ $carta ? 'Si subes otra foto, reemplaza a la actual.' : 'Consejo: foto derecha, con buena luz y que se lean los precios.' }}</div>

                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">2</span>Elige el diseño</h2>
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

                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">3</span>Datos de tu negocio</h2>
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

@push('scripts')
    <script>
        (function () {
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
            document.getElementById('form_carta').addEventListener('submit', () => {
                const b = document.getElementById('btn_carta');
                b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando…';
            });
        })();
    </script>
@endpush
