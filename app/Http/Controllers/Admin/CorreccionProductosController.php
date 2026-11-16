<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\ExcelLector;
use App\Support\Tenancy\CorreccionProductos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Corregir productos de una empresa con un Excel desde el panel (solo el dueño), sin el usuario del cliente: primero
 * se revisa qué cambiaría y luego se aplica. Ver App\Support\Tenancy\CorreccionProductos.
 */
class CorreccionProductosController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema corrige productos de las empresas.');
    }

    /** @return array<string, string> base => «nombre · RUC · subdominio · base» */
    private function bases(): array
    {
        $bases = [];
        foreach (Cliente::where('estado', '!=', 'ELIMINADO')->orderBy('razon_social')->orderBy('base_datos')->get() as $c) {
            $bases[$c->base_datos] = ($c->nombre_comercial ?: $c->razon_social).' · '.$c->ruc.' · '.$c->host().' · '.$c->base_datos;
        }

        return $bases;
    }

    private static function archivo(string $token): string
    {
        return storage_path('app/panel_correcciones/'.$token.'.json');
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $bases = $this->bases();
        $pendiente = null;
        $token = (string) $request->get('revision');
        if (preg_match('/^[a-zA-Z0-9-]{36}$/', $token) && is_file(self::archivo($token))) {
            $pendiente = json_decode((string) file_get_contents(self::archivo($token)), true);
            $pendiente['token'] = $token;
            $pendiente['revision'] = CorreccionProductos::revisar($pendiente['base'], $pendiente['filas']);
        }

        return view('admin.correccion_productos', ['bases' => $bases, 'pendiente' => $pendiente, 'base' => $pendiente['base'] ?? (string) $request->get('base')]);
    }

    /** Lee el Excel y muestra qué cambiaría (todavía no cambia nada) */
    public function revisar(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['base' => 'required|string|max:64', 'archivo' => 'required|file|max:20480|extensions:'.ExcelLector::EXTENSIONES],
            ['archivo.extensions' => 'Sube el Excel (.xlsx, .xls, .ods) o un .csv.'], ['archivo' => 'Excel']);
        abort_unless(isset($this->bases()[$d['base']]), 404, 'Esa empresa no existe.');
        try {
            $leido = CorreccionProductos::leer($request->file('archivo')->getRealPath(), $request->file('archivo')->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo leer el Excel: '.$e->getMessage()]);
        }
        $token = (string) Str::uuid();
        if (! is_dir(dirname(self::archivo($token)))) {
            mkdir(dirname(self::archivo($token)), 0775, true);
        }
        file_put_contents(self::archivo($token), json_encode(['base' => $d['base'], 'nombre' => $request->file('archivo')->getClientOriginalName(),
            'filas' => $leido['filas'], 'errores' => $leido['errores'], 'columnas' => $leido['columnas']]));

        return redirect()->route('admin.correccion_productos.index', ['revision' => $token]);
    }

    /** Aplica lo revisado (se vuelve a revisar contra la base de este momento) */
    public function aplicar(string $token): RedirectResponse
    {
        $this->autorizar();
        abort_unless(preg_match('/^[a-zA-Z0-9-]{36}$/', $token) && is_file(self::archivo($token)), 404, 'Esa revisión ya no existe: vuelve a subir el Excel.');
        $datos = json_decode((string) file_get_contents(self::archivo($token)), true);
        abort_unless(isset($this->bases()[$datos['base']]), 404);

        try {
            $r = CorreccionProductos::aplicar($datos['base'], $datos['filas'], (string) Auth::guard('superadmin')->user()->email, (string) $datos['nombre']);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.correccion_productos.index', ['revision' => $token])->withErrors(['archivo' => $e->getMessage()]);
        }
        @unlink(self::archivo($token));

        return redirect()->route('admin.correccion_productos.index', ['base' => $datos['base']])
            ->with('ok', "Listo en {$datos['base']}: {$r['precio']} precios, {$r['nombre']} nombres, {$r['costo']} costos, {$r['estado']} estados y {$r['stock']} stocks corregidos.");
    }
}
