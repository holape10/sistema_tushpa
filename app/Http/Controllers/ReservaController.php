<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;
use App\Support\MesasUnidas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Reservas (tablas del sistema antiguo: reservas, reserva_detalle).
 * Al llegar el cliente se "atiende" desde Comandas: se le da la mesa que pidió (o otra si está ocupada)
 * y la comanda se abre con los platos que reservó, listos para enviar a cocina.
 */
class ReservaController extends Controller
{
    public const ACTIVAS = ['Pendiente', 'Confirmada'];

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function reserva($id): object
    {
        $r = DB::table('reservas')->where('res_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($r, 404);

        return $r;
    }

    public function index(Request $request)
    {
        $sucursal = $this->sucursal();
        $fecha = Carbon::parse($request->get('fecha') ?: now()->toDateString())->toDateString();

        $reservas = DB::table('reservas as r')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'r.mes_id')->leftJoin('pisos as p', 'p.pis_id', '=', 'r.pis_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
            ->where('r.id_empresa_negocio', $sucursal)->where('r.fecha_reserva', $fecha)
            ->orderBy('r.hora_inicio')
            ->get(['r.*', 'm.mes_nom', 'p.pis_nom', 'u.apeusu as registrado_por']);
        $detalles = DB::table('reserva_detalle as d')->leftJoin('productos as pr', 'pr.IdProducto', '=', 'd.IdProducto')
            ->whereIn('d.res_id', $reservas->pluck('res_id'))->get(['d.*', 'pr.pronom'])->groupBy('res_id');

        // Cantidad de reservas activas de los próximos 7 días (para navegar rápido)
        $proximos = DB::table('reservas')->where('id_empresa_negocio', $sucursal)->whereIn('estado', self::ACTIVAS)
            ->whereBetween('fecha_reserva', [now()->toDateString(), now()->addDays(6)->toDateString()])
            ->groupBy('fecha_reserva')->select('fecha_reserva', DB::raw('COUNT(*) as n'))->pluck('n', 'fecha_reserva');

        return view('empresas.reservas.index', [
            'fecha' => $fecha, 'reservas' => $reservas, 'detalles' => $detalles, 'proximos' => $proximos,
            'pisos' => DB::table('pisos')->where('id_empresa_negocio', $sucursal)->orderBy('pis_nom')->get(['pis_id', 'pis_nom']),
            'mesas' => DB::table('mesas')->where('id_empresa_negocio', $sucursal)->orderBy('mes_nom')->get(['mes_id', 'mes_nom', 'pis_id']),
            'productos' => DB::table('productos')->where('id_empresa_negocio', $sucursal)->where('proest', 'Activo')
                ->whereNotIn('promocion', Producto::NO_VENDIBLES)->orderBy('pronom')->get(['IdProducto', 'pronom', 'propun']),
        ]);
    }

    public function guardar(Request $request, $id = null)
    {
        $sucursal = $this->sucursal();
        $actual = $id ? $this->reserva($id) : null;

        $d = $request->validate([
            'nombre_cliente' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:20',
            'cantidad_personas' => 'required|integer|min:1|max:500',
            'fecha_reserva' => ['required', 'date', $actual ? 'nullable' : 'after_or_equal:today'],
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'pis_id' => ['nullable', Rule::exists('pisos', 'pis_id')->where('id_empresa_negocio', $sucursal)],
            'mes_id' => ['nullable', Rule::exists('mesas', 'mes_id')->where('id_empresa_negocio', $sucursal)],
            'observacion' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.IdProducto' => ['required', Rule::exists('productos', 'IdProducto')->where('id_empresa_negocio', $sucursal)],
            'items.*.cantidad' => 'required|numeric|min:0.5|max:999',
            'items.*.nota' => 'nullable|string|max:100',
        ], ['hora_fin.after' => 'La hora de salida debe ser después de la hora de llegada.',
            'fecha_reserva.after_or_equal' => 'La fecha de la reserva no puede ser anterior a hoy.'],
            ['nombre_cliente' => 'Nombre', 'cantidad_personas' => 'Personas', 'fecha_reserva' => 'Fecha', 'hora_inicio' => 'Hora de llegada',
                'hora_fin' => 'Hora de salida', 'mes_id' => 'Mesa', 'items.*.IdProducto' => 'Plato']);

        // La mesa elegida no puede estar reservada a la misma hora (salvo que se confirme igual)
        if (! empty($d['mes_id']) && ! $request->boolean('forzar')) {
            $choque = DB::table('reservas')->where('id_empresa_negocio', $sucursal)->where('mes_id', $d['mes_id'])
                ->where('fecha_reserva', $d['fecha_reserva'])->whereIn('estado', self::ACTIVAS)
                ->when($id, fn ($q) => $q->where('res_id', '!=', $id))
                ->where('hora_inicio', '<', $d['hora_fin'].':00')->where('hora_fin', '>', $d['hora_inicio'].':00')
                ->first(['nombre_cliente', 'hora_inicio', 'hora_fin']);
            if ($choque) {
                return response()->json(['success' => false, 'choque' => true,
                    'message' => "Esa mesa ya está reservada por {$choque->nombre_cliente} de ".substr($choque->hora_inicio, 0, 5)
                        .' a '.substr($choque->hora_fin, 0, 5).'. ¿Guardar igual?']);
            }
        }
        if (! empty($d['mes_id'])) {
            $d['pis_id'] = DB::table('mesas')->where('mes_id', $d['mes_id'])->value('pis_id');
        }

        $precios = DB::table('productos')->whereIn('IdProducto', collect($d['items'] ?? [])->pluck('IdProducto'))->pluck('propun', 'IdProducto');
        $items = collect($d['items'] ?? [])->map(fn ($i) => [
            'IdProducto' => (int) $i['IdProducto'], 'cantidad' => (float) $i['cantidad'], 'precio' => (float) $precios[$i['IdProducto']],
            'subtotal' => round((float) $i['cantidad'] * (float) $precios[$i['IdProducto']], 2), 'nota' => $i['nota'] ?? null,
        ]);

        $datos = [
            'nombre_cliente' => mb_strtoupper(trim($d['nombre_cliente'])), 'telefono' => $d['telefono'] ?? null,
            'cantidad_personas' => $d['cantidad_personas'], 'fecha_reserva' => $d['fecha_reserva'],
            'hora_inicio' => $d['hora_inicio'], 'hora_fin' => $d['hora_fin'], 'pis_id' => $d['pis_id'] ?? null,
            'mes_id' => $d['mes_id'] ?? null, 'observacion' => $d['observacion'] ?? null,
            'total_estimado' => $items->sum('subtotal'), 'updated_at' => now(),
        ];

        DB::transaction(function () use (&$id, $datos, $items, $sucursal) {
            if ($id) {
                DB::table('reservas')->where('res_id', $id)->update($datos);
                DB::table('reserva_detalle')->where('res_id', $id)->delete();
            } else {
                $id = DB::table('reservas')->insertGetId($datos + [
                    'estado' => 'Pendiente', 'IdEmpresa' => Auth::user()->IdEmpresa, 'id_empresa_negocio' => $sucursal,
                    'IdUsuario' => Auth::id(), 'created_at' => now(),
                ]);
            }
            foreach ($items as $i) {
                DB::table('reserva_detalle')->insert([
                    'res_id' => $id, 'IdProducto' => $i['IdProducto'], 'cantidad' => $i['cantidad'], 'precio' => $i['precio'],
                    'precio_unitario' => $i['precio'], 'subtotal' => $i['subtotal'], 'nota_producto' => $i['nota'],
                    'id_empresa_negocio' => $sucursal,
                ]);
            }
        });

        return response()->json(['success' => true, 'res_id' => $id]);
    }

    public function estado(Request $request, $id)
    {
        $r = $this->reserva($id);
        $request->validate(['estado' => 'required|in:Pendiente,Confirmada,Cancelada,No asistió']);
        if ($r->estado === 'Atendida') {
            return response()->json(['success' => false, 'message' => 'La reserva ya fue atendida.']);
        }
        DB::table('reservas')->where('res_id', $id)->update(['estado' => $request->estado, 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    /** Reservas de hoy para el botón de Comandas, con el estado actual de la mesa reservada */
    public function delDia()
    {
        $sucursal = $this->sucursal();
        $ocupadas = array_keys(MesasUnidas::ocupadas((int) $sucursal));

        $reservas = DB::table('reservas as r')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'r.mes_id')->leftJoin('pisos as p', 'p.pis_id', '=', 'r.pis_id')
            ->where('r.id_empresa_negocio', $sucursal)->where('r.fecha_reserva', now()->toDateString())
            ->whereIn('r.estado', self::ACTIVAS)->orderBy('r.hora_inicio')
            ->get(['r.res_id', 'r.nombre_cliente', 'r.telefono', 'r.cantidad_personas', 'r.hora_inicio', 'r.hora_fin', 'r.estado',
                'r.observacion', 'r.mes_id', 'm.mes_nom', 'p.pis_nom', 'r.total_estimado']);
        $platos = DB::table('reserva_detalle as d')->leftJoin('productos as pr', 'pr.IdProducto', '=', 'd.IdProducto')
            ->whereIn('d.res_id', $reservas->pluck('res_id'))->get(['d.res_id', 'd.cantidad', 'pr.pronom'])->groupBy('res_id');

        return response()->json(['reservas' => $reservas->map(fn ($r) => (array) $r + [
            'mesa_libre' => $r->mes_id ? ! in_array($r->mes_id, $ocupadas) : null,
            'platos' => ($platos[$r->res_id] ?? collect())->map(fn ($p) => (float) $p->cantidad.' '.$p->pronom)->values(),
        ])]);
    }

    /**
     * Llega el cliente: se le asigna la mesa (la reservada si está libre, o la que elijan) y se abre la comanda
     * con los platos reservados en el carrito (todavía sin enviar a cocina).
     */
    public function atender(Request $request, $id)
    {
        $r = $this->reserva($id);
        $request->validate(['mes_id' => 'required|integer']);
        if (! in_array($r->estado, self::ACTIVAS, true)) {
            return response()->json(['success' => false, 'message' => 'La reserva ya no está activa.']);
        }

        $mesa = Mesa::where('mes_id', $request->mes_id)->where('id_empresa_negocio', $this->sucursal())->first();
        if (! $mesa) {
            return response()->json(['success' => false, 'message' => 'Mesa no válida.']);
        }
        if (MesasUnidas::pedidoDe($mesa->mes_id)) {
            return response()->json(['success' => false, 'message' => "La {$mesa->mes_nom} está ocupada. Elige otra mesa."]);
        }

        DB::table('reservas')->where('res_id', $id)->update([
            'estado' => 'Atendida', 'mes_id' => $mesa->mes_id, 'pis_id' => $mesa->pis_id, 'updated_at' => now(),
        ]);

        // Carrito de la comanda con lo reservado (precio pactado en la reserva)
        $cart = [];
        $detalle = DB::table('reserva_detalle as d')->join('productos as pr', 'pr.IdProducto', '=', 'd.IdProducto')
            ->where('d.res_id', $id)->get(['d.IdProducto', 'd.cantidad', 'd.precio_unitario', 'd.nota_producto', 'pr.pronom']);
        foreach ($detalle as $d) {
            $key = (string) $d->IdProducto;
            if (isset($cart[$key])) {
                $cart[$key]['cantidad'] += (float) $d->cantidad;

                continue;
            }
            $cart[$key] = [
                'id' => $key, 'IdProducto' => (int) $d->IdProducto, 'nombre' => $d->pronom, 'precio' => (float) $d->precio_unitario,
                'cantidad' => (float) $d->cantidad, 'observaciones' => (string) $d->nota_producto, 'is_old_item' => false,
            ];
        }

        session()->forget(['comanda_cart', 'comanda_order_type', 'comanda_mesa_id', 'comanda_mesa_nombre', 'comanda_pedido_id', 'comanda_eliminados']);
        session()->put([
            'comanda_order_type' => 'salon', 'comanda_mesa_id' => $mesa->mes_id, 'comanda_mesa_nombre' => Mesa::etiqueta($mesa->piso?->pis_nom, $mesa->mes_nom),
            'comanda_cart' => $cart, 'comanda_reserva_id' => (int) $id,
        ]);

        return response()->json(['success' => true, 'redirect' => route('comandas.menu')]);
    }
}
