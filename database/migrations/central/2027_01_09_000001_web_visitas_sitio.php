<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitas: además de la web (tushpa.app) se cuentan las de la demostración (demos.tushpa.app), cada una con su sitio
 * para verlas por separado en el panel. Las visitas que ya había son todas de la web.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('web_visitas', 'sitio')) {
            Schema::connection('central')->table('web_visitas', function (Blueprint $t) {
                $t->string('sitio', 10)->default('web');
                $t->index(['sitio', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('web_visitas', function (Blueprint $t) {
            $t->dropIndex(['sitio', 'fecha']);
            $t->dropColumn('sitio');
        });
    }
};
