<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios de producto: "cambio sí, devolución no". El cliente devuelve productos de una venta y se lleva otros de igual
 * o mayor valor:
 *  - la venta original no se toca (ni SUNAT ni notas);
 *  - lo devuelto entra al stock y lo que se lleva sale (kardex, con la referencia del cambio);
 *  - si lo nuevo vale más, la diferencia se cobra como una venta normal ("DIFERENCIA POR CAMBIO"), con su comprobante y
 *    a la caja del turno.
 * Lo devuelto en un cambio cuenta para las notas de crédito (no se devuelve dos veces) y la venta ya no se anula completa.
 */
class Cambios
{
    /** Hasta cuántos días después de la venta se acepta un cambio */
    public const DIAS = 30;

    public static function disponible(): bool
    {
        return Schema::hasTable('cambios');
    }

    /** "CB-000003" */
    public static function codigo(object $c): string
    {
        return 'CB-'.str_pad((string) $c->numero, 6, '0', STR_PAD_LEFT);
    }

    /** ¿La venta tuvo algún cambio? (entonces ya no se anula completa) */
    public static function tiene(int $cabId): bool
    {
        return self::disponible() && DB::table('cambios')->where('IdCpe_cabecera', $cabId)->exists();
    }

    /** Lo ya devuelto en cambios, por línea de la venta: [IdCpe_detalle => cantidad] */
    public static function devuelto(int $cabId): array
    {
        if (! self::disponible()) {
            return [];
        }

        return DB::table('cambios_detalle as d')->join('cambios as c', 'c.id', '=', 'd.cambio_id')
            ->where('c.IdCpe_cabecera', $cabId)->where('d.tipo', 'DEVUELVE')->whereNotNull('d.IdCpe_detalle')
            ->groupBy('d.IdCpe_detalle')->selectRaw('d.IdCpe_detalle, SUM(d.cantidad) as cant')->pluck('cant', 'IdCpe_detalle')
            ->map(fn ($v) => (float) $v)->all();
    }

    /**
     * La venta para cambiar: sus líneas con lo que aún se puede devolver (menos notas de crédito y cambios anteriores).
     *
     * @return array{venta: object, lineas: Collection<int, object>}
     */
    public static function venta(User $user, int $cabId): array
    {
        $v = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $v || ! in_array($v->tdocod, ['01', '03', '13'], true)) {
            throw new \RuntimeException('Esa venta no existe.');
        }
        if ($v->ccabaj || $v->anulado_nc) {
            throw new \RuntimeException('Esa venta está anulada.');
        }
        if (substr((string) $v->ccafem, 0, 10) < now()->subDays(self::DIAS)->toDateString()) {
            throw new \RuntimeException('La venta tiene más de '.self::DIAS.' días: ya no se aceptan cambios.');
        }
        $notas = in_array($v->tdocod, ['01', '03'], true) ? Notas::usado($cabId)['cantidades'] : [];
        $cambios = self::devuelto($cabId);
        $lineas = DB::table('cpe_detalle')->where('IdCpe_cabecera', $cabId)->whereNotNull('IdProducto')->where('cortesia', 0)->orderBy('IdCpe_detalle')
            ->get(['IdCpe_detalle', 'IdProducto', 'cdedes', 'cdecan', 'cdepuni', 'descuento', 'cpe_det_factor'])
            ->map(function ($l) use ($notas, $cambios) {
                $l->vendido = (float) $l->cdecan;
                $l->queda = round($l->vendido - ($notas[$l->IdCpe_detalle] ?? 0) - ($cambios[$l->IdCpe_detalle] ?? 0), 3);
                // Lo que de verdad pagó por unidad (con el descuento de la línea)
                $l->precio = round((float) $l->cdepuni - ($l->vendido > 0 ? (float) $l->descuento / $l->vendido : 0), 2);

                return $l;
            });

        return ['venta' => $v, 'lineas' => $lineas];
    }

    /**
     * Hace el cambio. Devuelve su id.
     *
     * @param  list<array{linea: int, cantidad: float}>  $devuelve  líneas de la venta y cuánto devuelve
     * @param  list<array{id: int, cantidad: float}>  $lleva  productos que se lleva (a su precio de lista)
     * @param  array{medio?: ?int, motivo?: ?string}  $pago  el medio con que paga la diferencia
     */
    public static function hacer(User $user, int $cabId, array $devuelve, array $lleva, array $pago = []): int
    {
        return DB::transaction(function () use ($user, $cabId, $devuelve, $lleva, $pago) {
            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->lockForUpdate()->first();
            ['venta' => $v, 'lineas' => $lineas] = self::venta($user, $cabId);
            $lineas = $lineas->keyBy('IdCpe_detalle');

            $devueltos = [];
            foreach ($devuelve as $d) {
                $cant = round((float) $d['cantidad'], 3);
                if ($cant <= 0) {
                    continue;
                }
                $l = $lineas[(int) $d['linea']] ?? null;
                if (! $l) {
                    throw new \RuntimeException('Una línea no es de esa venta.');
                }
                if ($cant > $l->queda + 0.0001) {
                    throw new \RuntimeException("De {$l->cdedes} solo quedan ".ControlStock::numero(max($l->queda, 0)).' por cambiar.');
                }
                $devueltos[] = ['linea' => $l, 'cantidad' => $cant, 'total' => round($cant * $l->precio, 2)];
            }
            if (! $devueltos) {
                throw new \RuntimeException('Elige qué producto devuelve el cliente.');
            }

            $productos = Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->where('proest', 'Activo')
                ->whereNotIn('promocion', Producto::NO_VENDIBLES)->whereIn('IdProducto', array_column($lleva, 'id'))->get()->keyBy('IdProducto');
            $nuevos = [];
            foreach ($lleva as $n) {
                $cant = round((float) $n['cantidad'], 3);
                $p = $productos[(int) $n['id']] ?? null;
                if ($cant <= 0) {
                    continue;
                }
                if (! $p) {
                    throw new \RuntimeException('Un producto que se lleva no es de esta sucursal.');
                }
                $nuevos[] = ['producto' => $p, 'cantidad' => $cant, 'precio' => round((float) $p->propun, 2), 'total' => round($cant * (float) $p->propun, 2)];
            }
            if (! $nuevos) {
                throw new \RuntimeException('Elige el producto que se lleva a cambio.');
            }

            $devuelto = round(array_sum(array_column($devueltos, 'total')), 2);
            $llevaTotal = round(array_sum(array_column($nuevos, 'total')), 2);
            $diferencia = round($llevaTotal - $devuelto, 2);
            if ($diferencia < -0.009) {
                throw new \RuntimeException('Lo que se lleva (S/ '.number_format($llevaTotal, 2).') vale menos que lo que devuelve (S/ '.number_format($devuelto, 2)
                    .'). En un cambio no se devuelve dinero: que elija algo de igual o mayor valor.');
            }

            $almacen = Kardex::almacenPredeterminado((int) $user->id_empresa_negocio);
            if (! $almacen) {
                throw new \RuntimeException('La sucursal no tiene almacén.');
            }
            $numero = (int) DB::table('cambios')->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->max('numero') + 1;
            $id = DB::table('cambios')->insertGetId([
                'numero' => $numero, 'id_empresa_negocio' => $user->id_empresa_negocio, 'IdCpe_cabecera' => $cabId, 'id_almacen' => $almacen->id_almacen,
                'devuelto' => $devuelto, 'lleva' => $llevaTotal, 'diferencia' => max($diferencia, 0),
                'motivo' => ($m = trim((string) ($pago['motivo'] ?? ''))) !== '' ? mb_substr(mb_strtoupper($m), 0, 150) : null,
                'IdUsuario' => $user->IdUsuario, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $codigo = 'CB-'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
            $doc = ['cod_tip_ope' => '99', 'cliente' => $v->ccanom, 'descripcion' => mb_substr("CAMBIO {$codigo} DE {$v->serdoc}-{$v->numdoc}", 0, 100)];

            // Lo devuelto vuelve al stock (en la unidad base: la línea pudo ser por presentación)
            foreach ($devueltos as $d) {
                $l = $d['linea'];
                Kardex::registrar((int) $l->IdProducto, (int) $almacen->id_almacen, round($d['cantidad'] * ((float) $l->cpe_det_factor ?: 1), 3), 'I', $doc);
                DB::table('cambios_detalle')->insert(['cambio_id' => $id, 'tipo' => 'DEVUELVE', 'IdProducto' => $l->IdProducto, 'IdCpe_detalle' => $l->IdCpe_detalle,
                    'descripcion' => mb_substr($l->cdedes, 0, 150), 'cantidad' => $d['cantidad'], 'precio' => $l->precio, 'total' => $d['total']]);
            }
            // Lo que se lleva sale del stock (con el control de stock de la sucursal)
            foreach ($nuevos as $n) {
                if (in_array((int) $n['producto']->promocion, Compras::TIPOS_CON_STOCK, true)) {
                    Kardex::registrar((int) $n['producto']->IdProducto, (int) $almacen->id_almacen, $n['cantidad'], 'E',
                        $doc + ControlStock::banderas((int) $user->id_empresa_negocio) + ['costo' => (float) $n['producto']->costo]);
                }
                DB::table('cambios_detalle')->insert(['cambio_id' => $id, 'tipo' => 'LLEVA', 'IdProducto' => $n['producto']->IdProducto, 'IdCpe_detalle' => null,
                    'descripcion' => mb_substr($n['producto']->pronom, 0, 150), 'cantidad' => $n['cantidad'], 'precio' => $n['precio'], 'total' => $n['total']]);
            }

            // La diferencia se cobra como una venta normal, con su comprobante, al mismo cliente
            if ($diferencia > 0.009) {
                $medio = (int) ($pago['medio'] ?? 0) ?: (int) DB::table('medios_pagos')->where('id_empresa_negocio', $user->id_empresa_negocio)
                    ->orderByDesc('predeterminado')->value('id_med_pag');
                $tdocod = in_array($v->tdocod, ['01', '03'], true) ? $v->tdocod : '13';
                $contado = (int) DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
                $cabDif = VentaDirecta::registrar($user, [
                    'tdocod' => $tdocod, 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
                    'tdicod' => (string) $v->tdicod, 'clinum' => (string) $v->ccandi, 'clinom' => (string) $v->ccanom, 'clidir' => (string) ($v->direccion ?? ''),
                    'observaciones' => mb_substr("Diferencia del cambio {$codigo} ({$v->serdoc}-{$v->numdoc})", 0, 100),
                    'items' => [['id' => self::productoDiferencia($user)->IdProducto, 'cantidad' => 1, 'precio' => $diferencia,
                        'descripcion' => mb_substr('DIFERENCIA POR CAMBIO '.$codigo.': '.implode(', ', array_map(fn ($n) => $n['producto']->pronom, $nuevos)), 0, 150)]],
                    'id_med_pag' => [$medio], 'mon_med_pag' => [$diferencia], 'paga' => $diferencia,
                ], 'PV');
                DB::table('cambios')->where('id', $id)->update(['IdCpe_diferencia' => $cabDif]);
            }

            Bitacora::registrar('venta.cambio', "Cambio {$codigo} de {$v->serdoc}-{$v->numdoc}: devuelve S/ ".number_format($devuelto, 2)
                .', lleva S/ '.number_format($llevaTotal, 2).($diferencia > 0.009 ? ', cobró la diferencia S/ '.number_format($diferencia, 2) : ''), null,
                ['referencia' => ['venta', $cabId]]);

            return $id;
        });
    }

    /** Servicio con el que se cobra la diferencia de un cambio (sin stock). Se crea la primera vez. */
    public static function productoDiferencia(User $user): object
    {
        $suc = (int) $user->id_empresa_negocio;
        $prod = DB::table('productos')->where('id_empresa_negocio', $suc)->where('procod', 'DIF-CAMBIO')->first();
        if ($prod) {
            return $prod;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->orderBy('cat_id')->value('cat_id');
        $id = Producto::create(['pronom' => 'DIFERENCIA POR CAMBIO', 'procod' => 'DIF-CAMBIO', 'propun' => 0, 'umecod' => 'ZZ', 'costo' => 0,
            'promocion' => Producto::SERVICIO, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa,
            'id_empresa_negocio' => $suc])->IdProducto;

        return DB::table('productos')->where('IdProducto', $id)->first();
    }

    /** Un cambio con su detalle (para verlo e imprimirlo) */
    public static function ver(User $user, int $id): object
    {
        $c = DB::table('cambios as c')->join('cpe_cabecera as v', 'v.IdCpe_cabecera', '=', 'c.IdCpe_cabecera')
            ->leftJoin('cpe_cabecera as d', 'd.IdCpe_cabecera', '=', 'c.IdCpe_diferencia')
            ->where('c.id', $id)->where('c.id_empresa_negocio', $user->id_empresa_negocio)
            ->first(['c.*', 'v.serdoc', 'v.numdoc', 'v.ccanom', 'v.ccandi', 'd.serdoc as dif_serie', 'd.numdoc as dif_numero']);
        if (! $c) {
            throw new \RuntimeException('Ese cambio no existe.');
        }
        $c->codigo = self::codigo($c);
        $c->detalle = DB::table('cambios_detalle')->where('cambio_id', $id)->orderBy('id')->get();

        return $c;
    }
}
