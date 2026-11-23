<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios delicados de una empresa que se avisan en el panel (lista de clientes con ⚠️) para revisarlos pronto:
 * editar la empresa o una sucursal (correlativos, certificado, envío a SUNAT…), importar productos (precios) y crear,
 * editar o eliminar usuarios. Se guardan en la base central al registrarse en la bitácora de la empresa; quedan hasta
 * que alguien del panel las descarta.
 */
class AlertasPanel
{
    /** Acciones de la bitácora que se avisan en el panel */
    public const VIGILADAS = [
        'config.empresa' => 'Editó la empresa',
        'config.sucursal' => 'Editó una sucursal',
        'producto.importar' => 'Importó productos (precios)',
        'usuario.crear' => 'Creó un usuario',
        'usuario.editar' => 'Editó un usuario',
        'usuario.eliminar' => 'Eliminó un usuario',
    ];

    /** Lo anota en el panel si es una acción vigilada y la empresa es un cliente del panel (nunca rompe lo que se estaba haciendo) */
    public static function avisar(string $accion, string $descripcion, ?string $usuario, ?string $ip, ?int $bitacoraId): void
    {
        if (! isset(self::VIGILADAS[$accion]) || ! Tenancy::activa()) {
            return;
        }
        try {
            $cliente = Tenancy::cliente() ?? Cliente::where('base_datos', Tenancy::baseActual())->first();
            if (! $cliente || ! Schema::connection('central')->hasTable('panel_alertas')) {
                return;
            }
            DB::connection('central')->table('panel_alertas')->insert([
                'cliente_id' => $cliente->id, 'bitacora_id' => $bitacoraId, 'fecha' => now(), 'accion' => $accion,
                'descripcion' => mb_substr($descripcion, 0, 255), 'usuario' => $usuario !== null ? mb_substr($usuario, 0, 120) : null,
                'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Alertas sin descartar de cada cliente.
     *
     * @param  iterable<int>  $clienteIds
     * @return array<int, int> [cliente_id => cantidad]
     */
    public static function pendientes(iterable $clienteIds): array
    {
        if (! Schema::connection('central')->hasTable('panel_alertas')) {
            return [];
        }

        return DB::connection('central')->table('panel_alertas')->whereIn('cliente_id', collect($clienteIds)->all())->whereNull('descartada_el')
            ->groupBy('cliente_id')->selectRaw('cliente_id, COUNT(*) AS n')->pluck('n', 'cliente_id')->map(fn ($n) => (int) $n)->all();
    }
}
