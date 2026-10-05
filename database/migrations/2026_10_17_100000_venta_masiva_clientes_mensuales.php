<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Venta masiva: clientes con facturación mensual (igual que en el sistema antiguo).
 * comprobante = 01 factura | 03 boleta | 13 nota de venta; mensual = 1 sale en la emisión masiva; monto = lo que se le cobra cada mes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $t) {
            $t->string('comprobante', 2)->nullable();
            $t->boolean('mensual')->default(false)->index();
            $t->decimal('monto', 10, 2)->default(0);
        });

        if (!DB::table('modulos')->where('mod_url', '/ventas/masiva')->exists()) {
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Venta Masiva', 'mod_url' => '/ventas/masiva', 'mod_gen' => 'Ventas']);
            foreach (DB::table('role_user')->whereIn('role_id', [2, 4])->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/ventas/masiva')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        Schema::table('cliente', fn(Blueprint $t) => $t->dropColumn(['comprobante', 'mensual', 'monto']));
    }
};
