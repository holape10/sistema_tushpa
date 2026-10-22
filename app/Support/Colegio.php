<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colegio: matrícula que genera la deuda del año (inscripción si es nuevo, matrícula y pensiones), descuento al 2° y 3°
 * alumno del grupo familiar (hermanos o referidos), cobro de uno o varios meses con descuento por adelantado, boleta o
 * factura a nombre de quien paga, pensión gratis (beca o sorteo) y morosidad.
 *
 * La deuda NO es comprobante: el comprobante se emite al cobrar.
 */
class Colegio
{
    public const NIVELES = ['INICIAL', 'PRIMARIA', 'SECUNDARIA'];

    public const RESPONSABLES = ['PADRE', 'MADRE', 'APODERADO'];

    public const CONCEPTOS = ['INSCRIPCION' => 'INSCRIPCIÓN', 'MATRICULA' => 'MATRÍCULA', 'PENSION' => 'PENSIÓN', 'OTRO' => 'OTRO'];

    /** Grados típicos de un colegio peruano (se cargan con un clic y luego se ajustan los precios) */
    public const GRADOS_TIPICOS = [
        ['INICIAL', '3 AÑOS'], ['INICIAL', '4 AÑOS'], ['INICIAL', '5 AÑOS'],
        ['PRIMARIA', 'PRIMERO'], ['PRIMARIA', 'SEGUNDO'], ['PRIMARIA', 'TERCERO'], ['PRIMARIA', 'CUARTO'], ['PRIMARIA', 'QUINTO'], ['PRIMARIA', 'SEXTO'],
        ['SECUNDARIA', 'PRIMERO'], ['SECUNDARIA', 'SEGUNDO'], ['SECUNDARIA', 'TERCERO'], ['SECUNDARIA', 'CUARTO'], ['SECUNDARIA', 'QUINTO'],
    ];

    public static function config(int $suc): object
    {
        $c = DB::table('col_config')->where('id_empresa_negocio', $suc)->first();
        if ($c) {
            return $c;
        }
        DB::table('col_config')->insert(['id_empresa_negocio' => $suc, 'anio' => (int) now()->year, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('col_config')->where('id_empresa_negocio', $suc)->first();
    }

    public static function nombreMes(string $periodo): string
    {
        return Socios::nombreMes($periodo);
    }

    public static function etiquetaGrado(?object $g): string
    {
        if (! $g) {
            return '—';
        }

        return trim($g->nivel.' '.$g->grado.(($g->seccion ?? '') !== '' && $g->seccion !== 'ÚNICA' ? ' "'.$g->seccion.'"' : ''));
    }

    /**
     * Producto (concepto del comprobante) de inscripción, matrícula o pensión: en la categoría COLEGIO, sin stock.
     */
    public static function concepto(User $user, string $tipo): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $campo = ['PENSION' => 'IdProducto_pension', 'MATRICULA' => 'IdProducto_matricula', 'INSCRIPCION' => 'IdProducto_inscripcion'][$tipo] ?? null;
        $cfg = self::config($suc);
        if ($campo && $cfg->$campo && DB::table('productos')->where('IdProducto', $cfg->$campo)->exists()) {
            return (int) $cfg->$campo;
        }
        $procod = 'COL-'.$tipo.'-'.$suc;
        if ($existe = DB::table('productos')->where('id_empresa_negocio', $suc)->where('procod', $procod)->value('IdProducto')) {
            if ($campo) {
                DB::table('col_config')->where('id_empresa_negocio', $suc)->update([$campo => $existe]);
            }

            return (int) $existe;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', 'COLEGIO')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'COLEGIO', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'tip_pro_id' => DB::table('tipo_producto')->where('id_empresa_negocio', $suc)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);
        $id = (int) Producto::create(['procod' => $procod, 'pronom' => self::CONCEPTOS[$tipo] ?? $tipo, 'umecod' => 'ZZ', 'costo' => 0, 'propun' => 0,
            'promocion' => 2, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc])->IdProducto;
        if ($campo) {
            DB::table('col_config')->where('id_empresa_negocio', $suc)->update([$campo => $id]);
        }

        return $id;
    }

    /** % de descuento en la pensión según el puesto en el grupo familiar (1° paga completo) */
    public static function descuentoGrupo(object $cfg, int $orden): float
    {
        return $orden >= 3 ? (float) $cfg->dscto_3 : ($orden === 2 ? (float) $cfg->dscto_2 : 0.0);
    }

    /** Puesto que tendría un alumno en su grupo familiar este año (cuántos del grupo ya están matriculados + 1) */
    public static function ordenEnGrupo(?int $grupoId, int $anio, ?int $sinAlumno = null): int
    {
        if (! $grupoId) {
            return 1;
        }

        return DB::table('col_matriculas as m')->join('col_alumnos as a', 'a.alu_id', '=', 'm.alu_id')
            ->where('a.grupo_id', $grupoId)->where('m.anio', $anio)->where('m.estado', 'ACTIVA')
            ->when($sinAlumno, fn ($q) => $q->where('a.alu_id', '!=', $sinAlumno))->count() + 1;
    }

    // ------------------------------------------------------------------ matrícula y deuda del año

    /**
     * Matricula al alumno en el año y le genera su deuda: inscripción (si es nuevo), matrícula y las pensiones de cada mes
     * del año escolar, que vencen el día configurado. La pensión ya lleva el descuento de grupo familiar.
     */
    public static function matricular(User $user, int $aluId, int $gradoId, int $anio, bool $nuevo, ?float $dsctoPct = null): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $cfg = self::config($suc);
        $alumno = DB::table('col_alumnos')->where('alu_id', $aluId)->where('id_empresa_negocio', $suc)->first();
        $grado = DB::table('col_grados')->where('grado_id', $gradoId)->where('id_empresa_negocio', $suc)->first();
        if (! $alumno || ! $grado) {
            throw new \RuntimeException('Alumno o grado no válido.');
        }
        if (DB::table('col_matriculas')->where('alu_id', $aluId)->where('anio', $anio)->exists()) {
            throw new \RuntimeException("{$alumno->nombres} ya está matriculado en {$anio}.");
        }

        return DB::transaction(function () use ($user, $suc, $cfg, $alumno, $grado, $anio, $nuevo, $dsctoPct) {
            $orden = self::ordenEnGrupo($alumno->grupo_id, $anio);
            $pct = $dsctoPct ?? self::descuentoGrupo($cfg, $orden);
            $matId = DB::table('col_matriculas')->insertGetId([
                'alu_id' => $alumno->alu_id, 'anio' => $anio, 'grado_id' => $grado->grado_id, 'nuevo' => $nuevo, 'orden_grupo' => $orden,
                'dscto_pct' => $pct, 'estado' => 'ACTIVA', 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc, 'creado' => now(),
            ]);
            $cargo = fn (string $concepto, ?string $periodo, string $desc, float $monto, ?string $vence, float $dscto = 0) => DB::table('col_cargos')->insert([
                'alu_id' => $alumno->alu_id, 'mat_id' => $matId, 'concepto' => $concepto, 'periodo' => $periodo, 'descripcion' => mb_substr($desc, 0, 150),
                'monto' => round($monto, 2), 'descuento' => round($dscto, 2), 'vence' => $vence, 'estado' => 'PENDIENTE',
                'IdProducto' => self::concepto($user, $concepto), 'id_empresa_negocio' => $suc, 'creado' => now(),
            ]);
            $hoy = now()->toDateString();
            if ($nuevo && (float) $grado->inscripcion > 0) {
                $cargo('INSCRIPCION', null, "INSCRIPCIÓN {$anio}", (float) $grado->inscripcion, $hoy);
            }
            if ((float) $grado->matricula > 0) {
                $cargo('MATRICULA', null, "MATRÍCULA {$anio}", (float) $grado->matricula, $hoy);
            }
            if ((float) $grado->pension > 0) {
                for ($mes = (int) $cfg->mes_inicio; $mes <= (int) $cfg->mes_fin; $mes++) {
                    $periodo = sprintf('%04d%02d', $anio, $mes);
                    $dia = min((int) $cfg->dia_vence, Carbon::create($anio, $mes, 1)->daysInMonth);
                    $cargo('PENSION', $periodo, 'PENSIÓN '.self::nombreMes($periodo), (float) $grado->pension,
                        Carbon::create($anio, $mes, $dia)->toDateString(), (float) $grado->pension * $pct / 100);
                }
            }
            DB::table('col_alumnos')->where('alu_id', $alumno->alu_id)->update(['estado' => 'ACTIVO']);

            return $matId;
        });
    }

    /** Cambia el % de descuento de la matrícula: las pensiones que aún no se pagan quedan con el nuevo descuento */
    public static function cambiarDescuento(int $matId, float $pct): void
    {
        DB::transaction(function () use ($matId, $pct) {
            DB::table('col_matriculas')->where('mat_id', $matId)->update(['dscto_pct' => $pct]);
            DB::table('col_cargos')->where('mat_id', $matId)->where('concepto', 'PENSION')->where('estado', 'PENDIENTE')->where('pagado', 0)
                ->update(['descuento' => DB::raw('ROUND(monto * '.((float) $pct).' / 100, 2)')]);
        });
    }

    // ------------------------------------------------------------------ cobro

    /**
     * Lo que se cobra por estos cargos: cada uno a su saldo y, si son varias pensiones juntas (N o más), el descuento
     * por pagar por adelantado sobre esas pensiones.
     *
     * @return array{lineas: array<int, array{car_id: int, descripcion: string, saldo: float, descuento: float, cobrar: float}>, adelanto: bool, total: float, ahorro: float}
     */
    public static function calcular(object $cfg, Collection $cargos): array
    {
        $pensiones = $cargos->where('concepto', 'PENSION')->count();
        $adelanto = (int) $cfg->adelanto_meses > 0 && (float) $cfg->adelanto_pct > 0 && $pensiones >= (int) $cfg->adelanto_meses;
        $lineas = $cargos->map(function ($c) use ($adelanto, $cfg) {
            $saldo = round((float) $c->monto - (float) $c->descuento - (float) $c->pagado, 2);
            $dscto = $adelanto && $c->concepto === 'PENSION' ? round($saldo * (float) $cfg->adelanto_pct / 100, 2) : 0.0;

            return ['car_id' => (int) $c->car_id, 'descripcion' => $c->descripcion, 'saldo' => $saldo, 'descuento' => $dscto, 'cobrar' => round($saldo - $dscto, 2)];
        })->values()->all();

        return ['lineas' => $lineas, 'adelanto' => $adelanto, 'total' => round(array_sum(array_column($lineas, 'cobrar')), 2),
            'ahorro' => round(array_sum(array_column($lineas, 'descuento')), 2)];
    }

    /**
     * Cobra cargos del alumno y emite el comprobante (boleta, factura o nota de venta) a nombre de quien paga.
     *
     * @param  array<int, int>  $carIds
     * @param  array<string, mixed>  $datos  tdocod, pagador, tdicod, clinum, clinom, clidir, id_med_pag[], mon_med_pag[], op_med_pag[], paga
     */
    public static function cobrar(User $user, int $aluId, array $carIds, array $datos): int
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $aluId, $carIds, $datos) {
            $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (! $turno) {
                throw new \RuntimeException('Debes aperturar tu turno de caja antes de cobrar.');
            }
            $alumno = DB::table('col_alumnos')->where('alu_id', $aluId)->where('id_empresa_negocio', $suc)->first();
            if (! $alumno) {
                throw new \RuntimeException('Alumno no encontrado.');
            }
            $cargos = DB::table('col_cargos')->where('alu_id', $aluId)->whereIn('car_id', $carIds)->where('estado', 'PENDIENTE')
                ->orderByRaw("FIELD(concepto, 'INSCRIPCION', 'MATRICULA', 'PENSION', 'OTRO')")->orderBy('periodo')->orderBy('car_id')->lockForUpdate()->get();
            if ($cargos->isEmpty() || $cargos->count() !== count(array_unique($carIds))) {
                throw new \RuntimeException('Algún concepto ya fue pagado o anulado. Actualiza la pantalla.');
            }
            $cfg = self::config($suc);
            $calculo = self::calcular($cfg, $cargos);
            if ($calculo['total'] <= 0) {
                throw new \RuntimeException('No hay nada que cobrar.');
            }

            $grado = DB::table('col_matriculas as m')->join('col_grados as g', 'g.grado_id', '=', 'm.grado_id')
                ->where('m.alu_id', $aluId)->orderByDesc('m.anio')->first(['g.*']);
            $lineas = collect($calculo['lineas'])->map(fn ($l) => [
                'IdProducto' => $cargos->firstWhere('car_id', $l['car_id'])->IdProducto, 'cantidad' => 1, 'precio' => $l['cobrar'],
                'descripcion' => mb_substr($l['descripcion'].($l['descuento'] > 0 ? ' (DSCTO. '.rtrim(rtrim(number_format((float) $cfg->adelanto_pct, 2), '0'), '.').'% PAGO ADELANTADO)' : ''), 0, 150),
            ])->all();

            $doc = trim((string) ($datos['clinum'] ?? '')) ?: ($alumno->dni ?: '00000000');
            $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
            $medios = array_values(array_filter((array) ($datos['id_med_pag'] ?? [])));
            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => $datos['tdocod'], 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
                'tdicod' => $datos['tdicod'] ?? (strlen($doc) === 11 ? '6' : '1'), 'clinum' => $doc,
                'clinom' => ($datos['clinom'] ?? null) ?: $alumno->nombres, 'clidir' => ($datos['clidir'] ?? null) ?: '--',
                'observaciones' => mb_substr('ALUMNO(A): '.$alumno->nombres.($alumno->dni ? ' · DNI '.$alumno->dni : '').' · '.self::etiquetaGrado($grado), 0, 100),
                'id_med_pag' => $medios, 'op_med_pag' => $datos['op_med_pag'] ?? [], 'paga' => $datos['paga'] ?? 0,
                // Con un solo medio de pago el monto es el total (nunca lo que llegue de la pantalla)
                'mon_med_pag' => count($medios) === 1 ? [$calculo['total']] : ($datos['mon_med_pag'] ?? []),
            ], $lineas, ['ped_tip' => 'COLEGIO', 'IdUsuario_ven' => $user->IdUsuario]);

            $pagador = in_array($datos['pagador'] ?? null, array_merge(self::RESPONSABLES, ['ALUMNO', 'OTRO']), true) ? $datos['pagador'] : null;
            foreach ($calculo['lineas'] as $l) {
                $c = $cargos->firstWhere('car_id', $l['car_id']);
                DB::table('col_pagos')->insert(['car_id' => $l['car_id'], 'IdCpe_cabecera' => $cabId, 'monto' => $l['cobrar'], 'descuento' => $l['descuento'],
                    'pagador' => $pagador, 'fecha' => now()]);
                DB::table('col_cargos')->where('car_id', $l['car_id'])->update(['pagado' => round((float) $c->pagado + $l['saldo'], 2), 'estado' => 'PAGADO']);
            }

            return $cabId;
        });
    }

    /** Si el comprobante se anula (o tiene nota de crédito total), lo que pagó vuelve a deberse */
    public static function revertirComprobante(int $cabId): void
    {
        if (! Schema::hasTable('col_pagos')) {
            return;
        }
        foreach (DB::table('col_pagos')->where('IdCpe_cabecera', $cabId)->where('anulado', 0)->get() as $p) {
            $c = DB::table('col_cargos')->where('car_id', $p->car_id)->lockForUpdate()->first();
            if ($c) {
                DB::table('col_cargos')->where('car_id', $c->car_id)->update([
                    'pagado' => max(0, round((float) $c->pagado - (float) $p->monto - (float) $p->descuento, 2)),
                    'estado' => $c->estado === 'ANULADO' ? 'ANULADO' : 'PENDIENTE',
                ]);
            }
            DB::table('col_pagos')->where('pag_id', $p->pag_id)->update(['anulado' => 1]);
        }
    }

    /** Pensión gratis (beca, sorteo, convenio): el cargo deja de deberse sin emitir comprobante */
    public static function becar(int $suc, int $carId, string $motivo): void
    {
        $n = DB::table('col_cargos')->where('car_id', $carId)->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')->where('pagado', 0)
            ->update(['estado' => 'BECADO', 'motivo' => mb_substr($motivo, 0, 200)]);
        if (! $n) {
            throw new \RuntimeException('Solo se puede dar gratis algo que aún no tiene pagos.');
        }
    }

    // ------------------------------------------------------------------ situación de cada alumno

    /**
     * Por alumno: pensiones vencidas sin pagar, cuánto debe vencido, cuánto falta en total del año y su último pago.
     *
     * @return Collection<int, object{alu_id: int, meses: int, vencido: float, pendiente: float, ultimo_pago: ?string}>
     */
    public static function situacion(int $suc, int $anio): Collection
    {
        $hoy = now()->toDateString();
        $deuda = DB::table('col_cargos as c')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')
            ->where('c.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('c.estado', 'PENDIENTE')
            ->groupBy('c.alu_id')->select('c.alu_id',
                DB::raw("SUM(CASE WHEN c.vence < '{$hoy}' AND c.concepto = 'PENSION' THEN 1 ELSE 0 END) as meses"),
                DB::raw("SUM(CASE WHEN c.vence < '{$hoy}' THEN c.monto - c.descuento - c.pagado ELSE 0 END) as vencido"),
                DB::raw('SUM(c.monto - c.descuento - c.pagado) as pendiente'))
            ->get()->keyBy('alu_id');
        $ultimo = DB::table('col_pagos as p')->join('col_cargos as c', 'c.car_id', '=', 'p.car_id')->where('c.id_empresa_negocio', $suc)
            ->where('p.anulado', 0)->groupBy('c.alu_id')->select('c.alu_id', DB::raw('MAX(p.fecha) as fecha'))->pluck('fecha', 'c.alu_id');

        return DB::table('col_matriculas')->where('id_empresa_negocio', $suc)->where('anio', $anio)->pluck('alu_id')
            ->mapWithKeys(fn ($id) => [$id => (object) [
                'alu_id' => $id, 'meses' => (int) ($deuda[$id]->meses ?? 0), 'vencido' => round((float) ($deuda[$id]->vencido ?? 0), 2),
                'pendiente' => round((float) ($deuda[$id]->pendiente ?? 0), 2), 'ultimo_pago' => $ultimo[$id] ?? null,
            ]]);
    }

    /** Etiqueta para la lista: AL DÍA, DEBE 1 MES, DEBE 3 MESES… */
    public static function estado(?object $s): array
    {
        if (! $s) {
            return ['SIN MATRÍCULA', 'gray'];
        }
        if ($s->meses >= 2) {
            return ['DEBE '.$s->meses.' MESES', 'red'];
        }
        if ($s->meses === 1) {
            return ['DEBE 1 MES', 'amber'];
        }
        if ($s->vencido > 0.009) {
            return ['CON DEUDA', 'amber'];
        }

        return ['AL DÍA', 'green'];
    }

    // ------------------------------------------------------------------ sorteo de pensión gratis

    /**
     * Padres puntuales: alumnos activos sin nada vencido que pagaron cada pensión a tiempo (y al menos una), con la pensión
     * del mes del sorteo todavía por pagar.
     */
    public static function candidatosSorteo(int $suc, int $anio, string $periodo): Collection
    {
        $hoy = now()->toDateString();
        $conVencidos = DB::table('col_cargos as c')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')->where('c.id_empresa_negocio', $suc)
            ->where('m.anio', $anio)->where('c.estado', 'PENDIENTE')->where('c.vence', '<', $hoy)->distinct()->pluck('c.alu_id');
        $impuntuales = DB::table('col_cargos as c')->join('col_pagos as p', 'p.car_id', '=', 'c.car_id')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')
            ->where('c.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('c.concepto', 'PENSION')->where('p.anulado', 0)
            ->whereRaw('DATE(p.fecha) > c.vence')->distinct()->pluck('c.alu_id');
        $pagaron = DB::table('col_cargos as c')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')->where('c.id_empresa_negocio', $suc)
            ->where('m.anio', $anio)->where('c.concepto', 'PENSION')->where('c.estado', 'PAGADO')->distinct()->pluck('c.alu_id');

        return DB::table('col_cargos as c')->join('col_alumnos as a', 'a.alu_id', '=', 'c.alu_id')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')
            ->join('col_grados as g', 'g.grado_id', '=', 'm.grado_id')
            ->where('c.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('m.estado', 'ACTIVA')->where('a.estado', 'ACTIVO')
            ->where('c.concepto', 'PENSION')->where('c.periodo', $periodo)->where('c.estado', 'PENDIENTE')->where('c.pagado', 0)
            ->whereIn('c.alu_id', $pagaron)->whereNotIn('c.alu_id', $conVencidos->merge($impuntuales)->all() ?: [0])
            ->orderBy('a.nombres')->get(['c.car_id', 'a.alu_id', 'a.nombres', 'g.nivel', 'g.grado', 'g.seccion', DB::raw('c.monto - c.descuento as monto')]);
    }

    /** Sortea entre los padres puntuales: la pensión de ese mes del ganador queda gratis */
    public static function sortear(User $user, string $periodo): object
    {
        $suc = (int) $user->id_empresa_negocio;
        $cfg = self::config($suc);

        return DB::transaction(function () use ($user, $suc, $cfg, $periodo) {
            $candidatos = self::candidatosSorteo($suc, (int) $cfg->anio, $periodo);
            if ($candidatos->isEmpty()) {
                throw new \RuntimeException('No hay padres puntuales con la pensión de ese mes por pagar.');
            }
            $ganador = $candidatos->random();
            self::becar($suc, (int) $ganador->car_id, 'SORTEO PADRES PUNTUALES '.now()->format('d/m/Y'));
            DB::table('col_sorteos')->insert(['alu_id' => $ganador->alu_id, 'car_id' => $ganador->car_id, 'periodo' => $periodo,
                'candidatos' => $candidatos->count(), 'IdUsuario' => $user->IdUsuario, 'fecha' => now(), 'id_empresa_negocio' => $suc]);
            $ganador->candidatos = $candidatos->count();

            return $ganador;
        });
    }
}
