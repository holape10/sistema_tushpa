<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persona natural con negocio (RUC 10): puede pedir que el ticket compacto no imprima su nombre (solo el nombre
 * comercial y el RUC), por seguridad. Apagado hasta que la sucursal lo active.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'ocultar_titular')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('ocultar_titular')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'ocultar_titular')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('ocultar_titular'));
        }
    }
};
