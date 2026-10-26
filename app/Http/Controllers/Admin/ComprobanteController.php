<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\User;
use App\Support\AnulacionVenta;
use App\Support\Sunat\SunatService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Comprobantes SUNAT de una empresa desde el panel (sin entrar a su base de datos): ver los pendientes y rechazados,
 * consultarlos o enviarlos a SUNAT, marcarlos como aceptados, descartarlos o anularlos en el sistema.
 * No se borran: el número ya se usó y debe quedar el rastro.
 */
class ComprobanteController extends Controller
{
    public const FILTROS = ['pendientes' => 'Pendientes de SUNAT', 'rechazados' => 'Rechazados', 'todos' => 'Todos'];

    private const ACCIONES = ['consultar', 'enviar', 'aceptado', 'descartar', 'anular'];

    private function autorizar(Cliente $cliente): void
    {
        $yo = Auth::guard('superadmin')->user();
        abort_unless($yo->es_dueno || (int) $cliente->creado_por === (int) $yo->id, 404);
    }

    public function index(Request $request, Cliente $cliente): View
    {
        $this->autorizar($cliente);
        $filtro = array_key_exists($request->get('filtro'), self::FILTROS) ? $request->get('filtro') : 'pendientes';
        $q = trim((string) $request->get('q'));
        $anio = (int) $request->get('anio');

        [$lista, $conteo, $anios] = Tenancy::en($cliente->base_datos, function () use ($filtro, $q, $anio, $request) {
            $base = fn () => DB::table('cpe_cabecera')->whereIn('tdocod', SunatService::TIPOS_ELECTRONICOS)->whereNull('ccabaj');
            $lista = $base()
                ->when($filtro === 'pendientes', fn ($w) => $w->where(fn ($x) => $x->whereNull('est_sunat')->orWhereIn('est_sunat', ['PENDIENTE', 'ERROR', 'EN RESUMEN', 'GENERADO'])))
                ->when($filtro === 'rechazados', fn ($w) => $w->where('est_sunat', 'RECHAZADO'))
                ->when($anio, fn ($w) => $w->whereYear('ccafem', $anio))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw("CONCAT(serdoc, '-', numdoc) LIKE ?", ["%{$q}%"])->orWhere('ccanom', 'like', "%{$q}%")->orWhere('ccandi', $q)))
                ->orderBy('ccafem')->orderBy('IdCpe_cabecera')
                ->paginate(100, ['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccanom', 'ccandi', 'ccaitv', 'est_sunat', 'ccadessun', 'ccasunrescod'], 'pagina', (int) $request->get('pagina', 1))
                ->withQueryString();
            $conteo = [
                'pendientes' => $base()->where(fn ($x) => $x->whereNull('est_sunat')->orWhereIn('est_sunat', ['PENDIENTE', 'ERROR', 'EN RESUMEN', 'GENERADO']))->count(),
                'rechazados' => $base()->where('est_sunat', 'RECHAZADO')->count(),
            ];
            $anios = $base()->selectRaw('DISTINCT YEAR(ccafem) AS a')->orderByDesc('a')->pluck('a');

            return [$lista, $conteo, $anios];
        });

        return view('admin.clientes.comprobantes', compact('cliente', 'lista', 'conteo', 'anios', 'filtro', 'q', 'anio'));
    }

    /** Una acción sobre uno o varios comprobantes (ids[]) */
    public function accion(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($cliente);
        $d = $request->validate(['accion' => 'required|in:'.implode(',', self::ACCIONES), 'ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer',
            'motivo' => 'nullable|string|max:70'], [], ['ids' => 'comprobantes']);
        $motivo = trim((string) ($d['motivo'] ?? '')) ?: match ($d['accion']) {
            'descartar' => 'RECHAZADO POR SUNAT - DESCARTADO DESDE EL PANEL',
            'anular' => 'ANULADO DESDE EL PANEL',
            default => '',
        };
        $quien = Auth::guard('superadmin')->user()->nombre;

        $r = Tenancy::en($cliente->base_datos, function () use ($d, $motivo, $quien) {
            $res = ['ok' => 0, 'fallas' => []];
            // Las acciones que mueven stock necesitan un usuario de la empresa (su administrador), sin abrir sesión
            $admin = User::join('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')->where('ru.role_id', 2)->orderBy('users.IdUsuario')->first(['users.*']);
            if ($admin) {
                Auth::guard('web')->setUser($admin);
            }
            foreach (DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', $d['ids'])->get() as $cab) {
                $numero = "{$cab->serdoc}-{$cab->numdoc}";
                try {
                    $this->aplicar($d['accion'], $cab, $motivo, $quien, $admin);
                    $res['ok']++;
                } catch (\Throwable $e) {
                    $res['fallas'][] = "{$numero}: ".mb_substr($e->getMessage(), 0, 140);
                }
            }

            return $res;
        });

        $nombres = ['consultar' => 'consultado(s)', 'enviar' => 'enviado(s)', 'aceptado' => 'marcado(s) como aceptado(s)', 'descartar' => 'descartado(s)', 'anular' => 'anulado(s)'];
        $mensaje = "{$r['ok']} comprobante(s) {$nombres[$d['accion']]}.";

        return back()->with($r['fallas'] ? 'error' : 'ok', $mensaje.($r['fallas'] ? ' Con problema: '.implode(' · ', array_slice($r['fallas'], 0, 8)) : ''));
    }

    private function aplicar(string $accion, object $cab, string $motivo, string $quien, ?User $admin): void
    {
        if ($cab->ccabaj) {
            throw new \RuntimeException('ya está anulado o descartado');
        }
        $sunat = fn () => new SunatService(Empresa::findOrFail($cab->IdEmpresa), EmpresaNegocio::findOrFail($cab->id_empresa_negocio));
        $aceptado = in_array($cab->est_sunat, ['ACEPTADO', 'OBSERVADO'], true);

        switch ($accion) {
            case 'consultar':   // ¿SUNAT lo tiene? (recupera su constancia)
                $r = $sunat()->consultarCdr($cab);
                if (! $r) {
                    throw new \RuntimeException('SUNAT no tiene su constancia (o la empresa no está en producción con clave SOL)');
                }
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                    'est_sunat' => $r['estado'], 'ccasunrescod' => $r['codigo'], 'ccadessun' => mb_substr($r['mensaje'], 0, 500),
                    'enviado' => $r['ok'] ? 1 : 0,
                ]);
                break;
            case 'enviar':
                $r = $sunat()->enviarComprobante((int) $cab->IdCpe_cabecera);
                if (! $r['ok']) {
                    throw new \RuntimeException($r['estado'].': '.$r['mensaje']);
                }
                break;
            case 'aceptado':    // Ya figura aceptado en SUNAT (consultado en su web) pero aquí quedó pendiente
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                    'est_sunat' => 'ACEPTADO', 'enviado' => 1, 'ccadessun' => mb_substr("Marcado como aceptado desde el panel por {$quien} el ".now()->format('d/m/Y H:i'), 0, 500),
                ]);
                break;
            case 'descartar':   // Rechazado (para SUNAT no existe): sale de pendientes, no mueve stock
                if ($aceptado) {
                    throw new \RuntimeException('está aceptado por SUNAT: se anula con nota de crédito o baja');
                }
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                    'ccabaj' => 'DESCARTADO '.now()->format('d/m/Y H:i'), 'motivo_baja' => mb_substr($motivo, 0, 70), 'IdUsuario_baja' => $admin?->IdUsuario,
                ]);
                break;
            case 'anular':      // Nunca llegó a SUNAT: se anula en el sistema y el stock vuelve al almacén
                if ($aceptado) {
                    throw new \RuntimeException('está aceptado por SUNAT: se anula con nota de crédito o baja');
                }
                if (! $admin) {
                    throw new \RuntimeException('la empresa no tiene un administrador');
                }
                AnulacionVenta::anular((int) $cab->IdCpe_cabecera, $motivo, $admin->IdUsuario, 'ANULADO');
                break;
        }
    }
}
