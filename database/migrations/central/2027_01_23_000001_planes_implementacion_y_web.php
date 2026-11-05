<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes con implementación (un pago único al empezar, además de la mensualidad) y "mostrar en la web" aparte de
 * "activo": un plan puede asignarse en el panel sin salir en tushpa.app. El contrato guarda la implementación pactada.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('planes', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('planes', 'implementacion')) {
                $t->decimal('implementacion', 10, 2)->nullable()->after('precio_anual');
            }
            if (! $central->hasColumn('planes', 'en_web')) {
                $t->boolean('en_web')->default(true)->after('activo');
            }
        });
        if (! $central->hasColumn('contratos', 'implementacion')) {
            $central->table('contratos', fn (Blueprint $t) => $t->decimal('implementacion', 10, 2)->nullable()->after('monto'));
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('planes', fn (Blueprint $t) => $t->dropColumn(['implementacion', 'en_web']));
        Schema::connection('central')->table('contratos', fn (Blueprint $t) => $t->dropColumn('implementacion'));
    }
};
