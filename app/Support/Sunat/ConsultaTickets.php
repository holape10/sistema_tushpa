<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los resúmenes diarios que quedaron ENVIADO o EN PROCESO se consultan solos (sunat:tickets, cada hora): pasada una hora
 * desde el envío se le pregunta a SUNAT por su ticket, así la empresa no tiene que estar tocando "Consultar ticket".
 * Se dejan de consultar a los 15 días (para entonces SUNAT ya respondió o hay que revisarlo a mano).
 */
class ConsultaTickets
{
    /** Tiempo mínimo desde el envío antes de consultar (SUNAT suele tardar) */
    public const ESPERA_MINUTOS = 60;

    public const DIAS_MAXIMO = 15;

    private const POR_VEZ = 50;

    /** Resúmenes por consultar de esta base */
    public static function pendientes()
    {
        return DB::table('resumenes')->whereNotNull('res_ticket')->where('res_ticket', '!=', '')
            ->whereIn('est_sunat', ['ENVIADO', 'EN PROCESO'])
            ->where('fecha_hora', '<=', now()->subMinutes(self::ESPERA_MINUTOS))
            ->where('fecha_hora', '>=', now()->subDays(self::DIAS_MAXIMO))
            ->orderBy('res_id')->limit(self::POR_VEZ)->get(['res_id', 'id_empresa_negocio']);
    }

    /**
     * Consulta los tickets pendientes de la base conectada.
     *
     * @return array{consultados: int, aceptados: int, rechazados: int, siguen: int}
     */
    public static function ejecutar(): array
    {
        $r = ['consultados' => 0, 'aceptados' => 0, 'rechazados' => 0, 'siguen' => 0];
        if (! Schema::hasTable('resumenes')) {
            return $r;
        }
        $pendientes = self::pendientes();
        if ($pendientes->isEmpty()) {
            return $r;
        }
        // Como lo hace el administrador de la empresa (lo que quede anotado sale a su nombre)
        if (! Auth::check()) {
            $admin = User::join('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')->where('ru.role_id', 2)->orderBy('users.IdUsuario')->first(['users.*']);
            if ($admin) {
                Auth::setUser($admin);
            }
        }
        $servicios = [];
        foreach ($pendientes as $p) {
            try {
                $servicios[$p->id_empresa_negocio] ??= (function () use ($p) {
                    $negocio = EmpresaNegocio::findOrFail($p->id_empresa_negocio);

                    return new SunatService(Empresa::findOrFail($negocio->IdEmpresa), $negocio);
                })();
                $estado = $servicios[$p->id_empresa_negocio]->consultarTicket((int) $p->res_id)['estado'] ?? null;
                $r['consultados']++;
                match ($estado) {
                    'ACEPTADO' => $r['aceptados']++,
                    'RECHAZADO' => $r['rechazados']++,
                    default => $r['siguen']++,
                };
            } catch (\Throwable $e) {
                $r['siguen']++;
                report($e);
            }
        }

        return $r;
    }
}
