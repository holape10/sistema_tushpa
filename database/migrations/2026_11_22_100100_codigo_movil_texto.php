<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código móvil de mozos y promotores: de 1 a 8 dígitos y como texto, para que un DNI que empieza con 0
 * (01234567) no pierda el cero. Los códigos actuales quedan igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'codigo_movil')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('codigo_movil', 8)->nullable()->change());
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->integer('codigo_movil')->nullable()->change());
    }
};
