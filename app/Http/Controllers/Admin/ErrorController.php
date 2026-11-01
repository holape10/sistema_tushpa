<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MonitorErrores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Errores del sistema (monitor): qué falló, en qué empresa, cuántas veces; el dueño los marca como resueltos */
class ErrorController extends Controller
{
    private function soloDueno(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema ve los errores.');
    }

    /** Errores sin resolver (para el aviso del menú) */
    public static function pendientes(): int
    {
        try {
            return DB::connection('central')->table('errores_sistema')->where('resuelto', false)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function index(Request $request): View
    {
        $this->soloDueno();
        $verResueltos = $request->boolean('resueltos');

        return view('admin.errores', [
            'errores' => DB::connection('central')->table('errores_sistema')->where('resuelto', $verResueltos)
                ->orderByDesc('ultima_vez')->paginate(50)->withQueryString(),
            'verResueltos' => $verResueltos,
            'pendientes' => self::pendientes(),
            'telegram' => (bool) (config('services.telegram.token') && config('services.telegram.chat_id')),
        ]);
    }

    public function resolver(int $id): RedirectResponse
    {
        $this->soloDueno();
        DB::connection('central')->table('errores_sistema')->where('id', $id)->update(['resuelto' => true]);

        return back()->with('ok', 'Marcado como resuelto. Si vuelve a pasar, se reabre y te avisa.');
    }

    public function resolverTodos(): RedirectResponse
    {
        $this->soloDueno();
        $n = DB::connection('central')->table('errores_sistema')->where('resuelto', false)->update(['resuelto' => true]);

        return back()->with('ok', "{$n} error(es) marcados como resueltos.");
    }

    /** Manda un mensaje de prueba al Telegram para ver que llega */
    public function probar(): RedirectResponse
    {
        $this->soloDueno();

        return MonitorErrores::avisar("✅ Prueba del monitor de errores de TUSHPA.\nSi te llegó este mensaje, los avisos de errores te llegarán aquí.")
            ? back()->with('ok', 'Mensaje de prueba enviado a tu Telegram.')
            : back()->with('error', 'No se pudo enviar: revisa TELEGRAM_BOT_TOKEN y TELEGRAM_CHAT_ID en el archivo .env del servidor.');
    }
}
