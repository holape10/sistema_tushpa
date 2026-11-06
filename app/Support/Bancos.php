<?php

namespace App\Support;

use App\Models\User;
use App\Support\Contabilidad\Contabilidad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bancos: cuentas bancarias, su libro bancos y los movimientos propios del banco, todo amarrado a la contabilidad.
 *
 *  - Cada cuenta tiene su cuenta contable (104x). Los medios de pago que caen en ella (Yape, transferencia, POS…) toman
 *    esa cuenta: la centralización de cobranzas y pagos ya los lleva a ella.
 *  - Libro bancos = lo que el sistema dice que entró y salió de la cuenta, uno por uno: cobros de ventas al contado,
 *    cobros de ventas al crédito, pagos a proveedores, gastos y los movimientos propios del banco.
 *  - Movimiento propio (comisión, ITF, interés, depósito de caja, retiro, transferencia entre cuentas, otros): al
 *    registrarlo se crea su asiento (subdiario 01, origen BANCOS); al anularlo se borra su asiento.
 *
 * Por ahora las cuentas son en soles (todos los cobros y pagos del sistema se guardan en soles).
 */
class Bancos
{
    /** Tipo => [nombre, signo (+1 entra / −1 sale / 0 lo elige el usuario), cuenta contrapartida sugerida] */
    public const TIPOS = [
        'DEPOSITO' => ['Depósito de caja al banco', 1, 'caja'],
        'RETIRO' => ['Retiro del banco a caja', -1, 'caja'],
        'COMISION' => ['Comisión bancaria', -1, '6791'],
        'ITF' => ['ITF', -1, '641'],
        'INTERES' => ['Intereses ganados', 1, '772'],
        'GASTO_FIN' => ['Otros gastos financieros', -1, '679'],
        'TRANSFERENCIA' => ['Transferencia a otra cuenta propia', -1, 'banco'],
        'OTRO_INGRESO' => ['Otro ingreso', 1, '759'],
        'OTRO_EGRESO' => ['Otro egreso', -1, '659'],
    ];

    /** Bancos más usados en el Perú (se puede escribir otro) */
    public const BANCOS = ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación', 'BanBif', 'Pichincha', 'Caja Arequipa', 'Caja Huancayo',
        'Caja Piura', 'Caja Maynas', 'Caja Cusco', 'Caja Trujillo', 'Mibanco'];

    /** Cuentas de la empresa (con el nombre de su cuenta contable) */
    public static function cuentas(string $ruc, bool $soloActivas = false): Collection
    {
        return DB::table('bancos_cuentas')->where('IdEmpresa', $ruc)->when($soloActivas, fn ($q) => $q->where('activo', true))
            ->orderByDesc('activo')->orderBy('banco')->get();
    }

    public static function cuenta(string $ruc, int $id): object
    {
        $c = DB::table('bancos_cuentas')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        if (! $c) {
            throw new \RuntimeException('La cuenta bancaria no existe.');
        }

        return $c;
    }

    /**
     * Crea o edita una cuenta y le asigna sus medios de pago (que desde ahora van a su cuenta contable).
     *
     * @param  array{banco: string, numero: string, cuenta_contable: string, saldo_inicial: float, fecha_inicio: string, activo?: bool, medios?: array<int, int>}  $d
     */
    public static function guardarCuenta(User $user, array $d, ?int $id = null): int
    {
        $ruc = (string) $user->IdEmpresa;
        Contabilidad::asegurar($ruc);
        if (! isset(Contabilidad::imputables($ruc)[$d['cuenta_contable']])) {
            throw new \RuntimeException("La cuenta contable {$d['cuenta_contable']} no existe en el plan contable o tiene subcuentas: elige una cuenta 104 que reciba movimientos.");
        }

        return DB::transaction(function () use ($d, $id, $ruc) {
            $datos = ['banco' => trim($d['banco']), 'numero' => trim($d['numero']), 'moneda' => 'PEN', 'cuenta_contable' => $d['cuenta_contable'],
                'saldo_inicial' => round((float) $d['saldo_inicial'], 2), 'fecha_inicio' => $d['fecha_inicio'], 'activo' => (bool) ($d['activo'] ?? true),
                'updated_at' => now()];
            if ($id) {
                $anterior = self::cuenta($ruc, $id);
                DB::table('bancos_cuentas')->where('id', $id)->update($datos);
                // Los medios que se le quitaron vuelven a la cuenta de banco general (si seguían en la de esta cuenta)
                $quitados = DB::table('medios_pagos')->where('cuenta_bancaria_id', $id)->whereNotIn('id_med_pag', $d['medios'] ?? []);
                (clone $quitados)->where('cuenta_contable', $anterior->cuenta_contable)->update(['cuenta_contable' => null]);
                $quitados->update(['cuenta_bancaria_id' => null]);
            } else {
                $id = DB::table('bancos_cuentas')->insertGetId($datos + ['IdEmpresa' => $ruc, 'created_at' => now()]);
            }
            if (! empty($d['medios'])) {
                DB::table('medios_pagos')->where('IdEmpresa', $ruc)->whereIn('id_med_pag', $d['medios'])
                    ->update(['cuenta_bancaria_id' => $id, 'cuenta_contable' => $d['cuenta_contable']]);
            }

            return $id;
        });
    }

    /**
     * Libro bancos: lo que el sistema dice que entró (+) y salió (−) de la cuenta, uno por uno.
     *
     * @return Collection<int, array{origen: string, id: int, fecha: string, descripcion: string, operacion: ?string, monto: float}>
     */
    public static function movimientos(object $cuenta, string $desde, string $hasta): Collection
    {
        $desde = max($desde, (string) $cuenta->fecha_inicio);
        if ($desde > $hasta) {
            return collect();
        }
        $medios = DB::table('medios_pagos')->where('cuenta_bancaria_id', $cuenta->id)->pluck('id_med_pag');
        $docVenta = fn ($r) => $r->serdoc.'-'.$r->numdoc.(trim((string) $r->ccanom) !== '' ? ' · '.$r->ccanom : '');
        $lista = collect();

        if ($medios->isNotEmpty()) {
            // Ventas al contado cobradas con un medio que cae en esta cuenta
            foreach (DB::table('venta_medio_pago as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
                ->whereIn('v.id_med_pag', $medios)->whereNull('c.ccabaj')->whereBetween('c.ccafem', [$desde, $hasta])
                ->get(['v.ven_med_pag_id', 'v.monto', 'v.num_operacion', 'c.ccafem', 'c.serdoc', 'c.numdoc', 'c.ccanom']) as $r) {
                $lista->push(['origen' => 'venta', 'id' => (int) $r->ven_med_pag_id, 'fecha' => (string) $r->ccafem, 'descripcion' => 'Venta '.$docVenta($r),
                    'operacion' => $r->num_operacion, 'monto' => round((float) $r->monto, 2)]);
            }
            // Cobros de ventas al crédito
            foreach (DB::table('cuentas_cobrar_medios as m')->join('cuentas_cobrar_detalle as d', 'd.cue_cob_det_id', '=', 'm.cue_cob_det_id')
                ->join('cuentas_cobrar as cc', 'cc.cue_cob_id', '=', 'd.cue_cob_id')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->whereIn('m.med_pag_id', $medios)->where('d.est_cue_cob_det', 'REGISTRADO')->whereBetween('d.fec_dep', [$desde, $hasta.' 23:59:59'])
                ->get(['m.cue_cob_med_id', 'm.monto', 'd.fec_dep', 'd.num_oper', 'c.serdoc', 'c.numdoc', 'c.ccanom']) as $r) {
                $lista->push(['origen' => 'cobro', 'id' => (int) $r->cue_cob_med_id, 'fecha' => substr((string) $r->fec_dep, 0, 10),
                    'descripcion' => 'Cobro de '.$docVenta($r), 'operacion' => $r->num_oper, 'monto' => round((float) $r->monto, 2)]);
            }
            // Pagos a proveedores
            foreach (DB::table('cuentas_pagar_medios as m')->join('cuentas_pagar_detalle as d', 'd.cue_pag_det_id', '=', 'm.cue_pag_det_id')
                ->join('cuentas_pagar as cp', 'cp.cue_pag_id', '=', 'd.cue_pag_id')->join('compras_cabecera as c', 'c.com_cab_id', '=', 'cp.com_cab_id')
                ->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
                ->whereIn('m.med_pag_id', $medios)->where('d.est_cue_pag_det', 'REGISTRADO')->whereBetween('d.fec_dep', [$desde, $hasta.' 23:59:59'])
                ->get(['m.cue_pag_med_id', 'm.monto', 'd.fec_dep', 'd.num_oper', 'c.com_doc_ser', 'c.com_doc_num', 'p.prov_raz']) as $r) {
                $lista->push(['origen' => 'pago', 'id' => (int) $r->cue_pag_med_id, 'fecha' => substr((string) $r->fec_dep, 0, 10),
                    'descripcion' => 'Pago de '.$r->com_doc_ser.'-'.$r->com_doc_num.' · '.$r->prov_raz, 'operacion' => $r->num_oper, 'monto' => -round((float) $r->monto, 2)]);
            }
            // Gastos pagados desde la cuenta
            foreach (DB::table('gastos')->whereIn('id_med_pag', $medios)->where('estado', 'Registrado')->whereBetween('fecha', [$desde, $hasta])->get() as $g) {
                $total = $g->moneda === 'USD' ? (float) $g->total * (float) $g->tipo_cambio : (float) $g->total;
                $lista->push(['origen' => 'gasto', 'id' => (int) $g->id, 'fecha' => (string) $g->fecha,
                    'descripcion' => 'Gasto '.trim($g->serie.'-'.$g->numero, '-').' · '.$g->prov_nombre, 'operacion' => null, 'monto' => -round($total, 2)]);
            }
        }
        // Movimientos propios del banco
        foreach (DB::table('bancos_movimientos')->where('cuenta_id', $cuenta->id)->where('estado', 'REGISTRADO')->whereBetween('fecha', [$desde, $hasta])->get() as $m) {
            $lista->push(['origen' => 'banco', 'id' => (int) $m->id, 'fecha' => (string) $m->fecha, 'descripcion' => (self::TIPOS[$m->tipo][0] ?? $m->tipo)
                .($m->descripcion ? ' · '.$m->descripcion : ''), 'operacion' => $m->operacion, 'monto' => round((float) $m->monto, 2)]);
        }

        return $lista->sortBy([['fecha', 'asc'], ['origen', 'asc'], ['id', 'asc']])->values();
    }

    /** Saldo según el sistema al cierre de ese día */
    public static function saldoLibros(object $cuenta, string $hasta): float
    {
        return round((float) $cuenta->saldo_inicial + self::movimientos($cuenta, (string) $cuenta->fecha_inicio, $hasta)->sum('monto'), 2);
    }

    /**
     * Registra un movimiento propio del banco con su asiento contable. Una transferencia entre cuentas propias crea el
     * movimiento en las dos cuentas con un solo asiento.
     *
     * @param  array{tipo: string, fecha: string, monto: float, descripcion?: ?string, operacion?: ?string, contrapartida?: ?string, cuenta_destino_id?: ?int, sentido?: ?string}  $d
     */
    public static function registrarMovimiento(User $user, object $cuenta, array $d): int
    {
        $ruc = (string) $user->IdEmpresa;
        [$nombre, $signo] = self::TIPOS[$d['tipo']] ?? throw new \RuntimeException('Tipo de movimiento no válido.');
        $monto = round(abs((float) $d['monto']), 2);
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a 0.');
        }
        if ($d['fecha'] < $cuenta->fecha_inicio) {
            throw new \RuntimeException('La fecha es anterior al inicio de la cuenta ('.date('d/m/Y', strtotime($cuenta->fecha_inicio)).').');
        }
        // Los "otros" pueden ser de entrada o de salida según el extracto
        if (in_array($d['tipo'], ['OTRO_INGRESO', 'OTRO_EGRESO'], true) && in_array($d['sentido'] ?? null, ['entra', 'sale'], true)) {
            $signo = $d['sentido'] === 'entra' ? 1 : -1;
        }
        Contabilidad::asegurar($ruc);
        $cfg = Contabilidad::config($ruc);
        $imp = Contabilidad::imputables($ruc);
        $destino = null;
        if ($d['tipo'] === 'TRANSFERENCIA') {
            $destino = self::cuenta($ruc, (int) ($d['cuenta_destino_id'] ?? 0));
            if ((int) $destino->id === (int) $cuenta->id) {
                throw new \RuntimeException('Elige otra cuenta de destino.');
            }
            $contrapartida = $destino->cuenta_contable;
        } else {
            $contrapartida = self::imputable($imp, (string) (($d['contrapartida'] ?? null) ?: match (self::TIPOS[$d['tipo']][2]) {
                'caja' => $cfg->cta_caja, 'banco' => $cfg->cta_banco, default => self::TIPOS[$d['tipo']][2],
            }));
        }
        $descripcion = mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 200);
        $operacion = mb_substr(trim((string) ($d['operacion'] ?? '')), 0, 40) ?: null;

        return DB::transaction(function () use ($user, $ruc, $cuenta, $d, $nombre, $signo, $monto, $contrapartida, $destino, $descripcion, $operacion, $imp) {
            // Entra al banco: banco al debe; sale del banco: banco al haber
            $glosa = mb_strtoupper($nombre.($descripcion !== '' ? ' '.$descripcion : '').' '.$cuenta->banco.' '.$cuenta->numero);
            $asiento = Contabilidad::crearAsiento($ruc, ['fecha' => $d['fecha'], 'subdiario' => '01', 'origen' => 'BANCOS', 'glosa' => $glosa,
                'documento' => $operacion, 'ref_tabla' => 'bancos_movimientos'], $signo > 0
                ? [['cuenta' => $cuenta->cuenta_contable, 'debe' => $monto], ['cuenta' => $contrapartida, 'haber' => $monto]]
                : [['cuenta' => $contrapartida, 'debe' => $monto], ['cuenta' => $cuenta->cuenta_contable, 'haber' => $monto]], $imp);

            $base = ['fecha' => $d['fecha'], 'tipo' => $d['tipo'], 'descripcion' => $descripcion, 'operacion' => $operacion, 'asiento_id' => $asiento,
                'estado' => 'REGISTRADO', 'IdUsuario' => $user->IdUsuario, 'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('bancos_movimientos')->insertGetId($base + ['cuenta_id' => $cuenta->id, 'monto' => $signo * $monto,
                'contrapartida' => $contrapartida, 'cuenta_destino_id' => $destino?->id]);
            if ($destino) {
                $par = DB::table('bancos_movimientos')->insertGetId($base + ['cuenta_id' => $destino->id, 'monto' => $monto,
                    'contrapartida' => $cuenta->cuenta_contable, 'cuenta_destino_id' => $cuenta->id, 'par_id' => $id,
                    'descripcion' => mb_substr('Desde '.$cuenta->banco.' '.$cuenta->numero.($descripcion !== '' ? ' · '.$descripcion : ''), 0, 200)]);
                DB::table('bancos_movimientos')->where('id', $id)->update(['par_id' => $par]);
            }
            DB::table('conta_asientos')->where('id', $asiento)->update(['ref_id' => $id]);

            return $id;
        });
    }

    /** Anula un movimiento propio: borra su asiento (si el mes está abierto) y lo saca de la conciliación */
    public static function anularMovimiento(User $user, int $id): object
    {
        $ruc = (string) $user->IdEmpresa;

        return DB::transaction(function () use ($ruc, $id) {
            $m = DB::table('bancos_movimientos as m')->join('bancos_cuentas as c', 'c.id', '=', 'm.cuenta_id')->where('m.id', $id)->where('c.IdEmpresa', $ruc)
                ->lockForUpdate()->first(['m.*']);
            if (! $m || $m->estado !== 'REGISTRADO') {
                throw new \RuntimeException('El movimiento no existe o ya está anulado.');
            }
            Contabilidad::validarPeriodo($ruc, date('Ym', strtotime($m->fecha)));
            $ids = array_filter([(int) $m->id, (int) $m->par_id]);
            foreach ($ids as $movId) {
                Conciliacion::desconciliarOrigen('banco', $movId);
            }
            if ($m->asiento_id) {
                DB::table('conta_asiento_detalle')->where('asiento_id', $m->asiento_id)->delete();
                DB::table('conta_asientos')->where('id', $m->asiento_id)->where('IdEmpresa', $ruc)->delete();
            }
            DB::table('bancos_movimientos')->whereIn('id', $ids)->update(['estado' => 'ANULADO', 'updated_at' => now()]);

            return $m;
        });
    }

    /** Saldo de la cuenta en el Libro Mayor (lo que dicen los asientos) al cierre de ese día */
    public static function saldoMayor(string $ruc, string $cuentaContable, string $hasta): float
    {
        return round((float) DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->where('d.cuenta', $cuentaContable)->where('a.fecha', '<=', $hasta)
            ->sum(DB::raw('d.debe - d.haber')), 2);
    }

    /** La cuenta pedida si recibe movimientos; si no, su primera subcuenta que sí */
    private static function imputable($imputables, string $cuenta): string
    {
        if (isset($imputables[$cuenta])) {
            return $cuenta;
        }
        $hija = collect($imputables)->keys()->map(fn ($c) => (string) $c)->filter(fn ($c) => str_starts_with($c, $cuenta))->sort()->first();
        if (! $hija) {
            throw new \RuntimeException("La cuenta {$cuenta} no existe en el plan contable: elige otra contrapartida.");
        }

        return $hija;
    }
}
