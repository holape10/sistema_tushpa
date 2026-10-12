<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Tenancy\Respaldos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Copias de seguridad (panel admin, solo el dueño): de cada empresa y del sistema principal.
 * Sacar una copia, descargarla, subir una copia y restaurar. Antes de restaurar se guarda la copia de cómo estaba.
 */
class RespaldoController extends Controller
{
    public function index(Request $request)
    {
        $bases = $this->bases();
        $base = $request->get('base');
        $base = isset($bases[$base]) ? $base : array_key_first($bases);

        return view('admin.respaldos', ['bases' => $bases, 'base' => $base, 'copias' => Respaldos::listar($base)]);
    }

    public function crear(Request $request)
    {
        $base = $this->baseElegida($request);
        set_time_limit(1800);
        try {
            $ruta = Respaldos::crear($base, 'manual');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Copia lista: '.basename($ruta).' ('.$this->tamano(filesize($ruta)).').');
    }

    public function descargar(Request $request): BinaryFileResponse
    {
        $base = $this->baseElegida($request);
        try {
            $ruta = Respaldos::ruta($base, (string) $request->get('archivo'));
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response()->download($ruta);
    }

    public function eliminar(Request $request)
    {
        $base = $this->baseElegida($request);
        try {
            unlink(Respaldos::ruta($base, (string) $request->input('archivo')));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Copia borrada.');
    }

    /** Restaura una copia guardada o un archivo subido (.sql o .sql.gz). Hay que escribir la palabra RESTAURAR */
    public function restaurar(Request $request)
    {
        $base = $this->baseElegida($request);
        $request->validate([
            'confirmacion' => 'required|in:RESTAURAR',
            'archivo' => 'nullable|string|max:120',
            'subido' => 'nullable|file|max:524288',
        ], ['confirmacion.in' => 'Escribe RESTAURAR en mayúsculas para confirmar.', 'subido.max' => 'El archivo puede pesar hasta 512 MB.'],
            ['confirmacion' => 'confirmación', 'subido' => 'archivo']);

        if ($request->hasFile('subido')) {
            $nombre = mb_strtolower($request->file('subido')->getClientOriginalName());
            if (! str_ends_with($nombre, '.sql') && ! str_ends_with($nombre, '.sql.gz') && ! str_ends_with($nombre, '.gz')) {
                return back()->with('error', 'Sube un archivo .sql o .sql.gz.');
            }
            $archivo = $request->file('subido')->getRealPath();
        } else {
            try {
                $archivo = Respaldos::ruta($base, (string) $request->input('archivo'));
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        set_time_limit(3600);
        try {
            $previa = Respaldos::restaurar($base, $archivo);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Información restaurada en '.$this->bases()[$base].'. Por si acaso, se guardó cómo estaba antes: '.$previa.'.');
    }

    /** @return array<string, string> base => nombre visible */
    private function bases(): array
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema maneja las copias de seguridad.');
        $bases = [config('tenancy.base_sistema') => '★ Sistema principal ('.config('tenancy.base_sistema').')'];
        foreach (Cliente::where('estado', '!=', 'ELIMINADO')->orderBy('razon_social')->get() as $c) {
            $bases[$c->base_datos] = ($c->nombre_comercial ?: $c->razon_social).' · '.$c->ruc;
        }

        return $bases;
    }

    private function baseElegida(Request $request): string
    {
        $base = (string) $request->input('base');
        abort_unless(isset($this->bases()[$base]), 404);

        return $base;
    }

    private function tamano(int $bytes): string
    {
        return $bytes > 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
