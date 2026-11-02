<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marco de la foto para redes a gusto de cada sucursal: estilo (degradado sobre la foto, banda de color debajo o marco
 * completo tipo polaroid), su color y un texto propio (su @usuario, un lema); sin texto sale la fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'foto_marco_estilo')) {
                $t->string('foto_marco_estilo', 10)->default('DEGRADADO');
            }
            if (! Schema::hasColumn('empresa_negocios', 'foto_marco_color')) {
                $t->string('foto_marco_color', 7)->default('#111827');
            }
            if (! Schema::hasColumn('empresa_negocios', 'foto_texto')) {
                $t->string('foto_texto', 60)->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['foto_marco_estilo', 'foto_marco_color', 'foto_texto'] as $columna) {
            if (Schema::hasColumn('empresa_negocios', $columna)) {
                Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
