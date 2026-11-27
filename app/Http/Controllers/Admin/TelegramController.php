<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AvisosTelegram;
use App\Support\TelegramCitas;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * ✈️ Quién usa el bot de Telegram (solo el dueño): cada persona vinculada, de qué empresa, desde cuándo, qué avisos
 * tiene prendidos y cuántas preguntas le hace al bot. Con filtro por empresa y búsqueda por nombre.
 */
class TelegramController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::guard('superadmin')->user()->es_dueno, 404);
        $central = DB::connection('central');
        $empresa = (string) $request->get('empresa', '');
        $q = trim((string) $request->get('q', ''));

        // Una fila por persona y chat (si se vinculó varias veces, la última)
        $vinculos = $central->table('telegram_vinculos as v')
            ->leftJoin('clientes as c', fn ($j) => $j->on('c.base_datos', '=', 'v.base')->where('c.estado', '!=', 'ELIMINADO'))
            ->whereNotNull('v.chat_id')
            ->when($empresa === TelegramCitas::PANEL, fn ($w) => $w->where('v.base', TelegramCitas::PANEL))
            ->when($empresa !== '' && $empresa !== TelegramCitas::PANEL, fn ($w) => $w->where('c.id', (int) $empresa))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('v.nombre', 'like', "%{$q}%")->orWhere('c.razon_social', 'like', "%{$q}%")
                ->orWhere('c.nombre_comercial', 'like', "%{$q}%")->orWhere('c.ruc', 'like', "{$q}%")))
            ->orderByDesc('v.vinculado_at')
            ->get(['v.id', 'v.base', 'v.IdUsuario', 'v.chat_id', 'v.nombre', 'v.vinculado_at', 'c.id as cliente_id', 'c.razon_social', 'c.nombre_comercial', 'c.ruc', 'c.subdominio'])
            ->unique(fn ($v) => $v->base.'|'.$v->IdUsuario.'|'.$v->chat_id)->values();

        $preguntas = $central->table('telegram_preguntas')->whereIn('chat_id', $vinculos->pluck('chat_id')->unique())
            ->groupBy('chat_id')->selectRaw('chat_id, COUNT(*) AS total, MAX(created_at) AS ultima')->get()->keyBy('chat_id');

        $empresas = $central->table('telegram_vinculos as v')->join('clientes as c', 'c.base_datos', '=', 'v.base')->whereNotNull('v.chat_id')
            ->where('c.estado', '!=', 'ELIMINADO')->distinct()->orderBy('c.razon_social')
            ->get(['c.id', 'c.razon_social', 'c.nombre_comercial', 'c.subdominio', 'c.ruc']);

        return view('admin.telegram', [
            'vinculos' => $vinculos, 'preguntas' => $preguntas, 'avisos' => $this->avisos($vinculos), 'empresas' => $empresas,
            'empresa' => $empresa, 'q' => $q, 'tipos' => AvisosTelegram::TIPOS, 'panel' => TelegramCitas::PANEL,
            'totales' => [
                'personas' => $vinculos->pluck('chat_id')->unique()->count(),
                'empresas' => $vinculos->where('base', '!=', TelegramCitas::PANEL)->pluck('base')->unique()->count(),
                'preguntas_mes' => $central->table('telegram_preguntas')->whereIn('chat_id', $vinculos->pluck('chat_id')->unique())
                    ->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }

    /**
     * Qué avisos tiene prendidos cada persona, leído de la base de su empresa (una consulta por empresa; si una base
     * falla, sus filas salen sin avisos y las demás siguen).
     *
     * @param  Collection<int, object>  $vinculos
     * @return array<string, array{avisos: list<string>, hora: ?string}> [base|IdUsuario => ...]
     */
    private function avisos(Collection $vinculos): array
    {
        $resultado = [];
        foreach ($vinculos->where('base', '!=', TelegramCitas::PANEL)->whereNotNull('cliente_id')->groupBy('base') as $base => $filas) {
            try {
                $usuarios = Tenancy::en($base, function () use ($filas) {
                    $columnas = array_values(array_filter(array_keys(AvisosTelegram::TIPOS), fn ($c) => Schema::hasColumn('users', $c)));
                    $hora = Schema::hasColumn('users', 'telegram_resumen_hora') ? ['telegram_resumen_hora'] : [];

                    return DB::table('users')->whereIn('IdUsuario', $filas->pluck('IdUsuario')->unique())->get(array_merge(['IdUsuario'], $columnas, $hora));
                });
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            foreach ($usuarios as $u) {
                $resultado[$base.'|'.$u->IdUsuario] = [
                    'avisos' => array_values(array_filter(array_keys(AvisosTelegram::TIPOS), fn ($c) => (bool) ($u->{$c} ?? false))),
                    'hora' => $u->telegram_resumen_hora ?? null,
                ];
            }
        }

        return $resultado;
    }
}
