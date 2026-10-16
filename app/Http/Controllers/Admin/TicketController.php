<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Soporte;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Tickets de soporte en el panel: cada usuario ve los de las empresas que creó; el dueño ve todos */
class TicketController extends Controller
{
    /** Tickets visibles para el usuario del panel, con los datos de su empresa */
    public static function visibles(): Builder
    {
        $yo = Auth::guard('superadmin')->user();

        return DB::connection('central')->table('soporte_tickets as t')->join('clientes as c', 'c.id', '=', 't.cliente_id')
            ->when(! $yo->es_dueno, fn ($q) => $q->where('c.creado_por', $yo->id));
    }

    /** Para el número rojo del menú */
    public static function sinLeer(): int
    {
        try {
            return self::visibles()->where('t.leido_soporte', false)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function ticket(int $id): object
    {
        $ticket = self::visibles()->where('t.id', $id)->first(['t.*', 'c.razon_social', 'c.nombre_comercial', 'c.ruc', 'c.subdominio', 'c.contacto_telefono']);
        abort_unless($ticket, 404);

        return $ticket;
    }

    public function index(Request $request): View
    {
        $estado = in_array($request->get('estado'), ['pendientes', 'cerrados', 'todos'], true) ? $request->get('estado') : 'pendientes';
        $q = trim((string) $request->get('q'));
        $tickets = self::visibles()
            ->when($estado === 'pendientes', fn ($w) => $w->where('t.estado', '!=', 'CERRADO'))
            ->when($estado === 'cerrados', fn ($w) => $w->where('t.estado', 'CERRADO'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('t.asunto', 'like', "%{$q}%")->orWhere('c.razon_social', 'like', "%{$q}%")
                ->orWhere('c.ruc', 'like', "{$q}%")->orWhere('t.id', (int) $q)))
            ->orderBy('t.leido_soporte')->orderByRaw("t.prioridad = 'URGENTE' DESC")->orderByDesc('t.updated_at')
            ->paginate(30, ['t.*', 'c.razon_social', 'c.nombre_comercial', 'c.ruc'])->withQueryString();
        $conteo = self::visibles()->groupBy('t.estado')->select('t.estado', DB::raw('COUNT(*) as total'))->pluck('total', 'estado');

        return view('admin.tickets.index', compact('tickets', 'estado', 'q', 'conteo'));
    }

    public function show(int $id): View
    {
        $ticket = $this->ticket($id);
        Soporte::db()->where('id', $id)->update(['leido_soporte' => true]);

        return view('admin.tickets.show', ['ticket' => $ticket, 'mensajes' => Soporte::mensajes($id)]);
    }

    public function responder(Request $request, int $id): RedirectResponse
    {
        $this->ticket($id);
        $request->validate(['texto' => 'nullable|string|max:5000', 'fotos' => 'nullable|array|max:'.Soporte::MAX_FOTOS,
            'fotos.*' => 'image|mimes:jpg,jpeg,png,webp|max:6144', 'cerrar' => 'nullable|boolean'], [], ['fotos.*' => 'foto']);
        try {
            Soporte::mensaje($id, 'SOPORTE', Auth::guard('superadmin')->user()->nombre, $request->texto, $request->file('fotos', []));
        } catch (RuntimeException $e) {
            return back()->withErrors(['texto' => $e->getMessage()]);
        }
        if ($request->boolean('cerrar')) {
            Soporte::db()->where('id', $id)->update(['estado' => 'CERRADO', 'cerrado_at' => now()]);
        }

        return back()->with('ok', 'Respuesta enviada. La empresa la verá en Soporte de su sistema.');
    }

    public function estado(Request $request, int $id): RedirectResponse
    {
        $this->ticket($id);
        $estado = $request->validate(['estado' => 'required|in:'.implode(',', array_keys(Soporte::ESTADOS))])['estado'];
        Soporte::db()->where('id', $id)->update(['estado' => $estado, 'cerrado_at' => $estado === 'CERRADO' ? now() : null, 'updated_at' => now()]);

        return back()->with('ok', 'Ticket: '.Soporte::ESTADOS[$estado].'.');
    }

    public function archivo(int $id, int $mensaje, int $n): BinaryFileResponse
    {
        $this->ticket($id);
        $ruta = Soporte::archivo($id, $mensaje, $n);
        abort_unless($ruta && is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=86400']);
    }
}
