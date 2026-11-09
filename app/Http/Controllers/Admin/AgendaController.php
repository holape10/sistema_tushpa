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
use Illuminate\Support\Facades\DB;
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
            'yo' => $yo, 'telegram' => TelegramCitas::configurado(),
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
        $enlace = TelegramCitas::enlace((int) Auth::guard('superadmin')->id(), TelegramCitas::PANEL);

        return response()->json($enlace ? ['ok' => true, 'enlace' => $enlace] : ['ok' => false, 'mensaje' => 'Falta el token del bot de Telegram en el servidor.']);
    }

    public function telegramVerificar(): JsonResponse
    {
        TelegramCitas::recibir();

        return response()->json(['ok' => true, 'vinculado' => (bool) DB::connection('central')->table('superadmins')
            ->where('id', Auth::guard('superadmin')->id())->value('telegram_chat_id')]);
    }

    public function telegramQuitar(): RedirectResponse
    {
        $id = (int) Auth::guard('superadmin')->id();
        DB::connection('central')->table('superadmins')->where('id', $id)->update(['telegram_chat_id' => null]);
        DB::connection('central')->table('telegram_vinculos')->where('base', TelegramCitas::PANEL)->where('IdUsuario', $id)->delete();

        return back()->with('ok', 'Telegram desvinculado: ya no te llegarán los avisos.');
    }

    public function telegramProbar(): JsonResponse
    {
        $chat = DB::connection('central')->table('superadmins')->where('id', Auth::guard('superadmin')->id())->value('telegram_chat_id');
        if (! $chat) {
            return response()->json(['ok' => false, 'mensaje' => 'Primero vincula tu Telegram.']);
        }
        [$ok, $detalle] = TelegramCitas::enviar((string) $chat, '📅 Prueba: así te llegarán los avisos de tu agenda.');

        return response()->json(['ok' => $ok, 'mensaje' => $ok ? '✔ Revisa tu Telegram.' : $detalle]);
    }
}
