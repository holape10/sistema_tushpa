<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Support\Autorizacion;
use App\Support\Cocina;
use App\Support\ControlStock;
use App\Support\HotelReporte;
use App\Support\Impresion\Impresion;
use App\Support\Porciones;
use App\Support\Precios;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hotel / hospedaje: habitaciones con tiempo que BAJA (cuenta regresiva).
 * Cada ingreso abre un pedido 'Hotel' con el servicio de tiempo; horas extra y consumos se agregan al mismo pedido
 * y se cobra con la misma pantalla de cobrar mesa (IGV 10.5% como Comandas).
 * La habitación se libera con "Dar salida" (pasa a Limpieza), no al cobrar: muchos pagan al entrar.
 * Lo delicado (quitar consumos, cambiar de habitación, salir sin cobrar el exceso, anular) queda en la bitácora hotel_eventos.
 */
class HotelController extends Controller
{
    public const ESTADOS = ['Libre', 'Ocupado', 'Limpieza', 'Mantenimiento'];

    /** Datos extra para la respuesta de error (ej. "hay una reserva" o "se pasó N minutos") */
    private array $extraError = [];

    /** Solo quien tiene "Hotel" en su menú (el menú ocultaba la opción, pero la dirección quedaba abierta) */
    private function sucursal(): int
    {
        abort_unless(Auth::user()->tieneModulo('/hotel'), 403, 'No tienes acceso al hotel.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    /** El precio lo cambia solo el administrador; los demás cobran el precio del servicio */
    private function precio(Producto $servicio, $precioEscrito): float
    {
        return Auth::user()->esAdmin() && $precioEscrito !== null ? (float) $precioEscrito : (float) $servicio->propun;
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede hacer esto.');
    }

    /** Minutos de gracia después de la hora de salida antes de exigir horas extra (configurable por sucursal) */
    private function tolerancia(int $suc): int
    {
        return (int) (DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('hotel_tolerancia') ?? 10);
    }

    public function index()
    {
        $suc = $this->sucursal();
        $user = Auth::user();

        return view('empresas.hotel.index', [
            'negocio' => EmpresaNegocio::find($suc),
            'puedeCobrar' => $user->esAdminOCaja(),
            'esAdmin' => $user->esAdmin(),
            'tolerancia' => $this->tolerancia($suc),
            'verReporte' => $user->tieneModulo('/hotel/reporte') || $user->esAdminOCaja(),
        ]);
    }

    /** Todo lo que pinta la pantalla: habitaciones, estadías activas, reservas próximas y servicios (se consulta cada 30 s) */
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

        // Reserva que viene (o que no llegó hace poco) de cada habitación
        $reservas = DB::table('hotel_reservas')->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')
            ->whereBetween('llegada', [now()->subHours(3), now()->addHours(24)])->orderBy('llegada')->get()->groupBy('hab_id');

        $habitaciones = DB::table('habitaciones')->where('id_empresa_negocio', $suc)->orderBy('hab_piso')->orderBy('hab_nom')->get()
            ->map(function ($h) use ($estadias, $saldos, $reservas) {
                $e = $estadias[$h->hab_id] ?? null;
                $h->estadia = $e ? [
                    'hos_id' => $e->hos_id, 'ped_id' => $e->ped_id, 'cliente' => $e->cliente, 'documento' => $e->documento,
                    'personas' => $e->personas, 'inicio' => $e->inicio, 'fin' => $e->fin,
                    'total' => round((float) ($saldos[$e->ped_id]->total ?? 0), 2),
                    'pendiente' => round((float) ($saldos[$e->ped_id]->pendiente ?? 0), 2),
                ] : null;
                $r = ($reservas[$h->hab_id] ?? collect())->first();
                $h->reserva = $r ? ['res_id' => $r->res_id, 'cliente' => $r->cliente, 'llegada' => $r->llegada, 'telefono' => $r->telefono] : null;

                return $h;
            });

        return response()->json([
            'ahora' => now()->format('Y-m-d H:i:s'),
            'habitaciones' => $habitaciones,
            'servicios' => $this->servicios(),
            'tolerancia' => $this->tolerancia($suc),
            'reservas_hoy' => DB::table('hotel_reservas')->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')
                ->whereDate('llegada', now()->toDateString())->count(),
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
        if (! $s) {
            throw new \RuntimeException('El servicio elegido no existe o ya no da tiempo.');
        }

        return $s;
    }

    /** Servicio con el que se cobra el tiempo de más: el que se llama "HORA EXTRA" (o el más corto) */
    private function servicioExtra(): ?Producto
    {
        $servicios = Producto::where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')->where('minutos', '>', 0)->orderBy('minutos')->get();

        return $servicios->first(fn ($s) => preg_match('/extra/i', $s->pronom)) ?? $servicios->first();
    }

    /** Estadía activa bloqueada para modificarla */
    private function estadia(int $hosId): object
    {
        $e = DB::table('hospedajes')->where('hos_id', $hosId)->where('id_empresa_negocio', $this->sucursal())
            ->where('hos_est', 'ACTIVO')->lockForUpdate()->first();
        if (! $e) {
            throw new \RuntimeException('Esta habitación ya no está ocupada. Actualiza la pantalla.');
        }

        return $e;
    }

    /** La primera línea de tiempo es la habitación en sí (las demás son horas extra) */
    private function lineaPrincipal(int $pedId): ?int
    {
        return PedidoDetalle::from('pedidos_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->where('d.ped_id', $pedId)->where('p.minutos', '>', 0)->orderBy('d.ped_det_id')->value('d.ped_det_id');
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
            ->sum(fn ($d) => $d->ped_det_can * $d->ped_det_pre);
        Pedido::where('ped_id', $pedId)->update(['ped_tot' => round($total, 2), 'fecha_hora_modificacion' => now()]);
    }

    private function pendiente(int $pedId): float
    {
        return (float) PedidoDetalle::where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()
            ->sum(fn ($x) => ($x->ped_det_can - $x->item_facturado) * $x->ped_det_pre);
    }

    /** Bitácora del hotel: lo que conviene que el dueño revise */
    private function evento(object $e, string $tipo, array $datos = []): void
    {
        DB::table('hotel_eventos')->insert([
            'hos_id' => $e->hos_id, 'hab_id' => $e->hab_id, 'tipo' => $tipo, 'minutos' => $datos['minutos'] ?? null,
            'monto' => $datos['monto'] ?? null, 'detalle' => isset($datos['detalle']) ? mb_substr($datos['detalle'], 0, 200) : null,
            'IdUsuario' => Auth::id(), 'autorizado_por' => $datos['autorizado_por'] ?? null,
            'id_empresa_negocio' => $e->id_empresa_negocio, 'created_at' => now(),
        ]);
    }

    private function responder(callable $accion, string $ok)
    {
        $this->extraError = [];
        try {
            $extra = DB::transaction($accion);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()] + $this->extraError);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo guardar.']);
        }

        return response()->json(['ok' => true, 'mensaje' => $ok] + (is_array($extra) ? $extra : []));
    }

    /** Reserva pendiente de esa habitación que choca con el intervalo (null si no hay) */
    private function reservaQueChoca(int $habId, Carbon $desde, Carbon $hasta, ?int $menos = null): ?object
    {
        return DB::table('hotel_reservas as r')->join('productos as p', 'p.IdProducto', '=', 'r.IdProducto')
            ->where('r.hab_id', $habId)->where('r.estado', 'PENDIENTE')->when($menos, fn ($q) => $q->where('r.res_id', '!=', $menos))
            ->where('r.llegada', '<', $hasta)
            ->whereRaw('DATE_ADD(r.llegada, INTERVAL ROUND(p.minutos * r.cantidad) MINUTE) > ?', [$desde])
            ->orderBy('r.llegada')->first(['r.*']);
    }

    // ================================================================ Ingreso y estadía

    /** Ingreso: abre el pedido con el servicio de tiempo y empieza la cuenta regresiva (también desde una reserva) */
    public function ingresar(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'hab_id' => 'required_without:res_id|nullable|integer', 'servicio' => 'required_without:res_id|nullable|integer',
            'cantidad' => 'required_without:res_id|nullable|numeric|min:1|max:365', 'precio' => 'nullable|numeric|min:0',
            'cliente' => 'nullable|string|max:150', 'documento' => 'nullable|string|max:15', 'personas' => 'nullable|integer|min:1|max:20',
            'res_id' => 'nullable|integer', 'forzar' => 'nullable|boolean',
        ]);
        $user = Auth::user();

        return $this->responder(function () use ($d, $user, $suc) {
            // Desde una reserva: sus datos mandan
            $reserva = null;
            if (! empty($d['res_id'])) {
                $reserva = DB::table('hotel_reservas')->where('res_id', $d['res_id'])->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
                if (! $reserva || $reserva->estado !== 'PENDIENTE') {
                    throw new \RuntimeException('Esa reserva ya no está pendiente.');
                }
                $d = ['hab_id' => $reserva->hab_id, 'servicio' => $reserva->IdProducto, 'cantidad' => (float) $reserva->cantidad,
                    'precio' => null, 'cliente' => $reserva->cliente, 'documento' => $reserva->documento, 'personas' => $reserva->personas] + $d;
            }
            $hab = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $hab) {
                throw new \RuntimeException('Habitación no válida.');
            }
            if ($hab->hab_est !== 'Libre') {
                throw new \RuntimeException("La habitación {$hab->hab_nom} está {$hab->hab_est}.");
            }
            $serv = $this->servicio((int) $d['servicio']);
            $fin = now()->addMinutes((int) round($serv->minutos * $d['cantidad']));

            // ¿Choca con la reserva de otra persona?
            $choca = $this->reservaQueChoca($hab->hab_id, now(), $fin, $reserva->res_id ?? null);
            if ($choca && empty($d['forzar'])) {
                $this->extraError = ['reserva' => true];
                throw new \RuntimeException("La habitación {$hab->hab_nom} está reservada para {$choca->cliente} a las "
                    .Carbon::parse($choca->llegada)->format('H:i').' del '.Carbon::parse($choca->llegada)->format('d/m').'. ¿Usar otra habitación?');
            }

            $cliente = mb_strtoupper(trim((string) ($d['cliente'] ?? ''))) ?: 'CLIENTE HOSPEDAJE';
            $pedido = Pedido::create([
                'ped_tip' => 'Hotel', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(),
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc, 'ped_est' => 'Aperturado',
                'mozo' => $user->IdUsuario, 'IdUsuario' => $user->IdUsuario, 'ped_cli_nom' => $cliente,
                'ped_num_doc' => $d['documento'] ?? null, 'ped_obs' => 'HAB. '.$hab->hab_nom, 'ped_tot' => 0,
            ]);
            $this->linea($pedido->ped_id, $serv, (float) $d['cantidad'], $this->precio($serv, $d['precio'] ?? null), 'HAB. '.$hab->hab_nom);
            $this->recalcular($pedido->ped_id);

            $hosId = DB::table('hospedajes')->insertGetId([
                'ped_id' => $pedido->ped_id, 'hab_id' => $hab->hab_id, 'id_empresa_negocio' => $suc,
                'cliente' => $cliente, 'documento' => $d['documento'] ?? null, 'personas' => $d['personas'] ?? 1,
                'inicio' => now(), 'fin' => $fin, 'hos_est' => 'ACTIVO', 'IdUsuario' => $user->IdUsuario,
            ]);
            DB::table('habitaciones')->where('hab_id', $hab->hab_id)->update(['hab_est' => 'Ocupado']);
            if ($reserva) {
                DB::table('hotel_reservas')->where('res_id', $reserva->res_id)->update(['estado' => 'INGRESADA', 'hos_id' => $hosId, 'updated_at' => now()]);
            }

            return ['ped_id' => $pedido->ped_id];
        }, 'Ingreso registrado.');
    }

    /** Horas extra: el tiempo SUBE desde la hora de salida programada */
    public function extender(Request $request)
    {
        $this->sucursal();
        $d = $request->validate(['hos_id' => 'required|integer', 'servicio' => 'required|integer',
            'cantidad' => 'required|numeric|min:1|max:365', 'precio' => 'nullable|numeric|min:0']);

        return $this->responder(function () use ($d) {
            $e = $this->estadia((int) $d['hos_id']);
            $serv = $this->servicio((int) $d['servicio']);
            $this->linea($e->ped_id, $serv, (float) $d['cantidad'], $this->precio($serv, $d['precio'] ?? null));
            $this->recalcular($e->ped_id);
            $fin = Carbon::parse($e->fin)->addMinutes((int) round($serv->minutos * $d['cantidad']));
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['fin' => $fin]);

            return ['fin' => $fin->format('Y-m-d H:i:s')];
        }, 'Tiempo agregado.');
    }

    /** Se pasó de su hora: agrega las horas extra que corresponden (redondeando hacia arriba) para ir a cobrarlas */
    public function cobrarExceso(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['hos_id' => 'required|integer']);

        return $this->responder(function () use ($d, $suc) {
            $e = $this->estadia((int) $d['hos_id']);
            $pasado = (int) floor(Carbon::parse($e->fin)->diffInMinutes(now(), false));
            if ($pasado <= $this->tolerancia($suc)) {
                throw new \RuntimeException('Todavía está dentro de su tiempo o de la tolerancia: no hay horas extra que cobrar.');
            }
            $serv = $this->servicioExtra();
            if (! $serv) {
                throw new \RuntimeException('Crea un servicio "HORA EXTRA" en Configurar › Servicios de tiempo.');
            }
            $cant = (int) ceil($pasado / $serv->minutos);
            $this->linea($e->ped_id, $serv, $cant, (float) $serv->propun, 'TIEMPO DE MÁS: '.$pasado.' MIN');
            $this->recalcular($e->ped_id);
            $fin = Carbon::parse($e->fin)->addMinutes($serv->minutos * $cant);
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['fin' => $fin]);
            $this->evento($e, 'EXCESO_COBRADO', ['minutos' => $pasado, 'monto' => $cant * (float) $serv->propun, 'detalle' => $cant.' x '.$serv->pronom]);

            return ['ped_id' => $e->ped_id, 'cantidad' => $cant, 'monto' => round($cant * (float) $serv->propun, 2), 'servicio' => $serv->pronom,
                'fin' => $fin->format('Y-m-d H:i:s')];
        }, 'Horas extra agregadas: ahora cóbralas.');
    }

    /** Consumo a la habitación (minibar, room service). Lo preparado va a cocina igual que una comanda */
    public function consumo(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['hos_id' => 'required|integer', 'producto' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.01|max:999', 'observacion' => 'nullable|string|max:100']);
        $cocina = [];

        $res = $this->responder(function () use ($d, $suc, &$cocina) {
            $e = $this->estadia((int) $d['hos_id']);
            $p = Producto::where('IdProducto', $d['producto'])->where('id_empresa_negocio', $suc)->where('proest', 'Activo')
                ->whereNotIn('promocion', Producto::NO_VENDIBLES)->first();
            if (! $p) {
                throw new \RuntimeException('Producto no válido.');
            }
            if ((int) $p->minutos > 0) {
                throw new \RuntimeException('Es un servicio de tiempo: agrégalo con "Agregar tiempo".');
            }
            if ($falta = Porciones::faltante($suc, [$p->IdProducto => (float) $d['cantidad']], true)) {
                throw new \RuntimeException($falta);
            }
            ControlStock::asegurar($suc, [['producto' => $p, 'cantidad' => (float) $d['cantidad']]]);
            $precio = (float) (Precios::vigentes(collect([$p]))[$p->IdProducto] ?? $p->propun);
            $this->linea($e->ped_id, $p, (float) $d['cantidad'], $precio, $d['observacion'] ?? null);
            $this->recalcular($e->ped_id);
            $cocina = ['ped_id' => $e->ped_id, 'lineas' => [['IdProducto' => $p->IdProducto, 'nombre' => $p->pronom,
                'cantidad' => (float) $d['cantidad'], 'observacion' => $d['observacion'] ?? '']]];
        }, 'Consumo agregado.');

        // Comanda a cocina/bar según la categoría (si tiene impresora) y pantalla de cocina
        if ($cocina) {
            try {
                Impresion::comanda($cocina['ped_id'], $cocina['lineas']);
                Cocina::registrar($cocina['ped_id'], $cocina['lineas']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $res;
    }

    /** Quitar un consumo u hora extra cargado por error (sin cobrar). Necesita autorización de un administrador */
    public function quitarItem(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['ped_det_id' => 'required|integer', 'motivo' => 'required|string|max:100'], [], ['motivo' => 'motivo']);
        $aut = Autorizacion::administrador($request, 'hotel-quitar');
        if (! $aut['ok']) {
            return response()->json($aut);
        }
        $anulado = null;

        $res = $this->responder(function () use ($d, $suc, $aut, &$anulado) {
            $linea = PedidoDetalle::where('ped_det_id', $d['ped_det_id'])->where('estadoitem', '!=', 'Eliminado')->lockForUpdate()->first();
            $hos = $linea ? DB::table('hospedajes')->where('ped_id', $linea->ped_id)->where('id_empresa_negocio', $suc)->value('hos_id') : null;
            if (! $hos) {
                throw new \RuntimeException('Ese consumo ya no existe.');
            }
            $e = $this->estadia((int) $hos);
            if ((float) $linea->item_facturado > 0) {
                throw new \RuntimeException('Ya se cobró: no se puede quitar. Anula el comprobante o emite una nota de crédito.');
            }
            if ((int) $linea->ped_det_id === $this->lineaPrincipal($e->ped_id)) {
                throw new \RuntimeException('Es la habitación en sí: para quitarla anula el ingreso.');
            }
            $prod = Producto::find($linea->IdProducto);
            // Si era tiempo (hora extra), la hora de salida vuelve atrás
            if ($prod && (int) $prod->minutos > 0) {
                $fin = Carbon::parse($e->fin)->subMinutes((int) round($prod->minutos * $linea->ped_det_can));
                DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['fin' => $fin->max(Carbon::parse($e->inicio))]);
            } else {
                $anulado = ['ped_id' => $e->ped_id, 'lineas' => [['IdProducto' => $linea->IdProducto, 'nombre' => $linea->descripcion,
                    'cantidad' => (float) $linea->ped_det_can, 'observacion' => 'Motivo: '.$d['motivo']]], 'motivo' => $d['motivo']];
            }
            $linea->update(['estadoitem' => 'Eliminado', 'item_obs' => mb_substr('[QUITADO: '.$d['motivo'].' - '.Auth::user()->apeusu.']', 0, 255)]);
            $this->recalcular($e->ped_id);
            $this->evento($e, 'CONSUMO_QUITADO', ['monto' => (float) $linea->ped_det_can * (float) $linea->ped_det_pre,
                'detalle' => ControlStock::numero((float) $linea->ped_det_can).' x '.$linea->descripcion.' · '.$d['motivo'], 'autorizado_por' => $aut['autorizado_por']]);
        }, 'Consumo quitado.');

        // Aviso de anulación a cocina/bar (si ya lo estaban preparando)
        if ($anulado) {
            try {
                Impresion::comanda($anulado['ped_id'], $anulado['lineas'], true, $anulado['motivo']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $res;
    }

    /** Cambiar de habitación sin anular ni volver a registrar: la de origen pasa a Limpieza */
    public function cambiarHabitacion(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['hos_id' => 'required|integer', 'hab_id' => 'required|integer', 'motivo' => 'nullable|string|max:100']);

        return $this->responder(function () use ($d, $suc) {
            $e = $this->estadia((int) $d['hos_id']);
            $origen = DB::table('habitaciones')->where('hab_id', $e->hab_id)->first();
            $destino = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $destino || (int) $destino->hab_id === (int) $e->hab_id) {
                throw new \RuntimeException('Elige otra habitación.');
            }
            if ($destino->hab_est !== 'Libre') {
                throw new \RuntimeException("La habitación {$destino->hab_nom} está {$destino->hab_est}.");
            }
            if ($choca = $this->reservaQueChoca($destino->hab_id, now(), Carbon::parse($e->fin))) {
                throw new \RuntimeException("La habitación {$destino->hab_nom} está reservada para {$choca->cliente} a las ".Carbon::parse($choca->llegada)->format('H:i').'.');
            }
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['hab_id' => $destino->hab_id]);
            $obs = (string) Pedido::where('ped_id', $e->ped_id)->value('ped_obs');
            Pedido::where('ped_id', $e->ped_id)->update(['ped_obs' => mb_substr(str_replace('HAB. '.$origen->hab_nom, 'HAB. '.$destino->hab_nom, $obs), 0, 255)]);
            if ($principal = $this->lineaPrincipal($e->ped_id)) {
                PedidoDetalle::where('ped_det_id', $principal)->update(['item_obs' => 'HAB. '.$destino->hab_nom]);
            }
            DB::table('habitaciones')->where('hab_id', $origen->hab_id)->update(['hab_est' => 'Limpieza']);
            DB::table('habitaciones')->where('hab_id', $destino->hab_id)->update(['hab_est' => 'Ocupado']);
            $this->evento($e, 'CAMBIO_HABITACION', ['detalle' => $origen->hab_nom.' → '.$destino->hab_nom.(! empty($d['motivo']) ? ' · '.$d['motivo'] : '')]);
        }, 'Cambio de habitación hecho. La anterior quedó en limpieza.');
    }

    /** Detalle de la estadía para el modal: consumos, lo cobrado y qué se puede quitar */
    public function detalle(int $hosId)
    {
        $e = DB::table('hospedajes')->where('hos_id', $hosId)->where('id_empresa_negocio', $this->sucursal())->firstOrFail();
        $principal = $this->lineaPrincipal($e->ped_id);
        $items = PedidoDetalle::where('ped_id', $e->ped_id)->where('estadoitem', '!=', 'Eliminado')->orderBy('ped_det_id')
            ->get(['ped_det_id', 'descripcion', 'ped_det_can', 'ped_det_pre', 'item_facturado', 'fecha_hora'])
            ->map(fn ($i) => $i->setAttribute('se_quita', (int) $i->ped_det_id !== (int) $principal && (float) $i->item_facturado <= 0));

        return response()->json(['estadia' => $e, 'items' => $items]);
    }

    /** Salida: solo con todo cobrado y sin tiempo de más sin pagar. Cierra el pedido y la habitación pasa a Limpieza */
    public function salida(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['hos_id' => 'required|integer', 'sin_exceso' => 'nullable|boolean', 'motivo' => 'nullable|string|max:100']);

        return $this->responder(function () use ($d, $suc) {
            $e = $this->estadia((int) $d['hos_id']);
            // Se pasó de su hora: primero se cobran las horas extra (si no, el tiempo de más se regala)
            $pasado = (int) floor(Carbon::parse($e->fin)->diffInMinutes(now(), false));
            if ($pasado > $this->tolerancia($suc)) {
                $serv = $this->servicioExtra();
                $horas = $serv ? (int) ceil($pasado / $serv->minutos) : 0;
                if (empty($d['sin_exceso'])) {
                    $this->extraError = ['exceso' => $pasado, 'horas_extra' => $horas, 'monto_extra' => $serv ? round($horas * (float) $serv->propun, 2) : 0,
                        'servicio_extra' => $serv->pronom ?? null];
                    throw new \RuntimeException("Se pasó {$pasado} min de su hora de salida. Agrega las horas extra y cóbralas antes de dar la salida.");
                }
                if (! Auth::user()->esAdmin() || trim((string) ($d['motivo'] ?? '')) === '') {
                    throw new \RuntimeException('Solo el administrador puede dar salida sin cobrar el tiempo de más, y debe escribir el motivo.');
                }
                Pedido::where('ped_id', $e->ped_id)->update(['ped_obs' => mb_substr(trim((string) Pedido::where('ped_id', $e->ped_id)->value('ped_obs'))
                    .' [SALIDA SIN COBRAR '.$pasado.' MIN: '.$d['motivo'].' - '.Auth::user()->apeusu.']', 0, 255)]);
                $this->evento($e, 'SALIDA_SIN_EXCESO', ['minutos' => $pasado, 'monto' => $serv ? $horas * (float) $serv->propun : null, 'detalle' => $d['motivo']]);
            }
            $pendiente = $this->pendiente($e->ped_id);
            if ($pendiente > 0.009) {
                throw new \RuntimeException('Falta cobrar S/ '.number_format($pendiente, 2).'. Cobra antes de dar la salida.');
            }
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['hos_est' => 'FINALIZADO', 'salida' => now()]);
            Pedido::where('ped_id', $e->ped_id)->update(['ped_est' => 'Cerrado', 'fecha_hora_modificacion' => now()]);
            DB::table('habitaciones')->where('hab_id', $e->hab_id)->update(['hab_est' => 'Limpieza']);
        }, 'Salida registrada. La habitación quedó en limpieza.');
    }

    /** Anular un ingreso hecho por error (solo administrador, sin nada cobrado y dentro de su tiempo) */
    public function anular(Request $request)
    {
        $this->sucursal();
        $this->soloAdmin();
        $d = $request->validate(['hos_id' => 'required|integer', 'motivo' => 'required|string|max:100']);

        return $this->responder(function () use ($d) {
            $e = $this->estadia((int) $d['hos_id']);
            if (PedidoDetalle::where('ped_id', $e->ped_id)->where('item_facturado', '>', 0)->exists()) {
                throw new \RuntimeException('Ya pagó (por adelantado o una parte): no se puede anular. Si fue un error, anula el comprobante o emite una nota de crédito.');
            }
            if (now()->greaterThan(Carbon::parse($e->fin))) {
                throw new \RuntimeException('Ya cumplió su tiempo en la habitación: no se puede anular. Cóbrale y dale salida.');
            }
            $total = $this->pendiente($e->ped_id);
            PedidoDetalle::where('ped_id', $e->ped_id)->update(['estadoitem' => 'Eliminado',
                'item_obs' => mb_substr('[ANULADO: '.$d['motivo'].' - '.Auth::user()->apeusu.']', 0, 255)]);
            Pedido::where('ped_id', $e->ped_id)->update(['ped_est' => 'Anulado', 'ped_tot' => 0, 'fecha_hora_modificacion' => now()]);
            DB::table('hospedajes')->where('hos_id', $e->hos_id)->update(['hos_est' => 'ANULADO', 'salida' => now()]);
            DB::table('habitaciones')->where('hab_id', $e->hab_id)->update(['hab_est' => 'Libre']);
            $this->evento($e, 'ANULACION', ['monto' => $total, 'detalle' => $e->cliente.' · '.$d['motivo']]);
        }, 'Ingreso anulado.');
    }

    /** Limpieza terminada / mantenimiento */
    public function cambiarEstado(Request $request)
    {
        $d = $request->validate(['hab_id' => 'required|integer', 'estado' => 'required|in:Libre,Limpieza,Mantenimiento']);

        return $this->responder(function () use ($d) {
            $hab = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $this->sucursal())->lockForUpdate()->first();
            if (! $hab || $hab->hab_est === 'Ocupado') {
                throw new \RuntimeException('La habitación está ocupada; primero dale salida.');
            }
            DB::table('habitaciones')->where('hab_id', $hab->hab_id)->update(['hab_est' => $d['estado']]);
        }, 'Habitación actualizada.');
    }

    // ================================================================ Reservas

    /** Reservas pendientes (desde ayer en adelante) */
    public function reservas()
    {
        $suc = $this->sucursal();

        return response()->json(DB::table('hotel_reservas as r')->join('habitaciones as h', 'h.hab_id', '=', 'r.hab_id')
            ->join('productos as p', 'p.IdProducto', '=', 'r.IdProducto')
            ->where('r.id_empresa_negocio', $suc)->where('r.estado', 'PENDIENTE')->where('r.llegada', '>=', now()->subDay())
            ->orderBy('r.llegada')->limit(200)
            ->get(['r.*', 'h.hab_nom', 'h.hab_est', 'p.pronom as servicio', 'p.minutos', 'p.propun']));
    }

    public function guardarReserva(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'res_id' => 'nullable|integer', 'hab_id' => 'required|integer', 'llegada' => 'required|date', 'servicio' => 'required|integer',
            'cantidad' => 'required|numeric|min:1|max:365', 'cliente' => 'required|string|max:150', 'documento' => 'nullable|string|max:15',
            'telefono' => 'nullable|string|max:20', 'personas' => 'nullable|integer|min:1|max:20', 'nota' => 'nullable|string|max:200',
        ], [], ['llegada' => 'fecha y hora de llegada', 'cliente' => 'nombre del huésped', 'hab_id' => 'habitación']);

        return $this->responder(function () use ($d, $suc) {
            $hab = DB::table('habitaciones')->where('hab_id', $d['hab_id'])->where('id_empresa_negocio', $suc)->first();
            if (! $hab) {
                throw new \RuntimeException('Habitación no válida.');
            }
            $serv = $this->servicio((int) $d['servicio']);
            $llegada = Carbon::parse($d['llegada']);
            if ($llegada->lt(now()->subMinutes(30))) {
                throw new \RuntimeException('La fecha de llegada ya pasó.');
            }
            $hasta = $llegada->copy()->addMinutes((int) round($serv->minutos * $d['cantidad']));
            if ($choca = $this->reservaQueChoca($hab->hab_id, $llegada, $hasta, $d['res_id'] ?? null)) {
                throw new \RuntimeException("Ya hay una reserva de {$choca->cliente} en la {$hab->hab_nom} para el "
                    .Carbon::parse($choca->llegada)->format('d/m H:i').'.');
            }
            // Si ahora está ocupada y su salida es después de la llegada, no se puede
            $ocupada = DB::table('hospedajes')->where('hab_id', $hab->hab_id)->where('hos_est', 'ACTIVO')->first();
            if ($ocupada && Carbon::parse($ocupada->fin)->gt($llegada)) {
                throw new \RuntimeException("La {$hab->hab_nom} está ocupada hasta las ".Carbon::parse($ocupada->fin)->format('d/m H:i').'. Elige otra hora u otra habitación.');
            }
            $fila = ['hab_id' => $hab->hab_id, 'llegada' => $llegada, 'IdProducto' => $serv->IdProducto, 'cantidad' => $d['cantidad'],
                'cliente' => mb_strtoupper(trim($d['cliente'])), 'documento' => $d['documento'] ?? null, 'telefono' => $d['telefono'] ?? null,
                'personas' => $d['personas'] ?? 1, 'nota' => $d['nota'] ?? null, 'updated_at' => now()];
            if (! empty($d['res_id'])) {
                $n = DB::table('hotel_reservas')->where('res_id', $d['res_id'])->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')->update($fila);
                if (! $n) {
                    throw new \RuntimeException('Esa reserva ya no está pendiente.');
                }
            } else {
                DB::table('hotel_reservas')->insert($fila + ['estado' => 'PENDIENTE', 'IdUsuario' => Auth::id(), 'id_empresa_negocio' => $suc, 'created_at' => now()]);
            }
        }, 'Reserva guardada.');
    }

    public function cancelarReserva(Request $request)
    {
        $suc = $this->sucursal();
        $d = $request->validate(['res_id' => 'required|integer', 'motivo' => 'nullable|string|max:100']);
        $reserva = DB::table('hotel_reservas')->where('res_id', $d['res_id'])->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')->first();
        $n = $reserva ? DB::table('hotel_reservas')->where('res_id', $reserva->res_id)->where('estado', 'PENDIENTE')->update([
            'estado' => 'CANCELADA', 'updated_at' => now(),
            'nota' => mb_substr(trim($reserva->nota.' [CANCELADA: '.($d['motivo'] ?? '').' - '.Auth::user()->apeusu.']'), 0, 200),
        ]) : 0;

        return response()->json(['ok' => (bool) $n, 'mensaje' => $n ? 'Reserva cancelada.' : 'Esa reserva ya no está pendiente.']);
    }

    // ================================================================ Reporte

    public function reporte(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->tieneModulo('/hotel/reporte') || ($user->esAdminOCaja() && $user->tieneModulo('/hotel')), 403, 'No tienes acceso al reporte del hotel.');
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        $desde = Carbon::parse($request->get('desde', now()->subDays(6)->toDateString()))->startOfDay();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        if ($desde->diffInDays($hasta) > 366) {
            $desde = $hasta->copy()->subDays(366)->startOfDay();
        }

        return view('empresas.hotel.reporte', HotelReporte::generar((int) $user->id_empresa_negocio, $desde, $hasta) + [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
        ]);
    }

    // ================================================================ Configuración (administrador)

    public function configurar(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['tolerancia' => 'required|integer|min:0|max:180'], [], ['tolerancia' => 'tolerancia']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->update(['hotel_tolerancia' => $d['tolerancia']]);

        return response()->json(['ok' => true, 'mensaje' => "Tolerancia: {$d['tolerancia']} min después de la hora de salida."]);
    }

    public function guardarHabitacion(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['hab_id' => 'nullable|integer', 'hab_nom' => 'required|string|max:50',
            'hab_tip' => 'nullable|string|max:40', 'hab_piso' => 'required|string|max:30', 'hab_obs' => 'nullable|string|max:150']);
        $fila = ['hab_nom' => mb_strtoupper(trim($d['hab_nom'])), 'hab_tip' => mb_strtoupper(trim((string) ($d['hab_tip'] ?? ''))) ?: null,
            'hab_piso' => mb_strtoupper(trim($d['hab_piso'])), 'hab_obs' => $d['hab_obs'] ?? null];

        $repetida = DB::table('habitaciones')->where('id_empresa_negocio', $this->sucursal())->where('hab_nom', $fila['hab_nom'])
            ->when($d['hab_id'] ?? null, fn ($q, $id) => $q->where('hab_id', '!=', $id))->exists();
        if ($repetida) {
            return response()->json(['ok' => false, 'mensaje' => "Ya existe la habitación {$fila['hab_nom']}."]);
        }

        if (! empty($d['hab_id'])) {
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
        if (! $hab) {
            return response()->json(['ok' => false, 'mensaje' => 'Habitación no válida.']);
        }
        if ($hab->hab_est === 'Ocupado') {
            return response()->json(['ok' => false, 'mensaje' => 'La habitación está ocupada.']);
        }
        if (DB::table('hospedajes')->where('hab_id', $id)->exists() || DB::table('hotel_reservas')->where('hab_id', $id)->exists()) {
            // Tiene historial: se deja fuera de servicio en vez de borrarla
            DB::table('habitaciones')->where('hab_id', $id)->update(['hab_est' => 'Mantenimiento']);

            return response()->json(['ok' => true, 'mensaje' => 'Tiene estadías o reservas registradas: quedó en Mantenimiento en vez de borrarse.']);
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

        if (! empty($d['IdProducto'])) {
            Producto::where('IdProducto', $d['IdProducto'])->where('id_empresa_negocio', $user->id_empresa_negocio)->update($datos);
        } else {
            // Categoría HOSPEDAJE (sin impresora: no va a cocina)
            $cat = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cat_nom', 'HOSPEDAJE')->value('cat_id')
                ?? DB::table('categorias')->insertGetId(['cat_nom' => 'HOSPEDAJE', 'IdEmpresa' => $user->IdEmpresa,
                    'id_empresa_negocio' => $user->id_empresa_negocio, 'tip_pro_id' => DB::table('tipo_producto')
                        ->where('id_empresa_negocio', $user->id_empresa_negocio)->value('tip_pro_id') ?? 1,
                    'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);
            Producto::create($datos + ['procod' => 'H'.time(), 'umecod' => 'ZZ', 'costo' => 0, 'promocion' => 2, 'cat_id' => $cat,
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
