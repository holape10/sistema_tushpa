<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AgendaPanel;
use App\Support\TelegramCitas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Agenda del panel: la de cada usuario y la general de su marca, con avisos por Telegram */
class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $yo = Auth::guard('superadmin')->user();
        try {
            $mes = Carbon::createFromFormat('!Y-m', (string) $request->get('mes', now()->format('Y-m')));
        } catch (\Throwable) {
            $mes = now()->startOfMonth();
        }
        // La grilla del mes empieza el lunes de la primera semana
        $desde = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $hasta = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return view('admin.agenda', [
            'mes' => $mes, 'desde' => $desde, 'hasta' => $hasta,
            'eventos' => AgendaPanel::entre($yo, $desde, $hasta),
            'proximos' => AgendaPanel::entre($yo, now()->startOfDay(), now()->addDays(14)->endOfDay())->where('inicio', '>=', now()->subHour()->toDateTimeString())->take(12),
            'yo' => $yo, 'telegram' => TelegramCitas::configurado(), 'telegrams' => TelegramCitas::vinculosPanel((int) $yo->id),
            'marca' => $yo->marca?->nombre ?? 'TUSHPA',
        ]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate([
            'titulo' => 'required|string|max:150', 'detalle' => 'nullable|string|max:1000', 'fecha' => 'required|date',
            'hora' => 'nullable|date_format:H:i', 'todo_el_dia' => 'nullable|boolean', 'general' => 'nullable|boolean',
            'color' => 'nullable|string|max:10', 'avisos' => 'nullable|array', 'avisos.*' => 'integer',
        ], [], ['titulo' => 'título']);
        $id = AgendaPanel::guardar(Auth::guard('superadmin')->user(), $d, $id);

        return redirect()->route('admin.agenda.index', ['mes' => Carbon::parse($d['fecha'])->format('Y-m')])->with('ok', 'Evento guardado.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        AgendaPanel::eliminar(Auth::guard('superadmin')->user(), $id);

        return back()->with('ok', 'Evento eliminado.');
    }

    // ---------- Telegram del usuario del panel ----------

    public function telegramEnlace(): JsonResponse
    {
        $id = (int) Auth::guard('superadmin')->id();
        if (count(TelegramCitas::chatsPanel($id)) >= TelegramCitas::MAX_PANEL) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya tienes '.TelegramCitas::MAX_PANEL.' Telegram vinculados. Quita uno para vincular otro.']);
        }
        $enlace = TelegramCitas::enlace($id, TelegramCitas::PANEL);

        return response()->json($enlace ? ['ok' => true, 'enlace' => $enlace] : ['ok' => false, 'mensaje' => 'Falta el token del bot de Telegram en el servidor.']);
    }

    /** Cuántos Telegram tiene vinculados (la pantalla espera a que suba al vincular uno nuevo) */
    public function telegramVerificar(): JsonResponse
    {
        TelegramCitas::recibir();

        return response()->json(['ok' => true, 'vinculados' => count(TelegramCitas::chatsPanel((int) Auth::guard('superadmin')->id()))]);
    }

    /** Quita uno de sus Telegram (o todos, sin indicar cuál) */
    public function telegramQuitar(Request $request): RedirectResponse
    {
        TelegramCitas::quitarPanel((int) Auth::guard('superadmin')->id(), $request->integer('vinculo') ?: null);

        return back()->with('ok', $request->integer('vinculo') ? 'Ese Telegram ya no recibirá los avisos.' : 'Telegram desvinculado: ya no te llegarán los avisos.');
    }

    /** Prueba a todos sus Telegram */
    public function telegramProbar(): JsonResponse
    {
        $chats = TelegramCitas::chatsPanel((int) Auth::guard('superadmin')->id());
        if (! $chats) {
            return response()->json(['ok' => false, 'mensaje' => 'Primero vincula tu Telegram.']);
        }
        $fallos = collect($chats)->reject(fn ($chat) => TelegramCitas::enviar($chat, '📅 Prueba: así te llegarán los avisos de tu agenda y las alertas de tus empresas.')[0])->count();

        return response()->json(['ok' => ! $fallos, 'mensaje' => $fallos ? "No llegó a {$fallos} de ".count($chats).' Telegram.' : '✔ Revisa tu Telegram ('.count($chats).').']);
    }
}
