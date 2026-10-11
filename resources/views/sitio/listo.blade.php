@extends('sitio.base')
@section('titulo', '¡Tu sistema está listo!')
@section('robots', 'noindex')

@section('contenido')
    <div class="row justify-content-center mt-3">
        <div class="col-md-9 col-lg-7">
            <div class="card tarjeta p-4 p-md-5 text-center">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-success text-white" style="width:72px;height:72px;font-size:2rem">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="h3 fw-bold">¡Tu sistema está listo!</h1>
                <p class="text-muted">{{ $cliente->nombre_comercial ?: $cliente->razon_social }}</p>

                <div class="rounded-4 bg-light p-4 text-start my-3">
                    <div class="mb-3">
                        <div class="small text-muted">Dirección de tu sistema (guárdala)</div>
                        <div class="fs-5 fw-bold text-primary text-break" id="direccion">{{ $url }}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="small text-muted">Usuario</div>
                            <div class="fw-bold">{{ $cliente->ruc }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Contraseña</div>
                            <div class="fw-bold">La que elegiste</div>
                        </div>
                    </div>
                    <hr>
                    <div class="small">
                        <i class="fas fa-gift text-primary me-1"></i>
                        Prueba gratis hasta el <b>{{ $cliente->prueba_hasta->format('d/m/Y') }}</b>
                        @if ($cliente->plan) con el plan <b>{{ $cliente->plan }}</b>@endif.
                    </div>
                </div>

                <a href="{{ $url }}/login" class="btn btn-primary btn-lg w-100 rounded-pill fw-semibold" id="btn_entrar">
                    Entrar a mi sistema <i class="fas fa-arrow-right ms-1"></i>
                </a>
                <p class="small text-muted mt-3 mb-0" id="https_estado">
                    @unless (str_starts_with($url, 'https://') || config('tenancy.esquema') !== 'https')
                        <span class="spinner-border spinner-border-sm me-1"></span> Activando el candado de seguridad (https)… menos de un minuto.
                    @endunless
                </p>
                <p class="small text-muted mt-4 mb-0">
                    ¿Dudas? Te ayudamos a configurar tu sistema por
                    <a href="https://wa.me/{{ config('soporte.whatsapp') }}?text={{ rawurlencode('Hola, acabo de crear mi sistema TUSHPA (RUC '.$cliente->ruc.'). ¿Me ayudan a configurarlo?') }}" target="_blank" rel="noopener">WhatsApp</a>.
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Cuando el servidor ya tiene el certificado del subdominio nuevo, el botón pasa a https
        (function () {
            const estado = document.getElementById('https_estado');
            if (!estado.querySelector('.spinner-border')) return;
            const revisar = () => fetch(@json($estadoUrl), { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => {
                if (!d.https) return setTimeout(revisar, 5000);
                document.getElementById('direccion').textContent = d.url;
                document.getElementById('btn_entrar').href = d.url + '/login';
                estado.innerHTML = '<i class="fas fa-lock text-success me-1"></i> Conexión segura activada.';
            }).catch(() => setTimeout(revisar, 8000));
            setTimeout(revisar, 4000);
        })();
    </script>
@endpush
