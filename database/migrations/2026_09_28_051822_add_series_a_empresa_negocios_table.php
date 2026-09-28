<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->string('SerNota', 4)->default('N001');
            $table->unsignedInteger('NumNota')->default(0);
            $table->string('tip_igv_pred', 2)->default('20'); // 10 gravado | 20 exonerado
            $table->string('tdocod_pred', 2)->default('13');  // comprobante predeterminado
        });
    }
    public function down(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->dropColumn(['SerNota', 'NumNota', 'tip_igv_pred', 'tdocod_pred']);
        });
    }
};