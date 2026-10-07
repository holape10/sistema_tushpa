<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ¿Cómo nos conoció el paciente? (recomendación, TikTok, Facebook…) para saber de dónde llegan */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('historia_clinica') && !Schema::hasColumn('historia_clinica', 'origen')) {
            Schema::table('historia_clinica', fn(Blueprint $t) => $t->string('origen', 30)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('historia_clinica', fn(Blueprint $t) => $t->dropColumn('origen'));
    }
};
