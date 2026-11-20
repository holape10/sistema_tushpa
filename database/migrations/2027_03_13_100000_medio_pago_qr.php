<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** QR de cobro de un medio de pago (Yape, Plin…): se muestra en el Punto Venta para que el cliente lo escanee */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('medios_pagos', 'qr')) {
            Schema::table('medios_pagos', fn (Blueprint $t) => $t->string('qr', 255)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('medios_pagos', 'qr')) {
            Schema::table('medios_pagos', fn (Blueprint $t) => $t->dropColumn('qr'));
        }
    }
};
