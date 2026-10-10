<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Carta;
use App\Support\Estacionamiento;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Estacionamiento / valet parking: entradas y salidas con ticket, mapa de espacios, cobro con comprobante,
 * valet (llaveros y pedido del auto desde el QR del ticket), abonados con pensión mensual y reporte.
 */
class EstacionamientoController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function operador(): void
    {
        abort_unless(Auth::user()->tieneModulo('/estacionamiento'), 403, 'No tienes acceso al estacionamiento.');
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede hacer esto.');
    }

    private function json(callable $accion): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function ticket(int $id): object
    {
        $t = DB::table('est_tickets')->where('tic_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($t, 404);

        return $t;
    }

    private function tarifas(int $suc)
    {
        return DB::table('est_tarifas')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderBy('orden')->orderBy('tar_id')->get()
            ->map(fn ($t) => (array) $t + ['texto' => Estacionamiento::textoTarifa($t), 'fa' => Estacionamiento::ICONOS[$t->icono] ?? 'fa-car-side']);
    }

    private function espacios(int $suc)
    {
        $ocupados = DB::table('est_tickets')->where('id_empresa_negocio', $suc)->whereIn('estado', ['DENTRO', 'SOLICITADO'])
            ->whereNotNull('esp_id')->pluck('placa', 'esp_id');
        $reservados = DB::table('est_abonados')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')->whereNotNull('esp_id')
            ->where('inicio', '<=', now()->toDateString())->where('fin', '>=', now()->toDateString())->pluck('placa', 'esp_id');

        return DB::table('est_espacios')->where('id_empresa_negocio', $suc)->orderBy('zona')->orderBy('orden')->orderBy('codigo')->get()
            ->map(fn ($e) => (array) $e + ['placa' => $ocupados[$e->esp_id] ?? null, 'reservado' => $reservados[$e->esp_id] ?? null]);
    }

    private function mediosPago(int $suc)
    {
        return MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']);
    }

    // ------------------------------------------------------------------ operación

    public function index(): View
    {
        $this->operador();
        $suc = $this->sucursal();

        return view('empresas.estacionamiento.index', [
            'tarifas' => $this->tarifas($suc),
            'espacios' => $this->espacios($suc),
            'dentro' => Estacionamiento::dentro($suc),
            'resumen' => Estacionamiento::resumenHoy($suc),
            'mediospagos' => $this->mediosPago($suc),
            'valets' => DB::table('users')->where('IdEmpresa', Auth::user()->IdEmpresa)->where('estusu', 1)->orderBy('apeusu')->get(['IdUsuario', 'apeusu']),
            'turno' => Turno::abiertoDe(Auth::user()),
            'esAdmin' => Auth::user()->esAdmin(),
            'modos' => Estacionamiento::MODOS,
            'iconos' => Estacionamiento::ICONOS,
        ]);
    }

    /** Lo que la pantalla refresca sola cada pocos segundos */
    public function estado(): JsonResponse
    {
        $this->operador();
        $suc = $this->sucursal();

        return response()->json([
            'dentro' => Estacionamiento::dentro($suc),
            'espacios' => $this->espacios($suc),
            'resumen' => Estacionamiento::resumenHoy($suc),
        ]);
    }

    public function entrada(Request $request): JsonResponse
    {
        $this->operador();
        $d = $request->validate([
            'placa' => 'required|string|max:12', 'tar_id' => 'required|integer', 'esp_id' => 'nullable|integer',
            'valet' => 'nullable|boolean', 'llavero' => 'nullable|string|max:10', 'IdUsuario_valet' => 'nullable|integer',
            'marca' => 'nullable|string|max:40', 'color' => 'nullable|string|max:20', 'observaciones' => 'nullable|string|max:200',
            'cliente' => 'nullable|string|max:120', 'telefono' => 'nullable|string|max:20',
        ]);

        return $this->json(function () use ($d) {
            $t = Estacionamiento::registrarEntrada(Auth::user(), $d);
            $abonado = $t->abo_id ? ' · ABONADO' : '';

            return ['mensaje' => 'Entrada registrada: ticket N° '.$t->numero.' · '.$t->placa.$abonado, 'tic_id' => $t->tic_id,
                'ticket' => route('estacionamiento.imprimir', $t->tic_id)];
        });
    }

    /** Encuentra el ticket por lo que escanean o escriben: código del QR, número de ticket o placa */
    public function buscar(Request $request): JsonResponse
    {
        $this->operador();
        $q = trim((string) $request->query('q'));
        $base = fn () => DB::table('est_tickets')->where('id_empresa_negocio', $this->sucursal());
        $codigo = strtoupper(preg_replace('#^.*/#', '', $q));   // si escanean la URL completa del QR

        $t = $base()->where('codigo', $codigo)->first()
            ?? (ctype_digit($q) ? $base()->where('numero', (int) $q)->first() : null)
            ?? $base()->where('placa', Estacionamiento::normalizarPlaca($q))->orderByRaw("estado in ('DENTRO','SOLICITADO') desc")->orderByDesc('entrada')->first();

        return response()->json($t ? ['ok' => true, 'tic_id' => $t->tic_id] : ['ok' => false, 'mensaje' => 'No se encontró el ticket o la placa "'.$q.'".']);
    }

    /** Detalle del ticket y lo que debe ahora */
    public function detalle(Request $request, int $id): JsonResponse
    {
        $this->operador();
        $t = $this->ticket($id);
        $c = Estacionamiento::cotizar($t, $request->boolean('perdido'));
        $usuarios = DB::table('users')->whereIn('IdUsuario', array_filter([$t->IdUsuario_entrada, $t->IdUsuario_salida, $t->IdUsuario_valet]))->pluck('apeusu', 'IdUsuario');
        $cpe = $t->IdCpe_cabecera ? DB::table('cpe_cabecera')->where('IdCpe_cabecera', $t->IdCpe_cabecera)->first(['serdoc', 'numdoc']) : null;

        return response()->json([
            'ticket' => (array) $t + [
                'usuario_entrada' => $usuarios[$t->IdUsuario_entrada] ?? null, 'usuario_salida' => $usuarios[$t->IdUsuario_salida] ?? null,
                'usuario_valet' => $usuarios[$t->IdUsuario_valet] ?? null,
                'comprobante' => $cpe ? $cpe->serdoc.'-'.str_pad($cpe->numdoc, 8, '0', STR_PAD_LEFT) : null,
                'url_publica' => route('valet.ver', $t->codigo),
            ],
            'cobro' => [
                'minutos' => $c['minutos'], 'duracion' => $c['duracion'], 'importe' => $c['importe'], 'penalidad' => $c['penalidad'],
                'detalle' => $c['detalle'], 'abonado' => $c['abonado'] ? ['clinom' => $c['abonado']->clinom, 'fin' => $c['abonado']->fin] : null,
            ],
        ]);
    }

    public function cobrar(Request $request, int $id): JsonResponse
    {
        $this->operador();
        $d = $request->validate([
            'tdocod' => 'required|in:01,03,13', 'clinum' => 'nullable|string|max:15', 'clinom' => 'nullable|string|max:150', 'clidir' => 'nullable|string|max:150',
            'perdido' => 'nullable|boolean', 'descuento' => 'nullable|numeric|min:0|max:99999', 'motivo' => 'nullable|string|max:200',
            'id_med_pag' => 'nullable|array|max:5', 'mon_med_pag' => 'nullable|array|max:5', 'paga' => 'nullable|numeric|min:0', 'imprimir' => 'nullable|boolean',
        ]);
        if ((float) ($d['descuento'] ?? 0) > 0) {
            abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden hacer descuentos.');
        }
        $this->ticket($id);

        try {
            $r = Estacionamiento::darSalida(Auth::user(), $id, $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar la salida.']);
        }

        if (! $r['cabId']) {
            return response()->json(['ok' => true, 'mensaje' => 'Salida registrada sin cobro.', 'id' => null]);
        }
        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($r['cabId']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso, 'mensaje' => 'Salida cobrada.'] + VentaDirecta::respuesta($r['cabId']));
    }

    /** Valet: el cliente pidió su auto (por teléfono o en persona) o ya se le entregó la llamada */
    public function solicitar(int $id): JsonResponse
    {
        $this->operador();
        $t = $this->ticket($id);

        return $this->json(function () use ($t) {
            if (! in_array($t->estado, ['DENTRO', 'SOLICITADO'])) {
                throw new \RuntimeException('Este vehículo ya salió.');
            }
            $pedir = $t->estado === 'DENTRO';
            DB::table('est_tickets')->where('tic_id', $t->tic_id)->update(['estado' => $pedir ? 'SOLICITADO' : 'DENTRO', 'solicitado' => $pedir ? now() : null]);

            return ['mensaje' => $pedir ? 'Auto '.$t->placa.' solicitado: tráelo a la salida.' : 'Pedido del auto '.$t->placa.' cancelado.'];
        });
    }

    public function moverEspacio(Request $request, int $id): JsonResponse
    {
        $this->operador();
        $t = $this->ticket($id);
        $espId = $request->integer('esp_id') ?: null;

        return $this->json(function () use ($t, $espId) {
            return DB::transaction(function () use ($t, $espId) {
                $codigo = null;
                if ($espId) {
                    $e = DB::table('est_espacios')->where('esp_id', $espId)->where('id_empresa_negocio', $this->sucursal())->lockForUpdate()->first();
                    $ocupado = DB::table('est_tickets')->where('esp_id', $espId)->where('tic_id', '!=', $t->tic_id)->whereIn('estado', ['DENTRO', 'SOLICITADO'])->exists();
                    if (! $e || $e->estado !== 'ACTIVO' || $ocupado) {
                        throw new \RuntimeException('Ese espacio no está libre.');
                    }
                    $codigo = $e->codigo;
                }
                DB::table('est_tickets')->where('tic_id', $t->tic_id)->update(['esp_id' => $espId, 'espacio' => $codigo]);

                return ['mensaje' => $codigo ? $t->placa.' ahora está en el espacio '.$codigo.'.' : 'Se quitó el espacio.'];
            });
        });
    }

    /** Ticket mal registrado (placa equivocada, entrada duplicada): sale sin cobro y queda en el historial como anulado */
    public function anular(Request $request, int $id): JsonResponse
    {
        $this->soloAdmin();
        $d = $request->validate(['motivo' => 'required|string|min:3|max:200']);
        $t = $this->ticket($id);

        return $this->json(function () use ($t, $d) {
            if (! in_array($t->estado, ['DENTRO', 'SOLICITADO'])) {
                throw new \RuntimeException('Solo se anulan tickets de vehículos que siguen dentro. Para un cobro ya hecho, anula su comprobante.');
            }
            DB::table('est_tickets')->where('tic_id', $t->tic_id)->update([
                'estado' => 'ANULADO', 'salida' => now(), 'motivo' => mb_substr('ANULADO: '.$d['motivo'], 0, 200), 'IdUsuario_salida' => Auth::id(),
            ]);

            return ['mensaje' => 'Ticket N° '.$t->numero.' anulado.'];
        });
    }

    /** Ticket impreso (80 mm) con el QR para que el cliente pida su auto o lo presente a la salida */
    public function imprimir(int $id): View
    {
        $this->operador();
        $t = $this->ticket($id);
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $t->id_empresa_negocio)->first(['nombre_comercial', 'direccion', 'telefono', 'IdEmpresa']);
        $tarifa = DB::table('est_tarifas')->where('tar_id', $t->tar_id)->first();

        return view('empresas.estacionamiento.ticket', [
            't' => $t, 'negocio' => $negocio, 'tarifa' => $tarifa ? Estacionamiento::textoTarifa($tarifa) : null,
            'qr' => Carta::qr(route('valet.ver', $t->codigo), 150),
        ]);
    }

    // ------------------------------------------------------------------ configuración (administrador)

    public function guardarTarifa(Request $request): JsonResponse
    {
        $this->soloAdmin();
        $d = $request->validate([
            'tar_id' => 'nullable|integer', 'nombre' => 'required|string|max:40', 'icono' => 'required|in:'.implode(',', array_keys(Estacionamiento::ICONOS)),
            'modo' => 'required|in:'.implode(',', array_keys(Estacionamiento::MODOS)), 'precio' => 'required|numeric|min:0.1|max:9999',
            'fraccion_min' => 'required|integer|min:1|max:60', 'tolerancia_min' => 'required|integer|min:0|max:120',
            'tope_dia' => 'nullable|numeric|min:0|max:99999', 'perdido' => 'nullable|numeric|min:0|max:9999', 'pension' => 'nullable|numeric|min:0|max:99999',
            'activo' => 'nullable|boolean',
        ], [], ['fraccion_min' => 'fracción', 'tolerancia_min' => 'tolerancia', 'tope_dia' => 'tope por día']);
        $suc = $this->sucursal();
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'icono' => $d['icono'], 'modo' => $d['modo'], 'precio' => $d['precio'],
            'fraccion_min' => $d['fraccion_min'], 'tolerancia_min' => $d['tolerancia_min'], 'tope_dia' => $d['tope_dia'] ?? 0,
            'perdido' => $d['perdido'] ?? 0, 'pension' => $d['pension'] ?? 0, 'activo' => (int) ($d['activo'] ?? 1)];

        return $this->json(function () use ($d, $suc, $fila) {
            return DB::transaction(function () use ($d, $suc, $fila) {
                if (! empty($d['tar_id'])) {
                    $existe = DB::table('est_tarifas')->where('tar_id', $d['tar_id'])->where('id_empresa_negocio', $suc)->exists();
                    if (! $existe) {
                        throw new \RuntimeException('Tarifa no encontrada.');
                    }
                    DB::table('est_tarifas')->where('tar_id', $d['tar_id'])->update($fila);
                    $id = (int) $d['tar_id'];
                } else {
                    $id = DB::table('est_tarifas')->insertGetId($fila + ['id_empresa_negocio' => $suc,
                        'orden' => (int) DB::table('est_tarifas')->where('id_empresa_negocio', $suc)->max('orden') + 1]);
                }
                Estacionamiento::productoDe(Auth::user(), DB::table('est_tarifas')->where('tar_id', $id)->first());

                return ['mensaje' => 'Tarifa guardada.', 'tarifas' => $this->tarifas($suc)];
            });
        });
    }

    /** Crea varios espacios de una vez: prefijo A, del 1 al 20 → A-01 … A-20 */
    public function generarEspacios(Request $request): JsonResponse
    {
        $this->soloAdmin();
        $d = $request->validate([
            'prefijo' => 'required|string|max:4|regex:/^[A-Za-z0-9]+$/', 'desde' => 'required|integer|min:1|max:999', 'hasta' => 'required|integer|gte:desde|max:999',
            'zona' => 'nullable|string|max:30', 'tar_id' => 'nullable|integer',
        ], ['prefijo.regex' => 'El prefijo solo lleva letras o números.']);
        abort_if($d['hasta'] - $d['desde'] > 299, 422, 'Máximo 300 espacios por vez.');
        $suc = $this->sucursal();

        return $this->json(function () use ($d, $suc) {
            $prefijo = mb_strtoupper($d['prefijo']);
            $existentes = DB::table('est_espacios')->where('id_empresa_negocio', $suc)->pluck('codigo')->flip();
            $nuevos = [];
            foreach (range($d['desde'], $d['hasta']) as $n) {
                $codigo = $prefijo.'-'.str_pad($n, 2, '0', STR_PAD_LEFT);
                if (! isset($existentes[$codigo])) {
                    $nuevos[] = ['codigo' => $codigo, 'zona' => trim((string) ($d['zona'] ?? '')) ? mb_strtoupper(trim($d['zona'])) : null,
                        'tar_id' => $d['tar_id'] ?? null, 'estado' => 'ACTIVO', 'orden' => $n, 'id_empresa_negocio' => $suc];
                }
            }
            DB::table('est_espacios')->insert($nuevos);

            return ['mensaje' => count($nuevos).' espacio(s) creados.', 'espacios' => $this->espacios($suc)];
        });
    }

    public function guardarEspacio(Request $request, int $espId): JsonResponse
    {
        $this->soloAdmin();
        $d = $request->validate([
            'codigo' => 'required|string|max:10', 'zona' => 'nullable|string|max:30', 'tar_id' => 'nullable|integer', 'estado' => 'required|in:ACTIVO,MANTENIMIENTO',
        ]);
        $suc = $this->sucursal();

        return $this->json(function () use ($d, $suc, $espId) {
            $codigo = mb_strtoupper(trim($d['codigo']));
            if (DB::table('est_espacios')->where('id_empresa_negocio', $suc)->where('codigo', $codigo)->where('esp_id', '!=', $espId)->exists()) {
                throw new \RuntimeException('Ya existe el espacio '.$codigo.'.');
            }
            DB::table('est_espacios')->where('esp_id', $espId)->where('id_empresa_negocio', $suc)->update([
                'codigo' => $codigo, 'zona' => trim((string) ($d['zona'] ?? '')) ? mb_strtoupper(trim($d['zona'])) : null,
                'tar_id' => $d['tar_id'] ?? null, 'estado' => $d['estado'],
            ]);

            return ['mensaje' => 'Espacio guardado.', 'espacios' => $this->espacios($suc)];
        });
    }

    public function quitarEspacio(int $espId): JsonResponse
    {
        $this->soloAdmin();
        $suc = $this->sucursal();

        return $this->json(function () use ($suc, $espId) {
            if (DB::table('est_tickets')->where('esp_id', $espId)->whereIn('estado', ['DENTRO', 'SOLICITADO'])->exists()) {
                throw new \RuntimeException('El espacio está ocupado: primero da salida al vehículo.');
            }
            DB::table('est_espacios')->where('esp_id', $espId)->where('id_empresa_negocio', $suc)->delete();

            return ['mensaje' => 'Espacio eliminado.', 'espacios' => $this->espacios($suc)];
        });
    }

    // ------------------------------------------------------------------ abonados

    private function abonadosPermitido(): void
    {
        abort_unless(Auth::user()->tieneModulo('/estacionamiento/abonados'), 403, 'No tienes acceso a los abonados.');
    }

    public function abonados(): View
    {
        $this->abonadosPermitido();
        $suc = $this->sucursal();
        $hoy = now()->toDateString();

        $abonados = DB::table('est_abonados as a')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'a.IdCpe_cabecera')
            ->leftJoin('est_tarifas as t', 't.tar_id', '=', 'a.tar_id')->leftJoin('est_espacios as e', 'e.esp_id', '=', 'a.esp_id')
            ->where('a.id_empresa_negocio', $suc)->orderByDesc('a.fin')
            ->get(['a.*', 't.nombre as tipo', 'e.codigo as espacio', DB::raw("CONCAT(c.serdoc, '-', LPAD(c.numdoc, 8, '0')) as comprobante")])
            ->map(function ($a) use ($hoy) {
                $dias = Carbon::parse($hoy)->diffInDays(Carbon::parse($a->fin), false);
                $a->situacion = $a->estado === 'ANULADO' ? 'ANULADO' : ($a->fin < $hoy ? 'VENCIDO' : ($a->inicio > $hoy ? 'PROXIMO' : ($dias <= 5 ? 'POR_VENCER' : 'VIGENTE')));
                $a->dias = (int) $dias;

                return $a;
            });

        return view('empresas.estacionamiento.abonados', [
            'abonados' => $abonados,
            'tarifas' => $this->tarifas($suc)->where('activo', 1)->values(),
            'espacios' => DB::table('est_espacios')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')->orderBy('codigo')->get(['esp_id', 'codigo', 'zona']),
            'mediospagos' => $this->mediosPago($suc),
            'turno' => Turno::abiertoDe(Auth::user()),
        ]);
    }

    public function venderPension(Request $request): JsonResponse
    {
        $this->abonadosPermitido();
        $d = $request->validate([
            'placa' => 'required|string|max:12', 'placa2' => 'nullable|string|max:12', 'tar_id' => 'required|integer', 'esp_id' => 'nullable|integer',
            'clinum' => 'nullable|string|max:15', 'clinom' => 'required|string|min:3|max:150', 'clidir' => 'nullable|string|max:150', 'telefono' => 'nullable|string|max:20',
            'inicio' => 'required|date|after_or_equal:'.now()->subDays(31)->toDateString(), 'meses' => 'required|integer|min:1|max:12',
            'precio' => 'required|numeric|min:0.1|max:99999', 'tdocod' => 'required|in:01,03,13', 'obs' => 'nullable|string|max:200',
            'id_med_pag' => 'nullable|array|max:5', 'mon_med_pag' => 'nullable|array|max:5', 'paga' => 'nullable|numeric|min:0', 'imprimir' => 'nullable|boolean',
        ], [], ['clinom' => 'nombre del cliente']);

        try {
            $r = Estacionamiento::venderPension(Auth::user(), $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar la pensión.']);
        }
        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($r['cabId']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso, 'mensaje' => 'Pensión registrada.'] + VentaDirecta::respuesta($r['cabId']));
    }

    /** Datos del abonado que no tocan el comprobante: segunda placa, teléfono, espacio reservado y nota */
    public function editarAbonado(Request $request, int $aboId): JsonResponse
    {
        $this->abonadosPermitido();
        $d = $request->validate(['placa2' => 'nullable|string|max:12', 'telefono' => 'nullable|string|max:20', 'esp_id' => 'nullable|integer', 'obs' => 'nullable|string|max:200']);

        return $this->json(function () use ($d, $aboId) {
            $n = DB::table('est_abonados')->where('abo_id', $aboId)->where('id_empresa_negocio', $this->sucursal())->update([
                'placa2' => Estacionamiento::normalizarPlaca($d['placa2'] ?? null) ?: null, 'telefono' => $d['telefono'] ?? null,
                'esp_id' => $d['esp_id'] ?? null, 'obs' => $d['obs'] ?? null,
            ]);
            if (! $n && ! DB::table('est_abonados')->where('abo_id', $aboId)->where('id_empresa_negocio', $this->sucursal())->exists()) {
                throw new \RuntimeException('Abonado no encontrado.');
            }

            return ['mensaje' => 'Datos del abonado guardados.'];
        });
    }

    // ------------------------------------------------------------------ reporte

    public function reporte(Request $request): View
    {
        abort_unless(Auth::user()->tieneModulo('/estacionamiento/reporte') || Auth::user()->esAdmin(), 403, 'No tienes acceso al reporte.');
        $suc = $this->sucursal();
        $desde = $request->date('desde')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $hasta = $request->date('hasta')?->toDateString() ?? now()->toDateString();
        $placa = Estacionamiento::normalizarPlaca($request->query('placa'));

        // Columnas con el nombre de la tabla: algunas consultas la unen con usuarios o comprobantes
        $base = fn () => DB::table('est_tickets')->where('est_tickets.id_empresa_negocio', $suc)
            ->whereBetween('est_tickets.entrada', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->when($placa, fn ($q) => $q->where('est_tickets.placa', 'like', $placa.'%'));
        $cobrados = fn () => $base()->where('est_tickets.estado', 'SALIO');

        $porDia = $cobrados()->selectRaw('DATE(salida) as dia, COUNT(*) as vehiculos, SUM(total) as total, AVG(minutos) as promedio')
            ->groupBy('dia')->orderBy('dia')->get();
        $porTipo = $cobrados()->selectRaw('tipo, COUNT(*) as vehiculos, SUM(total) as total, AVG(minutos) as promedio')->groupBy('tipo')->orderByDesc('total')->get();
        $porHora = $base()->where('estado', '!=', 'ANULADO')->selectRaw('HOUR(entrada) as hora, COUNT(*) as vehiculos')->groupBy('hora')->pluck('vehiculos', 'hora');
        $porUsuario = $cobrados()->leftJoin('users as u', 'u.IdUsuario', '=', 'est_tickets.IdUsuario_salida')
            ->selectRaw('u.apeusu as usuario, COUNT(*) as vehiculos, SUM(est_tickets.total) as total')->groupBy('u.apeusu')->orderByDesc('total')->get();

        $pensiones = DB::table('est_abonados')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')
            ->whereBetween('creado', [$desde.' 00:00:00', $hasta.' 23:59:59'])->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(precio), 0) as total')->first();

        $tickets = $base()->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'est_tickets.IdCpe_cabecera')
            ->orderByDesc('est_tickets.entrada')
            ->select(['est_tickets.*', DB::raw("CONCAT(c.serdoc, '-', LPAD(c.numdoc, 8, '0')) as comprobante")])
            ->paginate(50)->withQueryString();

        return view('empresas.estacionamiento.reporte', [
            'desde' => $desde, 'hasta' => $hasta, 'placa' => $placa,
            'totales' => [
                'vehiculos' => $base()->where('estado', '!=', 'ANULADO')->count(),
                'cobrado' => (float) $porDia->sum('total'),
                'sinCobro' => $cobrados()->where('total', 0)->count(),
                'descuentos' => (float) $cobrados()->sum('descuento'),
                'promedio' => (int) round((float) $cobrados()->avg('minutos')),
                'anulados' => $base()->where('estado', 'ANULADO')->count(),
                'pensiones' => $pensiones,
            ],
            'porDia' => $porDia, 'porTipo' => $porTipo, 'porHora' => $porHora, 'porUsuario' => $porUsuario, 'tickets' => $tickets,
        ]);
    }

    // ------------------------------------------------------------------ público (cliente con el QR de su ticket)

    public function publico(string $codigo): View
    {
        $t = DB::table('est_tickets')->where('codigo', strtoupper($codigo))->first();
        abort_unless($t, 404);
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $t->id_empresa_negocio)->first(['nombre_comercial', 'telefono']);
        $c = in_array($t->estado, ['DENTRO', 'SOLICITADO']) ? Estacionamiento::cotizar($t) : null;

        return view('empresas.estacionamiento.publico', ['t' => $t, 'negocio' => $negocio, 'cobro' => $c]);
    }

    public function pedir(string $codigo): JsonResponse
    {
        $t = DB::table('est_tickets')->where('codigo', strtoupper($codigo))->first();
        abort_unless($t, 404);
        if (! in_array($t->estado, ['DENTRO', 'SOLICITADO'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Este ticket ya fue cerrado.']);
        }
        if ($t->estado === 'DENTRO') {
            DB::table('est_tickets')->where('tic_id', $t->tic_id)->where('estado', 'DENTRO')->update(['estado' => 'SOLICITADO', 'solicitado' => now()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Listo, ya estamos trayendo tu vehículo.']);
    }
}
