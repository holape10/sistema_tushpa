<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Asistente\AsistenteSql;
use App\Support\EdicionBd;
use App\Support\Excel;
use App\Support\ExploradorBd;
use App\Support\Servidor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Explorador de bases (solo el dueño): tablas, filas con búsqueda, consultas SELECT con Excel y un asistente IA que
 * sugiere consultas. Además, con una SEGUNDA clave (aparte de la del panel), un modo edición de 15 minutos para
 * cambiar datos con frenos (ver App\Support\EdicionBd): primero se prueba cuántas filas cambia y luego se ejecuta.
 */
class BaseDatosController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema usa el explorador de bases.');
    }

    private function yo(): object
    {
        return Auth::guard('superadmin')->user();
    }

    /** ¿Está abierto el modo edición? (lo abre la segunda clave por unos minutos, solo para este usuario) */
    private function edicionActiva(Request $request): bool
    {
        $e = $request->session()->get('bd_edicion');

        return is_array($e) && (int) ($e['id'] ?? 0) === (int) $this->yo()->id && (int) ($e['hasta'] ?? 0) > now()->timestamp;
    }

    /** Firma de una orden ya probada: solo se ejecuta exactamente lo que se probó, en esa base */
    private function firma(string $base, string $sql): string
    {
        return hash_hmac('sha256', $this->yo()->id.'|'.$base.'|'.trim($sql), (string) config('app.key'));
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
        Log::info('Explorador de bases', ['usuario' => $this->yo()->email, 'base' => $base, 'sql' => mb_substr($d['sql'], 0, 1000)]);

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

    /** El asistente IA sugiere la consulta (no ejecuta nada) */
    public function ia(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['base' => 'required|string|max:64', 'pregunta' => 'required|string|max:2000']);
        abort_unless(ExploradorBd::base($d['base']), 404, 'Esa base no existe.');
        try {
            $r = AsistenteSql::sugerir($d['base'], (string) $this->yo()->email, $d['pregunta']);
        } catch (\RuntimeException $e) {
            return $this->pantalla($request, ['errorIa' => $e->getMessage(), 'pregunta' => $d['pregunta']]);
        }

        return $this->pantalla($request, ['respuestaIa' => $r['respuesta'], 'pregunta' => $d['pregunta']]);
    }

    /** Crea o cambia la segunda clave (pide la clave con que entró al panel, o la segunda clave actual) */
    public function clave(Request $request): RedirectResponse
    {
        $this->autorizar();
        $yo = $this->yo();
        $d = $request->validate(['actual' => 'required|string', 'nueva' => 'required|string|min:8|confirmed'], [],
            ['actual' => 'clave actual', 'nueva' => 'segunda clave']);
        $valida = $yo->clave_bd ? Hash::check($d['actual'], $yo->clave_bd) : Hash::check($d['actual'], $yo->password);
        if (! $valida) {
            return $this->volver($request)->withErrors(['actual' => $yo->clave_bd ? 'La segunda clave actual no es correcta.' : 'Esa no es tu clave del panel.']);
        }
        if (Hash::check($d['nueva'], $yo->password)) {
            return $this->volver($request)->withErrors(['nueva' => 'La segunda clave debe ser distinta a la del panel.']);
        }
        DB::connection('central')->table('superadmins')->where('id', $yo->id)->update(['clave_bd' => Hash::make($d['nueva'])]);
        $request->session()->forget('bd_edicion');

        return $this->volver($request)->with('ok', 'Segunda clave guardada. Úsala para abrir el modo edición.');
    }

    /** Abre el modo edición por unos minutos con la segunda clave */
    public function desbloquear(Request $request): RedirectResponse
    {
        $this->autorizar();
        $yo = $this->yo();
        $d = $request->validate(['clave_bd' => 'required|string']);
        if (! $yo->clave_bd || ! Hash::check($d['clave_bd'], $yo->clave_bd)) {
            Log::warning('Explorador de bases: segunda clave incorrecta', ['usuario' => $yo->email, 'ip' => $request->ip()]);

            return $this->volver($request)->withErrors(['clave_bd' => 'Segunda clave incorrecta.']);
        }
        $request->session()->put('bd_edicion', ['id' => $yo->id, 'hasta' => now()->addMinutes(EdicionBd::MINUTOS)->timestamp]);

        return $this->volver($request)->with('ok', 'Modo edición abierto por '.EdicionBd::MINUTOS.' minutos. Cada cambio se prueba primero y queda registrado.');
    }

    public function bloquear(Request $request): RedirectResponse
    {
        $this->autorizar();
        $request->session()->forget('bd_edicion');

        return $this->volver($request)->with('ok', 'Modo edición cerrado.');
    }

    /** Prueba una orden de cambio: corre y se deshace, para ver cuántas filas cambiaría */
    public function probar(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['base' => 'required|string|max:64', 'sql_cambio' => 'required|string|max:20000']);
        abort_unless(ExploradorBd::base($d['base']), 404, 'Esa base no existe.');
        if (! $this->edicionActiva($request)) {
            return $this->pantalla($request, ['errorCambio' => 'El modo edición está cerrado: ábrelo con tu segunda clave.']);
        }
        try {
            $filas = EdicionBd::probar($d['base'], $d['sql_cambio']);
        } catch (\InvalidArgumentException $e) {
            return $this->pantalla($request, ['errorCambio' => $e->getMessage()]);
        }

        return $this->pantalla($request, ['prueba' => ['sql' => $d['sql_cambio'], 'filas' => $filas, 'firma' => $this->firma($d['base'], $d['sql_cambio'])]]);
    }

    /** Ejecuta de verdad la orden que se probó (exactamente esa, en esa base) */
    public function ejecutar(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['base' => 'required|string|max:64', 'sql_cambio' => 'required|string|max:20000', 'firma' => 'required|string']);
        abort_unless(ExploradorBd::base($d['base']), 404, 'Esa base no existe.');
        if (! $this->edicionActiva($request)) {
            return $this->pantalla($request, ['errorCambio' => 'El modo edición se cerró: ábrelo de nuevo con tu segunda clave.']);
        }
        if (! hash_equals($this->firma($d['base'], $d['sql_cambio']), $d['firma'])) {
            return $this->pantalla($request, ['errorCambio' => 'La orden cambió después de probarla: pruébala de nuevo.']);
        }
        try {
            $filas = EdicionBd::ejecutar($d['base'], $d['sql_cambio'], $this->yo(), $request->ip());
        } catch (\InvalidArgumentException $e) {
            return $this->pantalla($request, ['errorCambio' => $e->getMessage()]);
        }

        return $this->pantalla($request, ['okCambio' => number_format($filas).' fila(s) cambiada(s) en '.$d['base'].'. Quedó registrado.']);
    }

    /** Vuelve al explorador en la misma base (no a la página anterior: puede ser el resultado de un POST) */
    private function volver(Request $request): RedirectResponse
    {
        return redirect()->route('admin.basedatos.index', array_filter(['base' => ExploradorBd::base($request->input('base'))]));
    }

    /** @return array<string, string> base => «nombre · RUC · subdominio · base» de las bases de empresas */
    private function etiquetas(): array
    {
        $res = [];
        foreach (Cliente::where('estado', '!=', 'ELIMINADO')->get() as $c) {
            $res[$c->base_datos] = ($c->nombre_comercial ?: $c->razon_social).' · '.$c->ruc.' · '.$c->host();
        }

        return $res;
    }

    private function pantalla(Request $request, array $extra = [])
    {
        $base = ExploradorBd::base($request->input('base'));
        $tabla = $base ? (string) $request->input('tabla', '') : '';
        $vista = null;
        $errorTabla = null;
        if ($base && $tabla !== '' && ! array_intersect_key($extra, array_flip(['resultado', 'error', 'respuestaIa', 'errorIa', 'prueba', 'errorCambio', 'okCambio']))) {
            try {
                $vista = ExploradorBd::ver($base, $tabla, $request->input('q'), (int) $request->input('pagina', 1));
            } catch (\Throwable $e) {
                $errorTabla = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No se pudo leer la tabla (¿demoró más de 8 segundos? prueba con una búsqueda más precisa).';
            }
        }
        $sesion = $request->session()->get('bd_edicion');

        return view('admin.basedatos', $extra + [
            'bases' => Servidor::bases(), 'etiquetas' => $this->etiquetas(), 'base' => $base, 'tablas' => $base ? ExploradorBd::tablas($base) : collect(),
            'tabla' => $tabla, 'vista' => $vista, 'errorTabla' => $errorTabla, 'q' => (string) $request->input('q', ''),
            'pagina' => max(1, (int) $request->input('pagina', 1)), 'sql' => (string) $request->input('sql', ''),
            'resultado' => $extra['resultado'] ?? null, 'error' => $extra['error'] ?? null,
            'tieneClave' => (bool) $this->yo()->clave_bd, 'edicion' => $this->edicionActiva($request) ? (int) $sesion['hasta'] : null,
            'sqlCambio' => (string) $request->input('sql_cambio', ''),
        ]);
    }
}
