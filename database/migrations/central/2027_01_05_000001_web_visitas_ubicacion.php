<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitas web: desde qué país (y en el Perú, qué región y ciudad) entra cada visita, para el panel. Se guarda solo la
 * ubicación aproximada, nunca el IP. La base de IPs pasa a tener el país de todo el mundo (en el Perú, además la ciudad).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('web_visitas', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('web_visitas', 'pais')) {
                $t->char('pais', 2)->nullable();
                $t->string('region', 80)->nullable();
                $t->string('ciudad', 80)->nullable();
            }
        });
        $central->table('web_geoip', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('web_geoip', 'pais')) {
                $t->char('pais', 2)->default('PE');   // los rangos que ya había son todos del Perú
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('web_visitas', fn (Blueprint $t) => $t->dropColumn(['pais', 'region', 'ciudad']));
        Schema::connection('central')->table('web_geoip', fn (Blueprint $t) => $t->dropColumn('pais'));
    }
};
