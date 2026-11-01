@extends('sitio.base')
@section('titulo', 'Condiciones del servicio')

@section('contenido')
    <div class="row justify-content-center mt-4">
        <div class="col-lg-9">
            <div class="card tarjeta p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Condiciones del servicio</h1>
                <p class="text-muted mb-4">Última actualización: 1 de noviembre de 2026</p>

                <p>Estas condiciones son entre <b>{{ config('soporte.nombre') }}</b> (TUSHPA) y la empresa que usa el sistema. Al usar TUSHPA las aceptas.</p>

                <h2 class="h5 fw-bold mt-4">1. El servicio</h2>
                <p>TUSHPA es un sistema en la nube de facturación electrónica y gestión de negocios (ventas, inventario, caja y más), que se usa por internet desde la PC, la tablet o el celular.</p>

                <h2 class="h5 fw-bold mt-4">2. Cuenta y usuarios</h2>
                <ul>
                    <li>La empresa es responsable de los usuarios que crea y de cuidar sus contraseñas.</li>
                    <li>La información que registra (precios, clientes, comprobantes) es de la empresa y es responsable de que sea correcta.</li>
                    <li>Los comprobantes se emiten con el RUC y el certificado digital de la empresa ante SUNAT.</li>
                </ul>

                <h2 class="h5 fw-bold mt-4">3. Pago del servicio</h2>
                <p>El servicio se paga según el plan contratado. Si el pago vence, el sistema puede suspenderse hasta que se regularice; la información no se borra por la suspensión.</p>

                <h2 class="h5 fw-bold mt-4">4. Disponibilidad y soporte</h2>
                <p>Trabajamos para que el sistema esté disponible todo el tiempo y sacamos copias de seguridad cada noche.
                    Puede haber pausas cortas por mantenimiento o fallas de terceros (internet, servidores, SUNAT); en esos casos la caja sin internet permite seguir vendiendo.
                    El soporte se da por WhatsApp al {{ config('soporte.telefono') }} y al correo <a href="mailto:{{ config('soporte.correo') }}">{{ config('soporte.correo') }}</a>.</p>

                <h2 class="h5 fw-bold mt-4">5. Uso correcto</h2>
                <p>No se permite usar el sistema para actividades ilegales, ni intentar acceder a información de otras empresas o dañar el servicio.</p>

                <h2 class="h5 fw-bold mt-4">6. Privacidad</h2>
                <p>El trato de la información se explica en la <a href="{{ route('sitio.privacidad') }}">política de privacidad</a>.</p>

                <h2 class="h5 fw-bold mt-4">7. Cambios y término</h2>
                <p class="mb-0">Podemos actualizar estas condiciones y lo publicaremos en esta página. La empresa puede dejar el servicio cuando quiera y pedir una copia de su información.</p>
            </div>
        </div>
    </div>
@endsection
