<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre corto de quien atendió (primer nombre y primer apellido) en tickets, comandas y cocina.
 * Se activa en Impresoras; viene activo para CORPORATION 360 (RUC 20616065034), que lo pidió.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'nombre_corto')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('nombre_corto')->default(false));
        }
        DB::table('empresa_negocios')->where('IdEmpresa', '20616065034')->update(['nombre_corto' => true]);
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('nombre_corto'));
    }
};
