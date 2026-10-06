<?php
namespace App\Http\Controllers;

use App\Support\Cocina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Pantalla de cocina (KDS): tarjetas por pedido, tiempo transcurrido con colores y "bump" con un toque */
class CocinaController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    /** Estación elegida en la pantalla (null = todas) */
    private function estacion(Request $request): ?int
    {
        return $request->filled('estacion') ? (int) $request->estacion : null;
    }

    public function index(Request $request)
    {
        $sucursal = $this->sucursal();
        return view('empresas.cocina.index', [
            'estaciones' => DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->where('activo', 1)->orderBy('descripcion')->get(['Id', 'descripcion']),
            'estacion' => $this->estacion($request),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(['kds_amarillo', 'kds_rojo', 'nombre_comercial']),
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    /** Lo que la pantalla muestra (se consulta cada pocos segundos) */
    public function datos(Request $request)
    {
        $sucursal = $this->sucursal();
        $estacion = $this->estacion($request);

        $items = fn() => DB::table('cocina_items as i')->join('cocina_tickets as t', 't.id', '=', 'i.ticket_id')
            ->where('t.id_empresa_negocio', $sucursal)
            ->when($estacion, fn($q) => $q->where('i.estacion', $estacion));

        // Tarjetas con algo pendiente en esta estación (las de hace más de 24 h se ignoran)
        $pendientes = $items()->whereNull('i.listo')->where('t.creado', '>=', now()->subDay())
            ->orderBy('t.creado')->orderBy('i.id')
            ->get(['i.id', 'i.ticket_id', 'i.descripcion', 'i.cantidad', 'i.observacion', 'i.anulado', 'i.listo',
                't.ped_id', 't.destino', 't.ped_tip', 't.mozo', 't.tipo', 't.creado']);

        // También se muestran las líneas ya listas de esas mismas tarjetas (tachadas), para no perder el contexto
        $ticketIds = $pendientes->pluck('ticket_id')->unique();
        $listasDeEsas = $items()->whereIn('i.ticket_id', $ticketIds)->whereNotNull('i.listo')
            ->get(['i.id', 'i.ticket_id', 'i.descripcion', 'i.cantidad', 'i.observacion', 'i.anulado', 'i.listo']);

        $tarjetas = $pendientes->groupBy('ticket_id')->map(function ($g, $ticketId) use ($listasDeEsas) {
            $t = $g->first();
            $lineas = $g->concat($listasDeEsas->where('ticket_id', $ticketId))->sortBy('id')->values()
                ->map(fn($i) => ['id' => $i->id, 'descripcion' => $i->descripcion, 'cantidad' => (float) $i->cantidad,
                    'observacion' => $i->observacion, 'anulado' => (bool) $i->anulado, 'listo' => (bool) $i->listo]);
            return [
                'id' => (int) $ticketId, 'ped_id' => $t->ped_id, 'destino' => $t->destino, 'ped_tip' => $t->ped_tip,
                'mozo' => $t->mozo, 'tipo' => $t->tipo, 'creado' => \Carbon\Carbon::parse($t->creado)->toIso8601String(),
                'lineas' => $lineas,
            ];
        })->values();

        // Recién despachadas (para "Recuperar" si se tocó por error)
        $recientes = $items()->whereNotNull('i.listo')->where('i.listo', '>=', now()->subMinutes(30))
            ->groupBy('t.id', 't.destino', 't.tipo')
            ->select('t.id', 't.destino', 't.tipo', DB::raw('MAX(i.listo) as listo'), DB::raw('COUNT(*) as items'))
            ->orderByDesc('listo')->limit(8)->get();

        // Tiempo promedio de preparación de hoy (desde que llegó hasta que se marcó listo)
        $promedio = $items()->whereNotNull('i.listo')->where('i.anulado', 0)->whereDate('t.creado', now()->toDateString())
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, t.creado, i.listo)'));

        return response()->json([
            'ahora' => now()->toIso8601String(),
            'tarjetas' => $tarjetas,
            'recientes' => $recientes,
            'promedio' => $promedio ? (int) round($promedio / 60) : null,
            'despachadosHoy' => $items()->whereNotNull('i.listo')->where('i.anulado', 0)->whereDate('i.listo', now()->toDateString())->count(),
        ]);
    }

    private function ticketPropio(int $id): object
    {
        $t = DB::table('cocina_tickets')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($t, 404);
        return $t;
    }

    /** Un toque en el plato: listo / no listo */
    public function alternarItem($id)
    {
        $item = DB::table('cocina_items')->where('id', $id)->first();
        abort_unless($item, 404);
        $this->ticketPropio((int) $item->ticket_id);

        DB::table('cocina_items')->where('id', $id)->update(['listo' => $item->listo ? null : now()]);
        Cocina::actualizarTicket((int) $item->ticket_id);
        return response()->json(['ok' => true]);
    }

    /** "LISTO": toda la tarjeta (de esta estación) despachada */
    public function listo(Request $request, $id)
    {
        $this->ticketPropio((int) $id);
        DB::table('cocina_items')->where('ticket_id', $id)->whereNull('listo')
            ->when($this->estacion($request), fn($q, $e) => $q->where('estacion', $e))
            ->update(['listo' => now()]);
        Cocina::actualizarTicket((int) $id);
        return response()->json(['ok' => true]);
    }

    /** "Recuperar": vuelve a la pantalla una tarjeta despachada por error */
    public function recuperar(Request $request, $id)
    {
        $this->ticketPropio((int) $id);
        DB::table('cocina_items')->where('ticket_id', $id)->where('listo', '>=', now()->subMinutes(30))
            ->when($this->estacion($request), fn($q, $e) => $q->where('estacion', $e))
            ->update(['listo' => null]);
        DB::table('cocina_tickets')->where('id', $id)->update(['listo' => null, 'entregado' => null]);
        return response()->json(['ok' => true]);
    }

    public function configuracion(Request $request)
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $d = $request->validate(['kds_amarillo' => 'required|integer|min:1|max:120', 'kds_rojo' => 'required|integer|gt:kds_amarillo|max:240'],
            [], ['kds_amarillo' => 'Minutos para amarillo', 'kds_rojo' => 'Minutos para rojo']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->update($d);
        return response()->json(['ok' => true]);
    }

    /** El mozo llevó a la mesa lo que cocina marcó como listo */
    public function entregado($pedId)
    {
        abort_unless(DB::table('pedidos')->where('ped_id', $pedId)->where('id_empresa_negocio', $this->sucursal())->exists(), 404);
        DB::table('cocina_tickets')->where('ped_id', $pedId)->whereNotNull('listo')->whereNull('entregado')->update(['entregado' => now()]);
        return response()->json(['ok' => true]);
    }
}
