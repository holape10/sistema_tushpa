<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones de la web por ciudad: en qué ciudades vale (Piura, Iquitos...) y el WhatsApp de cada una.
 * [{"nombre": "Piura", "whatsapp": "51965..."}]. Vacío = vale en todas, con el botón de siempre.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_promociones') && ! Schema::connection('central')->hasColumn('web_promociones', 'ciudades')) {
            Schema::connection('central')->table('web_promociones', fn (Blueprint $t) => $t->json('ciudades')->nullable());
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('web_promociones', fn (Blueprint $t) => $t->dropColumn('ciudades'));
    }
};
