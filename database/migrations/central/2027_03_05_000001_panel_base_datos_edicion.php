<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Edición de bases desde el panel (solo el dueño): una segunda clave, aparte de la del panel, para poder modificar
 * datos; y el registro de cada cambio que se hizo (quién, en qué base, qué orden y cuántas filas).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('superadmins', 'clave_bd')) {
            Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->string('clave_bd')->nullable());
        }
        if (! Schema::connection('central')->hasTable('panel_bd_cambios')) {
            Schema::connection('central')->create('panel_bd_cambios', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('superadmin_id');
                $t->string('usuario', 120);
                $t->string('base', 64);
                $t->text('sql');
                $t->unsignedInteger('filas')->default(0);
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['base', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('panel_bd_cambios');
        if (Schema::connection('central')->hasColumn('superadmins', 'clave_bd')) {
            Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn('clave_bd'));
        }
    }
};
