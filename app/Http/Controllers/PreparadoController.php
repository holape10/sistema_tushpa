<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Support\ControlStock;
use App\Support\Porciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestión de preparados: platos que se preparan por cantidad cada día (juanes, tamales, sopa del día...).
 * Se anota lo preparado hoy y el sistema muestra cuánto se vendió, cuánto está en comandas y cuánto queda.
 */
class PreparadoController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $suc = $this->sucursal();

        return view('empresas.preparados.index', [
            'platos' => Porciones::resumen($suc),
            // Para agregar: platos y entradas que aún no se controlan (primero los que no tienen receta)
            'disponibles' => DB::table('productos as p')->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')
                ->whereIn('p.promocion', [2, Producto::OPCION])->where('p.controla_porciones', 0)
                ->orderByRaw('EXISTS (SELECT 1 FROM producto_receta r WHERE r.IdProducto = p.IdProducto)')->orderBy('p.pronom')
                ->get(['p.IdProducto', 'p.pronom', 'p.promocion', DB::raw('EXISTS (SELECT 1 FROM producto_receta r WHERE r.IdProducto = p.IdProducto) as receta')]),
            'movimientos' => DB::table('porciones_movimientos as m')->join('productos as p', 'p.IdProducto', '=', 'm.IdProducto')
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'm.IdUsuario')
                ->where('m.id_empresa_negocio', $suc)->where('m.fecha', Porciones::hoy())->whereIn('m.tipo', ['PREPARADO', 'AJUSTE'])
                ->orderByDesc('m.id')->limit(30)->get(['m.*', 'p.pronom', 'u.apeusu', 'u.name']),
        ]);
    }

    /**
     * Guarda todo de una vez: las cantidades escritas en la lista (y los platos nuevos que se agregaron).
     * modo "preparar" suma lo preparado; modo "corregir" deja lo que de verdad queda.
     */
    public function guardarTodo(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'modo' => 'required|in:preparar,corregir',
            'cantidades' => 'array|max:300',
            'cantidades.*' => 'nullable|numeric|min:0|max:9999',
        ], [], ['cantidades.*' => 'cantidad']);
        $cantidades = collect($d['cantidades'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v);
        if ($cantidades->isEmpty()) {
            return back()->with('error', 'No escribiste ninguna cantidad.');
        }

        $platos = Producto::where('id_empresa_negocio', $suc)->whereIn('promocion', [2, Producto::OPCION])
            ->whereIn('IdProducto', $cantidades->keys())->get()->keyBy('IdProducto');
        $guardados = 0;
        DB::transaction(function () use ($platos, $cantidades, $suc, $d, &$guardados) {
            // Los que se agregaron recién empiezan a controlarse
            Producto::whereIn('IdProducto', $platos->where('controla_porciones', 0)->keys())->update(['controla_porciones' => 1]);
            $quedan = $d['modo'] === 'corregir' ? Porciones::resumen($suc)->keyBy('id') : collect();
            foreach ($platos as $id => $p) {
                $cant = $cantidades[$id];
                if ($d['modo'] === 'preparar') {
                    if ($cant > 0) {
                        Porciones::anotar($suc, $id, 'PREPARADO', $cant);
                        $guardados++;
                    }
                } else {
                    $diferencia = round($cant - (float) ($quedan[$id]->quedan ?? 0), 2);
                    if (abs($diferencia) >= 0.01) {
                        Porciones::anotar($suc, $id, 'AJUSTE', $diferencia, null, 'Corrección: quedan '.ControlStock::numero($cant));
                    }
                    $guardados++;
                }
            }
        });

        return back()->with('success', $d['modo'] === 'preparar'
            ? "Guardado: se anotó lo preparado de {$guardados} plato(s)."
            : "Corregido: {$guardados} plato(s).");
    }

    /** Empezar o dejar de controlar porciones de un plato */
    public function controlar(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate(['producto' => 'required|integer', 'activo' => 'required|boolean']);
        $p = Producto::where('IdProducto', $d['producto'])->where('id_empresa_negocio', $suc)->whereIn('promocion', [2, Producto::OPCION])->firstOrFail();
        $p->update(['controla_porciones' => $d['activo']]);

        return back()->with('success', $d['activo'] ? "{$p->pronom}: ahora anota cuántas porciones preparaste hoy." : "{$p->pronom} ya no se controla por porciones.");
    }

    /** "Hoy preparé 20" (suma) o "corregir: quedan 5" (ajusta la diferencia) */
    public function anotar(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'producto' => 'required|integer',
            'accion' => 'required|in:preparar,corregir',
            'cantidad' => 'required|numeric|min:0|max:9999',
            'observacion' => 'nullable|string|max:150',
        ], [], ['cantidad' => 'cantidad']);
        $p = Producto::where('IdProducto', $d['producto'])->where('id_empresa_negocio', $suc)->where('controla_porciones', 1)->firstOrFail();
        $cant = (float) $d['cantidad'];

        if ($d['accion'] === 'preparar') {
            if ($cant <= 0) {
                return back()->with('error', 'Escribe cuántas porciones preparaste.');
            }
            Porciones::anotar($suc, $p->IdProducto, 'PREPARADO', $cant, null, $d['observacion'] ?? null);

            return back()->with('success', "+{$cant} porciones de {$p->pronom}.");
        }

        // Corregir: lo que de verdad queda (sin contar lo que ya está en comandas)
        $fila = Porciones::resumen($suc)->firstWhere('id', $p->IdProducto);
        $diferencia = round($cant - $fila->quedan, 2);
        if (abs($diferencia) >= 0.01) {
            Porciones::anotar($suc, $p->IdProducto, 'AJUSTE', $diferencia, null, $d['observacion'] ?: 'Corrección: quedan '.$cant);
        }

        return back()->with('success', $p->pronom.': '.($cant == 1 ? 'queda 1 porción.' : 'quedan '.ControlStock::numero($cant).' porciones.'));
    }
}
