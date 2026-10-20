<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio anual de cada plan (se muestra al costado del mensual en tushpa.app).
 * Vacío = 10 meses (2 meses gratis).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('planes', 'precio_anual')) {
            Schema::connection('central')->table('planes', fn (Blueprint $t) => $t->decimal('precio_anual', 10, 2)->nullable()->after('precio'));
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('planes', fn (Blueprint $t) => $t->dropColumn('precio_anual'));
    }
};
