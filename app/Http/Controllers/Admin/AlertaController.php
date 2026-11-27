<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Bitacora;
use App\Support\Excel;
use App\Support\Tenancy\AlertasPanel;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\VisibilidadPanel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Alertas de una empresa en el panel (⚠️ en la lista de clientes): sus cambios delicados para revisarlos y descartarlos
 * cuando no son nada grave. Desde aquí también se descarga su bitácora en Excel, sin entrar a su sistema.
 */
class AlertaController extends Controller
{
    /** Máximo de filas del Excel de la bitácora */
    private const MAX_EXCEL = 20000;

    private function autorizar(Cliente $cliente): void
    {
        $yo = Auth::guard('superadmin')->user();
        abort_unless(VisibilidadPanel::puedeVer($yo, $cliente), 404);
    }

    public function index(Cliente $cliente): View
    {
        $this->autorizar($cliente);
        $tabla = DB::connection('central')->table('panel_alertas')->where('cliente_id', $cliente->id);
        $pendientes = (clone $tabla)->whereNull('descartada_el')->orderByDesc('id')->get();
        $descartadas = (clone $tabla)->whereNotNull('descartada_el')->orderByDesc('descartada_el')->limit(30)->get();

        return view('admin.clientes.alertas', ['cliente' => $cliente, 'pendientes' => $pendientes, 'descartadas' => $descartadas,
            'acciones' => AlertasPanel::VIGILADAS, 'detalles' => AlertasPanel::detalles($cliente, $pendientes->merge($descartadas)->pluck('bitacora_id')),
            'seguridad' => AlertasPanel::equipos($cliente, $pendientes)]);
    }

    /** Descarta una alerta (id) o todas las pendientes de la empresa: ya no sale el ⚠️ en la lista */
    public function descartar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($cliente);
        $id = (int) $request->input('id');
        $n = DB::connection('central')->table('panel_alertas')->where('cliente_id', $cliente->id)->whereNull('descartada_el')
            ->when($id, fn ($w) => $w->where('id', $id))
            ->update(['descartada_el' => now(), 'descartada_por' => mb_substr((string) Auth::guard('superadmin')->user()->nombre, 0, 120)]);

        return back()->with('success', $n === 1 ? 'Alerta descartada.' : "{$n} alertas descartadas.");
    }

    /** La bitácora de la empresa en Excel (por defecto los últimos 30 días) */
    public function bitacoraExcel(Request $request, Cliente $cliente): BinaryFileResponse
    {
        $this->autorizar($cliente);
        $d = $request->validate(['desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde']);
        $hasta = Carbon::parse($d['hasta'] ?? today())->endOfDay();
        $desde = Carbon::parse($d['desde'] ?? today()->subDays(30))->startOfDay();

        $filas = Tenancy::en($cliente->base_datos, function () use ($desde, $hasta) {
            if (! Schema::hasTable('bitacora')) {
                return [];
            }
            $sucursales = DB::table('empresa_negocios')->pluck('nombre_comercial', 'id_empresa_negocio');

            return DB::table('bitacora')->whereBetween('fecha', [$desde, $hasta])->orderBy('id')->limit(self::MAX_EXCEL)->get()
                ->map(fn ($b) => [
                    Carbon::parse($b->fecha)->format('d/m/Y H:i:s'), (string) $b->usuario, (string) $b->rol, $sucursales[$b->id_empresa_negocio] ?? '',
                    Bitacora::MODULOS[$b->modulo] ?? $b->modulo, $b->descripcion, Bitacora::resumen(json_decode((string) $b->cambios, true), 50),
                    $b->alerta ? 'Sí' : '', (string) $b->ip, (string) $b->agente,
                ])->all();
        });

        $ruta = (new Excel)->hoja('Bitácora', ['Fecha y hora', 'Usuario', 'Rol', 'Sucursal', 'Módulo', 'Qué hizo', 'Cambios (antes → después)', 'Para revisar', 'IP', 'Navegador'], $filas)
            ->guardar();
        $nombre = 'bitacora_'.$cliente->ruc.'_'.$desde->toDateString().'_al_'.$hasta->toDateString().'.xlsx';

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }
}
