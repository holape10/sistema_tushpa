<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El resumen de la caja en el momento del arqueo (ventas por medio de pago y comprobante, ingresos y egresos con su detalle) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arqueos_caja') && ! Schema::hasColumn('arqueos_caja', 'resumen')) {
            Schema::table('arqueos_caja', fn (Blueprint $t) => $t->longText('resumen')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('arqueos_caja', 'resumen')) {
            Schema::table('arqueos_caja', fn (Blueprint $t) => $t->dropColumn('resumen'));
        }
    }
};
