<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Carta;
use App\Support\ConsultaPeru;
use App\Support\Courier;
use App\Support\CourierCuentas;
use App\Support\CourierWeb;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Courier: envíos de la agencia (los que salen, los que vienen en camino, los que esperan ser recogidos), registro de un
 * envío nuevo con su cobro, guía con QR, despacho y recepción en bloque, entrega con clave, y tarifas y agencias.
 */
class CourierController extends Controller
{
    /** Vistas de la bandeja: lo que toca hacer en la agencia */
    private const VISTAS = [
        'salen' => 'Por despachar', 'camino' => 'En camino', 'llegan' => 'Por entregar', 'rezagados' => 'Rezagados', 'entregados' => 'Entregados', 'todos' => 'Todos',
    ];

    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan los envíos.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function admin(): string
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura las tarifas y agencias.');

        return (string) Auth::user()->IdEmpresa;
    }

    private function json(callable $accion): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Datos para cobrar (comprobantes, medios de pago y si hay turno abierto) */
    private function datosCobro(int $suc): array
    {
        return [
            'comprobantes' => DB::table('tipo_documento')->whereIn('tdocod', ['03', '01', '13'])->orderByRaw("FIELD(tdocod, '03', '01', '13')")->get(['tdocod', 'tdodes']),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'turno' => Turno::abiertoDe(Auth::user()),
        ];
    }

    private function reglasCobro(): array
    {
        return ['cobro' => 'nullable|array', 'cobro.tdocod' => 'nullable|in:01,03,13', 'cobro.clinum' => 'nullable|string|max:15',
            'cobro.clinom' => 'nullable|string|max:150', 'cobro.clidir' => 'nullable|string|max:200', 'cobro.id_med_pag' => 'nullable|integer',
            'cobro.paga' => 'nullable|numeric|min:0', 'imprimir' => 'nullable|boolean'];
    }

    /** Respuesta después de emitir: número, ticket y si salió por la impresora */
    private function respuestaCobro(?int $cabId, bool $imprimir): array
    {
        if (! $cabId) {
            return [];
        }
        $impreso = false;
        if ($imprimir) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['comprobante' => VentaDirecta::respuesta($cabId) + ['impreso' => $impreso]];
    }

    // ------------------------------------------------------------------ bandeja

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $user = Auth::user();
        Courier::agenciasPropias($user);
        $vista = array_key_exists($request->query('vista'), self::VISTAS) ? $request->query('vista') : 'salen';
        $q = trim((string) $request->query('q'));
        $limiteAlmacen = now()->subDays((int) Courier::config((string) $user->IdEmpresa)->dias_almacen);

        $base = fn () => Courier::deLaSucursal($user);
        $consulta = match ($vista) {
            'salen' => Courier::salen($user, $base())->where('e.estado', 'REGISTRADO'),
            'camino' => $base()->where('e.estado', 'EN_TRANSITO'),
            'llegan' => Courier::llegan($user, $base())->whereIn('e.estado', ['EN_DESTINO', 'EN_REPARTO']),
            // Esperan en la agencia más días de lo normal: se avisa al destinatario o se devuelven al remitente
            'rezagados' => Courier::llegan($user, $base())->where('e.estado', 'EN_DESTINO')->where('e.creado', '<', $limiteAlmacen),
            'entregados' => $base()->where('e.estado', 'ENTREGADO'),
            default => $base(),
        };
        if ($q !== '') {
            $consulta = $base()->where(fn ($w) => $w->where('e.codigo', strtoupper($q))->orWhere('e.rem_doc', $q)->orWhere('e.des_doc', $q)
                ->orWhere('e.rem_nombre', 'like', "%{$q}%")->orWhere('e.des_nombre', 'like', "%{$q}%")->orWhere('e.des_telefono', $q));
        }
        $envios = $consulta->orderByDesc('e.env_id')->limit(200)->get(['e.*']);
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->get()->keyBy('age_id');

        $hoy = now()->startOfDay();
        $kpi = (object) [
            'salen' => Courier::salen($user, Courier::deLaSucursal($user))->where('e.estado', 'REGISTRADO')->count(),
            'rezagados' => Courier::llegan($user, Courier::deLaSucursal($user))->where('e.estado', 'EN_DESTINO')->where('e.creado', '<', $limiteAlmacen)->count(),
            'vienen' => Courier::llegan($user, Courier::deLaSucursal($user))->where('e.estado', 'EN_TRANSITO')->count(),
            'llegan' => Courier::llegan($user, Courier::deLaSucursal($user))->whereIn('e.estado', ['EN_DESTINO', 'EN_REPARTO'])->count(),
            'porCobrar' => round((float) Courier::llegan($user, Courier::deLaSucursal($user))->whereIn('e.estado', ['EN_TRANSITO', 'EN_DESTINO', 'EN_REPARTO'])
                ->where('e.pago', 'DESTINO')->whereNull('e.IdCpe_cabecera')->sum('e.total'), 2),
            'hoy' => DB::table('cou_envios')->where('id_empresa_negocio', $suc)->where('creado', '>=', $hoy)->where('estado', '!=', 'ANULADO')->count(),
            'entregadosHoy' => Courier::llegan($user, Courier::deLaSucursal($user))->where('e.estado', 'ENTREGADO')->where('e.entregado', '>=', $hoy)->count(),
        ];

        return view('empresas.courier.index', [
            'vista' => $vista, 'vistas' => self::VISTAS, 'q' => $q, 'kpi' => $kpi, 'agencias' => $agencias,
            'envios' => $envios->map(function ($e) use ($agencias, $suc) {
                $lado = Courier::lado($e, $suc);

                return (object) ((array) $e + ['origen' => $agencias[$e->origen_id] ?? null, 'destino' => $agencias[$e->destino_id] ?? null,
                    'soyOrigen' => $lado['origen'], 'soyDestino' => $lado['destino']]);
            }),
        ] + $this->datosCobro($suc));
    }

    // ------------------------------------------------------------------ registro

    public function nuevo(): View
    {
        $suc = $this->sucursal();
        $user = Auth::user();
        $cfg = Courier::config((string) $user->IdEmpresa);

        return view('empresas.courier.nuevo', [
            'cfg' => $cfg, 'propias' => Courier::agenciasPropias($user), 'agencias' => Courier::agencias((string) $user->IdEmpresa),
            'tarifas' => DB::table('cou_tarifas')->where('IdEmpresa', $user->IdEmpresa)->get(),
        ] + $this->datosCobro($suc));
    }

    /** Nombre y teléfono de un DNI o RUC: primero los clientes, luego el padrón */
    public function documento(string $doc): JsonResponse
    {
        $this->sucursal();
        $doc = preg_replace('/\D/', '', $doc);
        $ruc = (string) Auth::user()->IdEmpresa;
        $cli = DB::table('cliente')->where('rucemp', $ruc)->where('clinum', $doc)->first(['clicod', 'clinom', 'telefono', 'clidir']);
        if ($cli) {
            // Cliente empresa con crédito: puede enviar sin pagar (hasta su límite)
            $credito = CourierCuentas::credito($ruc, (int) $cli->clicod);

            return response()->json(['nombre' => $cli->clinom, 'telefono' => $cli->telefono, 'direccion' => $cli->clidir !== '--' ? $cli->clidir : null,
                'credito' => $credito ? ['limite' => (float) $credito->limite, 'deuda' => round((float) CourierCuentas::sinFacturar((int) $cli->clicod)->sum('total'), 2)] : null]);
        }
        $r = strlen($doc) === 11 ? ConsultaPeru::ruc($doc) : (strlen($doc) === 8 ? ConsultaPeru::dni($doc) : null);

        return response()->json(['nombre' => $r['nombre'] ?? null, 'telefono' => null, 'direccion' => $r['direccion'] ?? null]);
    }

    /** Datos que el cliente dejó pre-registrados en la web (para llenar el formulario) */
    public function prerregistro(string $codigo): JsonResponse
    {
        $this->sucursal();
        $datos = CourierWeb::prerregistro($codigo);

        return response()->json($datos ? ['ok' => true, 'datos' => $datos]
            : ['ok' => false, 'mensaje' => 'No hay un pre-registro vigente con el código '.strtoupper($codigo).'.']);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate([
            'origen_id' => 'required|integer', 'destino_id' => 'required|integer',
            'rem_doc' => 'nullable|string|max:15', 'rem_nombre' => 'required|string|max:150', 'rem_telefono' => 'nullable|string|max:20',
            'des_doc' => 'nullable|string|max:15', 'des_nombre' => 'required|string|max:150', 'des_telefono' => 'nullable|string|max:20',
            'entrega' => 'required|in:AGENCIA,DOMICILIO', 'direccion_entrega' => 'nullable|string|max:200',
            'contenido' => 'nullable|string|max:200', 'valor_declarado' => 'nullable|numeric|min:0|max:9999999', 'pago' => 'required|in:'.implode(',', array_keys(Courier::PAGOS)),
            'observacion' => 'nullable|string|max:255', 'prerregistro' => 'nullable|string|size:6',
            'paquetes' => 'required|array|min:1|max:30', 'paquetes.*.tipo' => 'required|in:'.implode(',', Courier::TIPOS_PAQUETE),
            'paquetes.*.cantidad' => 'required|integer|min:1|max:500', 'paquetes.*.peso' => 'nullable|numeric|min:0|max:99999',
            'paquetes.*.largo' => 'nullable|numeric|min:0|max:9999', 'paquetes.*.ancho' => 'nullable|numeric|min:0|max:9999',
            'paquetes.*.alto' => 'nullable|numeric|min:0|max:9999', 'paquetes.*.descripcion' => 'nullable|string|max:100',
        ] + $this->reglasCobro(), [], ['rem_nombre' => 'nombre del remitente', 'des_nombre' => 'nombre del destinatario', 'destino_id' => 'agencia de destino']);
        $d['rem_doc'] = ($d['rem_doc'] ?? '') ?: '00000000';

        try {
            $r = Courier::registrar(Auth::user(), $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar el envío.']);
        }

        return response()->json(['ok' => true, 'id' => $r['env_id'], 'codigo' => DB::table('cou_envios')->where('env_id', $r['env_id'])->value('codigo'),
            'url' => route('courier.ver', $r['env_id']),
            'guia' => route('courier.guia', $r['env_id'])] + $this->respuestaCobro($r['cab_id'], (bool) ($d['imprimir'] ?? false)));
    }

    // ------------------------------------------------------------------ ficha y movimiento

    private function envio(int $id): object
    {
        $suc = $this->sucursal();
        $e = DB::table('cou_envios')->where('env_id', $id)->first();
        abort_unless($e, 404);
        $lado = Courier::lado($e, $suc);
        abort_unless($lado['origen'] || $lado['destino'] || (int) $e->id_empresa_negocio === $suc, 404);

        return $e;
    }

    public function ver(int $id): View
    {
        $e = $this->envio($id);
        $suc = (int) Auth::user()->id_empresa_negocio;
        $agencias = DB::table('cou_agencias')->whereIn('age_id', [$e->origen_id, $e->destino_id])->get()->keyBy('age_id');
        $lado = Courier::lado($e, $suc);

        return view('empresas.courier.ver', [
            'e' => $e, 'origen' => $agencias[$e->origen_id] ?? null, 'destino' => $agencias[$e->destino_id] ?? null, 'lado' => $lado,
            'paquetes' => DB::table('cou_paquetes')->where('env_id', $id)->get(),
            'eventos' => DB::table('cou_eventos as v')->leftJoin('cou_agencias as a', 'a.age_id', '=', 'v.age_id')->leftJoin('users as u', 'u.IdUsuario', '=', 'v.IdUsuario')
                ->where('v.env_id', $id)->orderByDesc('v.evt_id')->get(['v.*', 'a.ciudad', 'a.nombre as agencia', 'u.name as usuario']),
            'comprobante' => $e->IdCpe_cabecera ? DB::table('cpe_cabecera')->where('IdCpe_cabecera', $e->IdCpe_cabecera)->first(['IdCpe_cabecera', 'serdoc', 'numdoc', 'ccaitv']) : null,
            'rastreo' => Courier::urlRastreo($e->codigo), 'esAdmin' => Auth::user()->esAdmin(),
            // Cobra quien corresponde: el origen si pagó al enviar (comprobante anulado) o el destino si paga al recoger;
            // el crédito se cobra con la factura del estado de cuenta
            'puedeCobrar' => ! $e->IdCpe_cabecera && (float) $e->total > 0 && ! in_array($e->estado, ['ANULADO', 'DEVUELTO'], true)
                && ($e->pago === 'ORIGEN' ? $lado['origen'] : ($e->pago === 'DESTINO' && $lado['destino'])),
            'manifiesto' => $e->man_id ? DB::table('cou_manifiestos')->where('man_id', $e->man_id)->first() : null,
            'motorizado' => $e->mot_id ? DB::table('motorizados')->where('mot_id', $e->mot_id)->value('nombre') : null,
            'devolucion' => DB::table('cou_envios')->where('devuelto_de', $id)->first(['env_id', 'codigo']),
            'original' => $e->devuelto_de ? DB::table('cou_envios')->where('env_id', $e->devuelto_de)->first(['env_id', 'codigo']) : null,
            'reclamos' => DB::table('cou_reclamos')->where('env_id', $id)->orderByDesc('rec_id')->get(),
            'tiposReclamo' => CourierWeb::TIPOS_RECLAMO,
        ] + $this->datosCobro($suc));
    }

    /** Devolver al remitente lo que nadie recogió o rechazaron */
    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->envio($id);
        $d = $request->validate(['motivo' => 'required|string|max:120', 'cobrar' => 'nullable|boolean'], [], ['motivo' => 'motivo']);

        return $this->json(function () use ($id, $d) {
            $vuelta = Courier::devolver(Auth::user(), $id, $d['motivo'], (bool) ($d['cobrar'] ?? false));

            return ['mensaje' => 'Devolución registrada: imprime su guía.', 'url' => route('courier.ver', $vuelta), 'guia' => route('courier.guia', $vuelta)];
        });
    }

    /** Reclamo que el cliente hace en la agencia o por teléfono */
    public function reclamar(Request $request, int $id): JsonResponse
    {
        $e = $this->envio($id);
        $d = $request->validate(['tipo' => 'required|in:'.implode(',', array_keys(CourierWeb::TIPOS_RECLAMO)), 'detalle' => 'required|string|max:2000',
            'pedido' => 'nullable|string|max:255', 'nombre' => 'required|string|max:150', 'documento' => 'nullable|string|max:15',
            'telefono' => 'nullable|string|max:20'], [], ['detalle' => 'detalle del reclamo', 'nombre' => 'nombre de quien reclama']);
        $numero = CourierWeb::reclamar($e, $d, 'AGENCIA', Auth::id());

        return response()->json(['ok' => true, 'mensaje' => "Reclamo N° {$numero} registrado."]);
    }

    /** Guía para imprimir: copia del remitente (con su clave) y etiqueta para pegar en el paquete */
    public function guia(int $id): View
    {
        $e = $this->envio($id);
        $agencias = DB::table('cou_agencias')->whereIn('age_id', [$e->origen_id, $e->destino_id])->get()->keyBy('age_id');
        $negocio = DB::table('empresa_negocios as n')->join('empresa as em', 'em.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $e->id_empresa_negocio)->first(['n.nombre_comercial', 'n.direccion', 'n.telefono', 'em.NomEmpresa', 'em.IdEmpresa']);

        return view('empresas.courier.guia', [
            'e' => $e, 'origen' => $agencias[$e->origen_id] ?? null, 'destino' => $agencias[$e->destino_id] ?? null, 'negocio' => $negocio,
            'paquetes' => DB::table('cou_paquetes')->where('env_id', $id)->get(),
            // Una etiqueta por bulto (cada una con su número): así en destino se escanea bulto por bulto
            'qrs' => collect(range(1, max(1, (int) $e->piezas)))->mapWithKeys(fn ($b) => [$b => Carta::qr(Courier::urlRastreo($e->codigo).'?b='.$b, 180)]),
            'copias' => in_array(request()->query('copia'), ['remitente', 'paquete'], true) ? [request()->query('copia')] : ['remitente', 'paquete'],
        ]);
    }

    /** Despachar, recibir en destino o salir a reparto varios envíos a la vez */
    public function avanzar(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer', 'estado' => 'required|in:'.implode(',', array_keys(Courier::PASOS))]);

        return $this->json(function () use ($d) {
            $n = Courier::avanzar(Auth::user(), $d['ids'], $d['estado']);
            if (! $n) {
                throw new \RuntimeException('Ninguno de los envíos elegidos se puede pasar a ese estado desde tu agencia.');
            }

            return ['mensaje' => $n.' envío(s): '.mb_strtolower(Courier::ESTADOS[$d['estado']][0]).'.'];
        });
    }

    public function entregar(Request $request, int $id): JsonResponse
    {
        $this->envio($id);
        $d = $request->validate(['clave' => 'nullable|string|max:4', 'motivo_sin_clave' => 'nullable|string|max:120',
            'recibe_nombre' => 'nullable|string|max:150', 'recibe_doc' => 'nullable|string|max:15'] + $this->reglasCobro());

        try {
            $cabId = Courier::entregar(Auth::user(), $id, $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar la entrega.']);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Envío entregado.'] + $this->respuestaCobro($cabId, (bool) ($d['imprimir'] ?? false)));
    }

    /** Cobro pendiente: pagaba al recoger, o se anuló el comprobante con que se cobró */
    public function cobrar(Request $request, int $id): JsonResponse
    {
        $e = $this->envio($id);
        $d = $request->validate($this->reglasCobro());
        $lado = Courier::lado($e, (int) Auth::user()->id_empresa_negocio);
        if ($e->estado === 'ANULADO' || ! ($e->pago === 'ORIGEN' ? $lado['origen'] : $lado['destino'])) {
            return response()->json(['ok' => false, 'mensaje' => $e->pago === 'ORIGEN' ? 'Lo cobra la agencia de origen.' : 'Lo cobra la agencia de destino.']);
        }

        try {
            $cabId = DB::transaction(function () use ($id, $d, $e) {
                $envio = DB::table('cou_envios')->where('env_id', $id)->lockForUpdate()->first();

                return Courier::cobrar(Auth::user(), $envio, (array) ($d['cobro'] ?? []), $e->pago === 'ORIGEN'
                    ? ['clinum' => $e->rem_doc, 'clinom' => $e->rem_nombre] : ['clinum' => $e->des_doc ?: '00000000', 'clinom' => $e->des_nombre]);
            });
        } catch (\RuntimeException $ex) {
            return response()->json(['ok' => false, 'mensaje' => $ex->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Cobrado.'] + $this->respuestaCobro($cabId, (bool) ($d['imprimir'] ?? false)));
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $this->envio($id);
        $d = $request->validate(['motivo' => 'required|string|max:200'], [], ['motivo' => 'motivo']);

        return $this->json(function () use ($id, $d) {
            Courier::anular(Auth::user(), $id, $d['motivo']);

            return ['mensaje' => 'Envío anulado.'];
        });
    }

    // ------------------------------------------------------------------ tarifas y agencias

    public function tarifas(): View
    {
        $ruc = $this->admin();
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $ruc)->orderByDesc('activo')->orderBy('ciudad')->orderBy('nombre')->get();
        if ($agencias->isEmpty()) {
            Courier::agenciasPropias(Auth::user());

            return $this->tarifas();
        }

        return view('empresas.courier.tarifas', [
            'cfg' => Courier::config($ruc), 'agencias' => $agencias,
            'tarifas' => DB::table('cou_tarifas')->where('IdEmpresa', $ruc)->get(),
            'sucursales' => DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->orderBy('id_empresa_negocio')->get(['id_empresa_negocio', 'nombre_comercial']),
        ]);
    }

    public function guardarConfig(Request $request): RedirectResponse
    {
        $ruc = $this->admin();
        Courier::config($ruc);
        $d = $request->validate([
            'divisor_vol' => 'required|integer|min:0|max:20000', 'seguro_pct' => 'required|numeric|min:0|max:100', 'seguro_min' => 'required|numeric|min:0|max:9999',
            'base' => 'required|numeric|min:0|max:99999', 'kg_incluidos' => 'required|numeric|min:0|max:9999', 'por_kg' => 'required|numeric|min:0|max:9999',
            'sobre' => 'required|numeric|min:0|max:9999', 'domicilio' => 'required|numeric|min:0|max:9999', 'dias' => 'required|integer|min:0|max:60',
            'dias_almacen' => 'required|integer|min:1|max:365',
        ]);
        DB::table('cou_config')->where('IdEmpresa', $ruc)->update($d + ['web_publico' => $request->boolean('web_publico'), 'updated_at' => now()]);

        return back()->with('success', 'Tarifa general guardada.');
    }

    public function guardarAgencia(Request $request): RedirectResponse
    {
        $ruc = $this->admin();
        $d = $request->validate([
            'age_id' => 'nullable|integer', 'nombre' => 'required|string|max:100', 'ciudad' => 'required|string|max:60',
            'direccion' => 'nullable|string|max:200', 'telefono' => 'nullable|string|max:30', 'id_empresa_negocio' => 'nullable|integer', 'activo' => 'nullable|boolean',
            'comision_pct' => 'nullable|numeric|min:0|max:100',
        ], [], ['ciudad' => 'ciudad', 'comision_pct' => 'comisión']);
        if (! empty($d['id_empresa_negocio'])) {
            abort_unless(DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->where('id_empresa_negocio', $d['id_empresa_negocio'])->exists(), 404);
        }
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'ciudad' => mb_strtoupper(trim($d['ciudad'])),
            'direccion' => isset($d['direccion']) ? mb_strtoupper(trim($d['direccion'])) : null, 'telefono' => $d['telefono'] ?? null,
            'id_empresa_negocio' => ($d['id_empresa_negocio'] ?? null) ?: null, 'activo' => $request->boolean('activo', true),
            'comision_pct' => round((float) ($d['comision_pct'] ?? 0), 2)];
        if (! empty($d['age_id'])) {
            DB::table('cou_agencias')->where('age_id', $d['age_id'])->where('IdEmpresa', $ruc)->update($fila);
        } else {
            DB::table('cou_agencias')->insert($fila + ['IdEmpresa' => $ruc]);
        }

        return back()->with('success', 'Agencia guardada.');
    }

    public function guardarTarifa(Request $request): RedirectResponse
    {
        $ruc = $this->admin();
        $d = $request->validate([
            'origen_id' => 'required|integer|different:destino_id', 'destino_id' => 'required|integer',
            'base' => 'required|numeric|min:0|max:99999', 'kg_incluidos' => 'required|numeric|min:0|max:9999', 'por_kg' => 'required|numeric|min:0|max:9999',
            'sobre' => 'required|numeric|min:0|max:9999', 'domicilio' => 'required|numeric|min:0|max:9999', 'dias' => 'required|integer|min:0|max:60',
        ], ['origen_id.different' => 'El origen y el destino deben ser agencias distintas.']);
        abort_unless(DB::table('cou_agencias')->where('IdEmpresa', $ruc)->whereIn('age_id', [$d['origen_id'], $d['destino_id']])->count() === 2, 404);
        // Una sola tarifa por ruta (sirve en los dos sentidos): la de ida reemplaza a la de vuelta
        DB::table('cou_tarifas')->where('IdEmpresa', $ruc)->where('origen_id', $d['destino_id'])->where('destino_id', $d['origen_id'])->delete();
        DB::table('cou_tarifas')->updateOrInsert(['origen_id' => $d['origen_id'], 'destino_id' => $d['destino_id']], $d + ['IdEmpresa' => $ruc]);

        return back()->with('success', 'Tarifa de la ruta guardada (vale en los dos sentidos).');
    }

    public function quitarTarifa(int $id): RedirectResponse
    {
        $ruc = $this->admin();
        DB::table('cou_tarifas')->where('tar_id', $id)->where('IdEmpresa', $ruc)->delete();

        return back()->with('success', 'Esa ruta vuelve a usar la tarifa general.');
    }
}
