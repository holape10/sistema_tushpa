<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cuentas bancarias de la empresa (banco, moneda, número y CCI) que salen en el comprobante A4 para que el cliente pague */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa', 'cuentas_bancarias')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->text('cuentas_bancarias')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa', 'cuentas_bancarias')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn('cuentas_bancarias'));
        }
    }
};
