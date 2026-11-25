<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Los nombres de productos que se guardaron en minúsculas pasan a MAYÚSCULAS (desde ahora el modelo los guarda así) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('productos')) {
            DB::table('productos')->whereRaw('BINARY pronom <> BINARY UPPER(pronom)')->update(['pronom' => DB::raw('UPPER(pronom)')]);
        }
    }

    public function down(): void
    {
        // No se puede saber cómo estaban escritos
    }
};
