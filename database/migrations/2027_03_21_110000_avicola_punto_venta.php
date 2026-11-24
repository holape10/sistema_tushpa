<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** La venta por peso de las avícolas va suelta arriba en el menú (como el Punto Venta), con el nombre "Punto Venta Avícola" */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modulos')) {
            DB::table('modulos')->where('mod_url', '/avicola')->update(['mod_nom' => 'Punto Venta Avícola', 'mod_gen' => 'Principal']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos')) {
            DB::table('modulos')->where('mod_url', '/avicola')->update(['mod_nom' => 'Venta por peso', 'mod_gen' => 'Avícola']);
        }
    }
};
