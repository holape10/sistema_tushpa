<?php

/**
 * IMPRESIÓN SIN INTERNET (TUSHPA)
 * -------------------------------
 * Pequeño servidor solo para esta PC (http://127.0.0.1:17777). Cuando se cae el internet, la "Caja sin internet"
 * del navegador le manda aquí el ticket o la comanda y se imprime al instante, sin pasar por la nube.
 *
 * Lo inicia iniciar.bat con:  php -S 127.0.0.1:17777 local.php
 *
 *   GET  /estado    -> {"ok":true,"pc":"CAJA01"}  (la caja sabe que el agente está prendido)
 *   POST /imprimir  -> {"ruta":"CAJA","conexion":"COMPARTIDO","contenido":"<ESC/POS en base64>"}
 *
 * Solo acepta pedidos de las páginas de los sistemas de config.ini (los mismos dominios que el agente imprime).
 */
define('AGENTE_SOLO_FUNCIONES', true);
require __DIR__.DIRECTORY_SEPARATOR.'worker.php';

leerConfig();

/** ¿La página que pide es de uno de los sistemas de esta PC? (mismo dominio o un subdominio de él) */
function origenPermitido($origen)
{
    $host = strtolower((string) parse_url((string) $origen, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }
    if ($host === 'localhost' || $host === '127.0.0.1') {
        return true;
    }
    foreach ($GLOBALS['sistemas'] as $s) {
        $base = strtolower((string) parse_url($s['base'], PHP_URL_HOST));
        // El agente de una empresa (demo.tushpa.app) también sirve a la tienda o a otra sucursal del mismo dominio
        $raiz = implode('.', array_slice(explode('.', $base), -2));
        if ($base !== '' && ($host === $base || $host === $raiz || substr($host, -strlen('.'.$raiz)) === '.'.$raiz)) {
            return true;
        }
    }

    return false;
}

function responder($codigo, $datos)
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit;
}

$origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origen !== '') {
    if (! origenPermitido($origen)) {
        responder(403, ['ok' => false, 'error' => 'Página no autorizada para imprimir en esta PC']);
    }
    header('Access-Control-Allow-Origin: '.$origen);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    // Chrome pide permiso para que una página de internet hable con esta PC (Private Network Access)
    header('Access-Control-Allow-Private-Network: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pc = (string) (getenv('COMPUTERNAME') ?: gethostname());

if ($ruta === '/estado') {
    responder(200, ['ok' => true, 'pc' => $pc, 'php' => PHP_VERSION]);
}

if ($ruta === '/imprimir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode((string) file_get_contents('php://input'), true);
    if (! is_array($datos) || empty($datos['contenido'])) {
        responder(422, ['ok' => false, 'error' => 'No llegó nada para imprimir']);
    }
    $bytes = base64_decode((string) $datos['contenido'], true);
    if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024) {
        responder(422, ['ok' => false, 'error' => 'El ticket no es válido']);
    }
    $impresora = isset($datos['ruta']) ? (string) $datos['ruta'] : '';
    $conexion = isset($datos['conexion']) ? (string) $datos['conexion'] : 'COMPARTIDO';
    $error = imprimirBruto($bytes, $impresora, $conexion);
    registro(($error === '' ? 'OK  ' : 'ERR ').'SIN INTERNET · '.(isset($datos['referencia']) ? substr((string) $datos['referencia'], 0, 60) : '').' -> '.$impresora.($error ? ' | '.$error : ''));
    responder($error === '' ? 200 : 500, ['ok' => $error === '', 'error' => $error === '' ? null : $error]);
}

responder(404, ['ok' => false, 'error' => 'No existe']);
