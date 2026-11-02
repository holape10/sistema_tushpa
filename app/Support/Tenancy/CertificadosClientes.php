<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Support\MonitorErrores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vencimiento del certificado digital de cada empresa: se copia de su base a la central (para verlo en el panel) y el
 * dueño recibe por Telegram la lista de los que están por vencer, para avisarle a cada empresa a tiempo
 * (sin certificado vigente no se puede firmar ni enviar comprobantes a SUNAT).
 */
class CertificadosClientes
{
    /** Días antes de vencer en que se avisa (y cada día de la última semana y de la semana después de vencer) */
    public const AVISOS = [30, 15];

    /** Lee la fecha del certificado de la empresa y la guarda en la central */
    public static function sincronizar(Cliente $cliente): ?Carbon
    {
        $fecha = Tenancy::en($cliente->base_datos, fn () => DB::table('empresa')->where('IdEmpresa', $cliente->ruc)->value('fec_fin_cer')
            ?? DB::table('empresa')->value('fec_fin_cer'));
        $vence = $fecha ? Carbon::parse($fecha)->startOfDay() : null;
        $cliente->update(['cert_vence' => $vence?->toDateString()]);

        return $vence;
    }

    /** ¿Toca avisar hoy? A 30 y 15 días, cada día de la última semana y durante la semana después de vencer */
    public static function tocaAvisar(int $dias): bool
    {
        return in_array($dias, self::AVISOS, true) || ($dias <= 7 && $dias >= -7);
    }

    /**
     * Revisa todas las empresas activas y devuelve las que hay que avisar hoy
     *
     * @return array<int, array{empresa: string, ruc: string, vence: Carbon, dias: int, telefono: ?string}>
     */
    public static function revisar(): array
    {
        $avisos = [];
        foreach (Cliente::where('estado', 'ACTIVO')->get() as $cliente) {
            try {
                $vence = self::sincronizar($cliente);
            } catch (\Throwable) {
                continue;   // una base con problemas no detiene la revisión de las demás
            }
            if (! $vence) {
                continue;
            }
            $dias = (int) now()->startOfDay()->diffInDays($vence, false);
            if (self::tocaAvisar($dias)) {
                $avisos[] = ['empresa' => (string) ($cliente->nombre_comercial ?: $cliente->razon_social), 'ruc' => $cliente->ruc, 'vence' => $vence,
                    'dias' => $dias, 'telefono' => $cliente->contacto_telefono];
            }
        }
        usort($avisos, fn ($a, $b) => $a['dias'] <=> $b['dias']);

        return $avisos;
    }

    /** El mensaje de Telegram para el dueño */
    public static function mensaje(array $avisos): string
    {
        $lineas = array_map(function ($a) {
            $cuando = match (true) {
                $a['dias'] < 0 => '❌ VENCIÓ hace '.abs($a['dias']).' día(s)', $a['dias'] === 0 => '⚠️ vence HOY', $a['dias'] === 1 => '⚠️ vence MAÑANA',
                default => 'vence en '.$a['dias'].' días',
            };

            return "• {$a['empresa']} (RUC {$a['ruc']}): {$cuando} ({$a['vence']->format('d/m/Y')})".($a['telefono'] ? " · 📞 {$a['telefono']}" : '');
        }, $avisos);

        return "🔐 Certificados digitales por vencer\n\n".implode("\n", $lineas)
            ."\n\nSin certificado vigente no pueden enviar comprobantes a SUNAT. Avísales para que lo renueven y lo suban en Editar empresa.";
    }

    /** Revisa y avisa por Telegram (si hay algo que avisar). Devuelve cuántas empresas entraron en el aviso. */
    public static function avisar(): int
    {
        $avisos = self::revisar();
        if ($avisos) {
            MonitorErrores::avisar(self::mensaje($avisos));
        }

        return count($avisos);
    }
}
