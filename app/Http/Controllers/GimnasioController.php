<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Gimnasio;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gimnasio (recepción y administración): clientes con foto y huella, planes, venta de membresías,
 * congelamientos y el control de ingreso (DNI, QR del carnet o huella).
 * El cliente del gimnasio es un socio: usa el mismo carnet con QR y el portal {subdominio}/socio.
 */
class GimnasioController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    /** Recepción: administrador o caja */
    private function recepcion(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede hacer esto.');
    }

    private function json(callable $accion)
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function socio(int $id): object
    {
        $s = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.soc_id', $id)->where('s.id_empresa_negocio', $this->sucursal())
            ->first(['s.*', 'c.tdicod', 'c.clinum', 'c.clinom', 'c.clidir', 'c.clicor', 'c.telefono']);
        abort_unless($s, 404);

        return $s;
    }

    private static function urlFoto(?string $foto): ?string
    {
        return $foto && is_file(public_path($foto)) ? asset($foto) : null;
    }

    private function entrenadores()
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', Gimnasio::ROL_ENTRENADOR)->where('u.IdEmpresa', Auth::user()->IdEmpresa)->where('u.estusu', 1)
            ->orderBy('u.apeusu')->get(['u.IdUsuario', 'u.apeusu']);
    }

    // ------------------------------------------------------------------ pantalla principal

    public function index()
    {
        $this->recepcion();
        $suc = $this->sucursal();

        return view('empresas.gimnasio.index', [
            'clientes' => $this->lista(),
            'planes' => DB::table('gym_planes')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderBy('dias')->get(),
            'entrenadores' => $this->entrenadores(),
            'mediospagos' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'turno' => Turno::abiertoDe(Auth::user()),
            'esAdmin' => Auth::user()->esAdmin(),
            'motivos' => Gimnasio::MOTIVOS,
            'hoy' => DB::table('gym_asistencias')->where('id_empresa_negocio', $suc)->whereDate('fecha_hora', now()->toDateString())
                ->where('resultado', 'PERMITIDO')->distinct()->count('soc_id'),
        ]);
    }

    /** Clientes con su situación de hoy (para la lista; también la pide la pantalla al guardar) */
    public function lista()
    {
        $this->recepcion();
        $suc = $this->sucursal();
        $mems = Gimnasio::membresiasVigentes($suc);
        $cong = Gimnasio::congelamientosVigentes($mems->flatten()->pluck('mem_id')->all());
        $entrenadores = $this->entrenadores()->pluck('apeusu', 'IdUsuario');

        $clientes = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.id_empresa_negocio', $suc)->whereNotIn('s.estado', ['FALLECIDO'])->orderBy('c.clinom')
            ->get(['s.soc_id', 's.codigo', 's.estado', 's.foto', 's.huella', 's.entrenador_id', 's.fecha_nac', 'c.clinum', 'c.clinom', 'c.telefono'])
            ->map(function ($s) use ($mems, $cong, $entrenadores) {
                $sit = Gimnasio::situacion($mems->get($s->soc_id, collect()), $cong);

                return [
                    'soc_id' => $s->soc_id, 'codigo' => $s->codigo, 'estado_socio' => $s->estado, 'nombre' => $s->clinom, 'doc' => $s->clinum,
                    'telefono' => $s->telefono, 'foto' => self::urlFoto($s->foto), 'huella' => (bool) $s->huella,
                    'entrenador' => $entrenadores[$s->entrenador_id] ?? null,
                    'cumple' => $s->fecha_nac && Carbon::parse($s->fecha_nac)->format('m-d') === now()->format('m-d'),
                    'estado' => $sit['estado'], 'texto' => $sit['texto'], 'color' => $sit['color'],
                    'plan' => $sit['membresia']->plan ?? null, 'vence' => $sit['vence'], 'dias' => $sit['dias'],
                    'congelado_hasta' => $sit['congelamiento']->hasta ?? null,
                ];
            })->values();

        return request()->expectsJson() ? response()->json($clientes) : $clientes;
    }

    /** Ficha: datos, membresías, congelamientos, ingresos y nutrición */
    public function ficha(int $id)
    {
        $this->recepcion();
        $s = $this->socio($id);
        $sit = Gimnasio::situacionDe($id, $this->sucursal());

        return response()->json([
            'cliente' => [
                'soc_id' => $s->soc_id, 'codigo' => $s->codigo, 'estado' => $s->estado, 'clinum' => $s->clinum, 'clinom' => $s->clinom,
                'telefono' => $s->telefono, 'clicor' => $s->clicor, 'fecha_nac' => $s->fecha_nac, 'huella' => $s->huella,
                'entrenador_id' => $s->entrenador_id, 'obs' => $s->obs, 'foto' => self::urlFoto($s->foto),
                'edad' => $s->fecha_nac ? Carbon::parse($s->fecha_nac)->age : null, 'acceso' => $s->acceso, 'tiene_clave' => (bool) $s->clave,
            ],
            'situacion' => ['estado' => $sit['estado'], 'texto' => $sit['texto'], 'color' => $sit['color'], 'vence' => $sit['vence'], 'dias' => $sit['dias']],
            'membresias' => DB::table('gym_membresias as m')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'm.IdCpe_cabecera')
                ->where('m.soc_id', $id)->orderByDesc('m.inicio')->orderByDesc('m.mem_id')->limit(40)
                ->get(['m.mem_id', 'm.plan', 'm.dias', 'm.inicio', 'm.fin', 'm.precio', 'm.estado', 'm.congelar_max', 'm.IdCpe_cabecera',
                    DB::raw("CONCAT(c.serdoc, '-', LPAD(c.numdoc, 8, '0')) as comprobante")]),
            'congelamientos' => DB::table('gym_congelamientos as g')->leftJoin('users as u', 'u.IdUsuario', '=', 'g.IdUsuario')
                ->where('g.soc_id', $id)->orderByDesc('g.desde')->limit(40)->get(['g.*', 'u.apeusu']),
            'asistencias' => DB::table('gym_asistencias')->where('soc_id', $id)->orderByDesc('fecha_hora')->limit(60)
                ->get(['fecha_hora', 'metodo', 'resultado', 'motivo']),
            'nutricion' => $this->nutricionDe($id),
            'planes' => DB::table('gym_planes')->where('id_empresa_negocio', $this->sucursal())->where('activo', 1)->orderBy('dias')->get()
                ->map(fn ($p) => (array) $p + ['inicio' => Gimnasio::inicioSugerido($id, $this->sucursal(), (int) $p->dias)]),
        ]);
    }

    public static function nutricionDe(int $socId)
    {
        return DB::table('gym_nutricion as n')->leftJoin('users as u', 'u.IdUsuario', '=', 'n.IdUsuario')
            ->where('n.soc_id', $socId)->where('n.activo', 1)->orderByDesc('n.fecha')->orderByDesc('n.nut_id')
            ->get(['n.*', 'u.apeusu as entrenador'])
            ->map(fn ($n) => (array) $n + ['imc' => Gimnasio::imc((float) $n->peso, (float) $n->talla)]);
    }

    /** Registrar o editar cliente (con foto: archivo o captura de la cámara) */
    public function guardar(Request $request)
    {
        $this->recepcion();
        $d = $request->validate([
            'soc_id' => 'nullable|integer',
            'clinum' => ['required', 'regex:/^\d{8}$|^\d{11}$|^[A-Za-z0-9]{6,15}$/'],
            'clinom' => 'required|string|min:3|max:150',
            'telefono' => 'nullable|string|max:20', 'clicor' => 'nullable|email|max:100',
            'fecha_nac' => 'nullable|date|before:today', 'huella' => 'nullable|string|max:30',
            'entrenador_id' => 'nullable|integer', 'obs' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:6144', 'quitar_foto' => 'nullable|boolean',
        ], ['clinum.regex' => 'Escribe el DNI (8 dígitos), RUC (11) o carné de extranjería.'],
            ['clinum' => 'DNI', 'clinom' => 'nombre completo', 'fecha_nac' => 'fecha de nacimiento', 'telefono' => 'celular']);

        return $this->json(function () use ($request, $d) {
            if (! empty($d['entrenador_id']) && ! $this->entrenadores()->contains('IdUsuario', (int) $d['entrenador_id'])) {
                throw new \RuntimeException('Entrenador no válido.');
            }
            $socId = Gimnasio::guardarCliente(Auth::user(), $d);
            $this->guardarFoto($request, $socId);

            return ['mensaje' => 'Cliente guardado.', 'soc_id' => $socId];
        });
    }

    private function guardarFoto(Request $request, int $socId): void
    {
        $anterior = DB::table('socios')->where('soc_id', $socId)->value('foto');
        if ($request->hasFile('foto')) {
            $archivo = $request->file('foto');
            $carpeta = 'imagenes/gimnasio/'.preg_replace('/\D/', '', (string) Auth::user()->IdEmpresa);
            $nombre = $socId.'_'.uniqid().'.'.strtolower($archivo->guessExtension() ?: 'jpg');
            $archivo->move(public_path($carpeta), $nombre);
            DB::table('socios')->where('soc_id', $socId)->update(['foto' => $carpeta.'/'.$nombre]);
        } elseif ($request->boolean('quitar_foto')) {
            DB::table('socios')->where('soc_id', $socId)->update(['foto' => null]);
        } else {
            return;
        }
        if ($anterior && str_starts_with($anterior, 'imagenes/gimnasio/') && is_file(public_path($anterior))) {
            @unlink(public_path($anterior));
        }
    }

    /** Activo / retirado (el retirado no entra ni ve su portal) */
    public function estado(Request $request, int $id)
    {
        $this->soloAdmin();
        $d = $request->validate(['estado' => 'required|in:ACTIVO,RETIRADO,SUSPENDIDO']);
        $this->socio($id);
        DB::table('socios')->where('soc_id', $id)->update(['estado' => $d['estado'], 'suspendido_auto' => 0]);

        return response()->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
    }

    /** Olvidó su contraseña del portal: vuelve a ser su DNI */
    public function restablecerClave(int $id)
    {
        $this->recepcion();
        $s = $this->socio($id);
        DB::table('socios')->where('soc_id', $id)->update(['clave' => null]);

        return response()->json(['ok' => true, 'mensaje' => "Listo: ahora entra al portal con su DNI {$s->clinum} como usuario y contraseña."]);
    }

    // ------------------------------------------------------------------ planes

    public function guardarPlan(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'plan_id' => 'nullable|integer', 'nombre' => 'required|string|max:80', 'dias' => 'required|integer|min:1|max:1095',
            'precio' => 'required|numeric|min:0.1|max:99999', 'congelar_max' => 'nullable|integer|min:0|max:365', 'activo' => 'nullable|boolean',
        ], [], ['dias' => 'días', 'congelar_max' => 'días que puede congelar']);
        $suc = $this->sucursal();
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'dias' => $d['dias'], 'precio' => $d['precio'],
            'congelar_max' => (int) $d['dias'] > 1 ? (int) ($d['congelar_max'] ?? 0) : 0, 'activo' => (int) ($d['activo'] ?? 1)];

        return $this->json(function () use ($d, $suc, $fila) {
            return DB::transaction(function () use ($d, $suc, $fila) {
                if (! empty($d['plan_id'])) {
                    $plan = DB::table('gym_planes')->where('plan_id', $d['plan_id'])->where('id_empresa_negocio', $suc)->first();
                    if (! $plan) {
                        throw new \RuntimeException('Plan no encontrado.');
                    }
                    DB::table('gym_planes')->where('plan_id', $plan->plan_id)->update($fila);
                    $id = $plan->plan_id;
                } else {
                    $id = DB::table('gym_planes')->insertGetId($fila + ['id_empresa_negocio' => $suc]);
                }
                $plan = DB::table('gym_planes')->where('plan_id', $id)->first();
                DB::table('gym_planes')->where('plan_id', $id)->update(['IdProducto' => Gimnasio::productoDelPlan(Auth::user(), $plan)]);

                return ['mensaje' => 'Plan guardado.', 'planes' => DB::table('gym_planes')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderBy('dias')->get()];
            });
        });
    }

    // ------------------------------------------------------------------ venta

    public function vender(Request $request, int $id)
    {
        $this->recepcion();
        $d = $request->validate([
            'plan_id' => 'required|integer', 'inicio' => 'required|date|after_or_equal:'.now()->subDays(7)->toDateString(),
            'precio' => 'required|numeric|min:0.1|max:99999',
            'tdocod' => 'required|in:01,03,13', 'clinum' => 'nullable|string|max:15', 'clinom' => 'nullable|string|max:150',
            'tdicod' => 'nullable|string|size:1', 'clidir' => 'nullable|string|max:150',
            'id_med_pag' => 'nullable|array|max:5', 'mon_med_pag' => 'nullable|array|max:5', 'paga' => 'nullable|numeric|min:0',
            'imprimir' => 'nullable|boolean',
        ], ['inicio.after_or_equal' => 'La fecha de inicio no puede ser de hace más de 7 días.']);
        $this->socio($id);

        try {
            $r = Gimnasio::vender(Auth::user(), $id, (int) $d['plan_id'], $d['inicio'], $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar la venta.']);
        }

        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($r['cabId']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso, 'mensaje' => 'Plan vendido.'] + VentaDirecta::respuesta($r['cabId']));
    }

    // ------------------------------------------------------------------ congelamientos (administrador)

    public function congelar(Request $request, int $id)
    {
        $this->soloAdmin();
        $d = $this->validarCongelamiento($request);
        $this->socio($id);

        return $this->json(function () use ($id, $d) {
            Gimnasio::congelar($id, $this->sucursal(), $d, 'ADMIN', Auth::id());

            return ['mensaje' => 'Congelamiento registrado: la membresía se alargó '.(Carbon::parse($d['desde'])->diffInDays(Carbon::parse($d['hasta'])) + 1).' días.'];
        });
    }

    public function editarCongelamiento(Request $request, int $conId)
    {
        $this->soloAdmin();
        $d = $this->validarCongelamiento($request);
        $c = DB::table('gym_congelamientos')->where('con_id', $conId)->where('estado', 'ACTIVO')->first();
        abort_unless($c, 404);
        $this->socio((int) $c->soc_id);

        return $this->json(function () use ($c, $d) {
            Gimnasio::congelar((int) $c->soc_id, $this->sucursal(), $d, 'ADMIN', Auth::id(), (int) $c->con_id);

            return ['mensaje' => 'Congelamiento actualizado.'];
        });
    }

    public function anularCongelamiento(int $conId)
    {
        $this->soloAdmin();

        return $this->json(function () use ($conId) {
            Gimnasio::anularCongelamiento($conId, $this->sucursal(), Auth::id());

            return ['mensaje' => 'Congelamiento anulado: esos días ya no se suman a la membresía.'];
        });
    }

    /** El cliente volvió antes: desde hoy su membresía vuelve a correr */
    public function levantarCongelamiento(int $conId)
    {
        $this->soloAdmin();

        return $this->json(function () use ($conId) {
            Gimnasio::levantarCongelamiento($conId, $this->sucursal(), Auth::id());

            return ['mensaje' => 'Congelamiento levantado: desde hoy puede entrar y su plan vuelve a correr.'];
        });
    }

    private function validarCongelamiento(Request $request): array
    {
        return $request->validate([
            'desde' => 'required|date', 'hasta' => 'required|date|after_or_equal:desde',
            'motivo' => 'required|in:'.implode(',', Gimnasio::MOTIVOS), 'detalle' => 'nullable|string|max:200',
        ], [], ['desde' => 'desde', 'hasta' => 'hasta']);
    }

    // ------------------------------------------------------------------ control de ingreso

    public function acceso()
    {
        $this->recepcion();
        $suc = $this->sucursal();

        return view('empresas.gimnasio.acceso', [
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(),
            'esAdmin' => Auth::user()->esAdmin(),
            'ingresos' => $this->ingresosHoy(),
        ]);
    }

    /** Últimos ingresos de hoy (la pantalla de acceso los refresca) */
    public function ingresosHoy()
    {
        $this->recepcion();
        $filas = DB::table('gym_asistencias as a')->join('socios as s', 's.soc_id', '=', 'a.soc_id')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('a.id_empresa_negocio', $this->sucursal())->whereDate('a.fecha_hora', now()->toDateString())
            ->orderByDesc('a.asi_id')->limit(40)->get(['a.fecha_hora', 'a.resultado', 'a.metodo', 'c.clinom', 's.foto'])
            ->map(fn ($a) => ['hora' => Carbon::parse($a->fecha_hora)->format('H:i'), 'resultado' => $a->resultado, 'metodo' => $a->metodo,
                'nombre' => $a->clinom, 'foto' => self::urlFoto($a->foto)]);

        return request()->expectsJson() ? response()->json($filas) : $filas;
    }

    /** DNI, QR del carnet, código de huella o código de cliente */
    public function marcar(Request $request)
    {
        $this->recepcion();
        $d = $request->validate(['texto' => 'required|string|max:200']);
        [$socio, $metodo] = Gimnasio::identificar($d['texto'], $this->sucursal());
        if (! $socio) {
            return response()->json(['ok' => false, 'encontrado' => false, 'mensaje' => 'No se encontró al cliente. Revisa el DNI o regístralo en recepción.']);
        }

        return response()->json($this->respuestaIngreso($socio, Gimnasio::registrarIngreso($socio, $metodo)));
    }

    /** Ingreso con autorización del administrador (congelado o vencido): el administrador logueado o con su usuario y clave */
    public function aprobar(Request $request)
    {
        $this->recepcion();
        $d = $request->validate([
            'soc_id' => 'required|integer', 'motivo' => 'required|string|min:3|max:120',
            'usuario' => 'nullable|string|max:100', 'password' => 'nullable|string|max:100',
        ], [], ['motivo' => 'motivo']);
        $socio = DB::table('socios')->where('soc_id', $d['soc_id'])->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($socio, 404);

        $adminId = Auth::user()->esAdmin() ? Auth::id() : null;
        if (! $adminId) {
            $clave = 'gimnasio-aprobar|'.$request->ip();
            if (RateLimiter::tooManyAttempts($clave, 5)) {
                return response()->json(['ok' => false, 'mensaje' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.']);
            }
            $admin = DB::table('users')->where(fn ($w) => $w->where('email', $d['usuario'] ?? '')->orWhere('name', $d['usuario'] ?? ''))
                ->where('IdEmpresa', Auth::user()->IdEmpresa)->where('estusu', 1)->first();
            $esAdmin = $admin && DB::table('role_user')->where('user_IdUsuario', $admin->IdUsuario)->where('role_id', 2)->exists();
            if (! $admin || ! Hash::check((string) ($d['password'] ?? ''), $admin->password) || ! $esAdmin) {
                RateLimiter::hit($clave, 300);

                return response()->json(['ok' => false, 'mensaje' => 'Usuario o clave del administrador incorrectos.']);
            }
            RateLimiter::clear($clave);
            $adminId = (int) $admin->IdUsuario;
        }

        return response()->json($this->respuestaIngreso($socio, Gimnasio::registrarIngreso($socio, 'MANUAL', $adminId, trim($d['motivo']))));
    }

    private function respuestaIngreso(object $socio, array $r): array
    {
        $cli = DB::table('cliente')->where('clicod', $socio->clicod)->first(['clinom', 'clinum']);
        $sit = $r['situacion'];
        $proximo = $sit['proximo'] ?? null;

        return [
            'ok' => true, 'encontrado' => true, 'permitido' => $r['permitido'], 'requiere_aprobacion' => $r['requiere_aprobacion'],
            'mensaje' => $r['mensaje'], 'soc_id' => $socio->soc_id, 'nombre' => $cli->clinom ?? '', 'doc' => $cli->clinum ?? '',
            'foto' => self::urlFoto($socio->foto), 'plan' => $sit['membresia']->plan ?? null, 'estado' => $sit['texto'],
            'vence' => $sit['vence'] ? Carbon::parse($sit['vence'])->format('d/m/Y') : null, 'dias' => $sit['dias'],
            'cumple' => $socio->fecha_nac && Carbon::parse($socio->fecha_nac)->format('m-d') === now()->format('m-d'),
            'aviso_congelamiento' => $proximo ? 'Tiene congelamiento programado del '.Carbon::parse($proximo->desde)->format('d/m').' al '.Carbon::parse($proximo->hasta)->format('d/m').'.' : null,
        ];
    }
}
