<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mantenimiento › Avisos por Telegram: la central donde cada administrador elige qué le llega a su Telegram.
 * Se suman a los que ya había (telegram_resumen, telegram_pedidos, telegram_encuestas):
 *  - telegram_alertas: anulaciones, descuentos y cortesías, faltantes de caja, platos quitados (al momento)
 *  - telegram_precios: cambios de precios
 *  - telegram_cambios: los movimientos que la bitácora marca como importantes
 *  - telegram_resumen_hora / telegram_resumen_enviado: a qué hora le llega el resumen de ventas (y si ya salió hoy)
 */
return new class extends Migration
{
    private const BOOLEANOS = ['telegram_alertas', 'telegram_precios', 'telegram_cambios'];

    public function up(): void
    {
        if (Schema::hasTable('users')) {
            foreach (self::BOOLEANOS as $col) {
                if (! Schema::hasColumn('users', $col)) {
                    Schema::table('users', fn (Blueprint $t) => $t->boolean($col)->default(false));
                }
            }
            if (! Schema::hasColumn('users', 'telegram_resumen_hora')) {
                Schema::table('users', fn (Blueprint $t) => $t->string('telegram_resumen_hora', 5)->nullable());
            }
            if (! Schema::hasColumn('users', 'telegram_resumen_enviado')) {
                Schema::table('users', fn (Blueprint $t) => $t->date('telegram_resumen_enviado')->nullable());
            }
        }
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', '/avisos-telegram')->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Avisos por Telegram', 'mod_url' => '/avisos-telegram', 'mod_gen' => 'Mantenimiento']);
        foreach (DB::table('role_user')->where('role_id', 2)->distinct()->pluck('user_IdUsuario') as $usuario) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/avisos-telegram')->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        foreach ([...self::BOOLEANOS, 'telegram_resumen_hora', 'telegram_resumen_enviado'] as $col) {
            if (Schema::hasColumn('users', $col)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
