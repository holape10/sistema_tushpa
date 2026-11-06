<?php

namespace App\Http\Controllers;

use App\Support\Colas;
use App\Support\Procesos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Avance de un proceso en segundo plano y la descarga de lo que deja listo (solo quien lo pidió o el administrador) */
class ProcesoController extends Controller
{
    public function show(int $id): View
    {
        return view('empresas.procesos.show', ['proceso' => $this->proceso($id)]);
    }

    public function estado(int $id): JsonResponse
    {
        $p = $this->proceso($id);
        // Esperando hace rato y el trabajador ya no responde: que se vuelva a pedir (si luego la toma, ya no la hace)
        if ($p->estado === 'EN COLA' && strtotime($p->created_at) < time() - 300 && ! Colas::vivo()) {
            DB::table('procesos')->where('id', $id)->where('estado', 'EN COLA')->update(['estado' => 'ERROR', 'terminado_at' => now(), 'updated_at' => now(),
                'mensaje' => 'El trabajador de colas se detuvo antes de empezar. Vuelve a pedirlo: esta vez se hará en el momento.']);
            $p = $this->proceso($id);
        }

        return response()->json(['estado' => $p->estado, 'mensaje' => $p->mensaje, 'descargable' => $this->archivo($p) !== null]);
    }

    public function descargar(int $id): BinaryFileResponse
    {
        $p = $this->proceso($id);
        $archivo = $this->archivo($p);
        abort_unless($archivo, 404, 'El archivo ya no está disponible (se guarda '.Procesos::HORAS.' horas). Vuelve a pedirlo.');

        return response()->download($archivo, $p->nombre_archivo ?: basename($archivo));
    }

    private function proceso(int $id): object
    {
        $p = DB::table('procesos')->where('id', $id)->first();
        abort_unless($p && ((int) $p->IdUsuario === (int) Auth::id() || Auth::user()->esAdmin()), 404);

        return $p;
    }

    /** El archivo listo, solo si está dentro de la carpeta de esta empresa */
    private function archivo(object $p): ?string
    {
        if ($p->estado !== 'LISTO' || ! $p->archivo) {
            return null;
        }
        $real = realpath($p->archivo);
        $carpeta = realpath(Procesos::carpeta(Tenancy::baseActual()));

        return $real && $carpeta && str_starts_with($real, $carpeta.DIRECTORY_SEPARATOR) && is_file($real) ? $real : null;
    }
}
