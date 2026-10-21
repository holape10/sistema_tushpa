<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rol Contador (en todos los rubros): solo el Reporte SUNAT, el envío a SUNAT y, si la empresa los tiene,
 * contabilidad, planilla y SIRE. Ve únicamente lo que el administrador le marca en su menú.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('id', 15)->exists()) {
            DB::table('roles')->insert(['id' => 15, 'name' => 'contador', 'description' => 'Contador']);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('id', 15)->delete();
    }
};
