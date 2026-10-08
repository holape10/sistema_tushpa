<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del trabajador (se carga en Usuarios y se muestra en el kiosko de asistencia).
 * Comunicación de baja: el resumen (RA o RC con estado 3) y el comprobante que da de baja quedan enlazados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empleado', 'emp_foto')) {
            Schema::table('empleado', fn (Blueprint $t) => $t->string('emp_foto')->nullable());
        }
        if (! Schema::hasColumn('resumenes', 'es_baja')) {
            Schema::table('resumenes', fn (Blueprint $t) => $t->tinyInteger('es_baja')->default(0));
        }
        if (! Schema::hasColumn('cpe_cabecera', 'res_id_baja')) {
            Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->unsignedInteger('res_id_baja')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('empleado', fn (Blueprint $t) => $t->dropColumn('emp_foto'));
        Schema::table('resumenes', fn (Blueprint $t) => $t->dropColumn('es_baja'));
        Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->dropColumn('res_id_baja'));
    }
};
