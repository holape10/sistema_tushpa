<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\MonitorErrores;
use App\Support\TelegramCitas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimiento del certificado digital de cada empresa: se copia de su base a la central (para verlo en el panel) y se
 * avisa a tiempo (sin certificado vigente no se puede firmar ni enviar comprobantes a SUNAT):
 *  - al Telegram del dueño (todas) y al de cada usuario del panel (las empresas que ve: su marca o las que creó);
 *  - al Telegram de los administradores de la empresa que lo tienen vinculado;
 *  - y en el panel, una franja con las que vencen en 15 días o ya vencieron.
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
     * @return array<int, array{empresa: string, ruc: string, vence: Carbon, dias: int, telefono: ?string, cliente: Cliente}>
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
                    'dias' => $dias, 'telefono' => $cliente->contacto_telefono, 'cliente' => $cliente];
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

    /** El mensaje para el Telegram de los administradores de la empresa */
    public static function mensajeEmpresa(array $a): string
    {
        $cuando = match (true) {
            $a['dias'] < 0 => 'VENCIÓ hace '.abs($a['dias']).' día(s)', $a['dias'] === 0 => 'vence HOY', $a['dias'] === 1 => 'vence MAÑANA',
            default => 'vence en '.$a['dias'].' días',
        };

        return '🔐 Tu certificado digital '.$cuando.' ('.$a['vence']->format('d/m/Y').")\n\n"
            .($a['dias'] < 0 ? 'Ya no puedes enviar comprobantes a SUNAT.' : 'Sin certificado vigente no podrás enviar comprobantes a SUNAT.')
            ."\nRenuévalo y súbelo en Mantenimiento › Empresas.\n\n🏢 ".$a['empresa'].' · RUC '.$a['ruc'];
    }

    /**
     * Revisa y avisa por Telegram: al dueño, a los usuarios del panel (las empresas que ven) y a los administradores de
     * cada empresa. Devuelve cuántas empresas entraron en el aviso.
     */
    public static function avisar(): int
    {
        $avisos = self::revisar();
        if (! $avisos) {
            return 0;
        }
        MonitorErrores::avisar(self::mensaje($avisos));
        if (TelegramCitas::configurado()) {
            self::avisarPanel($avisos);
            foreach ($avisos as $a) {
                self::avisarEmpresa($a);
            }
        }

        return count($avisos);
    }

    /** A cada usuario del panel con Telegram vinculado, la lista de las empresas que ve (el chat del .env ya recibió todo) */
    private static function avisarPanel(array $avisos): void
    {
        $yaAvisado = (string) config('services.telegram.chat_id');
        foreach (Superadmin::where('activo', true)->get() as $u) {
            $suyos = array_values(array_filter($avisos, fn ($a) => VisibilidadPanel::puedeVer($u, $a['cliente'])));
            if (! $suyos) {
                continue;
            }
            foreach (TelegramCitas::chatsPanel((int) $u->id) as $chat) {
                if ($chat !== $yaAvisado) {
                    TelegramCitas::enviar($chat, self::mensaje($suyos));
                }
            }
        }
    }

    /** A los administradores de la empresa que tienen su Telegram vinculado */
    private static function avisarEmpresa(array $a): void
    {
        try {
            $chats = Tenancy::en($a['cliente']->base_datos, fn () => Schema::hasColumn('users', 'telegram_chat_id')
                ? DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('r.role_id', 2)
                    ->where('u.estusu', 1)->whereNotNull('u.telegram_chat_id')->where('u.telegram_chat_id', '!=', '')
                    ->distinct()->pluck('u.telegram_chat_id')->all()
                : []);
            foreach ($chats as $chat) {
                TelegramCitas::enviar((string) $chat, self::mensajeEmpresa($a));
            }
        } catch (\Throwable $e) {
            report($e);   // una empresa con problemas no detiene a las demás
        }
    }

    /**
     * Las empresas que ese usuario del panel ve con el certificado vencido o por vencer en 15 días (para la franja).
     *
     * @return array{vencidos: int, por_vencer: int}
     */
    public static function paraFranja(object $usuario): array
    {
        $base = fn () => VisibilidadPanel::filtrar(Cliente::query(), $usuario)->where('estado', 'ACTIVO')->whereNotNull('cert_vence');

        return ['vencidos' => $base()->where('cert_vence', '<', now()->toDateString())->count(),
            'por_vencer' => $base()->whereBetween('cert_vence', [now()->toDateString(), now()->addDays(15)->toDateString()])->count()];
    }
}
