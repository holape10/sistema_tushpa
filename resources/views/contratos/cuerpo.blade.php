{{--
    Texto del contrato de servicio (lo usan la página de la empresa, el PDF y la huella de lo firmado).
    Si se cambia el texto, los contratos ya firmados conservan su huella: así se sabe que se firmó la versión anterior.
--}}
@php
    $p = $proveedor;
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $empresa = $cliente?->razon_social ?? '';
    $esAnual = $contrato->periodo === 'ANUAL';
@endphp
<h1 class="titulo">CONTRATO DE SERVICIO DEL SISTEMA {{ $p['marca'] }}</h1>
<p class="sub">Contrato N° {{ $numero }} · Plan {{ $contrato->plan ?: '—' }} · {{ $periodo }}</p>

<p><b>POR UNA PARTE:</b> {{ $p['razon_social'] }}, con RUC {{ $p['ruc'] }}, con domicilio en {{ $p['direccion'] }}, representada por
    {{ $p['representante'] }}, identificada con DNI {{ $p['dni_representante'] }}, titular del sistema {{ $p['marca'] }} ({{ $p['web'] }}), en adelante
    <b>EL PROVEEDOR</b>.</p>
<p><b>Y POR OTRA PARTE:</b> {{ $empresa }}, con RUC {{ $cliente?->ruc }}@if ($cliente?->direccion), con domicilio en {{ $cliente->direccion }}@endif,
    representada por {{ $contrato->firmante ?: '______________________________' }}, identificado(a) con DNI {{ $contrato->firmante_dni ?: '____________' }}@if ($contrato->firmante_cargo), en calidad de {{ $contrato->firmante_cargo }}@endif,
    en adelante <b>EL CLIENTE</b>.</p>
<p><b>SE DISPONEN</b> a celebrar el presente contrato de prestación de servicio. Ambas partes declaran que no existe dolo, error ni otro vicio de la
    voluntad que afecte su validez, y que se sujetan a los términos siguientes y a las leyes de la República del Perú.</p>

<h2>PRIMERA · OBJETO</h2>
<p>EL PROVEEDOR brinda a EL CLIENTE el uso del sistema {{ $p['marca'] }} en la nube: facturación electrónica ante SUNAT (facturas, boletas, notas de
    crédito y débito, guías de remisión), punto de venta, inventario, caja y los módulos de su rubro incluidos en su plan, accesible por internet desde
    computadora, tablet o celular, con las actualizaciones y mejoras que EL PROVEEDOR publique.</p>

<h2>SEGUNDA · PLAN, PRECIO Y FORMA DE PAGO</h2>
<p>EL CLIENTE contrata el plan <b>{{ $contrato->plan ?: '—' }}</b> en modalidad <b>{{ mb_strtoupper($periodo) }}</b>, por el monto de
    <b>{{ $soles($contrato->monto) }}</b> {{ $esAnual ? 'por cada año de servicio' : 'por cada mes de servicio' }}, incluidos los impuestos de ley que
    correspondan. El pago es adelantado y se realiza por transferencia, depósito, billetera digital o pago en línea en los medios que EL PROVEEDOR
    indique. Cada pago extiende la vigencia del servicio por el periodo pagado.</p>

<h2>TERCERA · VIGENCIA Y RENOVACIÓN</h2>
<p>El contrato rige desde su firma por {{ $esAnual ? 'doce (12) meses' : 'un (1) mes' }} y se renueva automáticamente por periodos iguales mientras
    EL CLIENTE realice el pago correspondiente. Cualquiera de las partes puede no renovarlo avisando con al menos siete (7) días de anticipación.</p>

<h2>CUARTA · SUSPENSIÓN POR FALTA DE PAGO</h2>
<p>Si EL CLIENTE no paga en la fecha de vencimiento, EL PROVEEDOR le enviará un aviso y, de mantenerse la falta de pago, podrá suspender el acceso al
    sistema. La suspensión no borra la información: al regularizar el pago el acceso se restablece tal como estaba.</p>

<h2>QUINTA · OBLIGACIONES DE EL PROVEEDOR</h2>
<ul>
    <li>Mantener el sistema en funcionamiento y disponible por internet, salvo mantenimientos programados o hechos fuera de su control.</li>
    <li>Sacar copias de seguridad diarias de la información de EL CLIENTE y guardarlas en lugar distinto al servidor principal.</li>
    <li>Brindar soporte técnico por WhatsApp y correo en el horario de atención publicado.</li>
    <li>Mantener la conexión con SUNAT y adecuar el sistema a los cambios normativos de facturación electrónica.</li>
    <li>Guardar reserva sobre la información de EL CLIENTE y no venderla ni cederla a terceros.</li>
</ul>

<h2>SEXTA · OBLIGACIONES DE EL CLIENTE</h2>
<ul>
    <li>Pagar el servicio en la forma y plazos pactados.</li>
    <li>Registrar información veraz (productos, precios, clientes, montos) y revisar que sus comprobantes sean correctos antes de emitirlos.</li>
    <li>Cuidar las contraseñas de sus usuarios, su clave SOL y su certificado digital; lo que se haga con ellos es de su responsabilidad.</li>
    <li>Enviar a tiempo los comprobantes a SUNAT cuando use el envío manual y atender las observaciones o rechazos que SUNAT comunique.</li>
    <li>Usar el sistema solo para fines lícitos y no intentar acceder a información de otras empresas ni dañar el servicio.</li>
</ul>

<h2>SÉTIMA · PROTECCIÓN DE DATOS Y PROPIEDAD</h2>
<p>La información que EL CLIENTE registra en el sistema le pertenece; EL PROVEEDOR la trata solo para prestar el servicio, conforme a la Ley N° 29733,
    Ley de Protección de Datos Personales, y su reglamento. Al terminar el contrato, EL CLIENTE puede pedir una copia de su información. El sistema
    {{ $p['marca'] }}, su código, diseño y marca son propiedad de EL PROVEEDOR; este contrato solo otorga el derecho de uso durante su vigencia.</p>

<h2>OCTAVA · RESPONSABILIDAD</h2>
<p>EL PROVEEDOR no responde por multas, reparos o sanciones que resulten de información errada registrada por EL CLIENTE, del uso indebido de sus
    accesos o claves, de la falta de envío oportuno en el modo manual, ni por interrupciones causadas por fallas de internet, de SUNAT o de terceros,
    caso fortuito o fuerza mayor. Ante una interrupción del servicio atribuible a EL PROVEEDOR, este la atenderá con prioridad.</p>

<h2>NOVENA · DOCUMENTOS Y FIRMA ELECTRÓNICA</h2>
<p>EL CLIENTE adjunta la copia de su documento de identidad. Las partes aceptan que este contrato se firme de forma manuscrita o por medios
    electrónicos (firma trazada en pantalla), con el registro de fecha, hora, dirección IP y huella digital del documento, conforme a la Ley N° 27269,
    Ley de Firmas y Certificados Digitales, y que las comunicaciones se hagan por correo electrónico o WhatsApp a los datos aquí registrados.</p>

<h2>DÉCIMA · RESOLUCIÓN Y JURISDICCIÓN</h2>
<p>El contrato puede resolverse por incumplimiento de cualquiera de las obligaciones aquí pactadas, previo aviso a la otra parte. Ante cualquier
    discrepancia, las partes buscarán primero una solución directa; de no lograrla, se someten a los jueces y tribunales del distrito judicial
    correspondiente al domicilio de EL PROVEEDOR.</p>

<p>En señal de conformidad, las partes firman el presente contrato{{ $contrato->firmado_el ? ' el '.\Carbon\Carbon::parse($contrato->firmado_el)->locale('es')->translatedFormat('d \d\e F \d\e Y') : '' }}.</p>
