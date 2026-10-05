<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Formato en que la sucursal imprime sus comprobantes: TICKET (80 mm, el más usado) o A4
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->string('formato_impresion', 6)->default('TICKET');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn('formato_impresion'));
    }
};
