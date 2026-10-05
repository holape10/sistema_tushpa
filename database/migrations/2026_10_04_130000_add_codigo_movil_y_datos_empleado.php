<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campos del sistema antiguo: users.codigo_movil (login rápido de mozos en tablet/celular) y datos del empleado
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('codigo_movil')->nullable()->after('email');
            // Un código no se repite dentro de la misma sucursal
            $table->unique(['id_empresa_negocio', 'codigo_movil'], 'codigo_movil_sucursal_unico');
        });

        Schema::table('empleado', function (Blueprint $table) {
            $table->string('sex_cod', 2)->nullable()->after('emp_cel');
            $table->string('emp_est')->nullable()->after('emp_cor');
            $table->date('emp_fec_nac')->nullable()->after('est_cod');
            $table->integer('asistencia')->default(0)->after('rol_id');
        });
    }

    public function down(): void
    {
        Schema::table('empleado', fn(Blueprint $t) => $t->dropColumn(['sex_cod', 'emp_est', 'emp_fec_nac', 'asistencia']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('codigo_movil_sucursal_unico');
            $table->dropColumn('codigo_movil');
        });
    }
};
