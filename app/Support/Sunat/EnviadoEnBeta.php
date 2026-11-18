<?php

namespace App\Support\Sunat;

use App\Support\ConsultaCpe;
use App\Support\ConsultaPeru;
use Illuminate\Support\Facades\DB;

/**
 * Comprobante que se envió con la empresa en BETA (pruebas): en el sistema figura aceptado, pero SUNAT (producción) no
 * lo tiene. Se pregunta a SUNAT y, solo si no lo tiene, vuelve a pendiente para enviarlo en producción. Si SUNAT sí lo
 * tiene, no se toca.
 */
class EnviadoEnBeta
{
    /** @throws \RuntimeException con el motivo si no se devolvió a pendiente */
    public static function devolverAPendiente(object $cab, string $quien): void
    {
        if ($cab->ccabaj) {
            throw new \RuntimeException('está anulado o descartado');
        }
        $enviado = in_array($cab->est_sunat, ['ACEPTADO', 'OBSERVADO', 'EN RESUMEN', 'EN PROCESO'], true) || (int) $cab->enviado;
        if (! $enviado) {
            throw new \RuntimeException('no figura como enviado: ya está pendiente, envíalo nomás');
        }
        $v = ConsultaPeru::estadoCpe((string) $cab->IdEmpresa, $cab->tdocod, $cab->serdoc, (string) (int) $cab->numdoc, (string) $cab->ccafem, (float) $cab->ccaitv);
        if (! $v) {
            throw new \RuntimeException('no se pudo consultar a SUNAT. Intenta en unos minutos.');
        }
        if (! str_contains($v['estado'], 'NO EXISTE')) {
            throw new \RuntimeException("SUNAT dice {$v['estado']}: sí lo tiene (se envió en producción), no se toca.");
        }
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
            'est_sunat' => 'PENDIENTE', 'enviado' => 0, 'ccasunrescod' => null, 'res_id' => null,
            'ccadessun' => mb_substr('Se había enviado en BETA (pruebas): SUNAT no lo tiene. Volvió a pendiente el '.now()->format('d/m/Y H:i')." ({$quien}): envíalo en producción.", 0, 500),
        ]);
        // La constancia de BETA no vale: al enviarlo de nuevo se guarda la de producción
        @unlink(ConsultaCpe::archivo($cab, 'cdr'));
    }
}
