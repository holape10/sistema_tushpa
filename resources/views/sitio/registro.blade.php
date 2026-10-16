@extends('sitio.base')
@section('titulo', 'Prueba gratis')

@push('cabecera')
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <style>
        .plan-opcion { cursor: pointer; border: 2px solid #e2e8f0; border-radius: 1rem; padding: .9rem 1rem; transition: .15s; height: 100%; }
        .plan-opcion:hover { border-color: #93c5fd; }
        .btn-check:checked + .plan-opcion { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }
        .dominio-fijo { background: #f1f5f9; border-radius: 0 .75rem .75rem 0; }
        #empresa_sunat { display: none; }
        #creando { display: none; position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .75); color: #fff; }
    </style>
@endpush

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="text-center text-white mb-4">
                <h1 class="fw-bold">Prueba TUSHPA gratis {{ config('sitio.dias_prueba') }} días</h1>
                <p class="mb-0 text-white-50">Sin tarjeta. Tu sistema queda listo en segundos con su propia dirección.</p>
            </div>

            <form method="POST" action="{{ route('sitio.registrar') }}" class="card tarjeta p-4 p-md-5" id="form_registro" novalidate>
                @csrf
                <input type="text" name="web" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

                @if ($errors->any())
                    <div class="alert alert-warning rounded-4">
                        <b>Revisa estos datos:</b>
                        <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- 1. Empresa --}}
                <h2 class="h5 fw-bold mb-3"><span class="paso me-2">1</span>Tu empresa</h2>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="ruc">RUC</label>
                        <input id="ruc" name="ruc" value="{{ old('ruc') }}" inputmode="numeric" maxlength="11" required
                               class="form-control @error('ruc') is-invalid @enderror" placeholder="20123456789">
                        <div class="form-text" id="ruc_ayuda">Con tu RUC traemos tus datos de SUNAT.</div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="nombre_comercial">Nombre comercial <span class="text-muted fw-normal">(opcional)</span></label>
                        <input id="nombre_comercial" name="nombre_comercial" value="{{ old('nombre_comercial') }}" maxlength="120" class="form-control" placeholder="Ej. Restaurante El Sabor">
                    </div>
                    <div class="col-12" id="empresa_sunat">
                        <div class="rounded-4 bg-light p-3 small">
                            <div class="fw-bold" id="sunat_nombre"></div>
                            <div class="text-muted" id="sunat_direccion"></div>
                            <div id="sunat_estado" class="mt-1"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="rubro">Tipo de negocio</label>
                        <select id="rubro" name="rubro" required class="form-select @error('rubro') is-invalid @enderror">
                            <option value="">Elige tu rubro…</option>
                            @foreach ($rubros as $clave => $texto)
                                <option value="{{ $clave }}" @selected(old('rubro') === $clave)>{{ $texto }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Tu sistema arranca con el menú de tu rubro.</div>
                    </div>
                </div>

                {{-- 2. Sistema --}}
                <h2 class="h5 fw-bold mt-5 mb-3"><span class="paso me-2">2</span>Tu sistema</h2>
                <label class="form-label" for="subdominio">Dirección de tu sistema</label>
                <div class="input-group">
                    <span class="input-group-text" style="border-radius:.75rem 0 0 .75rem">https://</span>
                    <input id="subdominio" name="subdominio" value="{{ old('subdominio') }}" maxlength="40" autocomplete="off"
                           class="form-control @error('subdominio') is-invalid @enderror" placeholder="mirestaurante">
                    <span class="input-group-text dominio-fijo">.{{ config('tenancy.dominio') }}</span>
                </div>
                <div class="form-text" id="sub_ayuda">Solo letras, números y guiones. Si lo dejas vacío, tu dirección será tu RUC.</div>
                <div id="sub_sugerencias" class="d-flex flex-wrap gap-2 mt-1" style="display:none!important"></div>

                <div class="row g-3 mt-2">
                    @foreach ($planes as $plan)
                        <div class="col-md-{{ $planes->count() >= 3 ? 4 : 6 }}">
                            <input type="radio" class="btn-check" name="plan_id" id="plan_{{ $plan->id }}" value="{{ $plan->id }}" @checked((int) old('plan_id', $planElegido) === $plan->id)>
                            <label class="plan-opcion d-block" for="plan_{{ $plan->id }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-bold">{{ $plan->nombre }}</span>
                                    @if ($plan->destacado)<span class="badge bg-primary rounded-pill">Popular</span>@endif
                                </div>
                                <div class="text-primary fw-bold fs-5">S/ {{ rtrim(rtrim(number_format($plan->precio, 2), '0'), '.') }}<span class="text-muted fs-6 fw-normal"> /mes</span></div>
                                <div class="small text-muted">{{ $plan->descripcion }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Los {{ config('sitio.dias_prueba') }} días son gratis con el plan que elijas; puedes cambiarlo al activar.</div>

                {{-- 3. Acceso --}}
                <h2 class="h5 fw-bold mt-5 mb-3"><span class="paso me-2">3</span>Tus datos de acceso</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="contacto_nombre">Tu nombre</label>
                        <input id="contacto_nombre" name="contacto_nombre" value="{{ old('contacto_nombre') }}" required maxlength="120" class="form-control" autocomplete="name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contacto_telefono">Celular (WhatsApp)</label>
                        <input id="contacto_telefono" name="contacto_telefono" value="{{ old('contacto_telefono') }}" required inputmode="numeric" maxlength="9" class="form-control" placeholder="9XXXXXXXX" autocomplete="tel">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="contacto_correo">Correo</label>
                        <input id="contacto_correo" name="contacto_correo" type="email" value="{{ old('contacto_correo') }}" required maxlength="120" class="form-control" autocomplete="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Contraseña</label>
                        <input id="password" name="password" type="password" required minlength="8" class="form-control" autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres. Tu usuario será tu RUC.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Repite la contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="acepto" value="1" id="acepto" @checked(old('acepto')) required>
                    <label class="form-check-label small" for="acepto">
                        Entiendo que tengo <b>{{ config('sitio.dias_prueba') }} días gratis</b> y que luego debo activar un plan para seguir usando el sistema.
                        Durante la prueba, las boletas y facturas se envían a SUNAT en <b>modo de prueba</b> (no tienen validez).
                    </label>
                </div>

                @if ($turnstile)
                    <div class="cf-turnstile mt-3" data-sitekey="{{ $turnstile }}" data-language="es"></div>
                @endif

                <button class="btn btn-primary btn-lg w-100 rounded-pill fw-semibold mt-4" id="btn_crear">
                    <i class="fas fa-rocket me-2"></i>Crear mi sistema gratis
                </button>
                <p class="text-center text-muted small mt-3 mb-0">¿Ya tienes cuenta? <a href="{{ route('sitio.ingresar') }}">Ingresa aquí</a></p>
            </form>
        </div>
    </div>

    <div id="creando" class="align-items-center justify-content-center text-center p-4">
        <div>
            <div class="spinner-border mb-3" style="width:3rem;height:3rem"></div>
            <h2 class="h4 fw-bold">Creando tu sistema…</h2>
            <p class="text-white-50 mb-0">Preparamos tu base de datos, tu menú y tu usuario. Toma unos segundos, no cierres esta página.</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const el = (id) => document.getElementById(id);
            const URL_RUC = @json(url('/registro/ruc')) + '/';
            let subTocado = !!el('subdominio').value;

            // Dirección sugerida a partir del nombre (sin tildes, espacios ni símbolos)
            const sugerir = (texto) => texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/\b(s\.?a\.?c?|e\.?i\.?r\.?l|s\.?r\.?l|sociedad|anonima|cerrada|empresa|de|del|la|el|los|las|y)\b/g, ' ')
                .replace(/[^a-z0-9]+/g, '').slice(0, 30);
            // Avisa al momento si la dirección ya la usa otra empresa y propone otras libres
            const URL_SUB = @json(url('/registro/subdominio')) + '/';
            const AYUDA_SUB = el('sub_ayuda').textContent;
            let esperaSub = null;
            const revisarSub = () => {
                clearTimeout(esperaSub);
                const valor = el('subdominio').value, caja = el('sub_sugerencias');
                caja.innerHTML = ''; caja.style.setProperty('display', 'none', 'important');
                el('subdominio').classList.remove('is-invalid', 'is-valid');
                if (!valor) { el('sub_ayuda').textContent = AYUDA_SUB; el('sub_ayuda').className = 'form-text'; return; }
                esperaSub = setTimeout(() => fetch(URL_SUB + encodeURIComponent(valor), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
                    if (el('subdominio').value !== valor) return;
                    el('sub_ayuda').textContent = (d.ok ? '✔ ' : '✖ ') + d.mensaje;
                    el('sub_ayuda').className = 'form-text fw-semibold ' + (d.ok ? 'text-success' : 'text-danger');
                    el('subdominio').classList.add(d.ok ? 'is-valid' : 'is-invalid');
                    (d.sugerencias || []).forEach(s => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'btn btn-sm btn-outline-success'; b.textContent = s;
                        b.onclick = () => { el('subdominio').value = s; subTocado = true; revisarSub(); };
                        caja.appendChild(b);
                    });
                    if ((d.sugerencias || []).length) caja.style.removeProperty('display');
                }).catch(() => {}), 400);
            };
            const ponerSugerencia = (texto) => { if (!subTocado && texto) { el('subdominio').value = sugerir(texto); revisarSub(); } };
            el('subdominio').addEventListener('input', function () {
                subTocado = true;
                this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
                revisarSub();
            });
            el('nombre_comercial').addEventListener('input', function () { ponerSugerencia(this.value); });
            el('contacto_telefono').addEventListener('input', function () { this.value = this.value.replace(/\D/g, ''); });

            // RUC: al completar los 11 dígitos se consultan sus datos en SUNAT
            const ayuda = el('ruc_ayuda');
            el('ruc').addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '');
                el('empresa_sunat').style.display = 'none';
                this.classList.remove('is-invalid', 'is-valid');
                if (this.value.length !== 11) { ayuda.textContent = 'Con tu RUC traemos tus datos de SUNAT.'; ayuda.className = 'form-text'; return; }
                if (!/^(10|15|17|20)/.test(this.value)) { ayuda.textContent = 'El RUC empieza con 10, 15, 17 o 20.'; ayuda.className = 'form-text text-danger'; return; }
                ayuda.textContent = 'Consultando SUNAT…';
                fetch(URL_RUC + this.value, { headers: { Accept: 'application/json' } }).then(async (r) => ({ ok: r.ok, d: await r.json() })).then(({ ok, d }) => {
                    if (!ok) { ayuda.textContent = d.error || 'No se pudo consultar.'; ayuda.className = 'form-text text-danger'; el('ruc').classList.add('is-invalid'); return; }
                    ayuda.textContent = '✔ Encontrado en SUNAT'; ayuda.className = 'form-text text-success';
                    el('ruc').classList.add('is-valid');
                    el('sunat_nombre').textContent = d.nombre;
                    el('sunat_direccion').textContent = d.direccion || '';
                    const activo = !d.estado || d.estado.toUpperCase() === 'ACTIVO';
                    el('sunat_estado').innerHTML = '';
                    const chip = document.createElement('span');
                    chip.className = 'badge ' + (activo ? 'bg-success' : 'bg-danger');
                    chip.textContent = (d.estado || 'ACTIVO') + (d.condicion ? ' · ' + d.condicion : '');
                    el('sunat_estado').appendChild(chip);
                    el('empresa_sunat').style.display = 'block';
                    if (!el('nombre_comercial').value) ponerSugerencia(d.nombre);
                }).catch(() => { ayuda.textContent = 'No se pudo consultar SUNAT ahora; igual puedes continuar.'; ayuda.className = 'form-text text-warning'; });
            });
            if (el('ruc').value.length === 11) el('ruc').dispatchEvent(new Event('input'));

            // Mientras se crea el sistema (unos segundos) se muestra el aviso y no se puede enviar dos veces
            el('form_registro').addEventListener('submit', function (e) {
                if (!this.checkValidity()) { e.preventDefault(); this.classList.add('was-validated'); this.reportValidity(); return; }
                el('btn_crear').disabled = true;
                el('creando').style.display = 'flex';
            });
        })();
    </script>
@endpush
