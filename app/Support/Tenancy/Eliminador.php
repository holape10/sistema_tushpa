<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;

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
        try {
            return Respaldos::crear($base, 'eliminada');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('No se pudo sacar el respaldo, así que no se eliminó nada. '.$e->getMessage());
        }
    }
}
