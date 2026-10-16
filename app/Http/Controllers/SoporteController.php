<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Support\Soporte;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Soporte desde el sistema de cada empresa: contacto (todos) y tickets (solo el administrador).
 * Los tickets viven en la base central y los atiende el panel admin.
 */
class SoporteController extends Controller
{
    private const FOTOS = 'nullable|array|max:'.Soporte::MAX_FOTOS;

    private const FOTO = 'image|mimes:jpg,jpeg,png,webp|max:6144';

    /** Empresa del subdominio, si este usuario puede usar tickets */
    private function clienteId(): ?int
    {
        $cliente = Tenancy::cliente();

        return $cliente && Auth::user()->esAdmin() && Soporte::disponible() ? (int) $cliente->id : null;
    }

    private function ticket(int $id): object
    {
        $clienteId = $this->clienteId();
        abort_unless($clienteId, 403, 'Solo el administrador de la empresa envía tickets de soporte.');
        $ticket = Soporte::db()->where('id', $id)->where('cliente_id', $clienteId)->first();
        abort_unless($ticket, 404);

        return $ticket;
    }

    private function autor(): object
    {
        $u = Auth::user();

        return (object) ['id' => $u->IdUsuario, 'nombre' => trim((string) ($u->apeusu ?: $u->name))];
    }

    public function index(): View
    {
        $clienteId = $this->clienteId();
        $tickets = $clienteId ? Soporte::db()->where('cliente_id', $clienteId)->orderByRaw("estado = 'CERRADO'")->orderByDesc('updated_at')->limit(50)->get() : collect();

        return view('empresas.soporte', ['tickets' => $tickets, 'puedeTickets' => (bool) $clienteId]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clienteId = $this->clienteId();
        abort_unless($clienteId, 403, 'Solo el administrador de la empresa envía tickets de soporte.');
        $d = $request->validate([
            'asunto' => 'required|string|max:150', 'tipo' => 'required|in:'.implode(',', array_keys(Soporte::TIPOS)),
            'prioridad' => 'required|in:NORMAL,URGENTE', 'texto' => 'required|string|max:5000', 'telefono' => 'nullable|string|max:20',
            'fotos' => self::FOTOS, 'fotos.*' => self::FOTO,
        ], ['fotos.max' => 'Hasta '.Soporte::MAX_FOTOS.' fotos por mensaje.'], ['texto' => 'descripción', 'fotos.*' => 'foto']);

        $sucursal = (string) EmpresaNegocio::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial');
        $id = Soporte::crear($clienteId, $this->autor(), $sucursal, $d, $request->file('fotos', []));

        return redirect()->route('soporte.ticket', $id)->with('success', "Ticket N° {$id} enviado. Te responderemos aquí mismo.");
    }

    public function show(int $id): View
    {
        $ticket = $this->ticket($id);
        Soporte::db()->where('id', $id)->update(['leido_cliente' => true]);

        return view('empresas.soporte_ticket', ['ticket' => $ticket, 'mensajes' => Soporte::mensajes($id)]);
    }

    public function responder(Request $request, int $id): RedirectResponse
    {
        $this->ticket($id);
        $request->validate(['texto' => 'nullable|string|max:5000', 'fotos' => self::FOTOS, 'fotos.*' => self::FOTO],
            ['fotos.max' => 'Hasta '.Soporte::MAX_FOTOS.' fotos por mensaje.'], ['fotos.*' => 'foto']);
        try {
            Soporte::mensaje($id, 'CLIENTE', $this->autor()->nombre, $request->texto, $request->file('fotos', []));
        } catch (RuntimeException $e) {
            return back()->withErrors(['texto' => $e->getMessage()]);
        }

        return back()->with('success', 'Mensaje enviado.');
    }

    public function cerrar(int $id): RedirectResponse
    {
        $this->ticket($id);
        Soporte::db()->where('id', $id)->update(['estado' => 'CERRADO', 'cerrado_at' => now(), 'leido_cliente' => true, 'updated_at' => now()]);

        return back()->with('success', 'Ticket cerrado. ¡Gracias!');
    }

    public function archivo(int $id, int $mensaje, int $n): BinaryFileResponse
    {
        $this->ticket($id);
        $ruta = Soporte::archivo($id, $mensaje, $n);
        abort_unless($ruta && is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=86400']);
    }
}
