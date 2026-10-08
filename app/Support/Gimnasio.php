<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gimnasio: membresías, congelamientos, ingreso y nutrición.
 *
 *  - Una membresía cubre de inicio a fin; fin = inicio + días del plan - 1 + días congelados.
 *    Congelar alarga el fin: al terminar el congelamiento los días vuelven a correr solos.
 *  - Durante un congelamiento NO se ingresa: hace falta aprobación del administrador o comprar la rutina del día
 *    (otra membresía de 1 día, que no está congelada).
 *  - El cliente congela desde su portal (no lo puede deshacer); el administrador puede editarlo, levantarlo o anularlo.
 */
class Gimnasio
{
    public const ROL_ENTRENADOR = 11;

    public const MOTIVOS = ['VIAJE', 'ENFERMEDAD', 'LESIÓN', 'TRABAJO', 'ESTUDIOS', 'OTRO'];

    public const OBJETIVOS = ['BAJAR DE PESO', 'GANAR MASA MUSCULAR', 'DEFINICIÓN', 'MANTENIMIENTO', 'SALUD / REHABILITACIÓN'];

    /** Días que avisa "por vencer" */
    public const DIAS_AVISO = 5;

    public static function esEntrenador(User $u): bool
    {
        return $u->tieneRol([self::ROL_ENTRENADOR]);
    }

    /** La sucursal trabaja como gimnasio (tiene planes): el portal del socio muestra la vista del gimnasio */
    public static function usa(int $suc): bool
    {
        return DB::table('gym_planes')->where('id_empresa_negocio', $suc)->exists();
    }

    // ------------------------------------------------------------------ situación

    /** Membresías vigentes o futuras de la sucursal (para la lista), agrupadas por cliente */
    public static function membresiasVigentes(int $suc, ?int $socId = null): Collection
    {
        return DB::table('gym_membresias')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVA')
            ->when($socId, fn ($q) => $q->where('soc_id', $socId))
            ->where('fin', '>=', now()->subDays(60)->toDateString())
            ->orderBy('inicio')->get()->groupBy('soc_id');
    }

    /** Congelamientos activos que aún no terminan, agrupados por membresía */
    public static function congelamientosVigentes(array $memIds): Collection
    {
        return $memIds ? DB::table('gym_congelamientos')->whereIn('mem_id', $memIds)->where('estado', 'ACTIVO')
            ->where('hasta', '>=', now()->toDateString())->orderBy('desde')->get()->groupBy('mem_id') : collect();
    }

    /**
     * Situación del cliente en una fecha.
     *
     * Estados: VIGENTE | POR_VENCER | CONGELADO | PROXIMO (aún no empieza) | VENCIDO | SIN_PLAN.
     *
     * @return array{estado: string, texto: string, color: string, membresia: ?object, congelamiento: ?object, vence: ?string, dias: int, proximo: ?object}
     */
    public static function situacion(Collection $membresias, Collection $congelamientos, ?string $fecha = null): array
    {
        $hoy = $fecha ?: now()->toDateString();
        $cubren = $membresias->filter(fn ($m) => $m->inicio <= $hoy && $m->fin >= $hoy);
        $congeladaHoy = fn ($m) => ($congelamientos[$m->mem_id] ?? collect())->first(fn ($c) => $c->desde <= $hoy && $c->hasta >= $hoy);

        // Una membresía que cubre hoy y no está congelada (por ejemplo la rutina del día) deja entrar
        $libre = $cubren->first(fn ($m) => ! $congeladaHoy($m));
        $ultimoFin = $membresias->max('fin');
        $proximoCongelamiento = $membresias->flatMap(fn ($m) => $congelamientos[$m->mem_id] ?? [])->first(fn ($c) => $c->desde > $hoy);

        if ($libre) {
            $dias = Carbon::parse($hoy)->diffInDays(Carbon::parse($ultimoFin)) + 1;
            $porVencer = $dias <= self::DIAS_AVISO;

            return ['estado' => $porVencer ? 'POR_VENCER' : 'VIGENTE', 'texto' => $porVencer ? 'POR VENCER' : 'VIGENTE', 'color' => $porVencer ? 'amber' : 'green',
                'membresia' => $libre, 'congelamiento' => null, 'vence' => $ultimoFin, 'dias' => (int) $dias, 'proximo' => $proximoCongelamiento];
        }
        if ($m = $cubren->first()) {
            // Días que le quedan cuando vuelva (los congelados no se cuentan)
            $c = $congeladaHoy($m);

            return ['estado' => 'CONGELADO', 'texto' => 'CONGELADO', 'color' => 'sky', 'membresia' => $m, 'congelamiento' => $c,
                'vence' => $ultimoFin, 'dias' => (int) Carbon::parse($c->hasta)->diffInDays(Carbon::parse($ultimoFin)), 'proximo' => null];
        }
        $futura = $membresias->first(fn ($m) => $m->inicio > $hoy);
        if ($futura) {
            return ['estado' => 'PROXIMO', 'texto' => 'EMPIEZA EL '.Carbon::parse($futura->inicio)->format('d/m'), 'color' => 'slate', 'membresia' => $futura,
                'congelamiento' => null, 'vence' => $ultimoFin, 'dias' => 0, 'proximo' => null];
        }

        return $membresias->isNotEmpty()
            ? ['estado' => 'VENCIDO', 'texto' => 'VENCIDO', 'color' => 'red', 'membresia' => $membresias->last(), 'congelamiento' => null, 'vence' => $ultimoFin, 'dias' => 0, 'proximo' => null]
            : ['estado' => 'SIN_PLAN', 'texto' => 'SIN PLAN', 'color' => 'slate', 'membresia' => null, 'congelamiento' => null, 'vence' => null, 'dias' => 0, 'proximo' => null];
    }

    /** Situación de un solo cliente (lee sus membresías y congelamientos) */
    public static function situacionDe(int $socId, int $suc): array
    {
        $mems = self::membresiasVigentes($suc, $socId)->get($socId, collect());

        return self::situacion($mems, self::congelamientosVigentes($mems->pluck('mem_id')->all()));
    }

    /** fin = inicio + días del plan - 1 + días congelados (activos) */
    public static function recalcularFin(int $memId): void
    {
        $m = DB::table('gym_membresias')->where('mem_id', $memId)->first();
        $congelados = (int) DB::table('gym_congelamientos')->where('mem_id', $memId)->where('estado', 'ACTIVO')->sum('dias');
        $fin = Carbon::parse($m->inicio)->addDays($m->dias - 1 + $congelados)->toDateString();
        DB::table('gym_membresias')->where('mem_id', $memId)->update(['fin' => $fin]);
    }

    // ------------------------------------------------------------------ congelamiento

    /**
     * Registra un congelamiento. El cliente (desde su portal) solo desde mañana y dentro de los días que permite su plan;
     * el administrador puede cualquier fecha dentro de la membresía y sin límite.
     *
     * @param  array{desde: string, hasta: string, motivo: string, detalle?: ?string}  $d
     */
    public static function congelar(int $socId, int $suc, array $d, string $origen, ?int $usuarioId = null, ?int $conIdEditar = null): int
    {
        $desde = Carbon::parse($d['desde'])->toDateString();
        $hasta = Carbon::parse($d['hasta'])->toDateString();
        if ($hasta < $desde) {
            throw new \RuntimeException('La fecha final no puede ser antes de la inicial.');
        }
        $motivo = mb_strtoupper(trim($d['motivo']));
        if (! in_array($motivo, self::MOTIVOS, true)) {
            throw new \RuntimeException('Elige el motivo del congelamiento.');
        }
        $esCliente = $origen === 'CLIENTE';
        if ($esCliente && $desde <= now()->toDateString()) {
            throw new \RuntimeException('Solo puedes congelar desde mañana.');
        }
        $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;

        return DB::transaction(function () use ($socId, $suc, $d, $origen, $usuarioId, $conIdEditar, $desde, $hasta, $motivo, $esCliente, $dias) {
            $editar = $conIdEditar ? DB::table('gym_congelamientos')->where('con_id', $conIdEditar)->where('soc_id', $socId)->lockForUpdate()->first() : null;
            if ($conIdEditar && ! $editar) {
                throw new \RuntimeException('Congelamiento no encontrado.');
            }

            // La membresía que tiene la fecha de inicio (si se edita, la misma)
            $mem = DB::table('gym_membresias')->where('soc_id', $socId)->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVA')
                ->when($editar, fn ($q) => $q->where('mem_id', $editar->mem_id), fn ($q) => $q->where('inicio', '<=', $desde)->where('fin', '>=', $desde)->where('dias', '>', 1))
                ->lockForUpdate()->first();
            if (! $mem) {
                throw new \RuntimeException('No hay una membresía vigente en esa fecha para congelar.');
            }
            if ($desde < $mem->inicio) {
                throw new \RuntimeException('La membresía empieza el '.Carbon::parse($mem->inicio)->format('d/m/Y').'.');
            }

            $otros = DB::table('gym_congelamientos')->where('mem_id', $mem->mem_id)->where('estado', 'ACTIVO')
                ->when($editar, fn ($q) => $q->where('con_id', '!=', $editar->con_id))->get();
            if ($otros->first(fn ($c) => $c->desde <= $hasta && $c->hasta >= $desde)) {
                throw new \RuntimeException('Esas fechas se cruzan con otro congelamiento.');
            }
            if ($esCliente) {
                $usados = (int) $otros->sum('dias');
                if ($mem->congelar_max <= 0) {
                    throw new \RuntimeException('Tu plan no permite congelar. Consulta en recepción.');
                }
                if ($usados + $dias > $mem->congelar_max) {
                    $quedan = max(0, $mem->congelar_max - $usados);
                    throw new \RuntimeException("Tu plan permite congelar {$mem->congelar_max} días y te quedan {$quedan}.");
                }
            }

            $fila = ['desde' => $desde, 'hasta' => $hasta, 'dias' => $dias, 'motivo' => $motivo,
                'detalle' => mb_substr(trim((string) ($d['detalle'] ?? '')), 0, 200) ?: null];
            if ($editar) {
                DB::table('gym_congelamientos')->where('con_id', $editar->con_id)->update($fila + ['IdUsuario' => $usuarioId,
                    'nota' => mb_substr('Editado por el administrador el '.now()->format('d/m/Y H:i').' (antes '.Carbon::parse($editar->desde)->format('d/m').'–'.Carbon::parse($editar->hasta)->format('d/m').')', 0, 200)]);
                $id = $editar->con_id;
            } else {
                $id = DB::table('gym_congelamientos')->insertGetId($fila + ['mem_id' => $mem->mem_id, 'soc_id' => $socId,
                    'origen' => $origen, 'estado' => 'ACTIVO', 'creado' => now(), 'IdUsuario' => $usuarioId]);
            }
            self::recalcularFin($mem->mem_id);
            self::correrSiguientes($socId, $mem->mem_id);

            return $id;
        });
    }

    /** El administrador anula un congelamiento: los días vuelven a la membresía como si no se hubiera congelado */
    public static function anularCongelamiento(int $conId, int $suc, int $usuarioId): void
    {
        DB::transaction(function () use ($conId, $suc, $usuarioId) {
            $c = DB::table('gym_congelamientos as c')->join('gym_membresias as m', 'm.mem_id', '=', 'c.mem_id')
                ->where('c.con_id', $conId)->where('m.id_empresa_negocio', $suc)->lockForUpdate()->first(['c.*']);
            if (! $c || $c->estado !== 'ACTIVO') {
                throw new \RuntimeException('El congelamiento no existe o ya fue anulado.');
            }
            DB::table('gym_congelamientos')->where('con_id', $conId)->update(['estado' => 'ANULADO', 'IdUsuario' => $usuarioId,
                'nota' => 'Anulado por el administrador el '.now()->format('d/m/Y H:i')]);
            self::recalcularFin($c->mem_id);
            self::correrSiguientes($c->soc_id, $c->mem_id);
        });
    }

    /**
     * El cliente volvió antes: el congelamiento termina ayer y desde hoy vuelve a correr su membresía.
     * Si aún no había empezado, se anula.
     */
    public static function levantarCongelamiento(int $conId, int $suc, int $usuarioId): void
    {
        $c = DB::table('gym_congelamientos')->where('con_id', $conId)->where('estado', 'ACTIVO')->first();
        if (! $c) {
            throw new \RuntimeException('El congelamiento no existe o ya fue anulado.');
        }
        $ayer = now()->subDay()->toDateString();
        if ($c->desde > $ayer) {
            self::anularCongelamiento($conId, $suc, $usuarioId);

            return;
        }
        self::congelar((int) $c->soc_id, $suc, ['desde' => $c->desde, 'hasta' => min($c->hasta, $ayer), 'motivo' => $c->motivo, 'detalle' => $c->detalle],
            'ADMIN', $usuarioId, $conId);
        DB::table('gym_congelamientos')->where('con_id', $conId)->update(['nota' => 'Levantado por el administrador el '.now()->format('d/m/Y H:i').': el cliente volvió antes']);
    }

    /** Las membresías compradas por adelantado empiezan cuando termina la anterior: si esa se alarga o acorta, se corren */
    private static function correrSiguientes(int $socId, int $memId): void
    {
        $m = DB::table('gym_membresias')->where('mem_id', $memId)->first();
        $finAnterior = $m->fin;
        $siguientes = DB::table('gym_membresias')->where('soc_id', $socId)->where('estado', 'ACTIVA')->where('dias', '>', 1)
            ->where('mem_id', '>', $memId)->where('inicio', '>', $m->inicio)->orderBy('inicio')->get();
        foreach ($siguientes as $s) {
            // Solo las que estaban encadenadas (empezaban justo después) y aún no empiezan
            if ($s->inicio <= now()->toDateString()) {
                break;
            }
            $inicio = Carbon::parse($finAnterior)->addDay()->toDateString();
            DB::table('gym_membresias')->where('mem_id', $s->mem_id)->update(['inicio' => $inicio]);
            self::recalcularFin($s->mem_id);
            $finAnterior = DB::table('gym_membresias')->where('mem_id', $s->mem_id)->value('fin');
        }
    }

    // ------------------------------------------------------------------ venta de planes

    /** Desde cuándo empieza un plan nuevo: al día siguiente de lo que ya tiene pagado (la rutina del día, siempre hoy) */
    public static function inicioSugerido(int $socId, int $suc, int $dias): string
    {
        $hoy = now()->toDateString();
        if ($dias <= 1) {
            return $hoy;
        }
        $fin = DB::table('gym_membresias')->where('soc_id', $socId)->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVA')
            ->where('dias', '>', 1)->max('fin');

        return $fin && $fin >= $hoy ? Carbon::parse($fin)->addDay()->toDateString() : $hoy;
    }

    /**
     * Vende un plan: emite el comprobante y crea la membresía.
     *
     * @return array{cabId: int, memId: int}
     */
    public static function vender(User $user, int $socId, int $planId, string $inicio, array $datos): array
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $socId, $planId, $inicio, $datos) {
            $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (! $turno) {
                throw new \RuntimeException('Debes aperturar tu turno antes de cobrar.');
            }
            $socio = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
                ->where('s.soc_id', $socId)->where('s.id_empresa_negocio', $suc)->first(['s.*', 'c.clinum', 'c.clinom', 'c.tdicod', 'c.clidir', 'c.clicor', 'c.telefono']);
            if (! $socio) {
                throw new \RuntimeException('Cliente no encontrado.');
            }
            $plan = DB::table('gym_planes')->where('plan_id', $planId)->where('id_empresa_negocio', $suc)->where('activo', 1)->first();
            if (! $plan || ! $plan->IdProducto) {
                throw new \RuntimeException('Plan no encontrado.');
            }
            $precio = round((float) ($datos['precio'] ?? $plan->precio), 2);
            if ($precio <= 0) {
                throw new \RuntimeException('El precio debe ser mayor a cero.');
            }
            $inicio = $plan->dias <= 1 ? now()->toDateString() : Carbon::parse($inicio)->toDateString();
            $fin = Carbon::parse($inicio)->addDays($plan->dias - 1)->toDateString();

            $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
            $doc = trim((string) ($datos['clinum'] ?? '')) ?: ($socio->clinum ?: '00000000');
            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => $datos['tdocod'], 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
                'tdicod' => $datos['tdicod'] ?? ($socio->tdicod ?: (strlen($doc) === 11 ? '6' : '1')), 'clinum' => $doc,
                'clinom' => ($datos['clinom'] ?? null) ?: $socio->clinom, 'clidir' => ($datos['clidir'] ?? null) ?: $socio->clidir,
                'clicor' => $socio->clicor, 'telefono' => $socio->telefono,
                'observaciones' => $plan->dias > 1 ? 'GIMNASIO: DEL '.Carbon::parse($inicio)->format('d/m/Y').' AL '.Carbon::parse($fin)->format('d/m/Y') : 'GIMNASIO',
                'id_med_pag' => $datos['id_med_pag'] ?? [], 'mon_med_pag' => $datos['mon_med_pag'] ?? [], 'paga' => $datos['paga'] ?? 0,
            ], [['IdProducto' => $plan->IdProducto, 'cantidad' => 1, 'precio' => $precio, 'descripcion' => mb_substr($plan->nombre.($plan->dias > 1 ? ' ('.Carbon::parse($inicio)->format('d/m').' - '.Carbon::parse($fin)->format('d/m/Y').')' : ''), 0, 150)]],
                ['ped_tip' => 'GIMNASIO', 'IdUsuario_ven' => $user->IdUsuario]);

            $memId = DB::table('gym_membresias')->insertGetId([
                'soc_id' => $socId, 'plan_id' => $plan->plan_id, 'plan' => $plan->nombre, 'dias' => $plan->dias, 'congelar_max' => $plan->congelar_max,
                'inicio' => $inicio, 'fin' => $fin, 'precio' => $precio, 'IdCpe_cabecera' => $cabId, 'estado' => 'ACTIVA',
                'IdUsuario' => $user->IdUsuario, 'creado' => now(), 'id_empresa_negocio' => $suc,
            ]);
            // Si estaba retirado o suspendido y vuelve a comprar, se reactiva
            DB::table('socios')->where('soc_id', $socId)->whereIn('estado', ['SUSPENDIDO', 'RETIRADO'])->update(['estado' => 'ACTIVO', 'suspendido_auto' => 0]);

            return ['cabId' => $cabId, 'memId' => $memId];
        });
    }

    /** Si el comprobante se anula (o tiene nota de crédito total), la membresía que pagó queda anulada */
    public static function revertirComprobante(int $cabId): void
    {
        DB::table('gym_membresias')->where('IdCpe_cabecera', $cabId)->where('estado', 'ACTIVA')->update(['estado' => 'ANULADA']);
    }

    /** Producto (concepto del comprobante) de un plan: se crea en la categoría GIMNASIO sin stock y se mantiene con el nombre y precio */
    public static function productoDelPlan(User $user, object $plan): int
    {
        $datos = ['pronom' => mb_strtoupper($plan->nombre), 'propun' => $plan->precio];
        if ($plan->IdProducto && DB::table('productos')->where('IdProducto', $plan->IdProducto)->exists()) {
            DB::table('productos')->where('IdProducto', $plan->IdProducto)->update($datos);

            return (int) $plan->IdProducto;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cat_nom', 'GIMNASIO')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'GIMNASIO', 'IdEmpresa' => $user->IdEmpresa,
                'id_empresa_negocio' => $user->id_empresa_negocio, 'tip_pro_id' => DB::table('tipo_producto')
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);

        return (int) Producto::create($datos + ['procod' => 'G'.$plan->plan_id.'-'.time(), 'umecod' => 'ZZ', 'costo' => 0, 'promocion' => 2,
            'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio])->IdProducto;
    }

    // ------------------------------------------------------------------ ingreso

    /**
     * Encuentra al cliente por lo que escanean o escriben en recepción:
     * QR del carnet (enlace o token), DNI, código de huella o código de socio.
     *
     * @return array{0: ?object, 1: string} [socio, método]
     */
    public static function identificar(string $texto, int $suc): array
    {
        $texto = trim($texto);
        $base = fn () => DB::table('socios')->where('id_empresa_negocio', $suc);
        if (preg_match('~(?:/socio/v/)?([A-Za-z0-9]{32})$~', $texto, $m) && ($s = $base()->where('token', $m[1])->first())) {
            return [$s, 'QR'];
        }
        $soloDigitos = preg_replace('/\D/', '', $texto);
        if ($soloDigitos !== '' && $soloDigitos === $texto) {
            $s = $base()->whereIn('clicod', DB::table('cliente')->where('clinum', $texto)->pluck('clicod'))->first();
            if ($s) {
                return [$s, 'DNI'];
            }
            if ($s = $base()->where('huella', $texto)->first()) {
                return [$s, 'HUELLA'];
            }
        }
        if ($s = $base()->where('codigo', mb_strtoupper($texto))->first()) {
            return [$s, 'CODIGO'];
        }

        return [null, ''];
    }

    /**
     * Registra el intento de ingreso y dice si puede pasar.
     *
     * @return array{permitido: bool, mensaje: string, situacion: array, requiere_aprobacion: bool}
     */
    public static function registrarIngreso(object $socio, string $metodo, ?int $aprobadoPor = null, ?string $motivoAprobacion = null): array
    {
        $suc = (int) $socio->id_empresa_negocio;
        $sit = self::situacionDe((int) $socio->soc_id, $suc);
        $permitido = in_array($sit['estado'], ['VIGENTE', 'POR_VENCER'], true) && $socio->estado === 'ACTIVO';
        $requiere = false;

        if ($socio->estado !== 'ACTIVO') {
            $mensaje = "Cliente {$socio->estado}. Comunícate con la administración.";
        } elseif ($permitido) {
            $mensaje = $sit['estado'] === 'POR_VENCER'
                ? 'Bienvenido. Tu plan vence el '.Carbon::parse($sit['vence'])->format('d/m/Y').' ('.$sit['dias'].' '.($sit['dias'] === 1 ? 'día' : 'días').').'
                : 'Bienvenido. Plan vigente hasta el '.Carbon::parse($sit['vence'])->format('d/m/Y').'.';
        } elseif ($sit['estado'] === 'CONGELADO') {
            $c = $sit['congelamiento'];
            $mensaje = 'Membresía CONGELADA del '.Carbon::parse($c->desde)->format('d/m').' al '.Carbon::parse($c->hasta)->format('d/m/Y')." ({$c->motivo}). "
                .'Para entrar necesita aprobación del administrador o pagar la rutina del día.';
            $requiere = true;
        } elseif ($sit['estado'] === 'PROXIMO') {
            $mensaje = 'Su plan empieza el '.Carbon::parse($sit['membresia']->inicio)->format('d/m/Y').'. Hoy puede pagar la rutina del día.';
        } elseif ($sit['estado'] === 'VENCIDO' && $sit['vence']) {
            $mensaje = 'Plan vencido el '.Carbon::parse($sit['vence'])->format('d/m/Y').'. Renueva tu plan o paga la rutina del día.';
        } else {
            $mensaje = 'No tiene un plan vigente. Paga tu plan o la rutina del día.';
        }

        if (! $permitido && $aprobadoPor) {
            $permitido = true;
            $mensaje = 'Ingreso aprobado por el administrador'.($motivoAprobacion ? ': '.$motivoAprobacion : '').'.';
            $requiere = false;
        }

        DB::table('gym_asistencias')->insert([
            'soc_id' => $socio->soc_id, 'fecha_hora' => now(), 'metodo' => $metodo, 'resultado' => $permitido ? 'PERMITIDO' : 'DENEGADO',
            'motivo' => mb_substr($mensaje, 0, 200), 'mem_id' => $sit['membresia']->mem_id ?? null, 'IdUsuario' => $aprobadoPor,
            'id_empresa_negocio' => $suc,
        ]);

        return ['permitido' => $permitido, 'mensaje' => $mensaje, 'situacion' => $sit, 'requiere_aprobacion' => $requiere];
    }

    // ------------------------------------------------------------------ cliente

    /**
     * Registra o actualiza al cliente del gimnasio (es un socio: así tiene carnet con QR y portal).
     *
     * @param  array{soc_id?: ?int, clinum: string, clinom: string, telefono?: ?string, clicor?: ?string, fecha_nac?: ?string, huella?: ?string, entrenador_id?: ?int, obs?: ?string}  $d
     */
    public static function guardarCliente(User $user, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $d) {
            $doc = trim($d['clinum']);
            $cliente = Cliente::updateOrCreate(['clinum' => $doc, 'rucemp' => $user->IdEmpresa], [
                'clinom' => mb_strtoupper(trim($d['clinom'])), 'tdicod' => strlen($doc) === 11 ? '6' : (strlen($doc) === 8 ? '1' : '4'),
                'telefono' => $d['telefono'] ?? null, 'clicor' => $d['clicor'] ?? null,
            ]);
            $actual = ! empty($d['soc_id']) ? DB::table('socios')->where('soc_id', $d['soc_id'])->where('id_empresa_negocio', $suc)->first() : null;
            if (! empty($d['soc_id']) && ! $actual) {
                throw new \RuntimeException('Cliente no encontrado.');
            }
            $otro = DB::table('socios')->where('id_empresa_negocio', $suc)->where('clicod', $cliente->clicod)
                ->when($actual, fn ($q) => $q->where('soc_id', '!=', $actual->soc_id))->value('codigo');
            if ($otro) {
                throw new \RuntimeException("Esa persona ya está registrada (N° {$otro}).");
            }
            $huella = trim((string) ($d['huella'] ?? '')) ?: null;
            if ($huella && DB::table('socios')->where('id_empresa_negocio', $suc)->where('huella', $huella)
                ->when($actual, fn ($q) => $q->where('soc_id', '!=', $actual->soc_id))->exists()) {
                throw new \RuntimeException("El código de huella {$huella} ya lo tiene otro cliente.");
            }

            $fila = ['clicod' => $cliente->clicod, 'fecha_nac' => $d['fecha_nac'] ?? null, 'huella' => $huella,
                'entrenador_id' => $d['entrenador_id'] ?? null, 'obs' => $d['obs'] ?? null];
            if ($actual) {
                DB::table('socios')->where('soc_id', $actual->soc_id)->update($fila);

                return (int) $actual->soc_id;
            }
            $ultimo = DB::table('socios')->where('id_empresa_negocio', $suc)->whereRaw("codigo REGEXP '^[0-9]+$'")->max(DB::raw('CAST(codigo AS UNSIGNED)'));

            return (int) DB::table('socios')->insertGetId($fila + [
                'codigo' => str_pad((string) ((int) $ultimo + 1), 4, '0', STR_PAD_LEFT), 'fecha_ingreso' => now()->toDateString(),
                'estado' => 'ACTIVO', 'token' => Socios::token(), 'id_empresa_negocio' => $suc, 'creado' => now(),
            ]);
        });
    }

    /** IMC con 1 decimal (peso en kg, talla en metros) */
    public static function imc(?float $peso, ?float $talla): ?float
    {
        return $peso && $talla ? round($peso / ($talla * $talla), 1) : null;
    }
}
