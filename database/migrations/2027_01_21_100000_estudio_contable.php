<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estudio contable: en una misma base viven el estudio (propia = 1) y las empresas de sus clientes, cada una con su
 * RUC y su sucursal. Cada usuario del estudio trabaja en una empresa a la vez y solo ve las que se le asignaron.
 * Una base sin el estudio activado no cambia en nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estudio_empresas')) {
            Schema::create('estudio_empresas', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11)->unique();
                $t->boolean('propia')->default(false);
                $t->boolean('activa')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('estudio_accesos')) {
            Schema::create('estudio_accesos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('IdUsuario');
                $t->string('IdEmpresa', 11);
                $t->timestamps();
                $t->unique(['IdUsuario', 'IdEmpresa']);
            });
        }
        // La opción del menú; se le da al administrador recién cuando se activa el estudio
        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/estudio')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Empresas del estudio', 'mod_url' => '/estudio', 'mod_gen' => 'Estudio']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/estudio')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        Schema::dropIfExists('estudio_accesos');
        Schema::dropIfExists('estudio_empresas');
    }
};
