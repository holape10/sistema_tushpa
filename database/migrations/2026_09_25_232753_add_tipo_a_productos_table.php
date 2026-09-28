<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->tinyInteger('promocion')->default(0)->after('propun');
            // 0 = Producto | 2 = Preparado | 4 = Insumo | 6 = Combo
            $table->string('umecod', 3)->default('NIU')->change();
        });
    }
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('promocion');
        });
    }
};