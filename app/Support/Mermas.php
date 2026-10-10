<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mermas: lo que se pierde sin venderse (se malogró, se quemó, se cayó...). Sale del almacén con su costo.
 * Un insumo o producto sale directo; un plato saca los insumos de su receta.
 */
class Mermas
{
    /** Motivos en palabras simples, con su ícono */
    public const MOTIVOS = [
        'MALOGRADO' => ['🗑️', 'Se malogró o venció'],
        'MAL PREPARADO' => ['🔥', 'Se quemó o salió mal'],
        'DERRAMADO' => ['💧', 'Se cayó o derramó'],
        'DEVOLUCION' => ['↩️', 'Lo devolvió el cliente'],
        'PERSONAL' => ['🍽️', 'Consumo del personal'],
        'OTRO' => ['❓', 'Otro motivo'],
    ];

    /** Unidades para escribir la merma: un plato va por porción, un insumo en su unidad o su equivalente (g, ml) */
    public static function unidades(Producto $p): array
    {
        if ((int) $p->promocion === 2 || (int) $p->promocion === 6) {
            return [['ume' => 'NIU', 'nombre' => 'porción', 'factor' => 1]];
        }

        return collect(Recetas::unidadesPara($p->umecod ?: 'NIU'))
            ->map(fn ($f, $u) => ['ume' => $u, 'nombre' => Recetas::nombreUnidad($u), 'factor' => $f])->values()->all();
    }

    /** Costo de una unidad (de la unidad del producto): el plato vale lo que cuesta su receta */
    public static function costoUnitario(Producto $p): float
    {
        if ((int) $p->promocion === 2) {
            return Recetas::costos([$p->IdProducto])[$p->IdProducto] ?? (float) $p->costo;
        }

        return (float) $p->costo;
    }

    /**
     * Registra la merma y saca del almacén lo perdido.
     *
     * @param  array{cantidad: float, umecod: string, motivo: string, observacion: ?string}  $d
     */
    public static function registrar(User $user, Producto $producto, array $d): int
    {
        return DB::transaction(function () use ($user, $producto, $d) {
            $almacen = Kardex::almacenPredeterminado($user->id_empresa_negocio);
            if (! $almacen) {
                throw new \RuntimeException('La sucursal no tiene almacén.');
            }
            $factor = collect(self::unidades($producto))->firstWhere('ume', $d['umecod'])['factor'] ?? null;
            if ($factor === null) {
                throw new \RuntimeException('Unidad no válida para '.$producto->pronom.'.');
            }
            $base = round((float) $d['cantidad'] * $factor, 4);

            $id = DB::table('mermas')->insertGetId([
                'IdProducto' => $producto->IdProducto, 'cantidad' => $d['cantidad'], 'umecod' => $d['umecod'], 'cantidad_base' => $base,
                'motivo' => $d['motivo'], 'observacion' => $d['observacion'] ?? null,
                'costo' => round($base * self::costoUnitario($producto), 2),
                'id_almacen' => $almacen->id_almacen, 'IdUsuario' => $user->IdUsuario, 'fecha' => now()->toDateString(),
                'estado' => 'ACTIVA', 'id_empresa_negocio' => $user->id_empresa_negocio, 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Operación 13 de SUNAT (mermas). El plato saca sus insumos; el combo, sus productos
            $doc = ['cod_tip_ope' => '13', 'merma_id' => $id, 'descripcion' => 'MERMA: '.self::MOTIVOS[$d['motivo']][1], 'costo' => $producto->costo];
            in_array((int) $producto->promocion, [0, 4], true)
                ? Kardex::registrar($producto->IdProducto, $almacen->id_almacen, $base, 'E', $doc)
                : Kardex::salidaPorVenta($producto, $almacen->id_almacen, $base, $doc);

            return $id;
        });
    }

    public static function anular(int $mermaId, int $sucursal): void
    {
        DB::transaction(function () use ($mermaId, $sucursal) {
            $merma = DB::table('mermas')->where('merma_id', $mermaId)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
            if (! $merma || $merma->estado !== 'ACTIVA') {
                throw new \RuntimeException('Esa merma ya fue anulada.');
            }
            Kardex::revertirMerma($mermaId);
            DB::table('mermas')->where('merma_id', $mermaId)->update(['estado' => 'ANULADA', 'updated_at' => now()]);
        });
    }
}
