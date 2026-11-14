<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Excel;
use App\Support\ExploradorBd;
use App\Support\Servidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/** Explorador de bases de SOLO LECTURA (solo el dueño): tablas, filas con búsqueda y consultas SELECT con Excel */
class BaseDatosController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema usa el explorador de bases.');
    }

    public function index(Request $request)
    {
        $this->autorizar();

        return $this->pantalla($request);
    }

    /** Corre una consulta de lectura; con «excel» la descarga (hasta 5 000 filas) */
    public function consulta(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['base' => 'required|string|max:64', 'sql' => 'required|string|max:5000']);
        $base = ExploradorBd::base($d['base']);
        abort_unless($base, 404, 'Esa base no existe.');
        // Queda anotado quién consultó qué (solo lectura, pero es la base de un cliente)
        Log::info('Explorador de bases', ['usuario' => Auth::guard('superadmin')->user()->email, 'base' => $base, 'sql' => mb_substr($d['sql'], 0, 1000)]);

        try {
            $resultado = ExploradorBd::consultar($base, $d['sql'], $request->boolean('excel') ? ExploradorBd::FILAS_EXCEL : ExploradorBd::FILAS_PANTALLA);
        } catch (\InvalidArgumentException $e) {
            return $this->pantalla($request, ['error' => $e->getMessage()]);
        }
        if ($request->boolean('excel')) {
            $titulos = array_keys($resultado['filas'][0] ?? ['(sin filas)' => '']);
            $filas = array_map(fn ($f) => array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_values($f)), $resultado['filas']);
            $ruta = (new Excel)->hoja('Consulta', $titulos, $filas)->guardar();

            return response()->download($ruta, 'consulta_'.$base.'_'.now()->format('Ymd_His').'.xlsx')->deleteFileAfterSend();
        }

        return $this->pantalla($request, ['resultado' => $resultado]);
    }

    private function pantalla(Request $request, array $extra = [])
    {
        $base = ExploradorBd::base($request->input('base'));
        $tabla = $base ? (string) $request->input('tabla', '') : '';
        $vista = null;
        $errorTabla = null;
        if ($base && $tabla !== '' && ! isset($extra['resultado']) && ! isset($extra['error'])) {
            try {
                $vista = ExploradorBd::ver($base, $tabla, $request->input('q'), (int) $request->input('pagina', 1));
            } catch (\Throwable $e) {
                $errorTabla = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No se pudo leer la tabla (¿demoró más de 8 segundos? prueba con una búsqueda más precisa).';
            }
        }

        return view('admin.basedatos', $extra + [
            'bases' => Servidor::bases(), 'base' => $base, 'tablas' => $base ? ExploradorBd::tablas($base) : collect(),
            'tabla' => $tabla, 'vista' => $vista, 'errorTabla' => $errorTabla, 'q' => (string) $request->input('q', ''),
            'pagina' => max(1, (int) $request->input('pagina', 1)), 'sql' => (string) $request->input('sql', ''),
            'resultado' => $extra['resultado'] ?? null, 'error' => $extra['error'] ?? null,
        ]);
    }
}
