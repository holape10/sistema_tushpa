<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticketeras que tienen las letras al revés (NES POS E804 y otras genéricas): la letra "normal" sale chica y angosta y
 * la "pequeña" sale grande. Con esta marca el sistema les pide la otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configuracion_impresoras', 'letra_invertida')) {
            Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->boolean('letra_invertida')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuracion_impresoras', 'letra_invertida')) {
            Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->dropColumn('letra_invertida'));
        }
    }
};
