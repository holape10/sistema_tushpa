<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        $base = Tenancy::nombreBase($cliente->ruc, $cliente->prefijoBd());
        if ($base !== $cliente->base_datos) {
            throw new \RuntimeException('La base registrada no coincide con el RUC; revísala a mano.');
        }

        $respaldo = self::respaldar($base);
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$base}`");
        $cliente->update(['estado' => 'ELIMINADO', 'motivo_suspension' => 'Eliminada el '.now()->format('d/m/Y').' (respaldo: '.basename($respaldo).')']);

        return $respaldo;
    }

    /**
     * Borra todo de una empresa, sin dejar rastro en el panel: su base, sus respaldos automáticos, sus contratos (con
     * el DNI y la firma), sus tickets de soporte (con sus fotos), sus pagos, su consumo de XML y su registro. Solo una
     * empresa suspendida o ya eliminada. Ese RUC podrá volver a registrarse (y a usar la prueba gratis).
     *
     * @param  bool  $guardarCopia  deja una copia de su base en el servidor (por si acaso)
     * @return string|null la copia guardada
     */
    public static function eliminarDefinitivo(Cliente $cliente, bool $guardarCopia): ?string
    {
        if ($cliente->activo()) {
            throw new \RuntimeException('Primero suspende la empresa: solo se eliminan empresas suspendidas.');
        }
        $base = (string) $cliente->base_datos;
        if (Tenancy::nombreBase($cliente->ruc, $cliente->prefijoBd()) !== $base) {
            throw new \RuntimeException('La base registrada no coincide con el RUC; revísala a mano.');
        }
        $central = DB::connection('central');
        $existe = (bool) $central->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$base]);

        // 1. Su base (con su copia si se pidió) y sus copias automáticas
        $copia = $existe && $guardarCopia ? self::respaldar($base) : null;
        if ($existe) {
            $central->statement("DROP DATABASE `{$base}`");
        }
        foreach (Respaldos::listar($base) as $r) {
            if (! $copia || $r['archivo'] !== basename($copia)) {
                @unlink(config('sitio.respaldos').'/'.$r['archivo']);
            }
        }

        // 2. Lo que el panel guarda de ella (con sus archivos) y su registro
        $tabla = fn (string $t) => Schema::connection('central')->hasTable($t);
        $central->transaction(function () use ($central, $cliente, $tabla) {
            if ($tabla('contratos')) {
                foreach ($central->table('contratos')->where('cliente_id', $cliente->id)->pluck('id') as $id) {
                    Storage::disk('local')->deleteDirectory(config('contrato.carpeta').'/'.$id);
                }
                $central->table('contratos')->where('cliente_id', $cliente->id)->delete();
            }
            if ($tabla('soporte_tickets')) {
                $tickets = $central->table('soporte_tickets')->where('cliente_id', $cliente->id)->pluck('id');
                foreach ($tickets as $id) {
                    Storage::disk('local')->deleteDirectory('soporte/'.$id);
                }
                if ($tabla('soporte_mensajes')) {
                    $central->table('soporte_mensajes')->whereIn('ticket_id', $tickets->all() ?: [0])->delete();
                }
                $central->table('soporte_tickets')->where('cliente_id', $cliente->id)->delete();
            }
            foreach (['pagos_online', 'consumo_xml'] as $t) {
                if ($tabla($t)) {
                    $central->table($t)->where('cliente_id', $cliente->id)->delete();
                }
            }
            $central->table('clientes')->where('id', $cliente->id)->delete();
        });

        return $copia;
    }

    /** Respaldo .sql.gz de la base; devuelve la ruta. Lanza excepción si no se pudo */
    public static function respaldar(string $base): string
    {
        try {
            return Respaldos::crear($base, 'eliminada');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('No se pudo sacar el respaldo, así que no se eliminó nada. '.$e->getMessage());
        }
    }
}
