<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\User;
use App\Support\AnulacionVenta;
use App\Support\ConsultaPeru;
use App\Support\Sunat\CambioFecha;
use App\Support\Sunat\SunatService;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Comprobantes SUNAT de una empresa desde el panel (sin entrar a su base de datos): ver los pendientes y rechazados,
 * consultarlos o enviarlos a SUNAT, corregir la fecha de los vencidos, marcarlos como aceptados, descartarlos o anularlos.
 * Cada acción va de uno en uno (la pantalla manda varios a la vez y muestra el resultado en cada fila).
 * No se borran: el número ya se usó y debe quedar el rastro.
 */
class ComprobanteController extends Controller
{
    public const FILTROS = ['pendientes' => 'Pendientes de SUNAT', 'rechazados' => 'Rechazados', 'todos' => 'Todos'];

    private const ACCIONES = ['consultar', 'enviar', 'fecha', 'aceptado', 'descartar', 'anular'];

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

        // Pendiente = todo lo que no está aceptado, anulado ni rechazado (cualquier otro estado, incluso escrito distinto
        // o vacío), igual que cuenta el Tablero; así lo que allí sale "por revisar" siempre aparece aquí
        // ("ACEPTADO CO" de los importados del sistema antiguo también cuenta como aceptado)
        $pendiente = fn ($x) => $x->whereNull('est_sunat')->orWhereRaw("UPPER(TRIM(est_sunat)) NOT REGEXP '^(ACEPTAD|OBSERVAD|ANULAD|RECHAZAD)'");
        $rechazado = fn ($w) => $w->whereRaw("UPPER(TRIM(est_sunat)) REGEXP '^RECHAZAD'");

        [$lista, $conteo, $anios] = Tenancy::en($cliente->base_datos, function () use ($filtro, $q, $anio, $request, $pendiente, $rechazado) {
            $base = fn () => DB::table('cpe_cabecera')->whereIn('tdocod', SunatService::TIPOS_ELECTRONICOS)->whereNull('ccabaj');
            $lista = $base()
                ->when($filtro === 'pendientes', fn ($w) => $w->where($pendiente))
                ->when($filtro === 'rechazados', $rechazado)
                ->when($anio, fn ($w) => $w->whereYear('ccafem', $anio))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw("CONCAT(serdoc, '-', numdoc) LIKE ?", ["%{$q}%"])->orWhere('ccanom', 'like', "%{$q}%")->orWhere('ccandi', $q)))
                ->orderBy('ccafem')->orderBy('IdCpe_cabecera')
                ->paginate(100, ['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccanom', 'ccandi', 'ccaitv', 'est_sunat', 'ccadessun', 'ccasunrescod', 'ccabaj'], 'pagina', (int) $request->get('pagina', 1))
                ->withQueryString();
            $conteo = [
                'pendientes' => $base()->where($pendiente)->count(),
                'rechazados' => $rechazado($base())->count(),
            ];
            $anios = $base()->selectRaw('DISTINCT YEAR(ccafem) AS a')->orderByDesc('a')->pluck('a');

            return [$lista, $conteo, $anios];
        });

        return view('admin.clientes.comprobantes', compact('cliente', 'lista', 'conteo', 'anios', 'filtro', 'q', 'anio'));
    }

    /**
     * Una acción sobre un comprobante; responde cómo quedó para pintar su fila al instante.
     *
     * @return JsonResponse {ok, estado, mensaje, fecha, vencido, anulado}
     */
    public function accion(Request $request, Cliente $cliente, int $id): JsonResponse
    {
        $this->autorizar($cliente);
        $d = $request->validate(['accion' => 'required|in:'.implode(',', self::ACCIONES), 'motivo' => 'nullable|string|max:70']);
        $motivo = trim((string) ($d['motivo'] ?? '')) ?: match ($d['accion']) {
            'descartar' => 'RECHAZADO POR SUNAT - DESCARTADO DESDE EL PANEL',
            'anular' => 'ANULADO DESDE EL PANEL',
            default => '',
        };
        $quien = Auth::guard('superadmin')->user()->nombre;

        return response()->json(Tenancy::en($cliente->base_datos, function () use ($d, $id, $motivo, $quien) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
            if (! $cab) {
                return ['ok' => false, 'mensaje' => 'Comprobante no encontrado.'];
            }
            // Las acciones que mueven stock o cambian la fecha necesitan un usuario de la empresa (su administrador), sin abrir sesión
            $admin = User::join('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')->where('ru.role_id', 2)->orderBy('users.IdUsuario')->first(['users.*']);
            if ($admin) {
                Auth::guard('web')->setUser($admin);
            }
            try {
                $this->aplicar($d['accion'], $cab, $motivo, $quien, $admin);
                $ok = true;
                $error = null;
            } catch (\Throwable $e) {
                $ok = false;
                $error = mb_substr($e->getMessage(), 0, 300);
            }
            $c = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();

            return ['ok' => $ok, 'estado' => $c->est_sunat ?: 'PENDIENTE', 'mensaje' => $error ?? (string) $c->ccadessun,
                'fecha' => Carbon::parse($c->ccafem)->format('d/m/Y'), 'anulado' => (bool) $c->ccabaj,
                'vencido' => ! $c->ccabaj && in_array($c->est_sunat ?: 'PENDIENTE', SunatService::ESTADOS_REENVIABLES, true) && CambioFecha::vencido($c)];
        }));
    }

    private function aplicar(string $accion, object $cab, string $motivo, string $quien, ?User $admin): void
    {
        if ($cab->ccabaj) {
            throw new \RuntimeException('ya está anulado o descartado');
        }
        $sunat = fn () => new SunatService(Empresa::findOrFail($cab->IdEmpresa), EmpresaNegocio::findOrFail($cab->id_empresa_negocio));
        $aceptado = in_array($cab->est_sunat, ['ACEPTADO', 'OBSERVADO'], true);

        switch ($accion) {
            case 'consultar':   // ¿SUNAT lo tiene? Primero su constancia (CDR, facturas con clave SOL); si no, la consulta de validez
                $r = $sunat()->consultarCdr($cab);
                if ($r) {
                    DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                        'est_sunat' => $r['estado'], 'ccasunrescod' => $r['codigo'], 'ccadessun' => mb_substr($r['mensaje'], 0, 500),
                        'enviado' => $r['ok'] ? 1 : 0,
                    ]);
                    break;
                }
                // Consulta de validez (sirve también para boletas; gasta una consulta del plan de apiperu.dev)
                $v = ConsultaPeru::estadoCpe((string) $cab->IdEmpresa, $cab->tdocod, $cab->serdoc, (string) (int) $cab->numdoc, (string) $cab->ccafem, (float) $cab->ccaitv);
                if (! $v) {
                    throw new \RuntimeException('no se pudo consultar a SUNAT (sin constancia ni consulta de validez). Intenta en unos minutos.');
                }
                $hora = now()->format('d/m/Y H:i');
                if (str_starts_with($v['estado'], 'ACEPTADO')) {
                    DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                        'est_sunat' => 'ACEPTADO', 'enviado' => 1, 'ccadessun' => "Consultado en SUNAT el {$hora}: ACEPTADO.",
                    ]);
                } elseif (str_starts_with($v['estado'], 'ANULADO')) {
                    DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                        'est_sunat' => 'ANULADO', 'ccadessun' => "Consultado en SUNAT el {$hora}: ANULADO (comunicado de baja).",
                    ]);
                } else {
                    DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                        'ccadessun' => mb_substr("Consultado en SUNAT el {$hora}: {$v['estado']}. Todavía no lo tiene: envíalo.", 0, 500),
                    ]);
                    throw new \RuntimeException("SUNAT dice {$v['estado']}: todavía no lo tiene, envíalo.");
                }
                break;
            case 'fecha':       // Fuera de plazo: al último día que SUNAT todavía acepta (luego se envía)
                if (! $admin) {
                    throw new \RuntimeException('la empresa no tiene un administrador');
                }
                CambioFecha::aFechaValida((int) $cab->IdCpe_cabecera, (int) $cab->id_empresa_negocio, $admin);
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
