<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\Prestamos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Clientes de préstamos: DNI (el nombre sale solo), celular, dirección y referencia de cobro, zona, negocio, aval,
 * cobrador y límite de préstamo. Su ficha muestra la calificación según cómo pagó y cuánto se le puede prestar.
 */
class PrestamoClienteController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    /** Lista con su deuda y estado de hoy */
    public function index(): View
    {
        abort_unless(Prestamos::usa(Auth::user()), 403, 'No tienes acceso a los préstamos.');
        $suc = $this->suc();
        $u = Auth::user();
        $soloMios = Prestamos::esCobrador($u) && ! Prestamos::gestiona($u);
        $cartera = Prestamos::cartera($suc)->groupBy('pcl_id');
        $pagados = DB::table('prestamos')->where('id_empresa_negocio', $suc)->where('estado', 'PAGADO')->groupBy('pcl_id')
            ->select('pcl_id', DB::raw('COUNT(*) as n'), DB::raw('MAX(cancelado) as ultimo'))->get()->keyBy('pcl_id');
        $clientes = DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->leftJoin('users as u', 'u.IdUsuario', '=', 'k.cobrador_id')
            ->where('k.id_empresa_negocio', $suc)->when($soloMios, fn ($q) => $q->where('k.cobrador_id', $u->IdUsuario))->orderBy('c.clinom')
            ->get(['k.*', 'c.clinom', 'c.clinum', 'c.telefono', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as cobrador")])
            ->map(function ($k) use ($cartera, $pagados) {
                $vig = $cartera[$k->pcl_id] ?? collect();
                $peor = $vig->sortByDesc('vencidas')->first();

                return ['pcl_id' => $k->pcl_id, 'nombre' => $k->clinom, 'dni' => $k->clinum, 'telefono' => $k->telefono, 'zona' => $k->zona, 'direccion' => $k->direccion,
                    'negocio' => $k->negocio, 'cobrador' => $k->cobrador, 'estado_cliente' => $k->estado,
                    'vigentes' => $vig->count(), 'saldo' => round($vig->sum('saldo'), 2), 'vencidas' => (int) $vig->max('vencidas'),
                    'estado' => $peor->estado ?? ($k->estado === 'ACTIVO' ? 'SIN_DEUDA' : $k->estado),
                    'pagados' => (int) ($pagados[$k->pcl_id]->n ?? 0), 'pre_id' => $peor->pre_id ?? null];
            });

        return view('empresas.prestamos.clientes', ['clientes' => $clientes, 'cobradores' => PrestamoController::cobradores($suc),
            'gestiona' => Prestamos::gestiona($u), 'zonas' => $clientes->pluck('zona')->filter()->unique()->sort()->values()]);
    }

    /** Datos de un cliente con sus préstamos y su calificación */
    public static function datosCliente(int $pclId, int $suc): ?array
    {
        $k = DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->where('k.pcl_id', $pclId)->where('k.id_empresa_negocio', $suc)
            ->first(['k.*', 'c.clinom', 'c.clinum', 'c.telefono', 'c.clicor']);
        if (! $k) {
            return null;
        }
        $vigentes = Prestamos::cartera($suc, ['pcl_id' => $pclId]);

        return ['cliente' => $k, 'calificacion' => Prestamos::calificacion($pclId, $vigentes),
            'vigentes' => $vigentes->map(fn ($p) => ['pre_id' => $p->pre_id, 'numero' => $p->numero, 'monto' => (float) $p->monto, 'total' => (float) $p->total,
                'saldo' => $p->saldo, 'estado' => $p->estado, 'vencidas' => $p->vencidas, 'avance' => $p->avance, 'cuota' => (float) $p->cuota])->values(),
            'historial' => DB::table('prestamos')->where('pcl_id', $pclId)->whereIn('estado', ['PAGADO', 'ANULADO'])->orderByDesc('pre_id')->limit(30)
                ->get(['pre_id', 'numero', 'monto', 'total', 'fecha', 'ultima_cuota', 'cancelado', 'estado']),
            'esPrimero' => ! DB::table('prestamos')->where('pcl_id', $pclId)->where('estado', '!=', 'ANULADO')->exists()];
    }

    public function ficha(int $id): JsonResponse
    {
        abort_unless(Prestamos::usa(Auth::user()), 403);
        $d = self::datosCliente($id, $this->suc());
        abort_unless($d, 404);

        return response()->json($d);
    }

    public function guardar(Request $request): JsonResponse
    {
        abort_unless(Prestamos::gestiona(Auth::user()), 403, 'Solo el administrador o caja registran clientes.');
        $d = $request->validate([
            'pcl_id' => 'nullable|integer', 'clinum' => 'required|string|regex:/^\d{8,11}$/', 'clinom' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:20', 'direccion' => 'nullable|string|max:200', 'referencia' => 'nullable|string|max:200',
            'zona' => 'nullable|string|max:60', 'negocio' => 'nullable|string|max:120', 'aval_nombre' => 'nullable|string|max:150',
            'aval_dni' => 'nullable|string|max:15', 'aval_telefono' => 'nullable|string|max:20', 'cobrador_id' => 'nullable|integer',
            'limite' => 'nullable|numeric|min:0|max:9999999', 'estado' => 'nullable|in:ACTIVO,BLOQUEADO', 'obs' => 'nullable|string|max:2000',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
        ], ['clinum.regex' => 'El DNI tiene 8 números (o el RUC 11).'], ['clinum' => 'DNI', 'clinom' => 'nombre']);
        $user = Auth::user();
        $suc = $this->suc();

        try {
            $id = DB::transaction(function () use ($d, $user, $suc) {
                $cliente = Cliente::updateOrCreate(['clinum' => $d['clinum'], 'rucemp' => $user->IdEmpresa], [
                    'clinom' => mb_strtoupper(trim($d['clinom'])), 'tdicod' => strlen($d['clinum']) === 11 ? '6' : '1', 'telefono' => $d['telefono'] ?? null,
                ]);
                $actual = ! empty($d['pcl_id']) ? DB::table('prestamo_clientes')->where('pcl_id', $d['pcl_id'])->where('id_empresa_negocio', $suc)->first() : null;
                if (! empty($d['pcl_id']) && ! $actual) {
                    throw new \RuntimeException('Cliente no encontrado.');
                }
                $otro = DB::table('prestamo_clientes')->where('id_empresa_negocio', $suc)->where('clicod', $cliente->clicod)
                    ->when($actual, fn ($q) => $q->where('pcl_id', '!=', $actual->pcl_id))->exists();
                if ($otro) {
                    throw new \RuntimeException('Ese DNI ya está registrado como cliente.');
                }
                $fila = collect($d)->only(['direccion', 'referencia', 'zona', 'negocio', 'aval_nombre', 'aval_dni', 'aval_telefono', 'cobrador_id', 'limite', 'obs', 'lat', 'lng'])
                    ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : mb_strtoupper(trim($v))) : $v)->all();
                $fila['obs'] = isset($d['obs']) ? (trim($d['obs']) ?: null) : null;
                $fila['clicod'] = $cliente->clicod;
                $fila['estado'] = $d['estado'] ?? 'ACTIVO';
                if ($actual) {
                    DB::table('prestamo_clientes')->where('pcl_id', $actual->pcl_id)->update($fila);

                    return (int) $actual->pcl_id;
                }

                return (int) DB::table('prestamo_clientes')->insertGetId($fila + ['id_empresa_negocio' => $suc, 'creado' => now()]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Cliente guardado.', 'pcl_id' => $id, 'nuevo' => empty($d['pcl_id'])]);
    }

    /** Búsqueda rápida (nuevo préstamo) */
    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Prestamos::gestiona(Auth::user()), 403);
        $q = trim((string) $request->q);
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->where('k.id_empresa_negocio', $this->suc())
            ->where(fn ($w) => $w->where('c.clinom', 'like', '%'.$q.'%')->orWhere('c.clinum', 'like', $q.'%'))->orderBy('c.clinom')->limit(10)
            ->get(['k.pcl_id', 'c.clinom', 'c.clinum', 'k.zona', 'k.estado']));
    }
}
