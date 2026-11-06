<?php

namespace App\Support;

use App\Models\User;
use App\Support\Contabilidad\Contabilidad;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Activos fijos y depreciación (línea recta, como la acepta SUNAT):
 *  - Cada bien con su categoría y tasa (las máximas del Reglamento de la Ley del Impuesto a la Renta), su cuenta de
 *    activo (33x), de depreciación acumulada (3913) y de gasto (681), y su centro de costo.
 *  - Se deprecia desde el mes en que se empieza a usar: (costo − valor residual) × tasa ÷ 12 cada mes, hasta llegar al costo.
 *  - Cada mes se procesa una vez: un asiento 681 (D) → 3913 (H) por todas las cuentas, y su destino a la clase 9 por centro.
 *    Si un bien se registró con fecha pasada, ese mes se pone al día con lo atrasado. Solo se rehace el último mes procesado.
 *  - Alta (opcional) con asiento 33x (D) → la cuenta de pago; baja con asiento: sale el costo (33x H), se cancela lo
 *    depreciado (3913 D) y el valor que faltaba depreciar va a gasto (659 D).
 */
class ActivosFijos
{
    /** Categoría => [nombre, tasa anual máxima SUNAT %, cuenta de activo] */
    public const CATEGORIAS = [
        'EDIFICACIONES' => ['Edificios y construcciones', 5, '332'],
        'MAQUINARIA' => ['Maquinaria y equipo', 10, '333'],
        'VEHICULOS' => ['Vehículos de transporte terrestre', 20, '334'],
        'MUEBLES' => ['Muebles y enseres', 10, '3351'],
        'COMPUTO' => ['Equipos de procesamiento de datos (cómputo)', 25, '3361'],
        'OTROS' => ['Otros bienes del activo fijo', 10, '336'],
        'TERRENOS' => ['Terrenos (no se deprecian)', 0, '331'],
    ];

    public static function lista(string $ruc): Collection
    {
        return DB::table('activos_fijos as a')->leftJoin('centros_costo as c', 'c.id', '=', 'a.centro_costo_id')->where('a.IdEmpresa', $ruc)
            ->orderBy('a.estado')->orderBy('a.codigo')->get(['a.*', 'c.codigo as centro_codigo', 'c.nombre as centro_nombre']);
    }

    public static function activo(string $ruc, int $id): object
    {
        $a = DB::table('activos_fijos')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        if (! $a) {
            throw new \RuntimeException('El activo no existe.');
        }

        return $a;
    }

    /** Depreciación de un mes (sin pasar del costo depreciable) */
    public static function cuotaMensual(object $a): float
    {
        return round(max(0, (float) $a->costo - (float) $a->valor_residual) * (float) $a->tasa / 100 / 12, 2);
    }

    /** Lo depreciado hasta ese mes inclusive (lo inicial más lo procesado) */
    public static function acumulado(object $a, ?string $hastaPeriodo = null): float
    {
        return round((float) $a->depreciacion_inicial + (float) DB::table('activos_depreciacion')->where('activo_id', $a->id)
            ->when($hastaPeriodo, fn ($q) => $q->where('periodo', '<=', $hastaPeriodo))->sum('monto'), 2);
    }

    /**
     * Crea o edita un bien. El costo, la fecha de uso y la tasa solo se cambian mientras no tenga depreciación procesada.
     *
     * @param  array<string, mixed>  $d
     */
    public static function guardar(User $user, array $d, ?int $id = null): int
    {
        $ruc = (string) $user->IdEmpresa;
        Contabilidad::asegurar($ruc);
        $imp = Contabilidad::imputables($ruc);
        if (! isset(self::CATEGORIAS[$d['categoria']])) {
            throw new \RuntimeException('Categoría no válida.');
        }
        [, $tasaMaxima, $cuentaSugerida] = self::CATEGORIAS[$d['categoria']];
        $tasa = round((float) ($d['tasa'] ?? $tasaMaxima), 2);
        if ($tasa < 0 || $tasa > $tasaMaxima) {
            throw new \RuntimeException("La tasa de {$d['categoria']} va de 0% a {$tasaMaxima}% (máximo que acepta SUNAT).");
        }
        $costo = round((float) $d['costo'], 2);
        $residual = round((float) ($d['valor_residual'] ?? 0), 2);
        $inicial = round((float) ($d['depreciacion_inicial'] ?? 0), 2);
        if ($costo <= 0 || $residual < 0 || $residual >= $costo || $inicial < 0 || $inicial > $costo - $residual) {
            throw new \RuntimeException('Revisa los montos: el costo debe ser mayor al valor residual, y lo ya depreciado no puede pasar del costo.');
        }
        if ($d['fecha_uso'] < $d['fecha_adquisicion']) {
            throw new \RuntimeException('No se puede empezar a usar antes de comprarlo.');
        }
        $cuentas = [];
        foreach (['cuenta_activo' => $cuentaSugerida, 'cuenta_depreciacion' => '3913', 'cuenta_gasto' => '681'] as $campo => $sugerida) {
            $cuentas[$campo] = CentrosCosto::imputable($imp, (string) (($d[$campo] ?? null) ?: $sugerida))
                ?? throw new \RuntimeException('La cuenta '.(($d[$campo] ?? null) ?: $sugerida).' no existe en el plan contable.');
        }
        $centro = ! empty($d['centro_costo_id']) && CentrosCosto::valido($ruc, (int) $d['centro_costo_id']) ? (int) $d['centro_costo_id'] : null;
        $codigo = mb_strtoupper(trim((string) $d['codigo']));
        if (DB::table('activos_fijos')->where('IdEmpresa', $ruc)->where('codigo', $codigo)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw new \RuntimeException("Ya hay un activo con el código {$codigo}.");
        }

        $datos = ['codigo' => $codigo, 'descripcion' => mb_strtoupper(trim((string) $d['descripcion'])), 'categoria' => $d['categoria'],
            'marca_modelo' => ($d['marca_modelo'] ?? null) ?: null, 'serie' => ($d['serie'] ?? null) ?: null, 'ubicacion' => ($d['ubicacion'] ?? null) ?: null,
            'responsable' => ($d['responsable'] ?? null) ?: null, 'documento' => ($d['documento'] ?? null) ?: null,
            'fecha_adquisicion' => $d['fecha_adquisicion'], 'fecha_uso' => $d['fecha_uso'], 'costo' => $costo, 'valor_residual' => $residual,
            'tasa' => $tasa, 'depreciacion_inicial' => $inicial, 'centro_costo_id' => $centro, 'updated_at' => now()] + $cuentas;

        return DB::transaction(function () use ($user, $ruc, $d, $id, $datos, $imp, $costo, $inicial) {
            if ($id) {
                $antes = self::activo($ruc, $id);
                if ($antes->estado !== 'ACTIVO') {
                    throw new \RuntimeException('Un activo dado de baja ya no se edita.');
                }
                $procesado = DB::table('activos_depreciacion')->where('activo_id', $id)->exists();
                foreach (['costo', 'valor_residual', 'tasa', 'fecha_uso', 'depreciacion_inicial', 'cuenta_activo', 'cuenta_depreciacion', 'cuenta_gasto'] as $campo) {
                    if ($procesado && (string) $antes->{$campo} != (string) $datos[$campo]) {
                        throw new \RuntimeException('Ya tiene depreciación procesada: el costo, la tasa, la fecha de uso y las cuentas ya no se cambian. Dale de baja y regístralo de nuevo si estaba mal.');
                    }
                }
                DB::table('activos_fijos')->where('id', $id)->update($datos);

                return $id;
            }
            $id = DB::table('activos_fijos')->insertGetId($datos + ['IdEmpresa' => $ruc, 'id_empresa_negocio' => $user->id_empresa_negocio,
                'estado' => 'ACTIVO', 'IdUsuario' => $user->IdUsuario, 'created_at' => now()]);

            // Asiento de alta (si no está ya en la contabilidad): 33x al debe; lo ya depreciado a la 39; el resto, a la cuenta de pago
            if (! empty($d['contrapartida_alta'])) {
                $contrapartida = CentrosCosto::imputable($imp, (string) $d['contrapartida_alta'])
                    ?? throw new \RuntimeException("La cuenta {$d['contrapartida_alta']} no existe en el plan contable.");
                $asiento = Contabilidad::crearAsiento($ruc, ['fecha' => $d['fecha_adquisicion'], 'subdiario' => '35', 'origen' => 'ACTIVOS',
                    'glosa' => 'ALTA DE ACTIVO FIJO '.$datos['codigo'].' '.$datos['descripcion'], 'documento' => $datos['documento'],
                    'ref_tabla' => 'activos_fijos', 'ref_id' => $id], [
                        ['cuenta' => $datos['cuenta_activo'], 'debe' => $costo],
                        ['cuenta' => $datos['cuenta_depreciacion'], 'haber' => $inicial],
                        ['cuenta' => $contrapartida, 'haber' => round($costo - $inicial, 2)],
                    ], $imp);
                DB::table('activos_fijos')->where('id', $id)->update(['asiento_alta_id' => $asiento]);
            }

            return $id;
        });
    }

    /**
     * Procesa la depreciación del mes: lo que le toca a cada bien (con lo atrasado si se registró después) y un asiento.
     *
     * @return array{activos: int, total: float, asiento: ?int}
     */
    public static function depreciar(User $user, string $periodo): array
    {
        $ruc = (string) $user->IdEmpresa;
        if (! preg_match('/^\d{6}$/', $periodo) || $periodo > now()->format('Ym')) {
            throw new \RuntimeException('Elige un mes que ya llegó.');
        }
        Contabilidad::asegurar($ruc);
        Contabilidad::validarPeriodo($ruc, $periodo);
        $ultimo = DB::table('activos_depreciacion')->where('IdEmpresa', $ruc)->max('periodo');
        if ($ultimo && $ultimo > $periodo) {
            throw new \RuntimeException('Ya procesaste '.Contabilidad::nombrePeriodo((string) $ultimo).': solo se rehace el último mes procesado.');
        }
        $fin = Carbon::createFromFormat('!Ym', $periodo)->endOfMonth();

        return DB::transaction(function () use ($ruc, $periodo, $fin) {
            self::borrarMes($ruc, $periodo);
            $activos = DB::table('activos_fijos')->where('IdEmpresa', $ruc)->where('tasa', '>', 0)->where('fecha_uso', '<=', $fin->toDateString())
                // Un bien dado de baja se deprecia hasta el mes anterior a su baja
                ->where(fn ($q) => $q->where('estado', 'ACTIVO')->orWhere('fecha_baja', '>', $fin->toDateString()))->get();
            $montos = [];
            foreach ($activos as $a) {
                $monto = self::pendiente($a, $periodo);
                if ($monto > 0) {
                    $montos[$a->id] = $monto;
                }
            }
            if (! $montos) {
                return ['activos' => 0, 'total' => 0.0, 'asiento' => null];
            }

            // Un asiento del mes: gasto (681) al debe y depreciación acumulada (3913) al haber, por cuenta; y el destino por centro
            $imp = Contabilidad::imputables($ruc);
            $lineas = [];
            $porCentro = [];
            foreach ($activos->whereIn('id', array_keys($montos))->groupBy(fn ($a) => $a->cuenta_gasto.'|'.$a->cuenta_depreciacion) as $clave => $grupo) {
                [$gasto, $acumulada] = explode('|', $clave);
                $suma = round($grupo->sum(fn ($a) => $montos[$a->id]), 2);
                $lineas[] = ['cuenta' => $gasto, 'debe' => $suma, 'glosa' => 'DEPRECIACIÓN DEL MES'];
                $lineas[] = ['cuenta' => $acumulada, 'haber' => $suma];
            }
            foreach ($activos->whereIn('id', array_keys($montos)) as $a) {
                if ($a->centro_costo_id) {
                    $porCentro[$a->centro_costo_id] = ($porCentro[$a->centro_costo_id] ?? 0) + $montos[$a->id];
                }
            }
            $lineas = array_merge($lineas, CentrosCosto::lineasDestino($ruc, $porCentro, $imp));
            $asiento = Contabilidad::crearAsiento($ruc, ['fecha' => $fin->toDateString(), 'subdiario' => '35', 'origen' => 'ACTIVOS',
                'glosa' => 'DEPRECIACIÓN DE ACTIVOS FIJOS '.mb_strtoupper(Contabilidad::nombrePeriodo($periodo)), 'ref_tabla' => 'activos_depreciacion'], $lineas, $imp);

            foreach ($montos as $activo => $monto) {
                DB::table('activos_depreciacion')->insert(['activo_id' => $activo, 'IdEmpresa' => $ruc, 'periodo' => $periodo, 'monto' => $monto,
                    'asiento_id' => $asiento, 'created_at' => now()]);
            }

            return ['activos' => count($montos), 'total' => round(array_sum($montos), 2), 'asiento' => $asiento];
        });
    }

    /** Deshace la depreciación del último mes procesado (y su asiento) */
    public static function deshacerMes(User $user, string $periodo): void
    {
        $ruc = (string) $user->IdEmpresa;
        if (DB::table('activos_depreciacion')->where('IdEmpresa', $ruc)->max('periodo') !== $periodo) {
            throw new \RuntimeException('Solo se deshace el último mes procesado.');
        }
        Contabilidad::validarPeriodo($ruc, $periodo);
        DB::transaction(fn () => self::borrarMes($ruc, $periodo));
    }

    /** Baja: deja de depreciarse; sale el costo, se cancela lo depreciado y lo que faltaba va a gasto */
    public static function darDeBaja(User $user, int $id, string $fecha, string $motivo): void
    {
        $ruc = (string) $user->IdEmpresa;
        DB::transaction(function () use ($ruc, $id, $fecha, $motivo) {
            $a = DB::table('activos_fijos')->where('id', $id)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
            if (! $a || $a->estado !== 'ACTIVO') {
                throw new \RuntimeException('El activo no existe o ya está de baja.');
            }
            if ($fecha < $a->fecha_adquisicion) {
                throw new \RuntimeException('La baja no puede ser antes de su compra.');
            }
            $periodoBaja = date('Ym', strtotime($fecha));
            if (DB::table('activos_depreciacion')->where('activo_id', $id)->where('periodo', '>=', $periodoBaja)->exists()) {
                throw new \RuntimeException('Ya tiene depreciación de ese mes o posterior: deshaz esos meses o pon la baja en un mes siguiente.');
            }
            $imp = Contabilidad::imputables($ruc);
            $depreciado = self::acumulado($a);
            $neto = round((float) $a->costo - $depreciado, 2);
            $asiento = Contabilidad::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '35', 'origen' => 'ACTIVOS',
                'glosa' => 'BAJA DE ACTIVO FIJO '.$a->codigo.' '.$a->descripcion, 'ref_tabla' => 'activos_fijos', 'ref_id' => $id], [
                    ['cuenta' => $a->cuenta_depreciacion, 'debe' => $depreciado],
                    ['cuenta' => CentrosCosto::imputable($imp, '659') ?? '659', 'debe' => $neto, 'glosa' => 'VALOR NETO DADO DE BAJA'],
                    ['cuenta' => $a->cuenta_activo, 'haber' => (float) $a->costo],
                ], $imp);
            DB::table('activos_fijos')->where('id', $id)->update(['estado' => 'BAJA', 'fecha_baja' => $fecha, 'motivo_baja' => mb_substr(trim($motivo), 0, 255),
                'asiento_baja_id' => $asiento, 'updated_at' => now()]);
        });
    }

    /**
     * Cuadro de activos fijos a una fecha (como el registro de activos fijos de SUNAT).
     *
     * @return Collection<int, object>
     */
    public static function cuadro(string $ruc, string $fecha): Collection
    {
        $periodo = date('Ym', strtotime($fecha));
        $anio = substr($periodo, 0, 4);

        return self::lista($ruc)->filter(fn ($a) => $a->fecha_adquisicion <= $fecha)->map(function ($a) use ($periodo, $anio) {
            $a->acumulada = self::acumulado($a, $periodo);
            $a->del_ejercicio = round((float) DB::table('activos_depreciacion')->where('activo_id', $a->id)->whereBetween('periodo', [$anio.'01', $periodo])->sum('monto'), 2);
            $a->neto = round((float) $a->costo - $a->acumulada, 2);
            $a->de_baja = $a->estado === 'BAJA' && $a->fecha_baja <= date('Y-m-d', strtotime($periodo.'01 +1 month -1 day'));

            return $a;
        })->values();
    }

    /** Lo que le toca al bien ese mes: lo que debería llevar depreciado hasta ese mes menos lo que ya lleva */
    private static function pendiente(object $a, string $periodo): float
    {
        $base = round((float) $a->costo - (float) $a->valor_residual, 2);
        $inicio = Carbon::parse($a->fecha_uso)->startOfMonth();
        $meses = $inicio->diffInMonths(Carbon::createFromFormat('!Ym', $periodo)) + 1;
        $cuota = self::cuotaMensual($a);
        $deberia = min($base, round($cuota * $meses, 2));
        $lleva = self::acumulado($a, Carbon::createFromFormat('!Ym', $periodo)->subMonth()->format('Ym'));

        // Su cuota del mes, más lo atrasado si se registró tarde; nunca más de lo que le falta para llegar a su costo.
        // (Si trae depreciación de otro sistema por delante del calendario, sigue con su cuota normal.)
        return round(max(0, min($base - $lleva, max($cuota, $deberia - $lleva))), 2);
    }

    private static function borrarMes(string $ruc, string $periodo): void
    {
        $asientos = DB::table('activos_depreciacion')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->whereNotNull('asiento_id')->distinct()->pluck('asiento_id');
        DB::table('activos_depreciacion')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->delete();
        if ($asientos->isNotEmpty()) {
            DB::table('conta_asiento_detalle')->whereIn('asiento_id', $asientos)->delete();
            DB::table('conta_asientos')->whereIn('id', $asientos)->where('IdEmpresa', $ruc)->delete();
        }
    }
}
