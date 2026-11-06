<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tarea en segundo plano de una empresa. Lleva anotada la base de su empresa y quién la pidió: el trabajador (que
 * atiende a todas las empresas) entra a esa base, actúa como ese usuario (para permisos y bitácora) y al terminar sale.
 * El avance queda en la tabla procesos de la empresa: EN COLA → EN PROCESO → LISTO (con su archivo) o ERROR.
 *
 * No se reintenta sola: algunas tareas no se deben repetir (una copia a medias se vuelve a pedir desde la pantalla).
 */
abstract class ProcesoEmpresa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** Segundos máximos de una tarea (menos que retry_after de la cola) */
    public int $timeout = 3500;

    public function __construct(public string $base, public int $procesoId, public int $usuarioId) {}

    /**
     * El trabajo de verdad. Devuelve el archivo que deja listo (ruta y nombre para descargar) o null, y un mensaje.
     *
     * @return array{archivo?: ?string, nombre?: ?string, mensaje?: ?string}
     */
    abstract protected function ejecutar(object $proceso): array;

    public function handle(): void
    {
        Tenancy::en($this->base, function () {
            $proceso = DB::table('procesos')->where('id', $this->procesoId)->first();
            if (! $proceso || $proceso->estado !== 'EN COLA') {
                return;   // ya se hizo (o se canceló)
            }
            DB::table('procesos')->where('id', $this->procesoId)->update(['estado' => 'EN PROCESO', 'iniciado_at' => now(), 'updated_at' => now()]);
            $guard = Auth::guard('web');
            $anterior = $guard->user();
            if ($usuario = User::find($this->usuarioId)) {
                $guard->setUser($usuario);
            }
            try {
                $r = $this->ejecutar($proceso);
                DB::table('procesos')->where('id', $this->procesoId)->update([
                    'estado' => 'LISTO', 'archivo' => $r['archivo'] ?? null, 'nombre_archivo' => isset($r['nombre']) ? mb_substr($r['nombre'], 0, 150) : null,
                    'mensaje' => isset($r['mensaje']) ? mb_substr($r['mensaje'], 0, 500) : null, 'terminado_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e);
                $this->marcarError($e);
            } finally {
                // El trabajador sigue con otras empresas: no se queda con este usuario
                $anterior ? $guard->setUser($anterior) : $guard->forgetUser();
            }
        });
    }

    /** Si la tarea se cae fuera de handle (se pasó del tiempo, el trabajador se detuvo…) */
    public function failed(?\Throwable $e): void
    {
        rescue(fn () => Tenancy::en($this->base, fn () => $this->marcarError($e)), null, false);
    }

    private function marcarError(?\Throwable $e): void
    {
        $mensaje = $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo terminar. Inténtalo otra vez o escríbenos a soporte.';
        DB::table('procesos')->where('id', $this->procesoId)->whereIn('estado', ['EN COLA', 'EN PROCESO'])
            ->update(['estado' => 'ERROR', 'mensaje' => mb_substr($mensaje, 0, 500), 'terminado_at' => now(), 'updated_at' => now()]);
    }
}
