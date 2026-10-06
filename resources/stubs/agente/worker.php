<?php
/**
 * AGENTE DE IMPRESIÓN MULTI-SISTEMA (TUSHPA)
 * ------------------------------------------
 * Va en la PC donde están las impresoras. Cada 2 segundos revisa TODOS los sistemas de config.ini
 * y imprime lo pendiente sin vista previa:
 *   - Tickets (comprobantes, comandas, precuentas) directo a la ticketera.
 *   - Comprobantes A4 en PDF con sumatrapdf.exe (ponlo en esta misma carpeta).
 *
 * En config.ini cada bloque es un sistema:
 *   tipo = nuevo   -> sistema nuevo (servidor + token, se descarga desde Impresoras)
 *   sin "tipo"     -> sistema antiguo (dominio + token), igual que el worker.php anterior
 * Si agregas un bloque no hace falta reiniciar: el agente vuelve a leer config.ini cuando cambia.
 *
 * Funciona con PHP 7 u 8 (extensión openssl activa para https).
 */

date_default_timezone_set('America/Lima');
set_time_limit(0);

define('CARPETA', __DIR__);
define('CONFIG', CARPETA . DIRECTORY_SEPARATOR . 'config.ini');
define('LOG', CARPETA . DIRECTORY_SEPARATOR . 'agente.log');

function registro($mensaje)
{
    $linea = '[' . date('d/m/Y H:i:s') . '] ' . $mensaje . PHP_EOL;
    echo $linea;
    // agente.log: para revisar qué pasó cuando corre oculto (se reinicia al pasar 1 MB)
    if (@filesize(LOG) > 1048576) {
        @rename(LOG, LOG . '.anterior');
    }
    @file_put_contents(LOG, $linea, FILE_APPEND);
}

function empiezaCon($texto, $inicio)
{
    return substr((string) $texto, 0, strlen($inicio)) === $inicio;
}

/**
 * Separa la ruta en array(PC, COMPARTIDA). Acepta igual que el sistema antiguo:
 *   CAJA  |  PC_CAJA/CAJA  |  \\PC_CAJA\CAJA  |  smb://PC_CAJA/CAJA
 * Sin PC = esta misma PC.
 */
function partirRuta($ruta)
{
    $r = trim((string) $ruta);
    if (strtolower(substr($r, 0, 6)) === 'smb://') {
        $r = substr($r, 6);
    }
    $r = trim(str_replace('/', '\\', $r), '\\');
    $partes = explode('\\', $r, 2);
    if (count($partes) === 2 && $partes[0] !== '' && $partes[1] !== '') {
        return array($partes[0], $partes[1]);
    }
    return array('', $r);
}

/** ¿Ese nombre de PC es esta misma PC? */
function esEstaPc($pc)
{
    $pc = strtolower(trim($pc));
    return $pc === '' || $pc === 'localhost' || $pc === '127.0.0.1' || $pc === strtolower(gethostname())
        || $pc === strtolower((string) getenv('COMPUTERNAME'));
}

// ------------------------------------------------------------------ configuración

$GLOBALS['ajustes'] = array('intervalo' => 2, 'sumatra' => CARPETA . DIRECTORY_SEPARATOR . 'sumatrapdf.exe', 'verificar_ssl' => 0);
$GLOBALS['sistemas'] = array();
$GLOBALS['config_fecha'] = 0;

function leerConfig()
{
    clearstatcache(true, CONFIG);
    $fecha = @filemtime(CONFIG);
    if (!$fecha) {
        registro('ERROR: no se encontró config.ini en ' . CARPETA);
        return;
    }
    if ($fecha === $GLOBALS['config_fecha']) {
        return;
    }
    $datos = @parse_ini_file(CONFIG, true);
    if ($datos === false) {
        registro('ERROR: config.ini tiene un error de escritura. Revisa comillas y corchetes.');
        $GLOBALS['config_fecha'] = $fecha;
        return;
    }
    $GLOBALS['config_fecha'] = $fecha;

    $sistemas = array();
    foreach ($datos as $nombre => $c) {
        if (!is_array($c)) {
            continue;
        }
        if (strtoupper($nombre) === 'AGENTE') {
            foreach ($c as $k => $v) {
                $GLOBALS['ajustes'][$k] = $v;
            }
            continue;
        }
        if (empty($c['token'])) {
            continue;
        }
        $nuevo = isset($c['tipo']) && strtolower(trim($c['tipo'])) === 'nuevo';
        $base = $nuevo ? (isset($c['servidor']) ? $c['servidor'] : '') : (isset($c['dominio']) ? $c['dominio'] : '');
        $base = trim($base);
        if ($base === '') {
            continue;
        }
        if (strpos($base, '://') === false) {
            $base = 'https://' . $base;
        }
        $sistemas[$nombre] = array('nombre' => $nombre, 'nuevo' => $nuevo, 'base' => rtrim($base, '/'), 'token' => trim($c['token']), 'errores' => 0);
    }

    // Conserva el contador de errores de los que ya estaban
    foreach ($sistemas as $n => $s) {
        if (isset($GLOBALS['sistemas'][$n])) {
            $sistemas[$n]['errores'] = $GLOBALS['sistemas'][$n]['errores'];
        }
    }
    $GLOBALS['sistemas'] = $sistemas;

    registro('Configuración cargada: ' . count($sistemas) . ' sistema(s).');
    foreach ($sistemas as $s) {
        registro('  -> ' . $s['nombre'] . ' (' . ($s['nuevo'] ? 'nuevo' : 'antiguo') . '): ' . $s['base']);
    }
    if (!is_file($GLOBALS['ajustes']['sumatra'])) {
        registro('Aviso: no está sumatrapdf.exe (' . $GLOBALS['ajustes']['sumatra'] . '). Los PDF A4 no se podrán imprimir.');
    }
}

// ------------------------------------------------------------------ http

function contexto($metodo = 'GET', $cabeceras = '', $contenido = '', $timeout = 10)
{
    $verificar = (bool) (int) $GLOBALS['ajustes']['verificar_ssl'];
    return stream_context_create(array(
        'http' => array('method' => $metodo, 'header' => $cabeceras, 'content' => $contenido, 'timeout' => $timeout, 'ignore_errors' => true),
        'ssl' => array('verify_peer' => $verificar, 'verify_peer_name' => $verificar),
    ));
}

/** Devuelve array(código HTTP, cuerpo) o null si no hubo conexión */
function pedir($url, $metodo = 'GET', $cabeceras = '', $contenido = '', $timeout = 10)
{
    $cuerpo = @file_get_contents($url, false, contexto($metodo, $cabeceras, $contenido, $timeout));
    if ($cuerpo === false) {
        return null;
    }
    $codigo = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $codigo = (int) $m[1];
            }
        }
    }
    return array($codigo, $cuerpo);
}

// ------------------------------------------------------------------ impresión

/** Bytes en bruto (ESC/POS) a una impresora. Devuelve '' si salió bien o el mensaje de error. */
function imprimirBruto($bytes, $ruta, $conexion = 'COMPARTIDO')
{
    $ruta = trim((string) $ruta);
    if ($ruta === '') {
        return 'La impresora no tiene nombre o ruta configurada';
    }

    if ($conexion === 'RED') {
        $partes = explode(':', $ruta);
        $ip = $partes[0];
        $puerto = isset($partes[1]) ? (int) $partes[1] : 9100;
        $con = @fsockopen($ip, $puerto, $errno, $errstr, 5);
        if (!$con) {
            return "No se pudo conectar a $ip:$puerto ($errstr)";
        }
        stream_set_timeout($con, 10);
        $escrito = fwrite($con, $bytes);
        fclose($con);
        return $escrito === strlen($bytes) ? '' : 'La impresora no recibió todo el ticket';
    }

    // Windows: LPT1/COM1, \\PC\COMPARTIDA, smb://PC/COMPARTIDA o el nombre con el que se compartió en esta PC
    if (preg_match('/^(LPT|COM)\d+$/i', $ruta)) {
        $destino = $ruta;
    } else {
        list($pc, $compartida) = partirRuta($ruta);
        $destino = '\\\\' . ($pc === '' ? 'localhost' : $pc) . '\\' . $compartida;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'tsh');
    file_put_contents($tmp, $bytes);
    $salida = array();
    exec('copy /b ' . escapeshellarg($tmp) . ' ' . escapeshellarg($destino) . ' 2>&1', $salida, $codigo);
    @unlink($tmp);
    return $codigo === 0 ? '' : 'No se pudo imprimir en ' . $destino . ' (¿está compartida con ese nombre?): ' . trim(implode(' ', $salida));
}

/** PDF (A4) con SumatraPDF en la impresora de Windows con ese nombre (vacío = la predeterminada) */
function imprimirPdf($bytes, $impresora)
{
    $sumatra = $GLOBALS['ajustes']['sumatra'];
    if (!is_file($sumatra)) {
        return 'Falta sumatrapdf.exe en ' . $sumatra;
    }
    $pdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tushpa_' . date('His') . '_' . mt_rand(1000, 9999) . '.pdf';
    file_put_contents($pdf, $bytes);

    // Sumatra usa el nombre como aparece en Windows: de esta PC "CAJA"; de otra PC "\\PC_CAJA\CAJA" (agregada en esta PC)
    list($pc, $nombre) = partirRuta($impresora);
    $impresora = esEstaPc($pc) ? $nombre : '\\\\' . $pc . '\\' . $nombre;
    $destino = $impresora === '' ? '-print-to-default' : '-print-to "' . $impresora . '"';
    $salida = array();
    exec('"' . $sumatra . '" ' . $destino . ' -silent "' . $pdf . '" 2>&1', $salida, $codigo);
    @unlink($pdf);
    return $codigo === 0 ? '' : 'SumatraPDF no pudo imprimir en "' . $impresora . '" (revisa el nombre exacto en Windows)';
}

// ------------------------------------------------------------------ sistemas

/** Sistema nuevo: trae hasta 10 trabajos, los imprime y avisa el resultado de cada uno */
function atenderNuevo(&$s)
{
    $cab = 'X-Token: ' . $s['token'] . "\r\nAccept: application/json\r\n";
    $r = pedir($s['base'] . '/api/impresion/trabajos?espera=0', 'GET', $cab);
    if (!revisarConexion($s, $r)) {
        return;
    }
    $json = json_decode($r[1], true);
    $trabajos = isset($json['trabajos']) && is_array($json['trabajos']) ? $json['trabajos'] : array();

    foreach ($trabajos as $t) {
        $bytes = base64_decode((string) $t['contenido']);
        $conexion = isset($t['tip_conex_imp']) ? $t['tip_conex_imp'] : 'COMPARTIDO';
        $ruta = isset($t['ruta']) && $t['ruta'] !== '' ? $t['ruta'] : (isset($t['descripcion']) ? $t['descripcion'] : '');

        $error = empiezaCon($bytes, '%PDF') ? imprimirPdf($bytes, $ruta) : imprimirBruto($bytes, $ruta, $conexion);
        registro(($error === '' ? 'OK  ' : 'ERR ') . $s['nombre'] . ' · ' . $t['tipo'] . ' ' . $t['referencia'] . ' -> ' . $ruta . ($error ? ' | ' . $error : ''));

        pedir($s['base'] . '/api/impresion/resultado', 'POST', $cab . "Content-Type: application/json\r\n",
            json_encode(array('id' => $t['id'], 'ok' => $error === '', 'error' => $error === '' ? null : substr($error, 0, 240))));
    }
}

/** Sistema antiguo: mismo funcionamiento del worker.php anterior (un trabajo por vuelta) */
function atenderAntiguo(&$s)
{
    $r = pedir($s['base'] . '/api/impresion/pendiente?token=' . urlencode($s['token']));
    if (!revisarConexion($s, $r)) {
        return;
    }
    $pedido = json_decode($r[1], true);
    if (!$pedido || !isset($pedido['id'])) {
        return;
    }

    $impresora = isset($pedido['impresora']) ? $pedido['impresora'] : '';
    $contenido = isset($pedido['contenido']) ? (string) $pedido['contenido'] : '';

    if (strpos($contenido, 'http') === 0 && strpos($contenido, '.pdf') !== false) {
        $pdf = pedir($contenido, 'GET', '', '', 30);
        $error = $pdf && $pdf[0] < 400 ? imprimirPdf($pdf[1], $impresora) : 'No se pudo descargar el PDF';
        $tipo = 'PDF A4';
    } else {
        $error = imprimirBruto(base64_decode($contenido), $impresora);
        $tipo = 'Ticket';
    }
    registro(($error === '' ? 'OK  ' : 'ERR ') . $s['nombre'] . ' · ' . $tipo . ' -> ' . $impresora . ($error ? ' | ' . $error : ''));

    // Igual que el worker anterior: solo se marca si se imprimió (si falló, se reintenta en la siguiente vuelta)
    if ($error === '') pedir($s['base'] . '/api/impresion/marcar-impreso/' . $pedido['id'] . '?token=' . urlencode($s['token']));
}

/** Avisa una sola vez cuando un sistema se cae y cuando vuelve */
function revisarConexion(&$s, $r)
{
    if ($r === null || $r[0] >= 500 || $r[0] === 0) {
        $s['errores']++;
        if ($s['errores'] === 1 || $s['errores'] % 150 === 0) {
            registro('Sin conexión con ' . $s['nombre'] . ' (' . $s['base'] . '), reintentando...');
        }
        return false;
    }
    if ($r[0] === 401 || $r[0] === 403) {
        $s['errores']++;
        if ($s['errores'] === 1 || $s['errores'] % 150 === 0) {
            registro('CLAVE NO VÁLIDA en ' . $s['nombre'] . ': descarga de nuevo la clave desde el sistema (Impresoras).');
        }
        return false;
    }
    if ($s['errores']) {
        registro('Conectado otra vez con ' . $s['nombre'] . '.');
        $s['errores'] = 0;
    }
    return $r[0] < 400;
}

// ------------------------------------------------------------------ bucle

registro('Agente de impresión iniciado (PHP ' . PHP_VERSION . ').');
while (true) {
    leerConfig();
    foreach (array_keys($GLOBALS['sistemas']) as $n) {
        try {
            if ($GLOBALS['sistemas'][$n]['nuevo']) {
                atenderNuevo($GLOBALS['sistemas'][$n]);
            } else {
                atenderAntiguo($GLOBALS['sistemas'][$n]);
            }
        } catch (Throwable $e) {
            registro('Error en ' . $n . ': ' . $e->getMessage());
        }
    }
    sleep(max(1, (int) $GLOBALS['ajustes']['intervalo']));
}
