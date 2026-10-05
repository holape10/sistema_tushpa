<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Tienda virtual ({subdominio}/tiendavirtual): los clientes de la empresa entran con su DNI/RUC y hacen pedidos en línea.
 * Un cliente que ya compró entra la primera vez con su DNI/RUC como contraseña y luego la cambia.
 * Cada pedido llega como proforma (origen WEB): la empresa lo confirma y lo cobra como cualquier proforma.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $t) {
            $t->string('tienda_password')->nullable();      // null = aún no la cambió (entra con su DNI/RUC)
            $t->timestamp('tienda_acceso')->nullable();
        });

        Schema::table('empresa_negocios', function (Blueprint $t) {
            $t->boolean('tienda_activa')->default(false);
            $t->string('tienda_whatsapp', 20)->nullable();
            $t->text('tienda_mensaje')->nullable();          // formas de pago, delivery, horarios
            $t->boolean('tienda_mostrar_agotados')->default(true);
        });

        if (!DB::table('modulos')->where('mod_url', '/tienda/configuracion')->exists()) {
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Tienda Virtual', 'mod_url' => '/tienda/configuracion', 'mod_gen' => 'Ventas']);
            foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/tienda/configuracion')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn(['tienda_activa', 'tienda_whatsapp', 'tienda_mensaje', 'tienda_mostrar_agotados']));
        Schema::table('cliente', fn(Blueprint $t) => $t->dropColumn(['tienda_password', 'tienda_acceso']));
    }
};
