<?php

namespace App\Support;

use App\Jobs\ProcesoEmpresa;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Procesos en segundo plano de la empresa: se registran aquí, la tarea se manda a la cola (o se hace en el momento si no
 * hay trabajador) y el usuario mira el avance en /procesos/{id}. Los archivos que dejan listos viven en
 * storage/app/procesos/{base}/ y se borran a las 24 horas (procesos:limpiar).
 */
class Procesos
{
    /** Horas que se guarda el archivo listo para descargar */
    public const HORAS = 24;

    /**
     * Registra el proceso y lanza su tarea. Devuelve el id del proceso.
     *
     * @param  callable(string $base, int $procesoId, int $usuarioId): ProcesoEmpresa  $tarea
     */
    public static function lanzar(User $user, string $tipo, string $descripcion, callable $tarea): int
    {
        $id = DB::table('procesos')->insertGetId(['tipo' => $tipo, 'descripcion' => mb_substr($descripcion, 0, 200), 'estado' => 'EN COLA',
            'IdUsuario' => $user->IdUsuario, 'created_at' => now(), 'updated_at' => now()]);
        Colas::despachar($tarea(Tenancy::baseActual(), $id, (int) $user->IdUsuario));

        return $id;
    }

    /** Carpeta de los archivos de la empresa */
    public static function carpeta(string $base): string
    {
        $dir = storage_path('app/procesos/'.preg_replace('/[^A-Za-z0-9_]/', '', $base));
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** Borra los archivos vencidos de todas las empresas. Devuelve cuántos borró. */
    public static function limpiar(): int
    {
        $n = 0;
        foreach (glob(storage_path('app/procesos/*/*')) ?: [] as $archivo) {
            if (is_file($archivo) && time() - filemtime($archivo) > self::HORAS * 3600) {
                @unlink($archivo) && $n++;
            }
        }

        return $n;
    }
}
