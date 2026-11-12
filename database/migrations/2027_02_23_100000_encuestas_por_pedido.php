<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encuesta antes de cobrar: el mozo muestra el QR de la mesa (o el cliente la responde desde la carta QR), así que la
 * encuesta queda ligada al pedido y no solo al comprobante. Y el QR en el ticket se puede apagar (para no gastar papel).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'encuesta_qr_ticket')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('encuesta_qr_ticket')->default(true));
        }
        if (Schema::hasTable('encuestas') && ! Schema::hasColumn('encuestas', 'ped_id')) {
            Schema::table('encuestas', function (Blueprint $t) {
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->change();
                $t->unsignedInteger('ped_id')->nullable()->unique()->after('IdCpe_cabecera');
                $t->string('origen', 10)->nullable()->after('ped_id');
            });
            // Las de antes: su pedido (si la venta vino de una mesa) y que fueron por el ticket
            DB::statement('UPDATE encuestas e JOIN cpe_cabecera c ON c.IdCpe_cabecera = e.IdCpe_cabecera
                SET e.ped_id = c.ped_id WHERE c.ped_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM (SELECT ped_id FROM encuestas) x WHERE x.ped_id = c.ped_id)');
            DB::table('encuestas')->whereNull('origen')->update(['origen' => 'TICKET']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('encuestas', 'ped_id')) {
            Schema::table('encuestas', fn (Blueprint $t) => $t->dropColumn(['ped_id', 'origen']));
        }
        if (Schema::hasColumn('empresa_negocios', 'encuesta_qr_ticket')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('encuesta_qr_ticket'));
        }
    }
};
