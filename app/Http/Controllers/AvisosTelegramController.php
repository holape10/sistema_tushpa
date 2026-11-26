<?php

namespace App\Http\Controllers;

use App\Support\AvisosTelegram;
use App\Support\Bitacora;
use App\Support\ResumenTelegram;
use App\Support\TelegramCitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mantenimiento › Avisos por Telegram: la central de lo que le llega a cada administrador (resumen de ventas a su hora,
 * alertas al momento, cambios de precios, movimientos importantes, pedidos de almacén y clientes descontentos).
 */
class AvisosTelegramController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura los avisos por Telegram.');
    }

    private function administrador(int $usuario): object
    {
        $this->autorizar();
        $a = AvisosTelegram::administradores((string) Auth::user()->IdEmpresa)->firstWhere('IdUsuario', $usuario);
        abort_unless($a, 404);

        return $a;
    }

    public function index(): View
    {
        $this->autorizar();
        TelegramCitas::sincronizar();
        $tipos = AvisosTelegram::tipos();

        return view('empresas.avisos_telegram.index', [
            'configurado' => AvisosTelegram::disponible(),
            'tipos' => $tipos,
            'admins' => AvisosTelegram::administradores((string) Auth::user()->IdEmpresa)->map(fn ($a) => [
                'id' => (int) $a->IdUsuario, 'nombre' => trim((string) ($a->apeusu ?: $a->name)), 'vinculado' => (bool) $a->telegram_chat_id, 'enlace' => null,
                'hora' => $a->telegram_resumen_hora ?? AvisosTelegram::HORA_DEFECTO,
                'avisos' => collect($tipos)->mapWithKeys(fn ($t, $col) => [$col => (bool) $a->$col])->all(),
            ])->values(),
        ]);
    }

    public function enlace(int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        $enlace = TelegramCitas::enlace($usuario);

        return response()->json($enlace ? ['ok' => true, 'enlace' => $enlace]
            : ['ok' => false, 'mensaje' => 'Telegram no está configurado en el servidor (falta el token del bot).']);
    }

    /** ¿Ya tocaron Iniciar en el bot? */
    public function verificar(): JsonResponse
    {
        $this->autorizar();
        TelegramCitas::recibir();
        TelegramCitas::sincronizar();

        return response()->json(['ok' => true, 'vinculados' => AvisosTelegram::administradores((string) Auth::user()->IdEmpresa)
            ->whereNotNull('telegram_chat_id')->pluck('IdUsuario')->map(fn ($id) => (int) $id)->values()]);
    }

    /** Lo que recibe cada administrador y a qué hora el resumen */
    public function guardar(Request $request, int $usuario): JsonResponse
    {
        $a = $this->administrador($usuario);
        $tipos = array_keys(AvisosTelegram::tipos());
        $d = $request->validate([
            'avisos' => 'array', 'avisos.*' => 'boolean',
            'hora' => ['required', Rule::in(AvisosTelegram::HORAS)],
        ]);
        $cambios = collect($tipos)->mapWithKeys(fn ($col) => [$col => (bool) ($d['avisos'][$col] ?? false)])->all();
        $cambios['telegram_resumen_hora'] = $d['hora'];
        // Si cambió la hora, hoy puede volver a salir a la hora nueva
        if (($a->telegram_resumen_hora ?? null) !== $d['hora']) {
            $cambios['telegram_resumen_enviado'] = null;
        }
        DB::table('users')->where('IdUsuario', $usuario)->update($cambios);
        Bitacora::registrar('config.avisos_telegram', 'Cambió los avisos por Telegram de '.trim((string) ($a->apeusu ?: $a->name)));

        return response()->json(['ok' => true, 'mensaje' => '✓ Guardado.']);
    }

    /** Prueba: el resumen de hoy, para ver cómo llega */
    public function probar(int $usuario): JsonResponse
    {
        $a = $this->administrador($usuario);
        if (! $a->telegram_chat_id) {
            return response()->json(['ok' => false, 'mensaje' => 'Primero vincula su Telegram.']);
        }
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial');
        [$ok, $detalle] = TelegramCitas::enviar((string) $a->telegram_chat_id,
            "✅ Así te llegarán los avisos de {$negocio}.\n\n".ResumenTelegram::mensaje((int) Auth::user()->id_empresa_negocio, now()));

        return response()->json(['ok' => $ok, 'mensaje' => $ok ? '✓ Le llegó un mensaje de prueba a su Telegram.' : $detalle]);
    }

    /** Deja de recibir todo y desvincula su Telegram */
    public function quitar(int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        TelegramCitas::desvincular($usuario);
        DB::table('users')->where('IdUsuario', $usuario)->update(collect(array_keys(AvisosTelegram::tipos()))->mapWithKeys(fn ($c) => [$c => false])->all());

        return response()->json(['ok' => true]);
    }
}
