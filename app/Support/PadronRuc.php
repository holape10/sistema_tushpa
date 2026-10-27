<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta propia de RUC y DNI con el padrón reducido del RUC que SUNAT publica cada día (datos públicos), igual que
 * consultas.holape.app: todos los contribuyentes con su razón social, estado, condición y domicilio fiscal.
 * El DNI se obtiene de los RUC 10 (personas naturales con negocio: "10" + DNI + dígito verificador).
 *
 * Vive en su propia base (conexión "padron"), fuera de las copias de seguridad. Se actualiza cada madrugada:
 * se descarga el ZIP, se lee por partes (sin descomprimirlo en disco) a una tabla nueva y al final se cambia por la
 * anterior de golpe, así las consultas nunca se quedan sin datos.
 */
class PadronRuc
{
    public const URL = 'http://www2.sunat.gob.pe/padron_reducido_ruc.zip';

    /** Un padrón completo tiene millones de RUC: con menos se asume una descarga incompleta y no se reemplaza */
    public const MINIMO = 1000000;

    private const LOTE = 2000;

    private const COLUMNAS = ['ruc', 'razon_social', 'estado', 'condicion', 'ubigeo', 'tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona',
        'numero', 'interior', 'lote', 'departamento', 'manzana', 'kilometro'];

    public static function db(): Connection
    {
        return DB::connection('padron');
    }

    /** ¿Ya hay padrón cargado? (se recuerda 10 minutos para no preguntarlo en cada consulta) */
    public static function disponible(): bool
    {
        return Cache::remember('padron-disponible', 600, function () {
            try {
                return Schema::connection('padron')->hasTable('contribuyentes');
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /** @return array{nombre:string, direccion:?string, estado:?string, condicion:?string, ubigeo:?string}|null */
    public static function ruc(string $ruc): ?array
    {
        if (! preg_match('/^\d{11}$/', $ruc) || ! self::disponible()) {
            return null;
        }
        try {
            $c = self::db()->table('contribuyentes')->where('ruc', $ruc)->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $c ? self::datos($c) : null;
    }

    /** Persona natural con RUC 10: "10" + DNI + dígito verificador */
    public static function dni(string $dni): ?array
    {
        if (! preg_match('/^\d{8}$/', $dni) || ! self::disponible()) {
            return null;
        }
        try {
            $c = self::db()->table('contribuyentes')->whereBetween('ruc', ['10'.$dni.'0', '10'.$dni.'9'])->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $c ? self::datos($c) + ['ruc' => $c->ruc] : null;
    }

    private static function datos(object $c): array
    {
        $ubigeo = preg_match('/^\d{6}$/', (string) $c->ubigeo) ? $c->ubigeo : null;

        return ['nombre' => trim($c->razon_social), 'direccion' => self::direccion($c, $ubigeo), 'estado' => $c->estado ?: null,
            'condicion' => $c->condicion ?: null, 'ubigeo' => $ubigeo];
    }

    /** "JR. RAUL PILLCO PEREZ NRO. 285 - LORETO - MAYNAS - IQUITOS" (SUNAT usa "-" para los campos vacíos) */
    public static function direccion(object $c, ?string $ubigeo): ?string
    {
        $v = fn ($x) => ($x = trim((string) $x)) !== '' && $x !== '-' ? $x : null;
        $partes = array_filter([
            trim(($v($c->tipo_via) ?? '').' '.($v($c->nombre_via) ?? '')),
            $v($c->numero) ? 'NRO. '.$v($c->numero) : null,
            $v($c->interior) ? 'INT. '.$v($c->interior) : null,
            $v($c->departamento) ? 'DPTO. '.$v($c->departamento) : null,
            $v($c->manzana) ? 'MZA. '.$v($c->manzana) : null,
            $v($c->lote) ? 'LOTE. '.$v($c->lote) : null,
            $v($c->kilometro) ? 'KM. '.$v($c->kilometro) : null,
            // En el padrón, "código de zona" trae el tipo (A.H., URB.) y "tipo de zona" el nombre: "A.H. JOSE CARLOS MARIATEGUI"
            trim(($v($c->codigo_zona) ?? '').' '.($v($c->tipo_zona) ?? '')),
        ]);
        if (! $partes) {
            return null;
        }
        $lugar = Ubigeo::partes($ubigeo);

        return implode(' ', $partes).($lugar ? ' - '.$lugar['departamento'].' - '.$lugar['provincia'].' - '.$lugar['distrito'] : '');
    }

    /** Fecha de la última carga y cuántos RUC tiene (null si nunca se cargó) */
    public static function info(): ?object
    {
        try {
            return Schema::connection('padron')->hasTable('padron_info') ? self::db()->table('padron_info')->first() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Descarga el padrón de SUNAT y lo carga. $limite (solo para pruebas) carga las primeras N filas.
     *
     * @return array{filas: int, segundos: int}
     */
    public static function actualizar(?callable $avance = null, ?int $limite = null): array
    {
        $inicio = time();
        self::crearBase();
        $zip = self::descargar();
        try {
            $filas = self::cargar($zip, $avance, $limite);
        } finally {
            @unlink($zip);
        }
        if (! $limite && $filas < self::MINIMO) {
            self::db()->statement('DROP TABLE IF EXISTS contribuyentes_nuevo');
            throw new \RuntimeException("El padrón descargado solo trae {$filas} RUC: parece incompleto, se mantiene el anterior.");
        }
        self::reemplazar($filas);

        return ['filas' => $filas, 'segundos' => time() - $inicio];
    }

    private static function crearBase(): void
    {
        $base = (string) config('database.connections.padron.database');
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
            throw new \InvalidArgumentException('Nombre de la base del padrón no válido.');
        }
        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private static function descargar(): string
    {
        $dir = storage_path('app/padron');
        @mkdir($dir, 0750, true);
        $zip = $dir.'/padron_reducido_ruc.zip';
        @unlink($zip);
        $r = Http::timeout(1800)->withOptions(['sink' => $zip, 'verify' => false])->get(self::URL);
        if (! $r->successful() || ! is_file($zip) || filesize($zip) < 1024 * 1024) {
            @unlink($zip);
            throw new \RuntimeException('No se pudo descargar el padrón de SUNAT (respuesta '.$r->status().').');
        }

        return $zip;
    }

    /** Lee el .txt dentro del ZIP línea por línea (RUC|NOMBRE|ESTADO|CONDICIÓN|UBIGEO|…, en ISO-8859-1) */
    private static function cargar(string $zip, ?callable $avance, ?int $limite): int
    {
        $archivo = new \ZipArchive;
        if ($archivo->open($zip) !== true || $archivo->numFiles < 1) {
            throw new \RuntimeException('El ZIP del padrón está dañado.');
        }
        $flujo = $archivo->getStream($archivo->getNameIndex(0));
        if (! $flujo) {
            throw new \RuntimeException('No se pudo leer el padrón dentro del ZIP.');
        }

        $db = self::db();
        $db->disableQueryLog();
        $db->statement('DROP TABLE IF EXISTS contribuyentes_nuevo');
        Schema::connection('padron')->create('contribuyentes_nuevo', function (Blueprint $t) {
            $t->char('ruc', 11)->primary();
            $t->string('razon_social', 255);
            $t->string('estado', 40)->nullable();
            $t->string('condicion', 40)->nullable();
            $t->string('ubigeo', 6)->nullable();
            foreach (['tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona', 'numero', 'interior', 'lote', 'departamento', 'manzana', 'kilometro'] as $c) {
                $t->string($c, $c === 'nombre_via' || $c === 'codigo_zona' ? 120 : 40)->nullable();
            }
        });

        fgets($flujo); // cabecera
        $lote = [];
        $filas = 0;
        // Se confirma cada 100 mil filas: mucho más rápido que una confirmación por cada lote
        $db->beginTransaction();
        while (($linea = fgets($flujo)) !== false) {
            $campos = explode('|', rtrim(mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1'), "\r\n"));
            if (count($campos) < 15 || ! preg_match('/^\d{11}$/', $campos[0])) {
                continue;
            }
            $fila = [];
            foreach (self::COLUMNAS as $i => $col) {
                $valor = trim($campos[$i]);
                $fila[$col] = mb_substr($valor, 0, $col === 'razon_social' ? 255 : ($col === 'nombre_via' || $col === 'codigo_zona' ? 120 : 40));
            }
            $lote[] = $fila;
            if (count($lote) >= self::LOTE) {
                $db->table('contribuyentes_nuevo')->insertOrIgnore($lote);
                $filas += count($lote);
                $lote = [];
                if ($filas % 100000 === 0) {
                    $db->commit();
                    $db->beginTransaction();
                }
                if ($avance && $filas % 500000 === 0) {
                    $avance($filas);
                }
            }
            if ($limite && $filas + count($lote) >= $limite) {
                break;
            }
        }
        if ($lote) {
            $db->table('contribuyentes_nuevo')->insertOrIgnore($lote);
            $filas += count($lote);
        }
        $db->commit();
        fclose($flujo);
        $archivo->close();

        return $filas;
    }

    /** La tabla nueva reemplaza a la anterior de una sola vez */
    private static function reemplazar(int $filas): void
    {
        $db = self::db();
        if (Schema::connection('padron')->hasTable('contribuyentes')) {
            $db->statement('DROP TABLE IF EXISTS contribuyentes_viejo');
            $db->statement('RENAME TABLE contribuyentes TO contribuyentes_viejo, contribuyentes_nuevo TO contribuyentes');
            $db->statement('DROP TABLE contribuyentes_viejo');
        } else {
            $db->statement('RENAME TABLE contribuyentes_nuevo TO contribuyentes');
        }
        if (! Schema::connection('padron')->hasTable('padron_info')) {
            Schema::connection('padron')->create('padron_info', function (Blueprint $t) {
                $t->dateTime('actualizado_el');
                $t->unsignedInteger('filas');
            });
        }
        $db->table('padron_info')->delete();
        $db->table('padron_info')->insert(['actualizado_el' => now(), 'filas' => $filas]);
        Cache::forget('padron-disponible');
    }
}
