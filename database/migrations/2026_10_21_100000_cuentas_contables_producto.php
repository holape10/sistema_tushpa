<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta contable por producto (como el sistema antiguo: productos.debe / productos.haber).
 *  - Al vender se copian a cada línea (cpe_detalle.debe / haber): cambiar la cuenta después no altera lo ya vendido.
 *  - CONCAR arma el asiento por cuenta; las notas de crédito van al revés.
 *  - concar_config: formato del Excel ("PLANTILLA" oficial de 3 títulos o "ANTERIOR" como el sistema antiguo)
 *    y los códigos de tipo de documento que usa la tabla 06 del CONCAR de cada empresa.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['productos', 'cpe_detalle'] as $tabla) {
            if (!Schema::hasColumn($tabla, 'debe')) {
                Schema::table($tabla, function (Blueprint $t) {
                    $t->string('debe', 12)->nullable();
                    $t->string('haber', 12)->nullable();
                });
            }
        }

        if (Schema::hasTable('concar_config') && !Schema::hasColumn('concar_config', 'formato')) {
            Schema::table('concar_config', function (Blueprint $t) {
                $t->string('formato', 10)->default('PLANTILLA');
                $t->string('doc_factura', 2)->default('FT');
                $t->string('doc_boleta', 2)->default('BV');
                $t->string('doc_nc', 2)->default('NA');
                $t->string('doc_nd', 2)->default('ND');
            });
        }
    }

    public function down(): void
    {
        foreach (['productos', 'cpe_detalle'] as $tabla) {
            Schema::table($tabla, fn(Blueprint $t) => $t->dropColumn(['debe', 'haber']));
        }
        if (Schema::hasColumn('concar_config', 'formato')) {
            Schema::table('concar_config', fn(Blueprint $t) => $t->dropColumn(['formato', 'doc_factura', 'doc_boleta', 'doc_nc', 'doc_nd']));
        }
    }
};
