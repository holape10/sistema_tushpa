<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Panel de ventas: anulación con los campos del sistema antiguo (ccabaj ya existe, se agrega motivo_baja)
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            $table->string('motivo_baja', 70)->nullable()->after('ccabaj');
            $table->unsignedInteger('IdUsuario_baja')->nullable()->after('motivo_baja');
        });
        DB::table('modulos')->where('mod_nom', 'Panel Ventas')->update(['mod_url' => '/ventas']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'Panel Ventas')->update(['mod_url' => '#']);
        Schema::table('cpe_cabecera', fn(Blueprint $t) => $t->dropColumn(['motivo_baja', 'IdUsuario_baja']));
    }
};
