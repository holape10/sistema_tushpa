<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varias PCs con agente en una sucursal (una caja por PC):
 *  - impresion_agentes: qué PCs tienen el agente conectado. Una impresora "CAJA02/CPE2" la imprime el agente de CAJA02.
 *  - empresa_negocios.token_impresion_cifrado: la clave se guarda cifrada para que el instalador de otra PC use la
 *    misma (antes cada descarga generaba una nueva y la PC anterior dejaba de imprimir).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('impresion_agentes')) {
            Schema::create('impresion_agentes', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->string('pc', 60);
                $t->dateTime('contacto');
                $t->unique(['id_empresa_negocio', 'pc']);
            });
        }
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'token_impresion_cifrado')) {
                $t->text('token_impresion_cifrado')->nullable();
            }
        });
        // El N° de operación se busca al cobrar (no se puede repetir)
        if (! collect(Schema::getIndexes('venta_medio_pago'))->contains(fn ($i) => $i['columns'] === ['num_operacion'])) {
            Schema::table('venta_medio_pago', fn (Blueprint $t) => $t->index('num_operacion'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impresion_agentes');
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('token_impresion_cifrado'));
    }
};
