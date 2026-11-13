<?php

namespace App\Http\Controllers;

use App\Jobs\CargarRespaldoAntiguo;
use App\Jobs\ImportarSistemaAntiguo;
use App\Support\Antiguo\Importador;
use App\Support\Procesos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Importar datos del sistema antiguo (solo Administrador), en tres pasos:
 *  1. Origen: subir el respaldo .sql (se carga en una base temporal antiguo_{RUC}_{fecha}) o elegir una base ya cargada.
 *  2. Revisar: qué trae, a qué sucursal nueva va cada sucursal antigua, qué secciones importar y el ZIP de imágenes.
 *  3. Importar: todas las sucursales elegidas a la vez (catálogos y usuarios primero, luego el historial) y el reporte.
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
        return 'antiguo_'.preg_replace('/\D/', '', (string) Auth::user()->IdEmpresa).'_';
    }

    /** Bases con estructura del sistema antiguo que este usuario puede usar */
    private function basesDisponibles(): array
    {
        $actual = DB::connection()->getDatabaseName();
        $multiEmpresa = (bool) config('tenancy.dominio');
        $sistema = ['information_schema', 'mysql', 'performance_schema', 'sys', $actual, config('database.connections.central.database')];

        $bases = collect(DB::select('SHOW DATABASES'))->map(fn ($r) => array_values((array) $r)[0])
            ->filter(fn ($b) => ! in_array($b, $sistema, true))
            ->filter(fn ($b) => $multiEmpresa ? str_starts_with($b, $this->prefijo()) : true)
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
        if (! str_ends_with($nombre, '.sql') && ! str_ends_with($nombre, '.sql.gz') && ! str_ends_with($nombre, '.zip')) {
            return back()->withErrors(['archivo' => 'Sube el respaldo en formato .sql, .zip o .sql.gz. Si es .rar, descomprímelo y vuelve a comprimirlo en .zip.']);
        }

        set_time_limit(0);
        $ruta = $request->file('archivo')->storeAs('antiguo', uniqid().(str_ends_with($nombre, '.gz') ? '.sql.gz' : (str_ends_with($nombre, '.zip') ? '.zip' : '.sql')));
        $completa = storage_path('app/private/'.$ruta);
        if (! is_file($completa)) {
            $completa = storage_path('app/'.$ruta);
        }
        $bd = $this->prefijo().now()->format('Ymd_His');

        // En segundo plano: un respaldo grande (100 MB o más) tarda minutos en cargarse y la página se cortaba
        $id = Procesos::lanzar(Auth::user(), 'importar_carga', 'Cargar el respaldo del sistema antiguo',
            fn (string $base, int $proceso, int $usuario) => new CargarRespaldoAntiguo($base, $proceso, $usuario, $completa, $bd));

        return redirect()->route('procesos.show', $id);
    }

    /** Respaldo ya cargado en su base temporal: sigue al paso 2 */
    public function cargado(int $id)
    {
        $this->autorizar();
        $proceso = DB::table('procesos')->where('id', $id)->where('tipo', 'importar_carga')->first();
        $archivo = $proceso && $proceso->estado === 'LISTO' && $proceso->archivo ? realpath($proceso->archivo) : false;
        $carpeta = realpath(Procesos::carpeta(Tenancy::baseActual()));
        abort_unless($archivo && $carpeta && str_starts_with($archivo, $carpeta.DIRECTORY_SEPARATOR) && is_file($archivo), 404);
        $datos = json_decode((string) file_get_contents($archivo), true) ?: [];

        return redirect()->route('importar.revisar', ['bd' => $this->baseValida($datos['bd'] ?? null)])
            ->with('success', "Respaldo cargado: {$datos['ejecutadas']} sentencias de {$datos['tablas']} tablas.")
            ->with('errores_carga', $datos['errores'] ?? []);
    }

    /** Paso 2: qué trae la base antigua y opciones de importación */
    public function revisar(Request $request)
    {
        $this->autorizar();
        $bd = $this->baseValida($request->get('bd'));
        Importador::conectar($bd);
        $imp = new Importador;
        $sucursales = $imp->sucursales();
        $suc = (int) ($sucursales[0]['id'] ?? 1);
        // Sucursales de esta empresa: la actual primero; cada sucursal antigua se propone para una nueva, en orden
        $nuevas = $this->sucursalesNuevas();

        return view('empresas.importar.index', [
            'paso' => 2, 'bd' => $bd, 'resumen' => $imp->resumen(), 'sucursales' => $sucursales, 'suc' => $suc,
            'nuevas' => $nuevas, 'temporal' => str_starts_with($bd, 'antiguo_'),
            'mismoRuc' => $imp->mismoRuc(Auth::user(), $suc),
        ]);
    }

    /** Paso 3: importar */
    public function ejecutar(Request $request)
    {
        $this->autorizar();
        $datos = $request->validate([
            'bd' => 'required|string',
            'mapa' => 'required|array', 'mapa.*' => 'nullable|integer',
            'secciones' => 'required|array|min:1', 'secciones.*' => 'in:'.implode(',', array_keys(Importador::SECCIONES)),
            'dia0' => 'required|in:domingo,lunes',
            'imagenes' => 'nullable|file|mimes:zip|max:1048576',
        ], ['secciones.required' => 'Elige al menos una sección para importar.'], ['imagenes' => 'ZIP de imágenes']);
        $bd = $this->baseValida($datos['bd']);

        set_time_limit(0);   // por si se hace en el momento (sin trabajador de colas)
        // El ZIP subido se borra al terminar la petición: se copia para la tarea en segundo plano
        $zip = null;
        if ($request->hasFile('imagenes')) {
            $zip = Procesos::carpeta(Tenancy::baseActual()).'/imagenes-'.uniqid().'.zip';
            copy($request->file('imagenes')->getRealPath(), $zip);
        }
        Importador::conectar($bd);
        // Solo sucursales de esta empresa; "no importar" llega vacío. Dos sucursales antiguas no pueden ir a la misma nueva.
        $nuevas = $this->sucursalesNuevas();
        $mapa = array_filter(array_map('intval', $datos['mapa']));
        if (! $mapa || array_diff($mapa, array_keys($nuevas)) || count($mapa) !== count(array_unique($mapa))) {
            return back()->withErrors(['mapa' => 'Elige a qué sucursal va cada sucursal antigua (sin repetir ninguna).']);
        }
        // En segundo plano: con mucho historial toma varios minutos y la página se cortaba a la mitad (solo productos y clientes)
        $opciones = ['secciones' => $datos['secciones'], 'dia0' => $datos['dia0'], 'imagenes' => $zip];
        $id = Procesos::lanzar(Auth::user(), 'importar', 'Importar el sistema antiguo',
            fn (string $base, int $proceso, int $usuario) => new ImportarSistemaAntiguo($base, $proceso, $usuario, $bd, $mapa, $opciones));

        return redirect()->route('procesos.show', $id);
    }

    /** Paso 3: lo que dejó la importación (creados por sección y los avisos de cada sucursal) */
    public function resultado(int $id)
    {
        $this->autorizar();
        $proceso = DB::table('procesos')->where('id', $id)->where('tipo', 'importar')->first();
        $archivo = $proceso && $proceso->estado === 'LISTO' && $proceso->archivo ? realpath($proceso->archivo) : false;
        $carpeta = realpath(Procesos::carpeta(Tenancy::baseActual()));
        abort_unless($archivo && $carpeta && str_starts_with($archivo, $carpeta.DIRECTORY_SEPARATOR) && is_file($archivo), 404,
            'El resultado de la importación ya no está disponible (se guarda '.Procesos::HORAS.' horas).');
        $datos = json_decode((string) file_get_contents($archivo), true) ?: [];

        return view('empresas.importar.index', ['paso' => 3, 'bd' => (string) ($datos['bd'] ?? ''), 'resultado' => $datos['resultado'] ?? [],
            'nuevas' => $this->sucursalesNuevas(), 'nombresAntiguas' => collect($datos['nombres'] ?? []),
            'temporal' => str_starts_with((string) ($datos['bd'] ?? ''), 'antiguo_')]);
    }

    /** @return array<int, string> id => nombre de las sucursales de la empresa (la del usuario primero) */
    private function sucursalesNuevas(): array
    {
        $user = Auth::user();

        return DB::table('empresa_negocios')->where('IdEmpresa', $user->IdEmpresa)
            ->orderByRaw('id_empresa_negocio = ? DESC', [$user->id_empresa_negocio])->orderBy('id_empresa_negocio')
            ->get(['id_empresa_negocio', 'nombre_comercial', 'tipo_negocio'])
            ->mapWithKeys(fn ($s) => [(int) $s->id_empresa_negocio => trim((string) ($s->nombre_comercial ?: $s->tipo_negocio)) ?: 'Sucursal '.$s->id_empresa_negocio])
            ->all();
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
