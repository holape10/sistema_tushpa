<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\Asistente\CuadernoFoto;
use App\Support\VentasCuaderno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pasar el cuaderno con foto (IA): se fotografía cada hoja de ventas anotadas a mano, la IA la lee y empareja cada línea
 * con un producto; la persona revisa y registra cada hoja como una venta con la fecha del cuaderno (descuenta stock).
 * Lo usa quien vende: el administrador o quien tiene el Punto de venta o el Panel de ventas en su menú.
 */
class CuadernoController extends Controller
{
    private function autorizar(): void
    {
        $u = Auth::user();
        abort_unless($u->esAdmin() || $u->tieneModulo('/punto-venta') || $u->tieneModulo('/ventas'), 403, 'No tienes permiso para registrar ventas.');
    }

    public function index(): View
    {
        $this->autorizar();
        $user = Auth::user();

        return view('empresas.cuaderno.index', [
            'productos' => DB::table('productos')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('proest', 'Activo')
                ->whereNotIn('promocion', [8])->orderBy('pronom')->get(['IdProducto', 'pronom', 'propun'])
                ->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'precio' => (float) $p->propun])->values(),
            'turnoAbierto' => Turno::where('IdUsuario', $user->IdUsuario)->where('estado', 'ABIERTO')->exists(),
            'puedeCrear' => $user->esAdmin() || $user->tieneModulo('/productos'),
        ]);
    }

    /** La IA lee la hoja y empareja cada línea con un producto */
    public function leer(Request $request): JsonResponse
    {
        $this->autorizar();
        $request->validate(['foto' => 'required|file|max:12288|extensions:'.CuadernoFoto::EXTENSIONES],
            ['foto.extensions' => 'Toma una foto (JPG o PNG).', 'foto.max' => 'La foto pesa más de 12 MB.']);
        set_time_limit(150);
        try {
            return response()->json(['ok' => true] + CuadernoFoto::leer(Auth::user(), $request->file('foto')));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Registra una hoja revisada como una venta al contado con la fecha del cuaderno */
    public function registrar(Request $request): JsonResponse
    {
        $this->autorizar();
        $user = Auth::user();
        $d = $request->validate([
            'fecha' => 'required|date|before_or_equal:today',
            'nombre' => 'nullable|string|max:60',
            'tdocod' => 'required|in:13,03',
            'lineas' => 'required|array|min:1|max:80',
            'lineas.*.id' => 'required|integer',
            'lineas.*.cantidad' => 'required|numeric|min:0.001|max:999999',
            'lineas.*.precio' => 'required|numeric|min:0|max:999999',
        ], ['lineas.*.id.required' => 'Hay líneas sin producto: elige el producto o quita la línea.'], ['fecha' => 'fecha de la hoja']);

        $ids = collect($d['lineas'])->pluck('id')->unique();
        $propios = DB::table('productos')->where('id_empresa_negocio', $user->id_empresa_negocio)->whereIn('IdProducto', $ids)->count();
        if ($propios !== $ids->count()) {
            return response()->json(['ok' => false, 'mensaje' => 'Hay productos que no son de esta sucursal.'], 422);
        }
        try {
            $id = DB::transaction(fn () => VentasCuaderno::registrarHoja($user, $d['fecha'], $d['nombre'] ?? 'del '.$d['fecha'],
                array_map(fn ($l) => ['id' => (int) $l['id'], 'cantidad' => (float) $l['cantidad'], 'precio' => (float) $l['precio']], $d['lineas']), $d['tdocod']));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(['serdoc', 'numdoc', 'ccaitv']);

        return response()->json(['ok' => true, 'id' => $id, 'mensaje' => "Registrada: {$cab->serdoc}-{$cab->numdoc} por S/ ".number_format((float) $cab->ccaitv, 2)]);
    }
}
