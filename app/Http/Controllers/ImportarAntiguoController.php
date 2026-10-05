<?php
namespace App\Http\Controllers;

use App\Support\Antiguo\{CargadorSql, Importador};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Importar datos del sistema antiguo (solo Administrador), en tres pasos:
 *  1. Origen: subir el respaldo .sql (se carga en una base temporal antiguo_{RUC}_{fecha}) o elegir una base ya cargada.
 *  2. Revisar: qué trae, de qué sucursal antigua, qué secciones importar y el ZIP de imágenes.
 *  3. Importar: reporte de lo creado, actualizado y omitido.
 * Con multi-empresa activo solo se ven las bases temporales de esta empresa (nunca las de otros clientes).
 */
class ImportarAntiguoController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador importa datos del sistema antiguo.');
    }

    private function prefijo(): string
    {
        return 'antiguo_' . preg_replace('/\D/', '', (string) Auth::user()->IdEmpresa) . '_';
    }

    /** Bases con estructura del sistema antiguo que este usuario puede usar */
    private function basesDisponibles(): array
    {
        $actual = DB::connection()->getDatabaseName();
        $multiEmpresa = (bool) config('tenancy.dominio');
        $sistema = ['information_schema', 'mysql', 'performance_schema', 'sys', $actual, config('database.connections.central.database')];

        $bases = collect(DB::select('SHOW DATABASES'))->map(fn($r) => array_values((array) $r)[0])
            ->filter(fn($b) => !in_array($b, $sistema, true))
            ->filter(fn($b) => $multiEmpresa ? str_starts_with($b, $this->prefijo()) : true)
            ->values();
        if ($bases->isEmpty()) {
            return [];
        }

        // Estructura antigua: productos con pro_rel (el sistema nuevo no la tiene)
        $marcas = implode(',', array_fill(0, $bases->count(), '?'));
        return collect(DB::select("SELECT TABLE_SCHEMA AS bd FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA IN ({$marcas}) AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'pro_rel' ORDER BY TABLE_SCHEMA", $bases->all()))
            ->pluck('bd')->all();
    }

    private function baseValida(?string $bd): string
    {
        abort_unless($bd && in_array($bd, $this->basesDisponibles(), true), 404, 'Base de datos no disponible.');
        return $bd;
    }

    public function index()
    {
        $this->autorizar();
        return view('empresas.importar.index', ['paso' => 1, 'bases' => $this->basesDisponibles(), 'prefijo' => $this->prefijo()]);
    }

    /** Paso 1: subir el respaldo .sql y cargarlo en una base temporal */
    public function cargar(Request $request)
    {
        $this->autorizar();
        $request->validate(['archivo' => 'required|file|max:2097152'], [], ['archivo' => 'Respaldo .sql']);
        $nombre = strtolower($request->file('archivo')->getClientOriginalName());
        if (!str_ends_with($nombre, '.sql') && !str_ends_with($nombre, '.sql.gz')) {
            return back()->withErrors(['archivo' => 'Sube el respaldo en formato .sql (o .sql.gz). Si es .rar o .zip, descomprímelo primero.']);
        }

        set_time_limit(0);
        $ruta = $request->file('archivo')->storeAs('antiguo', uniqid() . (str_ends_with($nombre, '.gz') ? '.sql.gz' : '.sql'));
        $completa = storage_path('app/private/' . $ruta);
        if (!is_file($completa)) {
            $completa = storage_path('app/' . $ruta);
        }
        $bd = $this->prefijo() . now()->format('Ymd_His');

        try {
            $res = CargadorSql::cargar($completa, $bd);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['archivo' => 'No se pudo cargar el respaldo: ' . $e->getMessage()]);
        } finally {
            @unlink($completa);
        }

        if (!in_array('productos', $res['tablas'], true)) {
            DB::statement("DROP DATABASE IF EXISTS `{$bd}`");
            return back()->withErrors(['archivo' => 'El archivo no trae la tabla productos del sistema antiguo.'
                . ($res['errores'] ? ' Primer error: ' . $res['errores'][0] : '')]);
        }

        return redirect()->route('importar.revisar', ['bd' => $bd])
            ->with('success', "Respaldo cargado: {$res['ejecutadas']} sentencias de " . count($res['tablas']) . ' tablas.')
            ->with('errores_carga', $res['errores']);
    }

    /** Paso 2: qué trae la base antigua y opciones de importación */
    public function revisar(Request $request)
    {
        $this->autorizar();
        $bd = $this->baseValida($request->get('bd'));
        Importador::conectar($bd);
        $imp = new Importador();
        $sucursales = $imp->sucursales();
        $suc = (int) $request->get('sucursal', $sucursales[0]['id'] ?? 1);

        return view('empresas.importar.index', [
            'paso' => 2, 'bd' => $bd, 'resumen' => $imp->resumen(), 'sucursales' => $sucursales, 'suc' => $suc,
            'almacenes' => $imp->almacenes($suc), 'temporal' => str_starts_with($bd, 'antiguo_'),
        ]);
    }

    /** Paso 3: importar */
    public function ejecutar(Request $request)
    {
        $this->autorizar();
        $datos = $request->validate([
            'bd' => 'required|string', 'sucursal' => 'required|integer',
            'secciones' => 'required|array|min:1', 'secciones.*' => 'in:' . implode(',', array_keys(Importador::SECCIONES)),
            'dia0' => 'required|in:domingo,lunes', 'almacen' => 'nullable|integer',
            'imagenes' => 'nullable|file|mimes:zip|max:1048576',
        ], ['secciones.required' => 'Elige al menos una sección para importar.'], ['imagenes' => 'ZIP de imágenes']);
        $bd = $this->baseValida($datos['bd']);

        set_time_limit(0);
        $zip = $request->hasFile('imagenes') ? $request->file('imagenes')->getRealPath() : null;
        Importador::conectar($bd);
        $resultado = (new Importador())->importar(Auth::user(), (int) $datos['sucursal'], [
            'secciones' => $datos['secciones'], 'dia0' => $datos['dia0'], 'almacen' => $datos['almacen'] ?? null, 'imagenes' => $zip,
        ]);

        return view('empresas.importar.index', ['paso' => 3, 'bd' => $bd, 'resultado' => $resultado, 'temporal' => str_starts_with($bd, 'antiguo_')]);
    }

    /** Borra la base temporal cargada (solo las antiguo_*) */
    public function eliminar(Request $request)
    {
        $this->autorizar();
        $bd = $this->baseValida($request->get('bd'));
        abort_unless(str_starts_with($bd, 'antiguo_'), 403, 'Solo se eliminan las bases temporales cargadas desde aquí.');
        DB::statement("DROP DATABASE IF EXISTS `{$bd}`");
        return redirect()->route('importar.index')->with('success', "Base temporal {$bd} eliminada.");
    }
}
