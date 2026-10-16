<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro contable de ventas y compras (solo montos, como el CONCAR): cada comprobante genera su asiento.
 * Para estudios contables o empresas que no venden con el sistema. Menú: Registro de Ventas y Registro de Compras.
 */
return new class extends Migration
{
    private const MODULOS = [
        '/contabilidad/registro/ventas' => 'Registro de Ventas (contable)',
        '/contabilidad/registro/compras' => 'Registro de Compras (contable)',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('conta_registros')) {
            Schema::create('conta_registros', function (Blueprint $t) {
                $t->increments('id');
                $t->string('IdEmpresa', 11);
                $t->string('tipo', 6);                       // VENTA | COMPRA
                $t->char('periodo', 6);
                $t->date('fecha');
                $t->date('fecha_vence')->nullable();
                $t->string('tdocod', 2);                     // 01 factura, 03 boleta, 07 NC, 08 ND, 02 recibo por honorarios, 14 servicios públicos…
                $t->string('serie', 20)->nullable();
                $t->string('numero', 20);
                $t->string('doc_tipo', 1)->default('6');     // 6 RUC, 1 DNI, 0 sin documento
                $t->string('doc_num', 15)->nullable();
                $t->string('nombre', 200);
                $t->char('moneda', 3)->default('PEN');
                $t->decimal('tipo_cambio', 8, 3)->default(1);
                $t->decimal('base', 14, 2)->default(0);      // base imponible (gravada)
                $t->decimal('igv', 14, 2)->default(0);
                $t->decimal('no_gravado', 14, 2)->default(0); // exonerado / inafecto
                $t->decimal('otros', 14, 2)->default(0);      // ICBPER, otros tributos y cargos
                $t->decimal('total', 14, 2)->default(0);
                $t->string('cuenta', 12);                    // 70… ventas / 60…, 63… compras y gastos
                $t->string('condicion', 8)->default('CREDITO'); // CONTADO | CREDITO
                $t->string('cuenta_pago', 12)->nullable();   // caja o banco (contado)
                $t->string('glosa', 200)->nullable();
                $t->boolean('asiento_editado')->default(false);
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
                $t->index(['IdEmpresa', 'tipo', 'periodo']);
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $diario = DB::table('modulos')->where('mod_url', '/contabilidad/diario')->value('mod_id');
        $conDiario = $diario ? DB::table('modulos_usuario')->where('mod_id', $diario)->pluck('user_IdUsuario')->unique() : collect();
        foreach (self::MODULOS as $url => $nombre) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => 'Contabilidad']);
            foreach ($conDiario as $usuario) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', array_keys(self::MODULOS))->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        Schema::dropIfExists('conta_registros');
    }
};
