<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Copias de seguridad de la base de una empresa (o del sistema principal) con mysqldump, comprimidas (.sql.gz),
 * en config('sitio.respaldos'). Restaurar: primero se saca una copia de cómo está ahora, luego se carga el archivo
 * con el cliente mysql y al final se corren las migraciones (por si la copia es de una versión anterior).
 * Un archivo que intente tocar otra base (USE, CREATE/DROP DATABASE, usuarios o nombres de otras bases) se rechaza.
 */
class Respaldos
{
    /** Saca una copia; devuelve la ruta del .sql.gz. $motivo va en el nombre (ej. "antes-de-restaurar") */
    public static function crear(string $base, string $motivo = '', ?string $carpeta = null): string
    {
        self::validarBase($base);
        $carpeta ??= config('sitio.respaldos');
        @mkdir($carpeta, 0750, true);
        $sql = $carpeta.DIRECTORY_SEPARATOR.$base.'-'.now()->format('Ymd-His').($motivo ? '-'.$motivo : '').'.sql';

        $resultado = Process::env(['MYSQL_PWD' => (string) self::conexion()['password']])->timeout(1800)->run(array_merge(
            [config('sitio.mysqldump')], self::credenciales(),
            ['--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', '--result-file='.$sql, $base]
        ));
        if (! $resultado->successful() || ! is_file($sql) || filesize($sql) < 100 || ! str_contains((string) file_get_contents($sql, false, null, 0, 20000), 'CREATE TABLE')) {
            @unlink($sql);
            throw new \RuntimeException('No se pudo sacar la copia: '.trim($resultado->errorOutput() ?: 'mysqldump no generó el archivo.'));
        }

        // Comprimir por partes (las bases pueden pesar cientos de MB)
        $gz = gzopen($sql.'.gz', 'wb6');
        $entrada = fopen($sql, 'rb');
        while (! feof($entrada)) {
            gzwrite($gz, (string) fread($entrada, 1024 * 1024));
        }
        fclose($entrada);
        gzclose($gz);
        unlink($sql);

        return $sql.'.gz';
    }

    /**
     * Copias guardadas de una base, la más nueva primero.
     *
     * @return array<int, array{archivo: string, tamano: int, fecha: Carbon, motivo: string}>
     */
    public static function listar(string $base): array
    {
        self::validarBase($base);
        $lista = [];
        foreach (glob(config('sitio.respaldos').'/'.$base.'-*.sql.gz') ?: [] as $ruta) {
            if (preg_match('/^'.preg_quote($base, '/').'-(\d{8}-\d{6})(?:-([a-z-]+))?\.sql\.gz$/', basename($ruta), $m)) {
                $lista[] = ['archivo' => basename($ruta), 'tamano' => filesize($ruta),
                    'fecha' => Carbon::createFromFormat('Ymd-His', $m[1]), 'motivo' => str_replace('-', ' ', $m[2] ?? '')];
            }
        }
        usort($lista, fn ($a, $b) => $b['fecha'] <=> $a['fecha']);

        return $lista;
    }

    /** Ruta de una copia guardada de esa base (solo nombres generados por el sistema, nada de rutas) */
    public static function ruta(string $base, string $archivo): string
    {
        self::validarBase($base);
        if (! preg_match('/^'.preg_quote($base, '/').'-\d{8}-\d{6}(-[a-z-]+)?\.sql\.gz$/', $archivo) || ! is_file(config('sitio.respaldos').'/'.$archivo)) {
            throw new \RuntimeException('No se encontró esa copia.');
        }

        return config('sitio.respaldos').'/'.$archivo;
    }

    /**
     * Carga una copia (.sql o .sql.gz) en la base. Antes saca una copia de seguridad de cómo está ahora.
     * Devuelve el nombre de esa copia previa.
     */
    public static function restaurar(string $base, string $archivo): string
    {
        self::validarBase($base);
        $sql = self::descomprimir($archivo);
        try {
            self::revisar($sql, $base);
            $previa = self::crear($base, 'antes-de-restaurar');

            $entrada = fopen($sql, 'rb');
            $resultado = Process::env(['MYSQL_PWD' => (string) self::conexion()['password']])->timeout(3600)->input($entrada)
                ->run(array_merge([config('sitio.mysql')], self::credenciales(), ['--default-character-set=utf8mb4', $base]));
            is_resource($entrada) && fclose($entrada);
            if (! $resultado->successful()) {
                throw new \RuntimeException('MySQL no pudo cargar la copia (la copia previa quedó guardada como '.basename($previa).'): '
                    .mb_substr(trim($resultado->errorOutput()), 0, 300));
            }
        } finally {
            if ($sql !== $archivo) {
                @unlink($sql);
            }
        }

        // La copia puede ser de una versión anterior del sistema: se ponen al día sus tablas
        Tenancy::en($base, fn () => Artisan::call('migrate', ['--force' => true, '--database' => config('database.default')]));

        return basename($previa);
    }

    /** Un .sql.gz se descomprime a un archivo temporal; un .sql se usa tal cual */
    private static function descomprimir(string $archivo): string
    {
        $cabeza = (string) file_get_contents($archivo, false, null, 0, 2);
        if ($cabeza !== "\x1f\x8b") {
            return $archivo;
        }
        $destino = tempnam(sys_get_temp_dir(), 'restaurar');
        $gz = gzopen($archivo, 'rb');
        $salida = fopen($destino, 'wb');
        while (! gzeof($gz)) {
            fwrite($salida, (string) gzread($gz, 1024 * 1024));
        }
        gzclose($gz);
        fclose($salida);

        return $destino;
    }

    /** Debe ser una copia de tablas (mysqldump) que no toque otras bases, usuarios ni permisos */
    private static function revisar(string $sql, string $base): void
    {
        $inicio = (string) file_get_contents($sql, false, null, 0, 50000);
        if (! preg_match('/CREATE TABLE|INSERT INTO/i', $inicio)) {
            throw new \RuntimeException('El archivo no parece una copia de base de datos (.sql de mysqldump).');
        }
        $otras = collect(DB::connection('central')->select('SHOW DATABASES'))->map(fn ($r) => (string) array_values((array) $r)[0])
            ->reject(fn ($n) => $n === $base)->map(fn ($n) => preg_quote($n, '/'))->implode('|');
        $prohibido = '/^\s*(USE\s|CREATE\s+DATABASE|DROP\s+DATABASE|CREATE\s+USER|ALTER\s+USER|DROP\s+USER|GRANT\s|REVOKE\s|SET\s+PASSWORD|RENAME\s+USER)'
            .($otras ? '|`('.$otras.')`\s*\.|\b('.$otras.')\.`' : '').'/im';

        $f = fopen($sql, 'rb');
        $resto = '';
        while (! feof($f)) {
            $bloque = $resto.fread($f, 4 * 1024 * 1024);
            if (preg_match($prohibido, $bloque, $m)) {
                fclose($f);
                throw new \RuntimeException('El archivo intenta tocar otra base de datos o usuarios ("'.trim(mb_substr($m[0], 0, 60)).'"), así que no se cargó.');
            }
            $resto = substr($bloque, -300);
        }
        fclose($f);
    }

    /** Solo nombres simples de base (van como argumento de mysqldump/mysql) */
    private static function validarBase(string $base): void
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
            throw new \InvalidArgumentException('Nombre de base de datos no válido.');
        }
    }

    /** @return array<int, string> */
    private static function credenciales(): array
    {
        $con = self::conexion();

        return ['-h', (string) $con['host'], '-P', (string) $con['port'], '-u', (string) $con['username']];
    }

    private static function conexion(): array
    {
        return config('database.connections.central');
    }
}
