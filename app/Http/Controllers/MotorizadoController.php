<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Motorizados del delivery: lista, crear, editar y eliminar; y asignarlos al pedido (en la comanda o al cobrar).
 * Uno que ya tiene pedidos no se borra (se desactiva) para no perder el reporte.
 */
class MotorizadoController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index()
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');
        $suc = $this->sucursal();
        $pedidos = DB::table('pedidos')->where('id_empresa_negocio', $suc)->whereNotNull('mot_id')
            ->where('ped_fec', '>=', now()->startOfMonth()->toDateString())->groupBy('mot_id')->select('mot_id', DB::raw('COUNT(*) as n'))->pluck('n', 'mot_id');

        return view('empresas.motorizados.index', [
            'motorizados' => DB::table('motorizados')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderBy('nombre')->get(),
            'pedidosMes' => $pedidos,
        ]);
    }

    /** Activos de la sucursal (para los selectores de la comanda y de cobrar) */
    public static function activos(int $suc)
    {
        return DB::table('motorizados')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('nombre')->get(['mot_id', 'nombre', 'telefono']);
    }

    public function guardar(Request $request)
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403);
        $d = $request->validate([
            'mot_id' => 'nullable|integer', 'nombre' => 'required|string|min:2|max:100',
            'telefono' => 'nullable|string|max:20', 'placa' => 'nullable|string|max:10', 'activo' => 'nullable|boolean',
        ], [], ['nombre' => 'el nombre del motorizado']);
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'telefono' => $d['telefono'] ?? null,
            'placa' => isset($d['placa']) ? strtoupper(str_replace([' ', '-'], '', $d['placa'])) : null, 'activo' => (int) ($d['activo'] ?? 1)];
        if (! empty($d['mot_id'])) {
            DB::table('motorizados')->where('mot_id', $d['mot_id'])->where('id_empresa_negocio', $this->sucursal())->update($fila);
            $id = (int) $d['mot_id'];
        } else {
            $id = DB::table('motorizados')->insertGetId($fila + ['id_empresa_negocio' => $this->sucursal()]);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'mensaje' => 'Motorizado guardado.', 'motorizado' => ['mot_id' => $id, 'nombre' => $fila['nombre']]])
            : back()->with('success', 'Motorizado guardado.');
    }

    public function eliminar(int $id)
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403);
        $m = DB::table('motorizados')->where('mot_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($m, 404);
        if (DB::table('pedidos')->where('mot_id', $id)->exists()) {
            DB::table('motorizados')->where('mot_id', $id)->update(['activo' => 0]);

            return back()->with('success', "{$m->nombre} tiene pedidos registrados: se DESACTIVÓ para no perder el reporte.");
        }
        DB::table('motorizados')->where('mot_id', $id)->delete();

        return back()->with('success', "Se eliminó a {$m->nombre}.");
    }

    /** Elegir motorizado en la comanda (antes de enviar queda en la sesión) o en un pedido ya creado */
    public function asignar(Request $request)
    {
        $d = $request->validate(['mot_id' => 'nullable|integer', 'ped_id' => 'nullable|integer']);
        $suc = $this->sucursal();
        $motId = ! empty($d['mot_id']) && DB::table('motorizados')->where('mot_id', $d['mot_id'])->where('id_empresa_negocio', $suc)->exists() ? (int) $d['mot_id'] : null;
        $pedId = (int) ($d['ped_id'] ?? 0) ?: (int) session('comanda_pedido_id');
        session()->put('comanda_mot_id', $motId);
        if ($pedId) {
            DB::table('pedidos')->where('ped_id', $pedId)->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->update(['mot_id' => $motId]);
        }

        return response()->json(['ok' => true]);
    }
}
