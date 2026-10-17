<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prospectos: si ya se le escribió al negocio que creó su carta y una nota (ej. "le interesa el plan Básico") */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        if ($central->hasTable('cartas_web') && ! $central->hasColumn('cartas_web', 'contactado_at')) {
            $central->table('cartas_web', function (Blueprint $t) {
                $t->timestamp('contactado_at')->nullable();
                $t->string('nota', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('cartas_web', fn (Blueprint $t) => $t->dropColumn(['contactado_at', 'nota']));
    }
};
