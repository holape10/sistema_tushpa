<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fidelización por sucursal (empresa_negocios.fid_activo): el cliente con DNI o RUC gana puntos con cada compra
 * (1 punto por cada fid_soles_por_punto soles, desde fid_compra_minima) y los canjea por premios.
 * Si la venta se anula o tiene nota de crédito total, sus puntos se descuentan.
 */
class Fidelizacion
{
    /** Comprobantes que suman puntos (las notas de crédito/débito no) */
    private const TIPOS = ['01', '03', '13'];

    public static function config(int $suc): ?object
    {
        $c = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['fid_activo', 'fid_soles_por_punto', 'fid_compra_minima']);

        return $c && (int) $c->fid_activo === 1 && (float) $c->fid_soles_por_punto > 0 ? $c : null;
    }

    /** Puntos que da un monto con la regla de la sucursal */
    public static function puntosPor(float $total, object $cfg): int
    {
        if ($total < (float) $cfg->fid_compra_minima) {
            return 0;
        }

        return (int) floor(round($total / (float) $cfg->fid_soles_por_punto, 4));
    }

    /** Suma o resta puntos al cliente y deja el movimiento (con bloqueo, para que dos cajas no pisen el saldo) */
    private static function mover(int $clicod, int $suc, string $tipo, int $puntos, array $extra = []): int
    {
        return DB::transaction(function () use ($clicod, $suc, $tipo, $puntos, $extra) {
            $actual = (int) DB::table('cliente')->where('clicod', $clicod)->lockForUpdate()->value('puntos');
            $saldo = $actual + $puntos;
            if ($saldo < 0 && $tipo === 'CANJE') {
                throw new \RuntimeException("El cliente tiene {$actual} puntos; no le alcanza.");
            }
            DB::table('cliente')->where('clicod', $clicod)->update(['puntos' => $saldo]);
            DB::table('fid_movimientos')->insert($extra + [
                'clicod' => $clicod, 'tipo' => $tipo, 'puntos' => $puntos, 'saldo' => $saldo,
                'fecha' => now(), 'id_empresa_negocio' => $suc,
            ]);

            return $saldo;
        });
    }

    /** Al emitir un comprobante: suma los puntos de la compra (si la sucursal tiene fidelización y el cliente tiene DNI o RUC) */
    public static function acumular(int $cabId): void
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccandi', 'clicod', 'ccaitv', 'id_empresa_negocio', 'IdUsuario']);
        if (! $cab || ! in_array($cab->tdocod, self::TIPOS, true) || ! $cab->clicod || ! preg_match('/^(\d{8}|\d{11})$/', (string) $cab->ccandi) || $cab->ccandi === '00000000') {
            return;
        }
        $cfg = self::config((int) $cab->id_empresa_negocio);
        $puntos = $cfg ? self::puntosPor((float) $cab->ccaitv, $cfg) : 0;
        if ($puntos <= 0 || DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'VENTA')->exists()) {
            return;
        }
        self::mover((int) $cab->clicod, (int) $cab->id_empresa_negocio, 'VENTA', $puntos, [
            'IdCpe_cabecera' => $cabId, 'IdUsuario' => $cab->IdUsuario, 'detalle' => 'Compra '.$cab->serdoc.'-'.$cab->numdoc,
        ]);
    }

    /** Venta anulada o con nota de crédito total: se descuentan los puntos que dio (una sola vez) */
    public static function revertir(int $cabId): void
    {
        $ganados = DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'VENTA')->first();
        if (! $ganados || DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'ANULACION')->exists()) {
            return;
        }
        self::mover((int) $ganados->clicod, (int) $ganados->id_empresa_negocio, 'ANULACION', -((int) $ganados->puntos), [
            'IdCpe_cabecera' => $cabId, 'detalle' => 'Anulación: '.str_replace('Compra ', '', (string) $ganados->detalle),
        ]);
    }

    /** Premios que hoy se pueden canjear (activos y sin vencer) */
    public static function premiosVigentes(int $suc)
    {
        return DB::table('fid_premios')->where('id_empresa_negocio', $suc)->where('activo', 1)
            ->where(fn ($q) => $q->whereNull('vence')->orWhere('vence', '>=', now()->toDateString()))->orderBy('puntos');
    }

    /**
     * Canje de un premio. Si el premio es un producto del catálogo, sale del almacén predeterminado
     * (kardex con operación 08 "Premio"); los combos descuentan sus componentes.
     */
    public static function canjear(User $user, int $clicod, int $premioId): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $premio = DB::table('fid_premios')->where('premio_id', $premioId)->where('id_empresa_negocio', $suc)->where('activo', 1)->first();
        if (! $premio) {
            throw new \RuntimeException('Premio no encontrado.');
        }
        if ($premio->vence && $premio->vence < now()->toDateString()) {
            throw new \RuntimeException('Ese premio venció el '.Carbon::parse($premio->vence)->format('d/m/Y').'.');
        }

        return DB::transaction(function () use ($user, $suc, $clicod, $premio) {
            $saldo = self::mover($clicod, $suc, 'CANJE', -((int) $premio->puntos), [
                'premio_id' => $premio->premio_id, 'IdUsuario' => $user->IdUsuario, 'detalle' => 'Canje: '.$premio->nombre,
                'cantidad' => $premio->IdProducto ? $premio->cantidad : null,
            ]);
            if ($premio->IdProducto && ($producto = Producto::where('IdProducto', $premio->IdProducto)->where('id_empresa_negocio', $suc)->first())) {
                $almacen = Kardex::almacenPredeterminado($suc);
                if (! $almacen) {
                    throw new \RuntimeException('La sucursal no tiene almacén predeterminado para sacar el premio.');
                }
                $cliente = DB::table('cliente')->where('clicod', $clicod)->value('clinom');
                Kardex::salidaPorVenta($producto, (int) $almacen->id_almacen, (float) $premio->cantidad, [
                    'cod_tip_ope' => '08', 'cliente' => $cliente, 'precio' => 0, 'fecha_mov' => now()->toDateString(),
                    'descripcion' => mb_substr('CANJE DE PUNTOS: '.$premio->nombre, 0, 150), 'numero' => 'CANJE',
                ]);
            }

            return $saldo;
        });
    }

    /** Ajuste manual del administrador (+ o -) con su motivo */
    public static function ajustar(User $user, int $clicod, int $puntos, string $motivo): int
    {
        $actual = (int) DB::table('cliente')->where('clicod', $clicod)->value('puntos');
        if ($actual + $puntos < 0) {
            throw new \RuntimeException("El cliente tiene {$actual} puntos; no puede quedar en negativo.");
        }

        return self::mover($clicod, (int) $user->id_empresa_negocio, 'AJUSTE', $puntos, [
            'IdUsuario' => $user->IdUsuario, 'detalle' => mb_substr('Ajuste: '.trim($motivo), 0, 200),
        ]);
    }

    /**
     * Lo que se muestra al terminar la venta: puntos ganados, saldo y el premio más cercano.
     *
     * @return array{cliente: string, ganados: int, saldo: int, mensaje: string}|null
     */
    public static function resumen(int $cabId): ?array
    {
        $m = DB::table('fid_movimientos as m')->join('cliente as c', 'c.clicod', '=', 'm.clicod')
            ->where('m.IdCpe_cabecera', $cabId)->where('m.tipo', 'VENTA')->first(['m.puntos', 'm.id_empresa_negocio', 'c.clicod', 'c.clinom', 'c.puntos as saldo']);
        if (! $m) {
            return null;
        }
        $saldo = (int) $m->saldo;
        $premios = self::premiosVigentes((int) $m->id_empresa_negocio)->get(['nombre', 'puntos']);
        $alcanza = $premios->where('puntos', '<=', $saldo)->last();
        $siguiente = $premios->firstWhere('puntos', '>', $saldo);
        $mensaje = "Ganó {$m->puntos} ".($m->puntos == 1 ? 'punto' : 'puntos')." · ahora tiene {$saldo}.";
        if ($alcanza) {
            $mensaje .= " ¡Ya puede canjear: {$alcanza->nombre}!";
        } elseif ($siguiente) {
            $mensaje .= ' Le faltan '.($siguiente->puntos - $saldo)." para: {$siguiente->nombre}.";
        }

        return ['cliente' => $m->clinom, 'ganados' => (int) $m->puntos, 'saldo' => $saldo, 'mensaje' => $mensaje];
    }
}
