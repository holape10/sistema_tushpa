<?php
namespace App\Http\Controllers;

use App\Models\{EmpresaNegocio, Pedido, PedidoDetalle, Producto};
use App\Support\Impresion\Impresion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Hotel / hospedaje: habitaciones con tiempo que BAJA (cuenta regresiva).
 * Cada ingreso abre un pedido 'Hotel' con el servicio de tiempo; horas extra y consumos se agregan al mismo pedido
 * y se cobra con la misma pantalla de cobrar mesa (IGV 10.5% como Comandas).
 * La habitación se libera con "Dar salida" (pasa a Limpieza), no al cobrar: muchos pagan al entrar.
 */
class HotelController extends Controller
{
    public const ESTADOS = ['Libre', 'Ocupado', 'Limpieza', 'Mantenimiento'];

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede configurar el hotel.');
    }

    public function index()
    {
        $user = Auth::user();
        return view('empresas.hotel.index', [
            'negocio' => EmpresaNegocio::find($user->id_empresa_negocio),
            'puedeCobrar' => $user->esAdminOCaja(),
            'esAdmin' => $user->esAdmin(),
        ]);
    }

    /** Todo lo que pinta la pantalla: habitaciones, estadías activas y servicios de tiempo (se consulta cada 30 s) */
    public function estado()
    {
        $suc = $this->sucursal();
        $estadias = DB::table('hospedajes as h')->join('pedidos as p', 'p.ped_id', '=', 'h.ped_id')
            ->where('h.id_empresa_negocio', $suc)->where('h.hos_est', 'ACTIVO')
            ->get(['h.*', 'p.ped_tot'])->keyBy('hab_id');

        // Total y pendiente de cobro de cada estadía
        $saldos = DB::table('pedidos_detalle')->whereIn('ped_id', $estadias->pluck('ped_id'))->where('estadoitem', '!=', 'Eliminado')
            ->groupBy('ped_id')->select('ped_id', DB::raw('SUM(ped_det_can * ped_det_pre) as total'),
                DB::raw('SUM((ped_det_can - item_facturado) * ped_det_pre) as pendiente'))->get()->keyBy('ped_id');

        $habitaciones = DB::table('habitaciones')->where('id_empresa_negocio', $suc)->orderBy('hab_piso')->orderBy('hab_nom')->get()
            ->map(function ($h) use ($estadias, $saldos) {
                $e = $estadias[$h->hab_id] ?? null;
                $h->estadia = $e ? [
                    'hos_id' => $e->hos_id, 'ped_id' => $e->ped_id, 'cliente' => $e->cliente, 'documento' => $e->documento,
                    'personas' => $e->personas, 'inicio' => $e->inicio, 'fin' => $e->fin,
                    'total' => round((float) ($saldos[$e->ped_id]->total ?? 0), 2),
                    'pendiente' => round((float) ($saldos[$e->ped_id]->pendiente ?? 0), 2),
                ] : null;
                return $h;
            });

        return response()->json([
            'ahora' => now()->format('Y-m-d H:i:s'),
            'habitaciones' => $habitaciones,
            'servicios' => $this->servicios(),
        ]);
    }

    private function servicios()
    {
        return Producto::where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')->where('minutos', '>', 0)
            ->orderBy('minutos')->get(['IdProducto', 'pronom', 'propun', 'minutos']);
    }

    private function servicio(int $id): Producto
    {
        $s = Producto::where('IdProducto', $id)->where('id_empresa_negocio', $this->sucursal())->where('minutos', '>', 0)->first();
        if (!$s) {
            throw new \RuntimeException('El servicio elegido no existe o ya no da tiempo.');
        }
        return $s;
    }

    /** Estadía activa bloqueada para modificarla */
    private function estadia(int $hosId): object
    {
        $e = DB::table('hospedajes')->where('hos_id', $hosId)->where('id_empresa_negocio', $this->sucursal())
            ->where('hos_est', 'ACTIVO')->lockForUpdate()->first();
        if (!$e) {
            throw new \RuntimeException('Esta habitación ya no está ocupada. Actualiza la pantalla.');
        }
        return $e;
    }

    private function linea(int $pedId, Producto $p, float $cantidad, float $precio, ?string $obs = null): void
    {
        PedidoDetalle::create([
            'ped_id' => $pedId, 'IdProducto' => $p->IdProducto, 'IdEmpresa' => Auth::user()->IdEmpresa,
            'descripcion' => $p->pronom, 'detalle' => $p->pronom, 'ped_det_can' => $cantidad, 'ped_det_pre' => $precio,
            'item_obs' => $obs, 'estadoitem' => 'Ingresado', 'impreso' => 'impreso', 'fecha_hora' => now(),
        ]);
    }

    private function recalcular(int $pedId): void
    {
        $total = PedidoDetalle::where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()
            ->sum(fn($d) => $d->ped_det_can * $d->ped_det_pre);
        Pedido::where('ped_id', $pedId)->update(['ped_tot' => round($total, 2), 'fecha_hora_modificacion' => now()]);
    }

    private function responder(callable $accion, string $ok)
    {
        try {
            $extra = DB::transaction($accion);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo guardar.']);
        }
        return response()->json(['ok' => true, 'mensaje' => $ok] + (is_array($extra) ? $extra : []));
    }

    /** Ingreso: abre el pedido con el servicio de tiempo y empieza la cuenta regresiva */
    public function ingresar(Request $request)
    {
        $d = $request->validate([
            'hab_id' => 'required|integer', 'servicio' => 'required|integer', 'cantidad' => 'required|numeric|min:1|max:365',
            'precio' => 'required|numeric|min:0', 'cliente' => 'nullable|string|max:150', 'documento' => 'nullable|string|max:15',
            'personas' => 'nullable|integer|min:1|max:20',
        ]);
        $user = Auth::user();

        return $this->responder(function () use ($d, $user) {
            $hab = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $user->id_empresa_negocio)
                ->lockForUpdate()->first();
            if (!$hab) {
                throw new \RuntimeException('Habitación no válida.');
            }
            if ($hab->hab_est !== 'Libre') {
                throw new \RuntimeException("La habitación {$hab->hab_nom} está {$hab->hab_est}.");
            }
            $serv = $this->servicio((int) $d['servicio']);
            $cliente = mb_strtoupper(trim((string) ($d['cliente'] ?? ''))) ?: 'CLIENTE HOSPEDAJE';

            $pedido = Pedido::create([
                'ped_tip' => 'Hotel', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(),
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio, 'ped_est' => 'Aperturado',
                'mozo' => $user->IdUsuario, 'IdUsuario' => $user->IdUsuario, 'ped_cli_nom' => $cliente,
                'ped_num_doc' => $d['documento'] ?? null, 'ped_obs' => 'HAB. ' . $hab->hab_nom, 'ped_tot' => 0,
            ]);
            $this->linea($pedido->ped_id, $serv, (float) $d['cantidad'], (float) $d['precio'], 'HAB. ' . $hab->hab_nom);
            $this->recalcular($pedido->ped_id);

            DB::table('hospedajes')->insert([
                'ped_id' => $pedido->ped_id, 'hab_id' => $hab->hab_id, 'id_empresa_negocio' => $user->id_empresa_negocio,
                'cliente' => $cliente, 'documento' => $d['documento'] ?? null, 'personas' => $d['personas'] ?? 1,
                'inicio' => now(), 'fin' => now()->addMinutes((int) round($serv->minutos * $d['cantidad'])),
                'hos_est' => 'ACTIVO', 'IdUsuario' => $user->IdUsuario,
            ]);
            DB::table('habitaciones')->where('hab_id', $hab->hab_id)->update(['hab_est' => 'Ocupado']);
            return ['ped_id' => $pedido->ped_id];
        }, 'Ingreso registrado.');
    }

    /** Horas extra: el tiempo SUBE desde la hora de salida programada */
    public function extender(Request $request)
    {
        $d = $request->validate(['hos_id' => 'required|integer', 'servicio' => 'required|integer',
            'cantidad' => 'required|numeric|min:1|max:365', 'precio' => 'required|numeric|min:0']);

        return $this->responder(function () use ($d) {
            $e = $this->estadia((int) $d['hos_id']);
            $serv = $this->servicio((int) $d['servicio']);
            $this->linea($e->ped_id, $serv, (float) $d['cantidad'], (float) $d['precio']);
            $this->recalcular($e->ped_id);
            $fin = \Carbon\Carbon::parse($e->fin)->addMinutes((int) round($serv->minutos * $d['cantidad']));
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['fin' => $fin]);
            return ['fin' => $fin->format('Y-m-d H:i:s')];
        }, 'Tiempo agregado.');
    }

    /** Consumo a la habitación (minibar, room service). Lo preparado va a cocina igual que una comanda */
    public function consumo(Request $request)
    {
        $d = $request->validate(['hos_id' => 'required|integer', 'producto' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.01|max:999', 'observacion' => 'nullable|string|max:100']);
        $cocina = [];

        $res = $this->responder(function () use ($d, &$cocina) {
            $e = $this->estadia((int) $d['hos_id']);
            $p = Producto::where('IdProducto', $d['producto'])->where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')->first();
            if (!$p) {
                throw new \RuntimeException('Producto no válido.');
            }
            $precio = (float) (\App\Support\Precios::vigentes(collect([$p]))[$p->IdProducto] ?? $p->propun);
            $this->linea($e->ped_id, $p, (float) $d['cantidad'], $precio, $d['observacion'] ?? null);
            $this->recalcular($e->ped_id);
            $cocina = ['ped_id' => $e->ped_id, 'lineas' => [['IdProducto' => $p->IdProducto, 'nombre' => $p->pronom,
                'cantidad' => (float) $d['cantidad'], 'observacion' => $d['observacion'] ?? '']]];
        }, 'Consumo agregado.');

        // Comanda a cocina/bar según la categoría (si tiene impresora) y pantalla de cocina
        if ($cocina) {
            try {
                Impresion::comanda($cocina['ped_id'], $cocina['lineas']);
                \App\Support\Cocina::registrar($cocina['ped_id'], $cocina['lineas']);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return $res;
    }

    /** Detalle de la estadía para el modal: consumos y lo cobrado */
    public function detalle(int $hosId)
    {
        $e = DB::table('hospedajes')->where('hos_id', $hosId)->where('id_empresa_negocio', $this->sucursal())->firstOrFail();
        $items = PedidoDetalle::where('ped_id', $e->ped_id)->where('estadoitem', '!=', 'Eliminado')->orderBy('ped_det_id')
            ->get(['ped_det_id', 'descripcion', 'ped_det_can', 'ped_det_pre', 'item_facturado', 'fecha_hora']);
        return response()->json(['estadia' => $e, 'items' => $items]);
    }

    /** Salida: solo con todo cobrado. Cierra el pedido y la habitación pasa a Limpieza */
    public function salida(Request $request)
    {
        $d = $request->validate(['hos_id' => 'required|integer']);
        return $this->responder(function () use ($d) {
            $e = $this->estadia((int) $d['hos_id']);
            $pendiente = PedidoDetalle::where('ped_id', $e->ped_id)->where('estadoitem', '!=', 'Eliminado')->get()
                ->sum(fn($x) => ($x->ped_det_can - $x->item_facturado) * $x->ped_det_pre);
            if ($pendiente > 0.009) {
                throw new \RuntimeException('Falta cobrar S/ ' . number_format($pendiente, 2) . '. Cobra antes de dar la salida.');
            }
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['hos_est' => 'FINALIZADO', 'salida' => now()]);
            Pedido::where('ped_id', $e->ped_id)->update(['ped_est' => 'Cerrado', 'fecha_hora_modificacion' => now()]);
            DB::table('habitaciones')->where('hab_id', $e->hab_id)->update(['hab_est' => 'Limpieza']);
        }, 'Salida registrada. La habitación quedó en limpieza.');
    }

    /** Anular un ingreso hecho por error (solo administrador y sin nada cobrado) */
    public function anular(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['hos_id' => 'required|integer', 'motivo' => 'required|string|max:100']);
        return $this->responder(function () use ($d) {
            $e = $this->estadia((int) $d['hos_id']);
            if (PedidoDetalle::where('ped_id', $e->ped_id)->where('item_facturado', '>', 0)->exists()) {
                throw new \RuntimeException('Ya se cobró parte de esta estadía; no se puede anular. Emite una nota de crédito.');
            }
            PedidoDetalle::where('ped_id', $e->ped_id)->update(['estadoitem' => 'Eliminado',
                'item_obs' => mb_substr('[ANULADO: ' . $d['motivo'] . ' - ' . Auth::user()->apeusu . ']', 0, 255)]);
            Pedido::where('ped_id', $e->ped_id)->update(['ped_est' => 'Anulado', 'ped_tot' => 0, 'fecha_hora_modificacion' => now()]);
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['hos_est' => 'ANULADO', 'salida' => now()]);
            DB::table('habitaciones')->where('hab_id', $e->hab_id)->update(['hab_est' => 'Libre']);
        }, 'Ingreso anulado.');
    }

    /** Limpieza terminada / mantenimiento */
    public function cambiarEstado(Request $request)
    {
        $d = $request->validate(['hab_id' => 'required|integer', 'estado' => 'required|in:Libre,Limpieza,Mantenimiento']);
        return $this->responder(function () use ($d) {
            $hab = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $this->sucursal())->lockForUpdate()->first();
            if (!$hab || $hab->hab_est === 'Ocupado') {
                throw new \RuntimeException('La habitación está ocupada; primero dale salida.');
            }
            DB::table('habitaciones')->where('hab_id', $hab->hab_id)->update(['hab_est' => $d['estado']]);
        }, 'Habitación actualizada.');
    }

    // ---------------- Configuración (administrador) ----------------

    public function guardarHabitacion(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['hab_id' => 'nullable|integer', 'hab_nom' => 'required|string|max:50',
            'hab_tip' => 'nullable|string|max:40', 'hab_piso' => 'required|string|max:30', 'hab_obs' => 'nullable|string|max:150']);
        $fila = ['hab_nom' => mb_strtoupper(trim($d['hab_nom'])), 'hab_tip' => mb_strtoupper(trim((string) ($d['hab_tip'] ?? ''))) ?: null,
            'hab_piso' => mb_strtoupper(trim($d['hab_piso'])), 'hab_obs' => $d['hab_obs'] ?? null];

        $repetida = DB::table('habitaciones')->where('id_empresa_negocio', $this->sucursal())->where('hab_nom', $fila['hab_nom'])
            ->when($d['hab_id'] ?? null, fn($q, $id) => $q->where('hab_id', '!=', $id))->exists();
        if ($repetida) {
            return response()->json(['ok' => false, 'mensaje' => "Ya existe la habitación {$fila['hab_nom']}."]);
        }

        if (!empty($d['hab_id'])) {
            DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $this->sucursal())->update($fila);
        } else {
            DB::table('habitaciones')->insert($fila + ['IdEmpresa' => Auth::user()->IdEmpresa, 'id_empresa_negocio' => $this->sucursal(), 'hab_est' => 'Libre']);
        }
        return response()->json(['ok' => true, 'mensaje' => 'Habitación guardada.']);
    }

    public function eliminarHabitacion(int $id)
    {
        $this->soloAdmin();
        $hab = DB::table('habitaciones')->where('hab_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        if (!$hab) {
            return response()->json(['ok' => false, 'mensaje' => 'Habitación no válida.']);
        }
        if ($hab->hab_est === 'Ocupado') {
            return response()->json(['ok' => false, 'mensaje' => 'La habitación está ocupada.']);
        }
        if (DB::table('hospedajes')->where('hab_id', $id)->exists()) {
            // Tiene historial: se deja fuera de servicio en vez de borrarla
            DB::table('habitaciones')->where('hab_id', $id)->update(['hab_est' => 'Mantenimiento']);
            return response()->json(['ok' => true, 'mensaje' => 'Tiene estadías registradas: quedó en Mantenimiento en vez de borrarse.']);
        }
        DB::table('habitaciones')->where('hab_id', $id)->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Habitación eliminada.']);
    }

    /** Servicio de tiempo = producto sin stock con minutos (HABITACION 2 HORAS, 1 DIA, HORA EXTRA) */
    public function guardarServicio(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['IdProducto' => 'nullable|integer', 'pronom' => 'required|string|max:150',
            'propun' => 'required|numeric|min:0', 'horas' => 'required|integer|min:0|max:8760', 'min' => 'nullable|integer|min:0|max:59']);
        $minutos = (int) $d['horas'] * 60 + (int) ($d['min'] ?? 0);
        if ($minutos <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indica cuánto tiempo da el servicio.']);
        }
        $user = Auth::user();
        $datos = ['pronom' => mb_strtoupper(trim($d['pronom'])), 'propun' => $d['propun'], 'minutos' => $minutos];

        if (!empty($d['IdProducto'])) {
            Producto::where('IdProducto', $d['IdProducto'])->where('id_empresa_negocio', $user->id_empresa_negocio)->update($datos);
        } else {
            // Categoría HOSPEDAJE (sin impresora: no va a cocina)
            $cat = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cat_nom', 'HOSPEDAJE')->value('cat_id')
                ?? DB::table('categorias')->insertGetId(['cat_nom' => 'HOSPEDAJE', 'IdEmpresa' => $user->IdEmpresa,
                    'id_empresa_negocio' => $user->id_empresa_negocio, 'tip_pro_id' => DB::table('tipo_producto')
                        ->where('id_empresa_negocio', $user->id_empresa_negocio)->value('tip_pro_id') ?? 1,
                    'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);
            Producto::create($datos + ['procod' => 'H' . time(), 'umecod' => 'ZZ', 'costo' => 0, 'promocion' => 2, 'cat_id' => $cat,
                'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio]);
        }
        return response()->json(['ok' => true, 'mensaje' => 'Servicio guardado.']);
    }

    public function quitarServicio(int $id)
    {
        $this->soloAdmin();
        Producto::where('IdProducto', $id)->where('id_empresa_negocio', $this->sucursal())->update(['minutos' => null]);
        return response()->json(['ok' => true, 'mensaje' => 'Ya no aparece como servicio de tiempo (el producto sigue existiendo).']);
    }
}
