<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de costo en las compras del SIRE: las cuentas de gasto 62, 63, 64, 65, 67 y 68 lo llevan obligatorio
 * (94 gastos de administración, 95 de ventas, 97 financieros). Por comprobante (Cta. cargo del .dbf) y por línea del
 * XML (CONCAR con detalle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sire_ajustes') && ! Schema::hasColumn('sire_ajustes', 'ccosto')) {
            Schema::table('sire_ajustes', fn (Blueprint $t) => $t->string('ccosto', 6)->nullable());
        }
        if (Schema::hasTable('compras_xml_lineas') && ! Schema::hasColumn('compras_xml_lineas', 'centro_costo')) {
            Schema::table('compras_xml_lineas', fn (Blueprint $t) => $t->string('centro_costo', 6)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sire_ajustes', 'ccosto')) {
            Schema::table('sire_ajustes', fn (Blueprint $t) => $t->dropColumn('ccosto'));
        }
        if (Schema::hasColumn('compras_xml_lineas', 'centro_costo')) {
            Schema::table('compras_xml_lineas', fn (Blueprint $t) => $t->dropColumn('centro_costo'));
        }
    }
};
