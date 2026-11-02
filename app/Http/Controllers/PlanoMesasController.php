<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Piso;
use App\Support\PlanoMesas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Diseñar el plano del local (solo el administrador): arrastrar, agrandar y crear mesas y boxes, y poner la barra, paredes, DJ... */
class PlanoMesasController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador diseña el plano del local.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $pisos = Piso::where('id_empresa_negocio', $suc)->orderBy('pis_id')->get(['pis_id', 'pis_nom']);
        $piso = $pisos->firstWhere('pis_id', $request->integer('piso')) ?? $pisos->first();
        $mesas = $piso ? Mesa::where('id_empresa_negocio', $suc)->where('pis_id', $piso->pis_id)->orderBy('mes_nom')->get() : collect();

        return view('empresas.mesas.plano', [
            'pisos' => $pisos, 'piso' => $piso,
            'mesas' => $mesas->map(fn ($m) => ['id' => (int) $m->mes_id, 'nombre' => (string) $m->mes_nom, 'forma' => $m->plano_forma ?: 'CUADRADA',
                'x' => $m->plano_x, 'y' => $m->plano_y, 'w' => $m->plano_w, 'h' => $m->plano_h, 'ubicada' => $m->plano_x !== null])->values(),
            'elementos' => $piso ? PlanoMesas::elementos($suc, (int) $piso->pis_id)->map(fn ($e) => ['tipo' => $e->tipo, 'texto' => $e->texto,
                'x' => (int) $e->x, 'y' => (int) $e->y, 'w' => (int) $e->w, 'h' => (int) $e->h, 'color' => $e->color])->values() : collect(),
            'usaPlano' => PlanoMesas::usaPlano($suc),
            'alto' => $piso ? PlanoMesas::alto((int) $piso->pis_id) : PlanoMesas::ALTO,
            'ancho' => $piso ? PlanoMesas::ancho((int) $piso->pis_id) : PlanoMesas::ANCHO,
        ]);
    }

    public function guardar(Request $request, int $piso): JsonResponse
    {
        $suc = $this->sucursal();
        abort_unless(Piso::where('pis_id', $piso)->where('id_empresa_negocio', $suc)->exists(), 404);
        $d = $request->validate([
            'mesas' => 'present|array|max:300', 'mesas.*.id' => 'nullable|integer', 'mesas.*.nombre' => 'required|string|max:40',
            'mesas.*.forma' => 'required|string', 'mesas.*.x' => 'required|numeric', 'mesas.*.y' => 'required|numeric',
            'mesas.*.w' => 'required|numeric', 'mesas.*.h' => 'required|numeric',
            'elementos' => 'present|array|max:200', 'elementos.*.tipo' => 'required|string', 'elementos.*.texto' => 'nullable|string|max:40',
            'elementos.*.x' => 'required|numeric', 'elementos.*.y' => 'required|numeric', 'elementos.*.w' => 'required|numeric', 'elementos.*.h' => 'required|numeric',
            'elementos.*.color' => 'nullable|string|max:7',
            'usar_plano' => 'nullable|boolean',
            'alto' => 'nullable|integer|min:'.PlanoMesas::MEDIDA_MIN.'|max:'.PlanoMesas::MEDIDA_MAX,
            'ancho' => 'nullable|integer|min:'.PlanoMesas::MEDIDA_MIN.'|max:'.PlanoMesas::MEDIDA_MAX,
            'eliminar' => 'nullable|array|max:300', 'eliminar.*' => 'integer',
        ]);
        try {
            $n = PlanoMesas::guardar($suc, (int) Auth::user()->IdEmpresa, $piso, $d['mesas'], $d['elementos'],
                (int) ($d['alto'] ?? PlanoMesas::alto($piso)), $d['eliminar'] ?? [], (int) ($d['ancho'] ?? PlanoMesas::ancho($piso)));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        if ($request->has('usar_plano')) {
            DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)
                ->update(['vista_mesas' => $request->boolean('usar_plano') ? 'PLANO' : 'GRILLA']);
        }

        return response()->json(['ok' => true, 'mensaje' => "Plano guardado: {$n} mesa(s) en el plano.", 'recargar' => true]);
    }
}
