<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal del socio ({subdominio}/socio): entra con su DNI/RUC. La primera vez la contraseña es su mismo DNI/RUC
 * y se le pide crear una propia (socios.clave). socios.acceso = último ingreso.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('socios') && !Schema::hasColumn('socios', 'clave')) {
            Schema::table('socios', function (Blueprint $t) {
                $t->string('clave', 255)->nullable();
                $t->dateTime('acceso')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('socios', fn(Blueprint $t) => $t->dropColumn(['clave', 'acceso']));
    }
};
