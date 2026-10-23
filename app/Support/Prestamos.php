<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Préstamos con cobro diario ("gota a gota"), semanal, quincenal o mensual.
 *
 *  - Total = monto + interés (ej. S/ 100 al 20% = S/ 120). Se divide en N cuotas iguales (la última ajusta los céntimos).
 *  - Las cuotas diarias caen solo en los días de cobro (por defecto lunes a sábado) y saltan los feriados.
 *  - Lo pagado se aplica en orden a las cuotas: así se sabe cuántas van, cuántas están atrasadas y cuánto debe hoy.
 *  - Situación: AL DÍA (0 cuotas atrasadas), ATRASADO (1 a 3), MOROSO (4 o más, o ya pasó la última cuota), PAGADO.
 *  - Mora opcional: S/ X por cada cuota atrasada (después de los días de gracia). Se cobra aparte y es ganancia.
 *  - Renovar: el nuevo préstamo cancela el saldo del anterior y se entrega la diferencia.
 */
class Prestamos
{
    public const ROL_COBRADOR = 14;

    public const FRECUENCIAS = ['DIARIA' => 'Diario', 'SEMANAL' => 'Semanal', 'QUINCENAL' => 'Quincenal', 'MENSUAL' => 'Mensual'];

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public const MEDIOS = ['EFECTIVO', 'YAPE', 'PLIN', 'TRANSFERENCIA'];

    public const RESULTADOS_GESTION = ['NO_PAGO' => 'No pagó', 'PROMESA' => 'Promete pagar', 'NO_ESTABA' => 'No estaba', 'CERRADO' => 'Negocio cerrado'];

    /** Cuotas atrasadas desde las que el préstamo pasa a MOROSO */
    public const LIMITE_MOROSO = 4;

    public static function esCobrador(User $u): bool
    {
        return $u->tieneRol([self::ROL_COBRADOR]);
    }

    /** Entra al módulo: administrador, caja, cobrador o quien tenga el menú */
    public static function usa(User $u): bool
    {
        return $u->esAdminOCaja() || self::esCobrador($u) || $u->tieneModulo('/prestamos');
    }

    /** Crea clientes y préstamos (el cobrador solo cobra) */
    public static function gestiona(User $u): bool
    {
        return $u->esAdminOCaja() || $u->tieneModulo('/prestamos/clientes');
    }

    /** Configuración de la sucursal; la primera vez crea los planes de siempre */
    public static function config(int $suc): object
    {
        $c = DB::table('prestamo_config')->where('id_empresa_negocio', $suc)->first();
        if (! $c) {
            DB::table('prestamo_config')->insert(['id_empresa_negocio' => $suc, 'dias_cobro' => '1,2,3,4,5,6', 'mora_diaria' => 0, 'dias_gracia' => 0,
                'created_at' => now(), 'updated_at' => now()]);
            if (! DB::table('prestamo_planes')->where('id_empresa_negocio', $suc)->exists()) {
                DB::table('prestamo_planes')->insert([
                    ['nombre' => 'PRIMER PRÉSTAMO', 'interes' => 20, 'cuotas' => 20, 'frecuencia' => 'DIARIA', 'monto_min' => 100, 'monto_max' => 100, 'primer_prestamo' => 1, 'activo' => 1, 'id_empresa_negocio' => $suc],
                    ['nombre' => 'DIARIO 24 DÍAS', 'interes' => 20, 'cuotas' => 24, 'frecuencia' => 'DIARIA', 'monto_min' => 100, 'monto_max' => 0, 'primer_prestamo' => 0, 'activo' => 1, 'id_empresa_negocio' => $suc],
                    ['nombre' => 'SEMANAL 4 SEMANAS', 'interes' => 20, 'cuotas' => 4, 'frecuencia' => 'SEMANAL', 'monto_min' => 200, 'monto_max' => 0, 'primer_prestamo' => 0, 'activo' => 1, 'id_empresa_negocio' => $suc],
                ]);
            }
            $c = DB::table('prestamo_config')->where('id_empresa_negocio', $suc)->first();
        }
        $c->dias = array_map('intval', array_filter(explode(',', (string) $c->dias_cobro)));
        $c->lista_feriados = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $c->feriados))));

        return $c;
    }

    // ------------------------------------------------------------------ cálculo

    /**
     * Cuánto se cobra y en qué fechas.
     *
     * @param  array<int, int>  $dias  días de la semana en que se cobra (1 = lunes … 7 = domingo)
     * @param  array<int, string>  $feriados  fechas Y-m-d sin cobro
     * @return array{monto: float, interes_pct: float, interes: float, total: float, cuotas: int, cuota: float, frecuencia: string, fechas: array<int, array{numero: int, fecha: string, monto: float}>, primera: string, ultima: string}
     */
    public static function simular(float $monto, float $interesPct, int $cuotas, string $frecuencia, array $dias, string $desde, array $feriados = []): array
    {
        $monto = round($monto, 2);
        $interes = round($monto * $interesPct / 100, 2);
        $total = round($monto + $interes, 2);
        $cuotas = max(1, $cuotas);
        // Cuota redondeada a 10 céntimos (más fácil de cobrar); la última ajusta la diferencia
        $cuota = round(ceil($total / $cuotas * 10 - 0.0001) / 10, 2);
        if ($cuota * ($cuotas - 1) >= $total) {
            $cuota = round($total / $cuotas, 2);
        }
        $dias = $dias ?: [1, 2, 3, 4, 5, 6];
        $fecha = Carbon::parse($desde)->startOfDay();
        $lista = [];
        for ($i = 1; $i <= $cuotas; $i++) {
            $fecha = match ($frecuencia) {
                'SEMANAL' => $fecha->copy()->addWeek(),
                'QUINCENAL' => $fecha->copy()->addDays(15),
                'MENSUAL' => Carbon::parse($desde)->addMonthsNoOverflow($i),
                default => $fecha->copy()->addDay(),
            };
            // Diario: solo días de cobro y sin feriados (el semanal/mensual se corre al siguiente día de cobro)
            $vueltas = 0;
            while ((! in_array($fecha->dayOfWeekIso, $dias, true) || in_array($fecha->toDateString(), $feriados, true)) && $vueltas++ < 15) {
                $fecha->addDay();
            }
            $lista[] = ['numero' => $i, 'fecha' => $fecha->toDateString(), 'monto' => $i < $cuotas ? $cuota : round($total - $cuota * ($cuotas - 1), 2)];
        }

        return ['monto' => $monto, 'interes_pct' => round($interesPct, 2), 'interes' => $interes, 'total' => $total, 'cuotas' => $cuotas, 'cuota' => $cuota,
            'frecuencia' => $frecuencia, 'fechas' => $lista, 'primera' => $lista[0]['fecha'], 'ultima' => end($lista)['fecha']];
    }

    /**
     * Situación del préstamo hoy: lo pagado se aplica a las cuotas en orden.
     *
     * @param  Collection<int, object>|array  $cuotas
     */
    public static function situacion(object $p, $cuotas, ?string $hoy = null, ?object $cfg = null): array
    {
        $hoy ??= now()->toDateString();
        $resto = (float) $p->pagado;
        $vencidas = 0;
        $debeHoy = 0.0;
        $pagadas = 0;
        $proxima = null;
        $lista = [];
        foreach ($cuotas as $c) {
            $c = (object) $c;
            $aplicado = round(min((float) $c->monto, max(0, $resto)), 2);
            $resto = round($resto - $aplicado, 2);
            $falta = round((float) $c->monto - $aplicado, 2);
            $estado = $falta <= 0.009 ? 'PAGADA' : ($c->fecha < $hoy ? 'VENCIDA' : ($c->fecha === $hoy ? 'HOY' : ($aplicado > 0 ? 'PARCIAL' : 'PENDIENTE')));
            if ($estado === 'PAGADA') {
                $pagadas++;
            }
            if ($estado === 'VENCIDA') {
                $vencidas++;
            }
            if ($falta > 0 && $c->fecha <= $hoy) {
                $debeHoy += $falta;
            }
            if ($falta > 0 && ! $proxima) {
                $proxima = ['numero' => (int) $c->numero, 'fecha' => $c->fecha, 'falta' => $falta];
            }
            $lista[] = ['numero' => (int) $c->numero, 'fecha' => $c->fecha, 'monto' => (float) $c->monto, 'pagado' => $aplicado, 'estado' => $estado];
        }
        $saldo = round((float) $p->total - (float) $p->pagado, 2);
        $estado = $saldo <= 0.009 || $p->estado === 'PAGADO' ? 'PAGADO'
            : ($p->estado === 'ANULADO' ? 'ANULADO'
            : ($vencidas >= self::LIMITE_MOROSO || $p->ultima_cuota < $hoy ? 'MOROSO' : ($vencidas > 0 ? 'ATRASADO' : 'AL_DIA')));
        $cfg ??= null;
        $mora = $cfg && (float) $cfg->mora_diaria > 0 && $estado !== 'PAGADO' ? round(max(0, $vencidas - (int) $cfg->dias_gracia) * (float) $cfg->mora_diaria, 2) : 0.0;

        return [
            'estado' => $estado, 'saldo' => max(0, $saldo), 'pagadas' => $pagadas, 'vencidas' => $vencidas, 'debe_hoy' => round(min($debeHoy, max(0, $saldo)), 2),
            'proxima' => $proxima, 'avance' => (float) $p->total > 0 ? round(min(100, (float) $p->pagado / (float) $p->total * 100)) : 0,
            'mora_sugerida' => $mora, 'cuotas' => $lista,
            // Lo que se cobra hoy: lo atrasado más la cuota de hoy (si aún no la paga)
            'cobrar_hoy' => round(min($debeHoy, max(0, $saldo)), 2),
        ];
    }

    public const ESTADOS = [
        'AL_DIA' => ['Al día', 'emerald'], 'ATRASADO' => ['Atrasado', 'amber'], 'MOROSO' => ['Moroso', 'rose'], 'PAGADO' => ['Pagado', 'sky'], 'ANULADO' => ['Anulado', 'slate'],
    ];

    /**
     * Préstamos de la sucursal con su situación de hoy (una consulta para los préstamos y otra para sus cuotas).
     *
     * @return Collection<int, object>
     */
    public static function cartera(int $suc, array $filtros = []): Collection
    {
        $cfg = self::config($suc);
        $hoy = now()->toDateString();
        $prestamos = DB::table('prestamos as p')->join('prestamo_clientes as k', 'k.pcl_id', '=', 'p.pcl_id')->join('cliente as c', 'c.clicod', '=', 'k.clicod')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.cobrador_id')
            ->where('p.id_empresa_negocio', $suc)
            ->when($filtros['estados'] ?? ['VIGENTE'], fn ($q, $e) => $q->whereIn('p.estado', $e))
            ->when($filtros['cobrador'] ?? null, fn ($q, $id) => $q->where('p.cobrador_id', $id))
            ->when($filtros['pcl_id'] ?? null, fn ($q, $id) => $q->where('p.pcl_id', $id))
            ->orderBy('k.zona')->orderBy('c.clinom')
            ->get(['p.*', 'c.clinom', 'c.clinum', 'c.telefono', 'k.direccion', 'k.referencia', 'k.zona', 'k.negocio', 'k.lat', 'k.lng',
                DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as cobrador")]);
        $cuotas = DB::table('prestamo_cuotas')->whereIn('pre_id', $prestamos->pluck('pre_id'))->orderBy('numero')->get(['pre_id', 'numero', 'fecha', 'monto'])->groupBy('pre_id');

        return $prestamos->map(function ($p) use ($cuotas, $hoy, $cfg) {
            $s = self::situacion($p, $cuotas[$p->pre_id] ?? collect(), $hoy, $cfg);
            unset($s['cuotas']);

            // La situación de hoy (AL_DIA, ATRASADO…) reemplaza al estado guardado (VIGENTE); este queda en estado_registro
            return (object) (array_merge((array) $p, ['estado_registro' => $p->estado], $s) + ['capital_pendiente' => (float) $p->total > 0 ? round($s['saldo'] * (float) $p->monto / (float) $p->total, 2) : 0]);
        });
    }

    // ------------------------------------------------------------------ operaciones

    /**
     * Nuevo préstamo con su cronograma. Si renueva otro, el saldo del anterior se descuenta de lo que se entrega.
     *
     * @param  array{monto: float, plan_id?: ?int, interes_pct: float, cuotas: int, frecuencia: string, fecha?: ?string, cobrador_id?: ?int, medio_entrega?: ?string, renueva_pre_id?: ?int, obs?: ?string}  $d
     */
    public static function crear(User $user, int $pclId, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $pclId, $d) {
            $cli = DB::table('prestamo_clientes')->where('pcl_id', $pclId)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $cli) {
                throw new \RuntimeException('Cliente no encontrado.');
            }
            if ($cli->estado !== 'ACTIVO') {
                throw new \RuntimeException('El cliente está '.mb_strtolower($cli->estado).': no se le puede prestar.');
            }
            if ($cli->limite !== null && (float) $cli->limite > 0 && (float) $d['monto'] > (float) $cli->limite) {
                throw new \RuntimeException('El monto pasa el límite del cliente (S/ '.number_format((float) $cli->limite, 2).').');
            }
            $cfg = self::config($suc);
            $fecha = $d['fecha'] ?? now()->toDateString();
            $sim = self::simular((float) $d['monto'], (float) $d['interes_pct'], (int) $d['cuotas'], $d['frecuencia'], $cfg->dias, $fecha, $cfg->lista_feriados);

            $entregado = $sim['monto'];
            $anterior = null;
            if (! empty($d['renueva_pre_id'])) {
                $anterior = DB::table('prestamos')->where('pre_id', $d['renueva_pre_id'])->where('pcl_id', $pclId)->where('estado', 'VIGENTE')->lockForUpdate()->first();
                if (! $anterior) {
                    throw new \RuntimeException('El préstamo a renovar ya no está vigente.');
                }
                $saldoAnterior = round((float) $anterior->total - (float) $anterior->pagado, 2);
                $entregado = round($sim['monto'] - $saldoAnterior, 2);
                if ($entregado <= 0) {
                    throw new \RuntimeException('El nuevo monto debe ser mayor que lo que aún debe (S/ '.number_format($saldoAnterior, 2).').');
                }
            }

            $numero = (int) DB::table('prestamos')->where('id_empresa_negocio', $suc)->lockForUpdate()->max('numero') + 1;
            $id = DB::table('prestamos')->insertGetId([
                'numero' => $numero, 'pcl_id' => $pclId, 'plan_id' => $d['plan_id'] ?? null, 'monto' => $sim['monto'], 'interes_pct' => $sim['interes_pct'],
                'interes' => $sim['interes'], 'total' => $sim['total'], 'cuotas' => $sim['cuotas'], 'cuota' => $sim['cuota'], 'frecuencia' => $sim['frecuencia'],
                'dias_cobro' => implode(',', $cfg->dias), 'fecha' => $fecha, 'primera_cuota' => $sim['primera'], 'ultima_cuota' => $sim['ultima'],
                'entregado' => $entregado, 'medio_entrega' => $d['medio_entrega'] ?? 'EFECTIVO', 'renueva_pre_id' => $anterior?->pre_id, 'estado' => 'VIGENTE',
                'cobrador_id' => ($d['cobrador_id'] ?? null) ?: $cli->cobrador_id, 'obs' => $d['obs'] ?? null, 'IdUsuario' => $user->IdUsuario,
                'id_empresa_negocio' => $suc, 'creado' => now(),
            ]);
            DB::table('prestamo_cuotas')->insert(array_map(fn ($c) => $c + ['pre_id' => $id], $sim['fechas']));

            if ($anterior) {
                self::registrarPago($user, $anterior, round((float) $anterior->total - (float) $anterior->pagado, 2), 0, 'EFECTIVO', null,
                    'Cancelado con la renovación N° '.str_pad((string) $numero, 5, '0', STR_PAD_LEFT), 'RENOVACION');
            }
            if (($d['cobrador_id'] ?? null) && ! $cli->cobrador_id) {
                DB::table('prestamo_clientes')->where('pcl_id', $pclId)->update(['cobrador_id' => $d['cobrador_id']]);
            }

            return $id;
        });
    }

    /** Registra un pago (o la cancelación por renovación); con saldo 0 el préstamo queda PAGADO */
    public static function registrarPago(User $user, object $p, float $monto, float $mora, string $medio, ?string $operacion, ?string $obs, string $tipo = 'PAGO', ?float $lat = null, ?float $lng = null): int
    {
        $p = DB::table('prestamos')->where('pre_id', $p->pre_id)->lockForUpdate()->first();
        if ($p->estado !== 'VIGENTE') {
            throw new \RuntimeException('Este préstamo ya no está vigente.');
        }
        $saldo = round((float) $p->total - (float) $p->pagado, 2);
        $monto = round($monto, 2);
        $mora = round(max(0, $mora), 2);
        if ($monto <= 0 && $mora <= 0) {
            throw new \RuntimeException('Escribe cuánto paga.');
        }
        if ($monto > $saldo + 0.001) {
            throw new \RuntimeException('El pago pasa lo que debe (S/ '.number_format($saldo, 2).').');
        }
        $id = DB::table('prestamo_pagos')->insertGetId([
            'pre_id' => $p->pre_id, 'fecha_hora' => now(), 'monto' => $monto, 'mora' => $mora, 'medio' => $medio, 'operacion' => $operacion ? mb_strtoupper($operacion) : null,
            'tipo' => $tipo, 'obs' => $obs, 'lat' => $lat, 'lng' => $lng, 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $p->id_empresa_negocio,
        ]);
        self::recalcular((int) $p->pre_id);

        return $id;
    }

    /** El administrador anula un pago equivocado: el saldo vuelve y el préstamo vuelve a estar vigente */
    public static function anularPago(User $user, int $pagId, string $motivo): void
    {
        DB::transaction(function () use ($user, $pagId, $motivo) {
            $pg = DB::table('prestamo_pagos')->where('pag_id', $pagId)->where('id_empresa_negocio', $user->id_empresa_negocio)->where('anulado', 0)->lockForUpdate()->first();
            if (! $pg) {
                throw new \RuntimeException('Pago no encontrado.');
            }
            if ($pg->tipo === 'RENOVACION') {
                throw new \RuntimeException('Este pago es de una renovación: anula el préstamo nuevo.');
            }
            DB::table('prestamo_pagos')->where('pag_id', $pagId)->update(['anulado' => 1, 'anulado_por' => $user->IdUsuario, 'motivo_anulacion' => $motivo]);
            DB::table('prestamos')->where('pre_id', $pg->pre_id)->where('estado', 'PAGADO')->update(['estado' => 'VIGENTE', 'cancelado' => null]);
            self::recalcular((int) $pg->pre_id);
        });
    }

    /** Anula un préstamo mal registrado (solo sin pagos); si era una renovación, el anterior vuelve con su saldo */
    public static function anularPrestamo(User $user, int $preId, string $motivo): void
    {
        DB::transaction(function () use ($user, $preId, $motivo) {
            $p = DB::table('prestamos')->where('pre_id', $preId)->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (! $p || $p->estado === 'ANULADO') {
                throw new \RuntimeException('Préstamo no encontrado.');
            }
            if (DB::table('prestamo_pagos')->where('pre_id', $preId)->where('anulado', 0)->where('tipo', 'PAGO')->exists()) {
                throw new \RuntimeException('Ya tiene pagos: anula primero los pagos.');
            }
            DB::table('prestamos')->where('pre_id', $preId)->update(['estado' => 'ANULADO', 'obs' => mb_substr(trim(($p->obs ? $p->obs.' · ' : '').'Anulado: '.$motivo), 0, 255)]);
            if ($p->renueva_pre_id) {
                DB::table('prestamo_pagos')->where('pre_id', $p->renueva_pre_id)->where('tipo', 'RENOVACION')->where('anulado', 0)
                    ->update(['anulado' => 1, 'anulado_por' => $user->IdUsuario, 'motivo_anulacion' => 'Se anuló la renovación']);
                DB::table('prestamos')->where('pre_id', $p->renueva_pre_id)->update(['estado' => 'VIGENTE', 'cancelado' => null]);
                self::recalcular((int) $p->renueva_pre_id);
            }
        });
    }

    /**
     * El administrador corrige un préstamo ya registrado (monto, interés, cuotas, frecuencia o fecha): se rehace el cronograma
     * y lo ya pagado se vuelve a aplicar a las nuevas cuotas. Queda anotado qué tenía antes.
     *
     * @param  array{monto: float, interes_pct: float, cuotas: int, frecuencia: string, fecha: string, cobrador_id?: ?int, obs?: ?string}  $d
     */
    public static function corregir(User $user, int $preId, array $d): void
    {
        DB::transaction(function () use ($user, $preId, $d) {
            $p = DB::table('prestamos')->where('pre_id', $preId)->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (! $p || $p->estado === 'ANULADO') {
                throw new \RuntimeException('Préstamo no encontrado o anulado.');
            }
            $cfg = self::config((int) $p->id_empresa_negocio);
            $sim = self::simular((float) $d['monto'], (float) $d['interes_pct'], (int) $d['cuotas'], $d['frecuencia'], $cfg->dias, $d['fecha'], $cfg->lista_feriados);
            if ((float) $p->pagado > $sim['total'] + 0.009) {
                throw new \RuntimeException('El nuevo total (S/ '.number_format($sim['total'], 2).') es menor que lo que ya pagó (S/ '.number_format((float) $p->pagado, 2).').');
            }
            // Si era una renovación, lo entregado cambia en lo mismo que cambia el monto (el saldo del anterior no cambia)
            $entregado = round((float) $p->entregado + $sim['monto'] - (float) $p->monto, 2);
            if ($entregado <= 0) {
                throw new \RuntimeException('Con ese monto no se le entregaría nada (es una renovación: el monto debe cubrir el saldo del préstamo anterior).');
            }
            $cambio = $sim['monto'] != (float) $p->monto || $sim['interes_pct'] != (float) $p->interes_pct || $sim['cuotas'] !== (int) $p->cuotas
                || $sim['frecuencia'] !== $p->frecuencia || $d['fecha'] !== $p->fecha;
            $obs = trim((string) ($d['obs'] ?? $p->obs));
            if ($cambio) {
                $antes = 'Corregido '.now()->format('d/m/Y H:i').' por '.($user->apeusu ?: $user->name).'. Antes: S/ '.number_format((float) $p->monto, 2)
                    .' al '.(float) $p->interes_pct.'% en '.$p->cuotas.' cuotas '.mb_strtolower(self::FRECUENCIAS[$p->frecuencia] ?? $p->frecuencia)
                    .' desde '.Carbon::parse($p->fecha)->format('d/m/Y');
                $obs = trim($antes.($obs !== '' ? ' · '.$obs : ''));
            }
            DB::table('prestamos')->where('pre_id', $preId)->update([
                'monto' => $sim['monto'], 'interes_pct' => $sim['interes_pct'], 'interes' => $sim['interes'], 'total' => $sim['total'], 'cuotas' => $sim['cuotas'],
                'cuota' => $sim['cuota'], 'frecuencia' => $sim['frecuencia'], 'dias_cobro' => implode(',', $cfg->dias), 'fecha' => $d['fecha'],
                'primera_cuota' => $sim['primera'], 'ultima_cuota' => $sim['ultima'], 'entregado' => $entregado,
                'cobrador_id' => array_key_exists('cobrador_id', $d) ? ($d['cobrador_id'] ?: null) : $p->cobrador_id,
                'obs' => $obs !== '' ? mb_substr($obs, 0, 255) : null,
            ]);
            if ($cambio) {
                DB::table('prestamo_cuotas')->where('pre_id', $preId)->delete();
                DB::table('prestamo_cuotas')->insert(array_map(fn ($c) => $c + ['pre_id' => $preId], $sim['fechas']));
            }
            // Con el nuevo total puede quedar pagado o volver a tener saldo
            if ($p->estado === 'PAGADO' && $sim['total'] > (float) $p->pagado + 0.009) {
                DB::table('prestamos')->where('pre_id', $preId)->update(['estado' => 'VIGENTE', 'cancelado' => null]);
            }
            self::recalcular($preId);
        });
    }

    public static function recalcular(int $preId): void
    {
        $suma = DB::table('prestamo_pagos')->where('pre_id', $preId)->where('anulado', 0)->selectRaw('COALESCE(SUM(monto), 0) as m, COALESCE(SUM(mora), 0) as mo')->first();
        $p = DB::table('prestamos')->where('pre_id', $preId)->first();
        $pagado = round((float) $suma->m, 2);
        $cambios = ['pagado' => $pagado, 'mora_pagada' => round((float) $suma->mo, 2)];
        if ($p->estado === 'VIGENTE' && $pagado >= (float) $p->total - 0.009) {
            $cambios += ['estado' => 'PAGADO', 'cancelado' => now()];
        }
        DB::table('prestamos')->where('pre_id', $preId)->update($cambios);
    }

    // ------------------------------------------------------------------ cliente

    /**
     * Calificación según cómo pagó sus préstamos terminados (días de retraso respecto de su última cuota) y si hoy debe.
     *
     * @return array{nivel: string, texto: string, color: string, sugerido: ?float, prestamos: int, detalle: string}
     */
    public static function calificacion(int $pclId, ?Collection $vigentes = null): array
    {
        $terminados = DB::table('prestamos')->where('pcl_id', $pclId)->where('estado', 'PAGADO')->get(['monto', 'ultima_cuota', 'cancelado']);
        $vigentes ??= collect();
        $enMora = $vigentes->firstWhere('estado', 'MOROSO');
        if ($enMora) {
            return ['nivel' => 'MORA', 'texto' => 'En mora', 'color' => 'rose', 'sugerido' => null, 'prestamos' => $terminados->count(),
                'detalle' => 'Tiene '.$enMora->vencidas.' cuotas atrasadas: no prestarle hasta que se ponga al día.'];
        }
        if ($terminados->isEmpty()) {
            return ['nivel' => 'NUEVO', 'texto' => 'Nuevo', 'color' => 'slate', 'sugerido' => null, 'prestamos' => 0, 'detalle' => 'Aún no termina ningún préstamo.'];
        }
        $retrasos = $terminados->map(fn ($t) => max(0, Carbon::parse($t->ultima_cuota)->diffInDays(Carbon::parse($t->cancelado)->startOfDay(), false)));
        $peor = (int) $retrasos->max();
        $ultimo = (float) $terminados->sortByDesc('cancelado')->first()->monto;
        [$nivel, $texto, $color, $factor] = match (true) {
            $peor <= 2 => ['EXCELENTE', 'Excelente', 'emerald', 1.5],
            $peor <= 7 => ['BUENO', 'Bueno', 'sky', 1.2],
            $peor <= 15 => ['REGULAR', 'Regular', 'amber', 1.0],
            default => ['MALO', 'Malo', 'rose', 0],
        };

        return ['nivel' => $nivel, 'texto' => $texto, 'color' => $color, 'sugerido' => $factor > 0 ? round($ultimo * $factor / 10) * 10 : null,
            'prestamos' => $terminados->count(),
            'detalle' => $terminados->count().' préstamo(s) pagado(s); el que más se retrasó terminó '.($peor ? $peor.' día(s) después de su última cuota' : 'a tiempo').'.'];
    }

    /** Ganancia del periodo: la parte de interés de lo cobrado más la mora */
    public static function ganancia(int $suc, string $desde, string $hasta): float
    {
        return round((float) DB::table('prestamo_pagos as g')->join('prestamos as p', 'p.pre_id', '=', 'g.pre_id')
            ->where('g.id_empresa_negocio', $suc)->where('g.anulado', 0)->where('g.tipo', 'PAGO')->whereBetween('g.fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->value(DB::raw('COALESCE(SUM(g.monto * p.interes / NULLIF(p.total, 0) + g.mora), 0)')), 2);
    }

    /** 987654321 → 51987654321 para WhatsApp */
    public static function whatsapp(?string $tel, string $texto): ?string
    {
        $n = preg_replace('/\D/', '', (string) $tel);
        if (strlen($n) === 9) {
            $n = '51'.$n;
        }

        return strlen($n) >= 10 ? 'https://wa.me/'.$n.'?text='.rawurlencode($texto) : null;
    }
}
