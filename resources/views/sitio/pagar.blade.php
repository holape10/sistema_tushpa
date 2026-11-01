@extends('sitio.base')
@section('titulo', 'Pagar mi sistema')
@section('robots', 'noindex, nofollow')

@php
    $empresa = $cliente->nombre_comercial ?: $cliente->razon_social;
    $correo = filter_var($cliente->contacto_correo, FILTER_VALIDATE_EMAIL) ? $cliente->contacto_correo : '';
@endphp

@section('contenido')
    <div class="row justify-content-center mt-3">
        <div class="col-md-8 col-lg-6">
            <div class="card tarjeta p-4 p-md-5" id="pago">
                {{-- 1) Elegir cuánto pagar --}}
                <div data-paso="elegir">
                    <p class="text-uppercase small fw-bold text-primary mb-1">Pago seguro con Culqi</p>
                    <h1 class="h3 fw-bold mb-1">{{ $empresa }}</h1>
                    <p class="text-muted mb-1">RUC {{ $cliente->ruc }} · Plan {{ $cliente->planContratado->nombre ?? $cliente->plan }}</p>
                    <p class="mb-4 {{ $cliente->estado === 'SUSPENDIDO' ? 'text-danger fw-semibold' : 'text-muted' }}">
                        @if ($cliente->estado === 'SUSPENDIDO')
                            Tu sistema está suspendido: se reactiva apenas se confirme el pago.
                        @elseif ($cliente->vence_el)
                            Tu servicio vence el {{ $cliente->vence_el->format('d/m/Y') }}. Lo que pagues se suma desde esa fecha.
                        @endif
                    </p>

                    <div class="d-grid gap-2 mb-4">
                        @foreach ($opciones as $i => $o)
                            <label class="d-flex align-items-center gap-3 border rounded-4 p-3" style="cursor:pointer">
                                <input type="radio" name="meses" value="{{ $o['meses'] }}" class="form-check-input mt-0" @checked($i === 0)>
                                <span class="flex-grow-1 fw-semibold">{{ $o['texto'] }}</span>
                                <span class="fs-5 fw-bold text-nowrap">S/ {{ number_format($o['monto'], 2) }}</span>
                            </label>
                        @endforeach
                    </div>

                    <button type="button" id="btn-pagar" class="btn btn-primary btn-lg w-100 rounded-pill fw-semibold">
                        <i class="fas fa-lock me-1"></i> Pagar ahora
                    </button>
                    <p class="text-center small text-muted mt-3 mb-0">
                        <i class="fas fa-credit-card"></i> Tarjeta · <i class="fas fa-mobile-screen"></i> Yape · <i class="fas fa-qrcode"></i> QR de Plin y bancos
                    </p>
                    <p id="error" class="alert alert-danger mt-3 mb-0 d-none"></p>
                </div>

                {{-- 2) Esperando el pago con QR (billeteras, agentes, banca móvil) --}}
                <div data-paso="esperando" class="text-center d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h2 class="h4 fw-bold">Esperando tu pago…</h2>
                    <p class="text-muted mb-0">Apenas pagues en tu app, esta página se actualiza sola y tu sistema se activa. No la cierres.</p>
                    <button type="button" id="btn-reabrir" class="btn btn-link mt-2">Volver a ver las opciones de pago</button>
                </div>

                {{-- 3) Listo --}}
                <div data-paso="listo" class="text-center d-none">
                    <div class="display-3 mb-2">✅</div>
                    <h2 class="h3 fw-bold">¡Pago recibido!</h2>
                    <p class="text-muted">Tu sistema ya está activo. Gracias por confiar en TUSHPA.</p>
                    <a id="btn-entrar" href="{{ $cliente->url() }}" class="btn btn-success btn-lg rounded-pill fw-semibold px-5">Entrar a mi sistema</a>
                </div>
            </div>
            <p class="text-center text-white-50 small mt-3"><i class="fas fa-shield-halved"></i> Los datos de tu tarjeta los recibe Culqi; TUSHPA no los ve ni los guarda.</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://js.culqi.com/checkout-js"></script>
    <script src="https://3ds.culqi.com"></script>
    <script>
        if (window.Culqi3DS) Culqi3DS.publicKey = @js($publica);
        (function () {
            const csrf = @js(csrf_token());
            const rutas = { preparar: @js(\Illuminate\Support\Facades\URL::signedRoute('sitio.pagar.preparar', $cliente)), cobrar: @js(\Illuminate\Support\Facades\URL::signedRoute('sitio.pagar.cobrar', $cliente)),
                estado: @js(route('sitio.pagar.estado', [$cliente, '__PAGO__'])) };
            const $ = (s) => document.querySelector(s);
            let pago = null, checkout = null, vigilando = null;

            const paso = (nombre) => document.querySelectorAll('[data-paso]').forEach((d) => d.classList.toggle('d-none', d.dataset.paso !== nombre));
            const error = (texto) => { const e = $('#error'); e.textContent = texto || ''; e.classList.toggle('d-none', !texto); };
            const enviar = (url, datos) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(datos) })
                .then(async (r) => ({ ok: r.ok, datos: await r.json().catch(() => ({})) }));

            function listo(url) {
                clearInterval(vigilando);
                if (url) $('#btn-entrar').href = url;
                paso('listo');
            }

            // Billeteras con QR, agentes y banca móvil: el pago llega por fuera; se pregunta cada 5 segundos
            function vigilar() {
                clearInterval(vigilando);
                vigilando = setInterval(async () => {
                    const r = await fetch(rutas.estado.replace('__PAGO__', pago.pago), { headers: { Accept: 'application/json' } }).then((x) => x.json()).catch(() => null);
                    if (r && r.pagado) listo(r.url);
                }, 5000);
            }

            // 3D Secure: el banco puede pedir que el titular confirme (código por SMS o en su app). La librería avisa por mensaje.
            let cobro = null;
            window.addEventListener('message', async (evento) => {
                if (evento.origin !== window.location.origin || !cobro) return;
                const r3 = evento.data || {};
                if (r3.parameters3DS) {
                    const datos = Object.assign({}, cobro, { autenticacion: r3.parameters3DS });
                    cobro = null;
                    terminarCobro(await enviar(rutas.cobrar, datos));
                } else if (r3.error) {
                    cobro = null;
                    paso('elegir');
                    error(typeof r3.error === 'string' ? r3.error : 'No se pudo verificar la tarjeta con el banco.');
                }
            });

            function terminarCobro(r) {
                if (r.ok && r.datos.ok) return listo(r.datos.url);
                paso('elegir');
                error(r.datos.mensaje || r.datos.message || 'No se pudo cobrar. Intenta con otro medio.');
            }

            async function alResponderCulqi() {
                if (checkout.token) {
                    const token = checkout.token.id, correo = checkout.token.email || @js($correo) || null;
                    checkout.close();
                    paso('esperando');
                    const dispositivo = window.Culqi3DS ? await Culqi3DS.generateDevice().catch(() => null) : null;
                    const datos = { pago: pago.pago, token, correo, dispositivo };
                    const r = await enviar(rutas.cobrar, datos);
                    if (r.ok && r.datos.tresds && window.Culqi3DS) {
                        cobro = datos;
                        Culqi3DS.settings = { charge: { totalAmount: pago.monto, returnUrl: window.location.href, currency: 'PEN' }, card: { email: correo } };
                        Culqi3DS.options = { showModal: true, showLoading: true, showIcon: true, style: { btnColor: '#2563eb', btnTextColor: '#FFFFFF' } };
                        return Culqi3DS.initAuthentication(token);
                    }
                    terminarCobro(r);
                } else if (checkout.order) {
                    checkout.close();
                    paso('esperando');
                    vigilar();
                } else if (checkout.error) {
                    error(checkout.error.user_message || checkout.error.merchant_message || 'No se pudo completar el pago.');
                }
            }

            $('#btn-pagar').addEventListener('click', async () => {
                error('');
                const boton = $('#btn-pagar');
                boton.disabled = true;
                const meses = Number(document.querySelector('input[name=meses]:checked').value);
                const r = await enviar(rutas.preparar, { meses });
                boton.disabled = false;
                if (!r.ok) return error(r.datos.message || 'No se pudo preparar el pago.');
                pago = r.datos;
                // Con orden se muestran también las billeteras con QR, agentes y banca móvil; sin orden, tarjeta y Yape
                const conOrden = !!pago.orden;
                const metodos = { tarjeta: true, yape: true, billetera: conOrden, bancaMovil: conOrden, agente: conOrden, cuotealo: false };
                checkout = new CulqiCheckout(@js($publica), {
                    settings: Object.assign({ title: 'TUSHPA · ' + pago.texto, currency: 'PEN', amount: pago.monto }, conOrden ? { order: pago.orden } : {}),
                    client: @js($correo) ? { email: @js($correo) } : {},
                    options: { lang: 'es', installments: false, modal: true, paymentMethods: metodos, paymentMethodsSort: Object.keys(metodos) },
                    appearance: { menuType: 'sidebar', buttonCardPayText: 'Pagar S/ ' + (pago.monto / 100).toFixed(2), defaultStyle: { buttonBackground: '#2563eb', menuColor: '#2563eb', linksColor: '#2563eb' } },
                });
                checkout.culqi = alResponderCulqi;
                checkout.open();
                if (conOrden) vigilar();   // si paga con QR y cierra la ventana, igual se entera
            });
            $('#btn-reabrir').addEventListener('click', () => checkout && checkout.open());
        })();
    </script>
@endpush
