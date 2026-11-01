@extends('sitio.base')
@section('titulo', 'Política de privacidad')

@section('contenido')
    <div class="row justify-content-center mt-4">
        <div class="col-lg-9">
            <div class="card tarjeta p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Política de privacidad</h1>
                <p class="text-muted mb-4">Última actualización: 1 de noviembre de 2026</p>

                <p>En <b>TUSHPA</b>, sistema de facturación electrónica de <b>{{ config('soporte.nombre') }}</b> ({{ config('soporte.direccion') }}, Perú),
                    cuidamos la información de nuestros clientes y de sus negocios conforme a la Ley N.° 29733, Ley de Protección de Datos Personales, y su reglamento.</p>

                <h2 class="h5 fw-bold mt-4">1. Qué información guardamos</h2>
                <ul>
                    <li><b>Datos de la empresa cliente:</b> RUC, razón social, dirección, correo y teléfono de contacto.</li>
                    <li><b>Usuarios del sistema:</b> nombre, usuario, correo y contraseña (guardada cifrada; nadie puede leerla).</li>
                    <li><b>Información del negocio:</b> productos, ventas, comprobantes electrónicos, clientes del negocio, compras y caja, que registra cada empresa.</li>
                    <li><b>Ingreso con Google:</b> si eliges entrar con tu cuenta de Google, solo recibimos tu nombre y correo para identificarte.</li>
                </ul>

                <h2 class="h5 fw-bold mt-4">2. Para qué la usamos</h2>
                <ul>
                    <li>Brindar el servicio: emitir comprobantes, enviarlos a SUNAT y llevar las ventas, inventario y caja del negocio.</li>
                    <li>Dar soporte técnico y avisar sobre el servicio (vencimientos, mantenimientos y cambios de SUNAT).</li>
                    <li>Sacar copias de seguridad para que la información no se pierda.</li>
                </ul>
                <p>No vendemos ni alquilamos la información de nuestros clientes ni de sus compradores.</p>

                <h2 class="h5 fw-bold mt-4">3. Con quién se comparte</h2>
                <ul>
                    <li><b>SUNAT</b> y los servicios autorizados que exige la facturación electrónica.</li>
                    <li><b>Proveedores de infraestructura</b> que alojan el sistema y guardan las copias de seguridad (servidores y almacenamiento en la nube, incluido Google Drive), solo para ese fin.</li>
                    <li>Autoridades, cuando una ley o una orden judicial lo exija.</li>
                </ul>

                <h2 class="h5 fw-bold mt-4">4. Seguridad y copias</h2>
                <p>El acceso es por https, cada empresa tiene su propia base de datos y las contraseñas se guardan cifradas.
                    Cada noche se saca una copia de seguridad de la información, que se guarda por un tiempo limitado y luego se borra.</p>

                <h2 class="h5 fw-bold mt-4">5. Cuánto tiempo la guardamos</h2>
                <p>Mientras el servicio esté activo y el tiempo que exigen las normas tributarias para los comprobantes electrónicos.
                    Si una empresa deja el servicio, puede pedir una copia de su información.</p>

                <h2 class="h5 fw-bold mt-4">6. Tus derechos</h2>
                <p>Puedes pedir acceder, corregir o eliminar tus datos personales (derechos ARCO) escribiendo a
                    <a href="mailto:{{ config('soporte.correo') }}">{{ config('soporte.correo') }}</a> o por WhatsApp al {{ config('soporte.telefono') }}.</p>

                <h2 class="h5 fw-bold mt-4">7. Cambios</h2>
                <p class="mb-0">Si cambiamos esta política, publicaremos la nueva versión en esta página con su fecha.</p>
            </div>
        </div>
    </div>
@endsection
