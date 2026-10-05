<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * PV Grifo (venta de combustible por galones o por importe, con placa), compras por presentación
 * e importador de la base del sistema antiguo.
 */
return new class extends Migration {
    private const MODULOS = [
        ['mod_nom' => 'PV Grifo', 'mod_url' => '/pv-grifo', 'mod_gen' => 'Principal', 'roles' => [2, 4]],
        ['mod_nom' => 'Importar Sistema Antiguo', 'mod_url' => '/importar-antiguo', 'mod_gen' => 'Mantenimiento', 'roles' => [2]],
    ];

    public function up(): void
    {
        // Galones con 3 decimales (1.183 gal). Antes la cantidad vendida y el stock tenían 2.
        Schema::table('cpe_detalle', fn(Blueprint $t) => $t->decimal('cdecan', 12, 3)->default(0)->change());
        Schema::table('producto_stock', function (Blueprint $t) {
            $t->decimal('stock', 14, 3)->default(0)->change();
            $t->decimal('stock_inicial', 14, 3)->default(0)->change();
        });
        Schema::table('proforma_detalle', fn(Blueprint $t) => $t->decimal('cantidad', 12, 3)->change());

        Schema::table('cpe_cabecera', function (Blueprint $t) {
            $t->string('placa', 10)->nullable();
            $t->string('guia_remision', 20)->nullable();
        });

        // Productos que se venden por galón/litro en el PV Grifo (abre el teclado de importe o cantidad)
        Schema::table('productos', fn(Blueprint $t) => $t->boolean('es_combustible')->default(false)->after('control_lote'));

        // Compra por presentación: 1 SACO x 50 ingresa 50 al stock
        Schema::table('compras_detalle', function (Blueprint $t) {
            $t->unsignedInteger('id_presentacion')->nullable();
            $t->decimal('factor', 12, 3)->default(1);
        });

        foreach (self::MODULOS as $m) {
            if (DB::table('modulos')->where('mod_url', $m['mod_url'])->exists()) {
                continue;
            }
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => $m['mod_nom'], 'mod_url' => $m['mod_url'], 'mod_gen' => $m['mod_gen']]);
            foreach (DB::table('role_user')->whereIn('role_id', $m['roles'])->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::MODULOS as $m) {
            $mod = DB::table('modulos')->where('mod_url', $m['mod_url'])->value('mod_id');
            if ($mod) {
                DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
                DB::table('modulos')->where('mod_id', $mod)->delete();
            }
        }
        Schema::table('compras_detalle', fn(Blueprint $t) => $t->dropColumn(['id_presentacion', 'factor']));
        Schema::table('productos', fn(Blueprint $t) => $t->dropColumn('es_combustible'));
        Schema::table('cpe_cabecera', fn(Blueprint $t) => $t->dropColumn(['placa', 'guia_remision']));
        Schema::table('proforma_detalle', fn(Blueprint $t) => $t->decimal('cantidad', 12, 2)->change());
        Schema::table('producto_stock', function (Blueprint $t) {
            $t->decimal('stock', 10, 2)->default(0)->change();
            $t->decimal('stock_inicial', 10, 2)->default(0)->change();
        });
        Schema::table('cpe_detalle', fn(Blueprint $t) => $t->decimal('cdecan', 10, 2)->default(0)->change());
    }
};
