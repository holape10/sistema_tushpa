<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada marca (revendedor con dominio propio) tiene sus propios planes y precios. Los planes sin marca son los de
 * TUSHPA: los de siempre, los únicos que salen en tushpa.app.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('planes', 'marca_id')) {
            Schema::connection('central')->table('planes', fn (Blueprint $t) => $t->unsignedBigInteger('marca_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('planes', fn (Blueprint $t) => $t->dropColumn('marca_id'));
    }
};
