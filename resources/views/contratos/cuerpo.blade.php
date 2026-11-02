{{--
    Texto del contrato de servicio (lo usan la página de la empresa, el PDF y la huella de lo firmado). Cabe en una hoja A4.
    Si se cambia el texto, los contratos ya firmados conservan su huella: así se sabe que se firmó la versión anterior.
--}}
@php
    $p = $proveedor;
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $esAnual = $contrato->periodo === 'ANUAL';
    $firmante = $contrato->firmante ?: ($cliente?->representante ?: null);
    $dniFirmante = $contrato->firmante_dni ?: ($cliente?->representante_dni ?: null);
@endphp
<h1 class="titulo">CONTRATO DE SERVICIO DEL SISTEMA {{ $p['marca'] }}</h1>
<p class="sub">Contrato N° {{ $numero }} · Plan {{ $contrato->plan ?: '—' }} · {{ $periodo }}</p>

<p><b>POR UNA PARTE:</b> {{ $p['razon_social'] }}, con RUC {{ $p['ruc'] }}, domiciliada en {{ $p['direccion'] }}, representada por {{ $p['representante'] }} (DNI {{ $p['dni_representante'] }}),
    titular del sistema {{ $p['marca'] }} ({{ $p['web'] }}), en adelante <b>EL PROVEEDOR</b>.
    <b>Y POR OTRA PARTE:</b> {{ $cliente?->razon_social }}, con RUC {{ $cliente?->ruc }}@if ($cliente?->direccion), domiciliada en {{ $cliente->direccion }}@endif,
    representada por {{ $firmante ?: '______________________________' }}, identificado(a) con DNI {{ $dniFirmante ?: '____________' }}@if ($contrato->firmante_cargo), en calidad de {{ $contrato->firmante_cargo }}@endif,
    en adelante <b>EL CLIENTE</b>. <b>SE DISPONEN</b> a celebrar el presente contrato de servicio; ambas partes declaran que no existe dolo, error ni otro vicio de la voluntad
    y se sujetan a los términos siguientes y a las leyes de la República del Perú.</p>

<p><b>PRIMERA · OBJETO:</b> EL PROVEEDOR brinda a EL CLIENTE el uso del sistema {{ $p['marca'] }} en la nube: facturación electrónica ante SUNAT (facturas, boletas, notas de
    crédito y débito, guías de remisión), punto de venta, inventario, caja y los módulos de su rubro incluidos en su plan, desde computadora, tablet o celular, con las
    actualizaciones y mejoras que EL PROVEEDOR publique.</p>

<p><b>SEGUNDA · PLAN, PRECIO Y PAGO:</b> EL CLIENTE contrata el plan <b>{{ $contrato->plan ?: '—' }}</b> en modalidad <b>{{ mb_strtoupper($periodo) }}</b> por
    <b>{{ $soles($contrato->monto) }}</b> {{ $esAnual ? 'por cada año' : 'por cada mes' }} de servicio, incluidos los impuestos de ley que correspondan. El pago es adelantado,
    por transferencia, depósito, billetera digital o pago en línea, y cada pago extiende el servicio por el periodo pagado.</p>

<p><b>TERCERA · VIGENCIA Y SUSPENSIÓN:</b> El contrato rige desde su firma por {{ $esAnual ? 'doce (12) meses' : 'un (1) mes' }} y se renueva automáticamente por periodos iguales
    mientras EL CLIENTE pague; cualquiera de las partes puede no renovarlo avisando con siete (7) días de anticipación. Si EL CLIENTE no paga en la fecha de vencimiento, EL PROVEEDOR
    le avisará y podrá suspender el acceso; la suspensión no borra la información y el acceso vuelve tal como estaba al regularizar el pago.</p>

<p><b>CUARTA · OBLIGACIONES DE EL PROVEEDOR:</b> a) Mantener el sistema disponible por internet, salvo mantenimientos o hechos fuera de su control. b) Sacar copias de seguridad
    diarias de la información de EL CLIENTE fuera del servidor principal. c) Brindar soporte por WhatsApp y correo en su horario de atención. d) Mantener la conexión con SUNAT y
    adecuar el sistema a los cambios de la facturación electrónica. e) Guardar reserva sobre la información de EL CLIENTE y no venderla ni cederla a terceros.</p>

<p><b>QUINTA · OBLIGACIONES DE EL CLIENTE:</b> a) Pagar el servicio en la forma y plazos pactados. b) Registrar información veraz y revisar sus comprobantes antes de emitirlos.
    c) Cuidar las contraseñas de sus usuarios, su clave SOL y su certificado digital, cuyo uso es de su responsabilidad. d) Enviar a tiempo sus comprobantes a SUNAT cuando use el
    envío manual y atender sus observaciones o rechazos. e) Usar el sistema solo para fines lícitos, sin intentar acceder a información de otras empresas ni dañar el servicio.</p>

<p><b>SEXTA · DATOS Y PROPIEDAD:</b> La información que EL CLIENTE registra le pertenece; EL PROVEEDOR la trata solo para prestar el servicio, conforme a la Ley N° 29733, Ley de
    Protección de Datos Personales, y al terminar el contrato EL CLIENTE puede pedir una copia. El sistema {{ $p['marca'] }}, su código, diseño y marca son de EL PROVEEDOR; este
    contrato solo otorga su uso durante la vigencia.</p>

<p><b>SÉTIMA · RESPONSABILIDAD:</b> EL PROVEEDOR no responde por multas, reparos o sanciones derivados de información errada registrada por EL CLIENTE, del uso indebido de sus
    accesos o claves, de la falta de envío oportuno en el modo manual, ni por interrupciones causadas por fallas de internet, de SUNAT o de terceros, caso fortuito o fuerza mayor.</p>

<p><b>OCTAVA · DOCUMENTOS Y FIRMA ELECTRÓNICA:</b> EL CLIENTE adjunta la copia de su documento de identidad. Las partes aceptan que este contrato se firme de forma manuscrita o
    electrónica (firma trazada en pantalla), con registro de fecha, hora, dirección IP y huella del documento, conforme a la Ley N° 27269, y que las comunicaciones se hagan por
    correo electrónico o WhatsApp a los datos registrados.</p>

<p><b>NOVENA · RESOLUCIÓN Y JURISDICCIÓN:</b> El contrato puede resolverse por incumplimiento de cualquiera de las obligaciones pactadas, previo aviso a la otra parte. Ante cualquier
    discrepancia las partes buscarán una solución directa; de no lograrla, se someten a los jueces y tribunales del distrito judicial del domicilio de EL PROVEEDOR.</p>

<p>En señal de conformidad, EL CLIENTE firma el presente contrato{{ $contrato->firmado_el ? ' el '.\Carbon\Carbon::parse($contrato->firmado_el)->locale('es')->translatedFormat('d \d\e F \d\e Y') : '' }}.</p>
