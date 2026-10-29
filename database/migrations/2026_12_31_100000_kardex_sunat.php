<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex oficial de SUNAT (Registro de Inventario Permanente 12.1 en unidades físicas y 13.1 valorizado, con su archivo
 * del PLE): cada producto lleva su código de existencia (UNSPSC de 8 dígitos o ítem CUBSO de 16) y su tipo de existencia
 * (tabla 5), y el menú "Kardex SUNAT (PLE)" en Almacén para quien ya ve el Kardex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'unspsc')) {
                $t->string('unspsc', 16)->nullable();          // UNSPSC (8 dígitos) o ítem del CUBSO del OECE (16 dígitos)
                $t->char('tipo_existencia', 2)->nullable();    // tabla 5 (vacío = según el tipo de producto)
            }
        });

        // Tabla 12 completa (tipo de operación), para que todo movimiento tenga su nombre oficial
        if (Schema::hasTable('tipo_operacion_sunat')) {
            $tabla12 = ['01' => 'VENTA NACIONAL', '02' => 'COMPRA NACIONAL', '03' => 'CONSIGNACIÓN RECIBIDA', '04' => 'CONSIGNACIÓN ENTREGADA',
                '05' => 'DEVOLUCIÓN RECIBIDA', '06' => 'DEVOLUCIÓN ENTREGADA', '07' => 'BONIFICACIÓN', '08' => 'PREMIO', '09' => 'DONACIÓN',
                '10' => 'SALIDA A PRODUCCIÓN', '11' => 'SALIDA POR TRANSFERENCIA ENTRE ALMACENES', '12' => 'RETIRO', '13' => 'MERMAS', '14' => 'DESMEDROS',
                '15' => 'DESTRUCCIÓN', '16' => 'SALDO INICIAL', '17' => 'EXPORTACIÓN', '18' => 'IMPORTACIÓN', '19' => 'ENTRADA DE PRODUCCIÓN',
                '20' => 'ENTRADA POR DEVOLUCIÓN DE PRODUCCIÓN', '21' => 'ENTRADA POR TRANSFERENCIA ENTRE ALMACENES', '22' => 'ENTRADA POR IDENTIFICACIÓN ERRÓNEA',
                '23' => 'SALIDA POR IDENTIFICACIÓN ERRÓNEA', '24' => 'ENTRADA POR DEVOLUCIÓN DEL CLIENTE', '25' => 'SALIDA POR DEVOLUCIÓN AL PROVEEDOR',
                '26' => 'ENTRADA PARA SERVICIO DE PRODUCCIÓN', '27' => 'SALIDA POR SERVICIO DE PRODUCCIÓN', '28' => 'AJUSTE POR DIFERENCIA DE INVENTARIO',
                '29' => 'ENTRADA DE BIENES EN PRÉSTAMO', '30' => 'SALIDA DE BIENES EN PRÉSTAMO', '31' => 'ENTRADA DE BIENES EN CUSTODIA',
                '32' => 'SALIDA DE BIENES EN CUSTODIA', '33' => 'MUESTRAS MÉDICAS', '34' => 'PUBLICIDAD', '35' => 'GASTOS DE REPRESENTACIÓN',
                '36' => 'RETIRO PARA ENTREGA A TRABAJADORES', '37' => 'RETIRO POR CONVENIO COLECTIVO', '38' => 'RETIRO POR SUSTITUCIÓN DE BIEN SINIESTRADO', '99' => 'OTROS'];
            DB::table('tipo_operacion_sunat')->insertOrIgnore(collect($tabla12)->map(fn ($des, $cod) => ['cod_tip_ope' => $cod, 'des_tip_ope' => $des])->values()->all());
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $mod = DB::table('modulos')->where('mod_url', '/kardex/sunat')->value('mod_id')
            ?? DB::table('modulos')->insertGetId(['mod_nom' => 'Kardex SUNAT (PLE)', 'mod_url' => '/kardex/sunat', 'mod_gen' => 'Almacén']);
        $kardex = DB::table('modulos')->where('mod_url', '/kardex')->value('mod_id');
        foreach (DB::table('modulos_usuario')->where('mod_id', $kardex)->pluck('user_IdUsuario') as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/kardex/sunat')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
        DB::table('modulos')->where('mod_id', $mod)->delete();
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['unspsc', 'tipo_existencia']));
    }
};
