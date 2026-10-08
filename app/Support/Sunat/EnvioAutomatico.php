<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Empresa con "Envío automático" (empresa.tipo_envio = 1): cada factura, boleta o nota se manda a SUNAT apenas se emite.
 * Se envía después de responder a la caja (no la hace esperar). Si SUNAT no responde, queda PENDIENTE para el envío manual.
 */
class EnvioAutomatico
{
    private const TIPOS = ['01', '03', '07', '08'];

    public static function programar(int $cabId): void
    {
        // Solo cuando la venta ya quedó guardada (si la transacción falla, no se envía nada)
        DB::afterCommit(function () use ($cabId) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['tdocod', 'IdEmpresa']);
            $empresa = $cab ? Empresa::find($cab->IdEmpresa) : null;
            if (! $cab || ! in_array($cab->tdocod, self::TIPOS, true) || ! $empresa
                || (int) $empresa->tipo_envio !== 1 || (string) $empresa->tip_env_fac_id === '02') {
                return;
            }
            $user = Auth::user();
            if (! $user) {
                return;
            }
            app()->terminating(function () use ($cabId, $user) {
                try {
                    SunatService::paraUsuario($user)->enviarComprobante($cabId);
                } catch (\Throwable $e) {
                    Log::warning("Envío automático a SUNAT del comprobante {$cabId}: ".$e->getMessage());
                }
            });
        });
    }
}
