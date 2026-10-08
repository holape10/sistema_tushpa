<?php

namespace App\Support;

use App\Http\Controllers\SucursalController;
use App\Models\EmpresaNegocio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea otra sucursal de la misma empresa (otra calle u otra ciudad): con sus propias series y correlativos,
 * su almacén, contado/crédito y medios de pago. Opcionalmente copia el catálogo de productos de otra sucursal
 * (categorías, productos, presentaciones, precios por horario y combos) sin stock: cada sucursal tiene el suyo.
 */
class SucursalNueva
{
    /**
     * Series libres sugeridas para la nueva sucursal: el siguiente número de cada tipo (F001 → F002, T001 → T002).
     *
     * @return array<string, string> columna de serie => serie sugerida
     */
    public static function seriesSugeridas(string $ruc): array
    {
        $existentes = EmpresaNegocio::where('IdEmpresa', $ruc)->get();
        $sugeridas = [];
        foreach (SucursalController::SERIES as $c) {
            $usadas = $existentes->pluck($c['serie'])->filter()->map(fn ($s) => strtoupper($s))->all();
            $base = $existentes->first()?->{$c['serie']} ?: 'X001';
            $prefijo = preg_replace('/\d+$/', '', strtoupper($base)) ?: substr($base, 0, 1);
            $digitos = 4 - strlen($prefijo);
            for ($n = 1; $n < 10 ** $digitos; $n++) {
                $serie = $prefijo.str_pad((string) $n, $digitos, '0', STR_PAD_LEFT);
                if (! in_array($serie, $usadas, true)) {
                    $sugeridas[$c['serie']] = $serie;
                    break;
                }
            }
        }

        return $sugeridas;
    }

    /**
     * @param  array<string, mixed>  $d  nombre_comercial, direccion, ubigeo, departamento, provincia, distrito, telefono, correo, codigofiscal + series
     */
    public static function crear(User $admin, array $d, ?int $copiarDe = null): EmpresaNegocio
    {
        return DB::transaction(function () use ($admin, $d, $copiarDe) {
            $ruc = $admin->IdEmpresa;
            $origen = $copiarDe ? EmpresaNegocio::where('IdEmpresa', $ruc)->where('id_empresa_negocio', $copiarDe)->first() : null;
            $modelo = $origen ?? EmpresaNegocio::where('IdEmpresa', $ruc)->orderBy('id_empresa_negocio')->first();

            $fila = collect($d)->only(['nombre_comercial', 'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito', 'telefono', 'correo', 'codigofiscal'])->all();
            foreach (SucursalController::SERIES as $c) {
                $fila[$c['serie']] = $d[$c['serie']];
                $fila[$c['numero']] = 0;
            }
            $sucursal = EmpresaNegocio::create($fila + [
                'IdEmpresa' => $ruc, 'tipo_negocio' => 'Sucursal - '.$ruc, 'estado' => 'Activo',
                // Misma forma de trabajar que la sucursal modelo
                'tip_igv_pred' => $modelo->tip_igv_pred ?? '10', 'tdocod_pred' => $modelo->tdocod_pred ?? '03',
                'formato_impresion' => $modelo->formato_impresion ?? 'TICKET', 'logo_suc' => $modelo->logo_suc ?? null,
            ]);
            $id = $sucursal->id_empresa_negocio;

            $almacen = DB::table('almacenes')->insertGetId([
                'descripcion' => 'ALMACEN '.mb_strtoupper(mb_substr($sucursal->nombre_comercial, 0, 40)), 'predeterminado' => 1,
                'id_empresa_negocio' => $id, 'direccion' => $sucursal->direccion, 'ubigeo' => $sucursal->ubigeo,
            ]);

            foreach ([['CONTADO', 'CONTADO'], ['CREDITO', 'PERSONALIZADO']] as [$nombre, $tipo]) {
                DB::table('credito_dias')->insert(['IdEmpresa' => $ruc, 'id_empresa_negocio' => $id, 'cre_dia_nom' => $nombre, 'cre_dia_fac' => 0, 'cre_dia_tip' => $tipo]);
            }

            // Medios de pago: los mismos de la sucursal modelo (o EFECTIVO)
            $medios = $modelo ? DB::table('medios_pagos')->where('id_empresa_negocio', $modelo->id_empresa_negocio)->get() : collect();
            if ($medios->isEmpty()) {
                DB::table('medios_pagos')->insert(['IdEmpresa' => $ruc, 'nom_med_pag' => 'EFECTIVO', 'predeterminado' => '1', 'id_empresa_negocio' => $id]);
            }
            foreach ($medios as $m) {
                $copia = (array) $m;
                unset($copia['id_med_pag']);
                DB::table('medios_pagos')->insert(['id_empresa_negocio' => $id] + $copia);
            }

            if ($origen) {
                self::copiarCatalogo($origen->id_empresa_negocio, $id, $almacen);
            } else {
                $tipo = DB::table('tipo_producto')->insertGetId(['tip_pro_nom' => 'GENERAL', 'IdEmpresa' => $ruc, 'id_empresa_negocio' => $id]);
                $cat = DB::table('categorias')->insertGetId(['IdEmpresa' => $ruc, 'color' => '#3f4aee', 'id_empresa_negocio' => $id,
                    'predeterminado' => 1, 'cat_nom' => 'GENERAL', 'tip_pro_id' => $tipo]);
                DB::table('subcategorias')->insert(['color' => '#3f4aee', 'id_empresa_negocio' => $id, 'subcat_nom' => 'GENERAL', 'cat_id' => $cat, 'IdEmpresa' => $ruc]);
            }

            return $sucursal;
        });
    }

    /** Copia tipos, categorías, subcategorías, productos (sin stock), presentaciones, precios por horario y combos */
    private static function copiarCatalogo(int $de, int $a, int $almacen): void
    {
        $copiar = function (string $tabla, string $pk, array $cambios = [], ?callable $filtro = null) use ($de, $a) {
            $mapa = [];
            DB::table($tabla)->where('id_empresa_negocio', $de)->when($filtro, $filtro)->orderBy($pk)->get()->each(function ($f) use ($tabla, $pk, $cambios, $a, &$mapa) {
                $fila = (array) $f;
                $viejo = $fila[$pk];
                unset($fila[$pk]);
                foreach ($cambios as $col => $fn) {
                    $fila[$col] = $fn($fila[$col] ?? null);
                }
                $mapa[$viejo] = DB::table($tabla)->insertGetId(['id_empresa_negocio' => $a] + $fila);
            });

            return $mapa;
        };

        $tipos = $copiar('tipo_producto', 'tip_pro_id');
        $cats = $copiar('categorias', 'cat_id', ['tip_pro_id' => fn ($v) => $tipos[$v] ?? $v, 'impresora' => fn () => null]);
        $subcats = $copiar('subcategorias', 'subcat_id', ['cat_id' => fn ($v) => $cats[$v] ?? $v]);
        $productos = $copiar('productos', 'IdProducto', [
            'cat_id' => fn ($v) => $cats[$v] ?? $v, 'subcat_id' => fn ($v) => $subcats[$v] ?? $v,
            'tip_pro_id' => fn ($v) => $tipos[$v] ?? $v, 'id_almacen' => fn () => $almacen,
            'created_at' => fn () => now(), 'updated_at' => fn () => now(),
        ], fn ($q) => $q->where('proest', 'Activo'));

        foreach (['producto_presentacion' => 'id_presentacion', 'producto_precio_dinamico' => 'id_precio_dinamico'] as $tabla => $pk) {
            DB::table($tabla)->whereIn('IdProducto', array_keys($productos))->get()->each(function ($f) use ($tabla, $pk, $productos) {
                $fila = (array) $f;
                unset($fila[$pk]);
                $fila['IdProducto'] = $productos[$fila['IdProducto']];
                DB::table($tabla)->insert($fila);
            });
        }
        DB::table('combos')->whereIn('IdProducto_rel', array_keys($productos))->get()->each(function ($c) use ($productos) {
            if (isset($productos[$c->IdProducto_comb])) {
                DB::table('combos')->insert(['IdProducto_rel' => $productos[$c->IdProducto_rel], 'IdProducto_comb' => $productos[$c->IdProducto_comb], 'prod_comb_cant' => $c->prod_comb_cant]);
            }
        });
    }
}
