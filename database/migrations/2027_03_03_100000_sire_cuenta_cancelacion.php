<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta de cancelación de cada comprobante del SIRE (con qué se pagó o cobró: caja, banco…). Va al .dbf del sistema
 * contable: almaingr.CTACANCE (compras) y tienfact.NUMERO (ventas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sire_ajustes') && ! Schema::hasColumn('sire_ajustes', 'ctacance')) {
            Schema::table('sire_ajustes', fn (Blueprint $t) => $t->string('ctacance', 12)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sire_ajustes', 'ctacance')) {
            Schema::table('sire_ajustes', fn (Blueprint $t) => $t->dropColumn('ctacance'));
        }
    }
};
