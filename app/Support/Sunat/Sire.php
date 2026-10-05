<?php
namespace App\Support\Sunat;

use App\Models\Empresa;
use Illuminate\Support\Facades\{Cache, Http};

/**
 * Cliente del API SIRE de SUNAT (Manual de servicios web API RVIE v30 y RCE v28).
 * Token OAuth2 con client_id/client_secret + usuario SOL; las propuestas se piden con un ticket
 * que SUNAT procesa en segundo plano y luego se descargan como .zip con un .txt separado por "|".
 * La carga de archivos (reemplazo de propuesta) usa el protocolo TUS y SUNAT la documenta solo en Java.
 */
class Sire
{
    public const VENTAS = '140000';   // RVIE
    public const COMPRAS = '080000';  // RCE

    // Anexo III del manual: código de estado de envío del ticket
    public const ESTADOS = [
        '01' => 'Cargado (solicitado)', '02' => 'Validando archivo', '03' => 'Procesado con errores',
        '04' => 'Procesado sin errores', '05' => 'En proceso', '06' => 'Terminado',
    ];

    private const URL_TOKEN = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/%s/oauth2/token/';
    private const URL_API = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros';

    public function __construct(private Empresa $empresa) {}

    public static function configurado(Empresa $empresa): bool
    {
        return $empresa->client_id && $empresa->client_secret
            && ($empresa->sire_usuario ?: $empresa->wsusuario) && ($empresa->sire_clave ?: $empresa->claveSunat);
    }

    /** Token OAuth2; se guarda en caché hasta poco antes de que venza */
    public function token(bool $renovar = false): string
    {
        $llave = 'sire_token_' . $this->empresa->IdEmpresa;
        if ($renovar) {
            Cache::forget($llave);
        }
        if ($token = Cache::get($llave)) {
            return $token;
        }
        if (!self::configurado($this->empresa)) {
            throw new \RuntimeException('Faltan las credenciales del SIRE. Ingrésalas en SIRE → Credenciales SIRE.');
        }

        $r = Http::asForm()->timeout(30)->post(sprintf(self::URL_TOKEN, $this->empresa->client_id), [
            'grant_type'    => 'password',
            'scope'         => 'https://api-sire.sunat.gob.pe',
            'client_id'     => $this->empresa->client_id,
            'client_secret' => $this->empresa->client_secret,
            'username'      => $this->empresa->IdEmpresa . ($this->empresa->sire_usuario ?: $this->empresa->wsusuario),
            'password'      => $this->empresa->sire_clave ?: $this->empresa->claveSunat,
        ]);

        $token = $r->json('access_token');
        if (!$r->successful() || !$token) {
            $detalle = rtrim($r->json('error_description') ?: $r->json('msg') ?: $r->json('error') ?: ('HTTP ' . $r->status()), '. ');
            $usuario = $this->empresa->sire_usuario ?: $this->empresa->wsusuario;

            // "autenticacion del usuario" = el ID/CLAVE pasaron, falló el usuario o la clave SOL
            if (str_contains(mb_strtolower($detalle), 'usuario')) {
                throw new \RuntimeException("SUNAT aceptó el ID y la CLAVE, pero rechazó el usuario SOL \"{$usuario}\" ({$detalle}). "
                    . 'Revisa que su clave SOL sea la actual y que ese usuario secundario tenga permiso para el SIRE '
                    . '(SOL → Administración de usuarios secundarios → perfil con Registros de Ventas y Compras / SIRE).');
            }
            throw new \RuntimeException("SUNAT no aceptó las credenciales del API ({$detalle}). Revisa el ID y la CLAVE de \"Credenciales de API SUNAT\".");
        }

        Cache::put($llave, $token, max(60, ((int) $r->json('expires_in', 3600)) - 120));
        return $token;
    }

    /** Periodos habilitados del libro: [['periodo' => '202609', 'anio' => '2026', 'estado' => '...'], ...] (más reciente primero) */
    public function periodos(string $libro): array
    {
        $data = $this->get("/rvierce/padron/web/omisos/{$libro}/periodos");
        $lista = [];
        foreach ((array) $data as $ejercicio) {
            foreach ($ejercicio['lisPeriodos'] ?? [] as $p) {
                $lista[] = ['periodo' => $p['perTributario'], 'anio' => $ejercicio['numEjercicio'] ?? substr($p['perTributario'], 0, 4),
                            'estado' => $p['desEstado'] ?? ''];
            }
        }
        usort($lista, fn($a, $b) => strcmp($b['periodo'], $a['periodo']));
        return $lista;
    }

    /** Pide a SUNAT generar el archivo de la propuesta (txt); devuelve el número de ticket */
    public function solicitarPropuesta(string $libro, string $periodo): string
    {
        $data = $libro === self::VENTAS
            ? $this->get("/rvie/propuesta/web/propuesta/{$periodo}/exportapropuesta", ['codTipoArchivo' => 0])
            : $this->get("/rce/propuesta/web/propuesta/{$periodo}/exportacioncomprobantepropuesta", ['codTipoArchivo' => 0, 'codOrigenEnvio' => 2]);

        $ticket = $data['numTicket'] ?? null;
        if (!$ticket) {
            throw new \RuntimeException('SUNAT no devolvió un ticket para el periodo ' . $periodo . '.');
        }
        return (string) $ticket;
    }

    /** Estado del ticket (registros[0] del servicio 5.16); null si SUNAT aún no lo lista */
    public function estadoTicket(string $libro, string $periodo, string $ticket): ?array
    {
        $data = $this->get('/rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets', [
            'perIni' => $periodo, 'perFin' => $periodo, 'page' => 1, 'perPage' => 20,
            'numTicket' => $ticket, 'codLibro' => $libro, 'codOrigenEnvio' => 2,
        ]);
        foreach ($data['registros'] ?? [] as $r) {
            if ((string) ($r['numTicket'] ?? '') === $ticket) {
                return $r;
            }
        }
        return $data['registros'][0] ?? null;
    }

    /** Descarga el .zip generado por un ticket terminado (servicio 5.17) */
    public function descargarArchivo(string $libro, array $registro): string
    {
        $archivo = $registro['archivoReporte'][0] ?? null;
        if (!$archivo || empty($archivo['nomArchivoReporte'])) {
            throw new \RuntimeException('El ticket terminó pero SUNAT no indicó un archivo para descargar.');
        }

        $r = $this->peticion('/rvierce/gestionprocesosmasivos/web/masivo/archivoreporte', [
            'nomArchivoReporte'     => $archivo['nomArchivoReporte'],
            // El manual nombra el campo de salida "codTipoAchivoReporte" (sin r); si viene null se envía null
            'codTipoArchivoReporte' => $archivo['codTipoAchivoReporte'] ?? $archivo['codTipoArchivoReporte'] ?? 'null',
            'codLibro'              => $libro,
            'perTributario'         => $registro['perTributario'] ?? null,
            'codProceso'            => $registro['codProceso'] ?? null,
            'numTicket'             => $registro['numTicket'] ?? null,
        ]);
        return $r->body();
    }

    /** Lee el .txt de un .zip de propuesta: ['cabecera' => [...], 'filas' => [[...], ...]] */
    public static function leerZip(string $rutaZip): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            throw new \RuntimeException('El archivo descargado de SUNAT no es un .zip válido.');
        }
        $texto = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_ends_with(strtolower($zip->getNameIndex($i)), '.txt')) {
                $texto = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        if ($texto === null) {
            throw new \RuntimeException('El .zip de SUNAT no trae un archivo .txt.');
        }
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);   // SUNAT antepone el BOM de UTF-8
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
        }

        $lineas = array_values(array_filter(preg_split('/\r\n|\r|\n/', $texto), fn($l) => trim($l) !== ''));
        $cabecera = $lineas ? array_map('trim', explode('|', rtrim(array_shift($lineas), '|'))) : [];
        $filas = array_map(fn($l) => array_map('trim', explode('|', rtrim($l, '|'))), $lineas);

        return ['cabecera' => $cabecera, 'filas' => $filas];
    }

    // ---------------- HTTP ----------------

    private function get(string $ruta, array $query = []): array
    {
        return (array) $this->peticion($ruta, $query)->json();
    }

    /** GET con el token; si SUNAT responde 401 se renueva el token una vez */
    private function peticion(string $ruta, array $query = [], bool $reintento = true)
    {
        $r = Http::withToken($this->token())->acceptJson()->timeout(60)->get(self::URL_API . $ruta, $query);

        if ($r->status() === 401 && $reintento) {
            $this->token(true);
            return $this->peticion($ruta, $query, false);
        }
        if (!$r->successful()) {
            $errores = collect($r->json('errors') ?? [])->map(fn($e) => ($e['cod'] ?? '') . ' ' . ($e['msg'] ?? ''))->implode('; ');
            $msg = $errores ?: ($r->json('msg') ?: 'HTTP ' . $r->status());
            throw new \RuntimeException('SUNAT respondió con error: ' . trim($msg));
        }
        return $r;
    }
}
