<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de IP del Perú con su región y ciudad (DB-IP Lite, licencia CC BY 4.0): sirve para mostrar en tushpa.app el
 * contacto del partner de la región desde donde entra la visita (ej. Piura). Se recarga cada mes (geoip:actualizar).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_geoip')) {
            return;
        }
        Schema::connection('central')->create('web_geoip', function (Blueprint $t) {
            $t->boolean('v6')->default(false);
            $t->binary('ip_desde', 16);
            $t->binary('ip_hasta', 16);
            $t->string('region', 80)->nullable();
            $t->string('ciudad', 80)->nullable();
            $t->index(['v6', 'ip_desde']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_geoip');
    }
};
