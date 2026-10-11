<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Elimina la base de una empresa (p. ej. no pagó al terminar la prueba). Antes saca un respaldo comprimido con
 * mysqldump en config('sitio.respaldos'); si el respaldo falla, no borra nada. El registro del cliente queda como
 * ELIMINADO (así no vuelve a usar la prueba gratis con el mismo RUC) y se puede volver a crear desde el panel.
 */
class Eliminador
{
    public static function eliminar(Cliente $cliente): string
    {
        if ($cliente->activo()) {
            throw new \RuntimeException('Primero suspende la empresa: solo se eliminan empresas suspendidas.');
        }
        $base = Tenancy::nombreBase($cliente->ruc);
        if ($base !== $cliente->base_datos) {
            throw new \RuntimeException('La base registrada no coincide con el RUC; revísala a mano.');
        }

        $respaldo = self::respaldar($base);
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$base}`");
        $cliente->update(['estado' => 'ELIMINADO', 'motivo_suspension' => 'Eliminada el '.now()->format('d/m/Y').' (respaldo: '.basename($respaldo).')']);

        return $respaldo;
    }

    /** Respaldo .sql.gz de la base; devuelve la ruta. Lanza excepción si no se pudo */
    public static function respaldar(string $base): string
    {
        $carpeta = config('sitio.respaldos');
        @mkdir($carpeta, 0750, true);
        $sql = $carpeta.DIRECTORY_SEPARATOR.$base.'-'.now()->format('Ymd-His').'.sql';
        $con = config('database.connections.central');

        $resultado = Process::env(['MYSQL_PWD' => (string) $con['password']])->timeout(600)->run([
            config('sitio.mysqldump'), '-h', (string) $con['host'], '-P', (string) $con['port'], '-u', (string) $con['username'],
            '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', '--result-file='.$sql, $base,
        ]);
        if (! $resultado->successful() || ! is_file($sql) || filesize($sql) < 100 || ! str_contains((string) file_get_contents($sql, false, null, 0, 20000), 'CREATE TABLE')) {
            @unlink($sql);
            throw new \RuntimeException('No se pudo sacar el respaldo, así que no se eliminó nada: '.trim($resultado->errorOutput() ?: 'mysqldump no generó el archivo.'));
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
}
