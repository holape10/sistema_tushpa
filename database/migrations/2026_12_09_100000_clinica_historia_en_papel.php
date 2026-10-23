<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clínica: historias en papel de antes del sistema. Se suben como archivos de tipo PAPEL y guardan la fecha que
 * tiene la hoja (no la del día en que se subió), para leerlas en orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinica_archivos') && ! Schema::hasColumn('clinica_archivos', 'fecha_doc')) {
            Schema::table('clinica_archivos', fn (Blueprint $t) => $t->date('fecha_doc')->nullable()->after('descripcion'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinica_archivos', 'fecha_doc')) {
            Schema::table('clinica_archivos', fn (Blueprint $t) => $t->dropColumn('fecha_doc'));
        }
    }
};
