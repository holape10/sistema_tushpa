<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada usuario del panel (que no es el dueño) puede poner sus propios datos de cobro: QR, banco, cuenta, CCI y WhatsApp.
 * Las empresas que creó ven esos datos en la pantalla de "Servicio suspendido"; si no los puso, ven los del dueño.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('superadmins', function (Blueprint $t) use ($central) {
            foreach ([
                'cobro_titular' => 120, 'cobro_banco' => 60, 'cobro_cuenta' => 40, 'cobro_cci' => 40,
                'cobro_qr' => 200, 'cobro_whatsapp' => 20, 'cobro_telefono' => 20,
            ] as $columna => $largo) {
                if (! $central->hasColumn('superadmins', $columna)) {
                    $t->string($columna, $largo)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn([
            'cobro_titular', 'cobro_banco', 'cobro_cuenta', 'cobro_cci', 'cobro_qr', 'cobro_whatsapp', 'cobro_telefono',
        ]));
    }
};
