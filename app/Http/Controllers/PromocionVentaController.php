<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Support\PromocionesVenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Promociones automáticas del restaurante (solo el administrador): 2x1, 3x2 o "3 por S/ 40" de uno o varios productos,
 * en los días y horas que se elijan. Se aplican solas al cobrar; aquí se ve cuántas veces salió cada una y cuánto se ahorró.
 */
class PromocionVentaController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador maneja las promociones.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $promos = PromocionesVenta::deSucursal($suc);
        // Lo vendido con cada promo en los últimos 30 días
        $uso = DB::table('cpe_promociones as cp')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cp.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->where('c.fecha_hora', '>=', now()->subDays(30))
            ->groupBy('cp.promocion_id')->selectRaw('cp.promocion_id, SUM(cp.veces) as veces, SUM(cp.ahorro) as ahorro')->get()->keyBy('promocion_id');
        $productos = DB::table('productos as p')->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')->whereNotIn('p.promocion', Producto::NO_VENDIBLES)
            ->orderBy('c.cat_nom')->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom', 'p.propun', 'c.cat_nom']);
        $editar = $request->integer('editar') ? $promos->firstWhere('id', $request->integer('editar')) : null;

        return view('empresas.promociones.index', compact('promos', 'uso', 'productos', 'editar'));
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $suc = $this->sucursal();
        $actual = $id ? DB::table('promociones_venta')->where('id', $id)->where('id_empresa_negocio', $suc)->first() : null;
        abort_if($id && ! $actual, 404);
        $d = $request->validate([
            'nombre' => 'required|string|max:80',
            'tipo' => ['required', Rule::in(array_keys(PromocionesVenta::TIPOS))],
            'lleva' => 'required|integer|min:2|max:50',
            'paga' => 'nullable|required_if:tipo,LLEVA_PAGA|integer|min:1|lt:lleva',
            'precio' => 'nullable|required_if:tipo,POR_PRECIO|numeric|min:0.1|max:99999',
            'dias' => 'required|array|min:1', 'dias.*' => 'integer|between:1,7',
            'todo_el_dia' => 'nullable|boolean',
            'hora_inicio' => 'nullable|required_unless:todo_el_dia,1|date_format:H:i',
            'hora_fin' => 'nullable|required_unless:todo_el_dia,1|date_format:H:i|different:hora_inicio',
            'desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde',
            'productos' => 'required|array|min:1', 'productos.*' => 'integer',
        ], [
            'paga.lt' => 'Lo que se paga tiene que ser menos de lo que se lleva (por ejemplo, lleva 3 y paga 2).',
            'productos.required' => 'Elige al menos un producto de la promo.',
            'dias.required' => 'Elige al menos un día.',
            'hora_inicio.required_unless' => 'Pon desde qué hora vale, o marca "Todo el día".',
            'hora_fin.required_unless' => 'Pon hasta qué hora vale, o marca "Todo el día".',
        ], ['lleva' => 'lleva', 'paga' => 'paga', 'precio' => 'precio de la promo', 'hora_fin' => 'hora final', 'hasta' => 'hasta']);

        $productos = DB::table('productos')->where('id_empresa_negocio', $suc)->whereIn('IdProducto', $d['productos'])->pluck('IdProducto')->all();
        if (! $productos) {
            return back()->withInput()->withErrors(['productos' => 'Elige al menos un producto de la promo.']);
        }
        $todoElDia = $request->boolean('todo_el_dia');
        $dias = collect($d['dias'])->map(fn ($x) => (int) $x)->unique()->sort()->implode('');
        $fila = [
            'nombre' => mb_strtoupper(trim($d['nombre'])), 'tipo' => $d['tipo'], 'lleva' => (int) $d['lleva'],
            'paga' => $d['tipo'] === 'LLEVA_PAGA' ? (int) $d['paga'] : null,
            'precio' => $d['tipo'] === 'POR_PRECIO' ? round((float) $d['precio'], 2) : null,
            'dias' => $dias, 'hora_inicio' => $todoElDia ? null : $d['hora_inicio'], 'hora_fin' => $todoElDia ? null : $d['hora_fin'],
            'desde' => $d['desde'] ?? null, 'hasta' => $d['hasta'] ?? null, 'updated_at' => now(),
        ];

        DB::transaction(function () use ($id, $fila, $suc, $productos, &$promoId) {
            if ($id) {
                DB::table('promociones_venta')->where('id', $id)->update($fila);
                $promoId = $id;
            } else {
                $promoId = DB::table('promociones_venta')->insertGetId($fila + ['id_empresa_negocio' => $suc, 'activo' => true, 'created_at' => now()]);
            }
            DB::table('promocion_venta_producto')->where('promocion_id', $promoId)->delete();
            DB::table('promocion_venta_producto')->insert(array_map(fn ($p) => ['promocion_id' => $promoId, 'IdProducto' => $p], $productos));
        });

        return redirect()->route('promociones.index')->with('success', 'Promoción '.$fila['nombre'].' guardada: se aplica sola al cobrar.');
    }

    public function estado(int $id): RedirectResponse
    {
        $suc = $this->sucursal();
        $promo = DB::table('promociones_venta')->where('id', $id)->where('id_empresa_negocio', $suc)->first();
        abort_unless($promo, 404);
        DB::table('promociones_venta')->where('id', $id)->update(['activo' => ! $promo->activo, 'updated_at' => now()]);

        return back()->with('success', $promo->activo ? 'Promoción pausada.' : 'Promoción activada.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $suc = $this->sucursal();
        abort_unless(DB::table('promociones_venta')->where('id', $id)->where('id_empresa_negocio', $suc)->exists(), 404);
        DB::transaction(function () use ($id) {
            DB::table('promocion_venta_producto')->where('promocion_id', $id)->delete();
            DB::table('promociones_venta')->where('id', $id)->delete();
        });

        return redirect()->route('promociones.index')->with('success', 'Promoción eliminada. Los comprobantes ya emitidos no cambian.');
    }
}
