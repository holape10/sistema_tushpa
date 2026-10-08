<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada usuario del panel ve solo las empresas que creó (clientes.creado_por).
 * El dueño del sistema (superadmins.es_dueno, el primer usuario) ve todas y puede asignarlas.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('superadmins', 'es_dueno')) {
            Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->tinyInteger('es_dueno')->default(0));
        }
        if (! Schema::connection('central')->hasColumn('clientes', 'creado_por')) {
            Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->unsignedBigInteger('creado_por')->nullable()->index());
        }

        // El primer usuario es el dueño y le quedan las empresas que ya existían
        $dueno = DB::connection('central')->table('superadmins')->min('id');
        if ($dueno) {
            DB::connection('central')->table('superadmins')->where('id', $dueno)->update(['es_dueno' => 1]);
            DB::connection('central')->table('clientes')->whereNull('creado_por')->update(['creado_por' => $dueno]);
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn('creado_por'));
        Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn('es_dueno'));
    }
};
