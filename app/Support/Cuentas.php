<?php
namespace App\Support;

use App\Models\{MedioPago, Turno, User};
use Illuminate\Support\Facades\DB;

/**
 * Cuentas por cobrar (ventas al crédito) y por pagar (compras al crédito).
 * - La cuenta se abre sola al emitir la venta / registrar la compra al crédito.
 * - Cada pago (parcial o total) queda en _detalle con sus medios en _medios y un número de recibo.
 * - El efectivo cobrado entra a la caja del turno (007 CUENTAS POR COBRAR); el pago a proveedores con
 *   efectivo de caja sale como 009 PAGO A PROVEEDORES. Así el arqueo cuadra.
 * Todo debe llamarse dentro de una transacción (registrarPago/anularPago abren la suya).
 */
class Cuentas
{
    /** Nombres de tablas y columnas de cada tipo (se mantienen los del sistema antiguo) */
    public const T = [
        'cobrar' => [
            'tabla' => 'cuentas_cobrar', 'pk' => 'cue_cob_id', 'doc' => 'IdCpe_cabecera',
            'det' => 'cuentas_cobrar_detalle', 'detPk' => 'cue_cob_det_id', 'estDet' => 'est_cue_cob_det',
            'med' => 'cuentas_cobrar_medios', 'caja' => '007', 'recibo' => 'RC',
        ],
        'pagar' => [
            'tabla' => 'cuentas_pagar', 'pk' => 'cue_pag_id', 'doc' => 'com_cab_id',
            'det' => 'cuentas_pagar_detalle', 'detPk' => 'cue_pag_det_id', 'estDet' => 'est_cue_pag_det',
            'med' => 'cuentas_pagar_medios', 'caja' => '009', 'recibo' => 'RP',
        ],
    ];

    // ---------------- Apertura (desde la venta / compra) ----------------

    public static function abrirPorCobrar(int $cpeId): void
    {
        $c = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cpeId)->first();
        DB::table('cuentas_cobrar')->insertOrIgnore([
            'IdCpe_cabecera' => $c->IdCpe_cabecera, 'clicod' => $c->clicod, 'IdEmpresa' => $c->IdEmpresa,
            'id_empresa_negocio' => $c->id_empresa_negocio, 'total' => $c->ccaitv, 'saldo' => $c->ccaitv,
            'fec_ven' => $c->ccafve, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Crea, actualiza o cierra la cuenta por pagar de una compra recién guardada/editada.
     * Si ya tiene pagos, no deja bajar el total por debajo de lo pagado ni pasarla a contado.
     */
    public static function sincronizarPorPagar(int $comId): void
    {
        $c = DB::table('compras_cabecera')->where('com_cab_id', $comId)->first();
        $cuenta = DB::table('cuentas_pagar')->where('com_cab_id', $comId)->lockForUpdate()->first();
        $credito = $c->est_compra === 'Registrado' && (float) $c->tot_cre > 0;
        $pagado = $cuenta ? (float) $cuenta->abono : 0;

        if (!$credito) {
            if ($pagado > 0) {
                throw new \RuntimeException('Esta compra tiene pagos registrados (S/ ' . number_format($pagado, 2)
                    . '). Anula primero esos pagos en Cuentas por Pagar.');
            }
            if ($cuenta) {
                DB::table('cuentas_pagar')->where('cue_pag_id', $cuenta->cue_pag_id)
                    ->update(['estado_cob' => 'ANULADO', 'saldo' => 0, 'updated_at' => now()]);
            }
            DB::table('compras_cabecera')->where('com_cab_id', $comId)->update(['saldofactura' => 0]);
            return;
        }

        $total = round((float) $c->total_com, 2);
        if ($pagado > $total + 0.001) {
            throw new \RuntimeException('Ya se pagaron S/ ' . number_format($pagado, 2) . ' de esta compra; el nuevo total no puede ser menor.');
        }
        $saldo = round($total - $pagado, 2);
        $datos = [
            'clicod' => $c->prov_id, 'IdEmpresa' => $c->IdEmpresa, 'id_empresa_negocio' => $c->id_empresa_negocio,
            'total' => $total, 'saldo' => $saldo, 'fec_ven' => $c->com_fec_ven,
            'estado_cob' => self::estado($pagado, $saldo), 'updated_at' => now(),
        ];
        $cuenta
            ? DB::table('cuentas_pagar')->where('cue_pag_id', $cuenta->cue_pag_id)->update($datos)
            : DB::table('cuentas_pagar')->insert($datos + ['com_cab_id' => $comId, 'created_at' => now()]);
        DB::table('compras_cabecera')->where('com_cab_id', $comId)->update(['saldofactura' => $saldo]);
    }

    /** Al anular una venta o compra: solo si no tiene pagos */
    public static function anularPorDocumento(string $tipo, int $docId): void
    {
        $t = self::T[$tipo];
        $cuenta = DB::table($t['tabla'])->where($t['doc'], $docId)->lockForUpdate()->first();
        if (!$cuenta) {
            return;
        }
        if ((float) $cuenta->abono > 0) {
            throw new \RuntimeException('Tiene pagos registrados (S/ ' . number_format($cuenta->abono, 2) . '). Anula primero esos pagos.');
        }
        DB::table($t['tabla'])->where($t['pk'], $cuenta->{$t['pk']})->update(['estado_cob' => 'ANULADO', 'saldo' => 0, 'updated_at' => now()]);
    }

    // ---------------- Pagos ----------------

    /**
     * @param array $d fecha, medios [[id, monto], ...], num_oper, comentario, desde_caja (solo pagar)
     * @return int id del pago (detalle)
     */
    public static function registrarPago(User $user, string $tipo, int $cuentaId, array $d): int
    {
        $t = self::T[$tipo];
        return DB::transaction(function () use ($user, $tipo, $t, $cuentaId, $d) {
            $cuenta = DB::table($t['tabla'])->where($t['pk'], $cuentaId)
                ->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (!$cuenta) {
                throw new \RuntimeException('La cuenta no existe.');
            }
            if (in_array($cuenta->estado_cob, ['PAGADO', 'ANULADO'], true)) {
                throw new \RuntimeException('Esta cuenta ya está ' . strtolower($cuenta->estado_cob) . '.');
            }

            // Medios de pago: válidos, positivos y sin repetir
            $validos = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->get()->keyBy('id_med_pag');
            $medios = collect($d['medios'])->map(fn($m) => ['id' => (int) $m['id'], 'monto' => round((float) $m['monto'], 2)])
                ->filter(fn($m) => $m['monto'] > 0)->values();
            if ($medios->isEmpty()) {
                throw new \RuntimeException('Ingresa el monto del pago.');
            }
            if ($medios->contains(fn($m) => !isset($validos[$m['id']])) || $medios->pluck('id')->duplicates()->isNotEmpty()) {
                throw new \RuntimeException('Revisa los medios de pago.');
            }
            $monto = round($medios->sum('monto'), 2);
            if ($monto > (float) $cuenta->saldo + 0.001) {
                throw new \RuntimeException('El pago (S/ ' . number_format($monto, 2) . ') es mayor al saldo pendiente (S/ ' . number_format($cuenta->saldo, 2) . ').');
            }

            // Efectivo: los cobros siempre entran a la caja; los pagos a proveedores solo si se marcó "desde caja"
            $efectivo = round($medios->filter(fn($m) => strtoupper((string) $validos[$m['id']]->nom_med_pag) === 'EFECTIVO')->sum('monto'), 2);
            $usaCaja = $efectivo > 0 && ($tipo === 'cobrar' || !empty($d['desde_caja']));
            $turno = Turno::abiertoDe($user);
            if ($usaCaja && !$turno) {
                throw new \RuntimeException('Para ' . ($tipo === 'cobrar' ? 'cobrar' : 'pagar con') . ' efectivo de caja debes tener tu turno abierto.');
            }

            $saldo = round((float) $cuenta->saldo - $monto, 2);
            $abono = round((float) $cuenta->abono + $monto, 2);
            $doc = self::documento($tipo, $cuenta->{$t['doc']});

            $movCaja = null;
            if ($usaCaja) {
                $movCaja = DB::table('movimientoscaja')->insertGetId([
                    'tip_caj_id' => $t['caja'], 'importe' => $efectivo, 'estado' => 'ACTIVO',
                    'mov_com' => ($tipo === 'cobrar' ? 'Cobro ' : 'Pago ') . $doc['numero'] . ' - ' . $doc['nombre'],
                    'mov_num_doc' => $doc['numero'], 'mov_fecha' => now()->toDateString(), 'registro' => now()->format('Y-m-d H:i:s'),
                    'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
                    'id_turno' => $turno->id_turno, 'IdUsuario' => $user->IdUsuario,
                ]);
            }

            $detId = DB::table($t['det'])->insertGetId([
                $t['pk'] => $cuentaId, 'fec_dep' => $d['fecha'], 'abono' => $monto, 'saldo_detalle' => $saldo,
                'num_oper' => ($d['num_oper'] ?? null) ?: null, 'comentario' => ($d['comentario'] ?? null) ?: null,
                $t['estDet'] => 'REGISTRADO', 'id_turno' => $turno?->id_turno, 'mov_caj_id' => $movCaja,
                'IdUsuario' => $user->IdUsuario, 'fec_reg' => now(),
            ]);
            DB::table($t['det'])->where($t['detPk'], $detId)
                ->update(['numero_recibo' => $t['recibo'] . '-' . str_pad((string) $detId, 6, '0', STR_PAD_LEFT)]);
            foreach ($medios as $m) {
                DB::table($t['med'])->insert([$t['detPk'] => $detId, 'med_pag_id' => $m['id'], 'monto' => $m['monto'],
                    'id_empresa_negocio' => $user->id_empresa_negocio]);
            }

            DB::table($t['tabla'])->where($t['pk'], $cuentaId)->update([
                'abono' => $abono, 'saldo' => $saldo, 'estado_cob' => self::estado($abono, $saldo),
                'fec_pago' => $d['fecha'], 'updated_at' => now(),
            ]);
            if ($tipo === 'pagar') {
                DB::table('compras_cabecera')->where('com_cab_id', $cuenta->com_cab_id)->update(['saldofactura' => $saldo]);
            }

            return $detId;
        });
    }

    /** Anula un pago: devuelve el saldo y, si movió efectivo de caja, anula ese movimiento (turno aún abierto) */
    public static function anularPago(User $user, string $tipo, int $detId, string $motivo): void
    {
        $t = self::T[$tipo];
        DB::transaction(function () use ($user, $tipo, $t, $detId, $motivo) {
            $det = DB::table($t['det'])->where($t['detPk'], $detId)->lockForUpdate()->first();
            $cuenta = $det ? DB::table($t['tabla'])->where($t['pk'], $det->{$t['pk']})
                ->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first() : null;
            if (!$det || !$cuenta) {
                throw new \RuntimeException('El pago no existe.');
            }
            if ($det->{$t['estDet']} !== 'REGISTRADO') {
                throw new \RuntimeException('El pago ya estaba anulado.');
            }

            if ($det->mov_caj_id) {
                $mov = DB::table('movimientoscaja')->where('mov_caj_id', $det->mov_caj_id)->first();
                $turnoAbierto = $mov && DB::table('turnos')->where('id_turno', $mov->id_turno)->where('estado', 'ABIERTO')->exists();
                if (!$turnoAbierto) {
                    throw new \RuntimeException('Este pago movió efectivo de un turno que ya se cerró; no se puede anular.');
                }
                DB::table('movimientoscaja')->where('mov_caj_id', $det->mov_caj_id)->update(['estado' => 'ANULADO']);
            }

            DB::table($t['det'])->where($t['detPk'], $detId)->update([
                $t['estDet'] => 'ANULADO', 'IdUsuario_anula' => $user->IdUsuario, 'motivo_anula' => mb_substr($motivo, 0, 100),
            ]);
            $abono = round((float) $cuenta->abono - (float) $det->abono, 2);
            $saldo = round((float) $cuenta->saldo + (float) $det->abono, 2);
            DB::table($t['tabla'])->where($t['pk'], $cuenta->{$t['pk']})->update([
                'abono' => $abono, 'saldo' => $saldo, 'estado_cob' => self::estado($abono, $saldo), 'updated_at' => now(),
            ]);
            if ($tipo === 'pagar') {
                DB::table('compras_cabecera')->where('com_cab_id', $cuenta->com_cab_id)->update(['saldofactura' => $saldo]);
            }
        });
    }

    // ---------------- Consultas ----------------

    /** Consulta base de cuentas con los datos del documento y del cliente/proveedor */
    public static function consulta(string $tipo, int $sucursal)
    {
        if ($tipo === 'cobrar') {
            return DB::table('cuentas_cobrar as cc')
                ->join('cpe_cabecera as d', 'd.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->where('cc.id_empresa_negocio', $sucursal)
                ->select('cc.cue_cob_id as id', 'cc.total', 'cc.abono', 'cc.saldo', 'cc.fec_ven', 'cc.estado_cob', 'cc.fec_pago',
                    'd.IdCpe_cabecera as doc_id', 'd.tdocod', 'd.serdoc as serie', 'd.numdoc as numero', 'd.ccafem as fecha',
                    'd.ccandi as doc_persona', 'd.ccanom as persona', 'd.moncod as moneda');
        }
        return DB::table('cuentas_pagar as cp')
            ->join('compras_cabecera as d', 'd.com_cab_id', '=', 'cp.com_cab_id')
            ->leftJoin('proveedor as p', 'p.prov_id', '=', 'd.prov_id')
            ->where('cp.id_empresa_negocio', $sucursal)
            ->select('cp.cue_pag_id as id', 'cp.total', 'cp.abono', 'cp.saldo', 'cp.fec_ven', 'cp.estado_cob', 'cp.fec_pago',
                'd.com_cab_id as doc_id', 'd.tdocod', 'd.com_doc_ser as serie', 'd.com_doc_num as numero', 'd.com_fec as fecha',
                'p.prov_ruc as doc_persona', 'p.prov_raz as persona', 'd.mon_id as moneda');
    }

    /** Pagos de una cuenta, con sus medios */
    public static function pagos(string $tipo, int $cuentaId)
    {
        $t = self::T[$tipo];
        $pagos = DB::table($t['det'] . ' as p')->leftJoin('users as u', 'u.IdUsuario', '=', 'p.IdUsuario')
            ->where('p.' . $t['pk'], $cuentaId)->orderBy('p.' . $t['detPk'])
            ->select('p.*', 'p.' . $t['detPk'] . ' as id', 'p.' . $t['estDet'] . ' as estado', 'u.apeusu as usuario')->get();
        $medios = DB::table($t['med'] . ' as m')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'm.med_pag_id')
            ->whereIn('m.' . $t['detPk'], $pagos->pluck('id'))
            ->select('m.' . $t['detPk'] . ' as pago', 'mp.nom_med_pag', 'm.monto')->get()->groupBy('pago');
        return $pagos->map(function ($p) use ($medios) {
            $p->medios = $medios[$p->id] ?? collect();
            return $p;
        });
    }

    public static function documento(string $tipo, int $docId): array
    {
        if ($tipo === 'cobrar') {
            $d = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $docId)->first(['serdoc', 'numdoc', 'ccanom']);
            return ['numero' => $d->serdoc . '-' . $d->numdoc, 'nombre' => $d->ccanom];
        }
        $d = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.com_cab_id', $docId)->first(['c.com_doc_ser', 'c.com_doc_num', 'p.prov_raz']);
        return ['numero' => $d->com_doc_ser . '-' . $d->com_doc_num, 'nombre' => $d->prov_raz];
    }

    private static function estado(float $abono, float $saldo): string
    {
        if ($saldo <= 0.001) return 'PAGADO';
        return $abono > 0 ? 'PARCIAL' : 'PENDIENTE';
    }
}
