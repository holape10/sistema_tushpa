<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Productos: código de barras, unidad equivalente (insumos), presentaciones (SACO x 50 KG) y precios dinámicos por día y hora.
 * Proformas: cotizaciones de los puntos de venta con su propia serie y correlativo por sucursal.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->string('codigo_barra', 50)->nullable()->after('procod')->index();
            // Insumos: 1 [umecod] = factor_equivalente [ume_equivalente] (ej. 1 KGM = 1000 GRM)
            $t->string('ume_equivalente', 3)->nullable()->after('umecod');
            $t->decimal('factor_equivalente', 14, 4)->default(1)->after('ume_equivalente');
        });

        // Otras formas de vender el mismo producto: factor = cuántas unidades base trae (SACO = 50 KG)
        Schema::create('producto_presentacion', function (Blueprint $t) {
            $t->increments('id_presentacion');
            $t->unsignedInteger('IdProducto')->index();
            $t->string('umecod', 3);
            $t->string('nombre', 60);
            $t->decimal('factor', 12, 3)->default(1);
            $t->decimal('precio', 15, 2)->default(0);
            $t->string('codigo_barra', 50)->nullable()->index();
            $t->boolean('estado')->default(true); // false = quitada (se conserva por las proformas que la usan)
            $t->timestamps();
        });

        // Precio especial por día y rango de horas; si hora_fin <= hora_inicio el rango cruza la medianoche
        Schema::create('producto_precio_dinamico', function (Blueprint $t) {
            $t->increments('id_precio_dinamico');
            $t->unsignedInteger('IdProducto')->index();
            $t->unsignedTinyInteger('dia')->default(0); // 0 = todos los días, 1 = lunes ... 7 = domingo
            $t->time('hora_inicio');
            $t->time('hora_fin');
            $t->decimal('precio', 15, 2);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        // La venta guarda el factor de la presentación (lo que salió del stock = cantidad x factor)
        Schema::table('cpe_detalle', function (Blueprint $t) {
            $t->decimal('cpe_det_factor', 12, 3)->default(1)->change();
        });

        Schema::table('empresa_negocios', function (Blueprint $t) {
            $t->string('SerProforma', 4)->default('PR01');
            $t->unsignedInteger('NumProforma')->default(0);
        });

        Schema::create('proformas', function (Blueprint $t) {
            $t->increments('id_proforma');
            $t->string('serie', 4);
            $t->unsignedInteger('numero');
            $t->date('fecha');
            $t->string('tdicod', 1)->default('1');
            $t->string('clinum', 15)->default('00000000');
            $t->string('clinom', 120)->default('VENTA AL PORTADOR');
            $t->string('clidir', 150)->nullable();
            $t->string('observaciones', 100)->nullable();
            $t->decimal('total', 15, 2)->default(0);
            $t->string('estado', 10)->default('PENDIENTE'); // PENDIENTE | FACTURADA
            $t->string('origen', 10)->default('PV');       // PV | FARMACIA | POS | TACTIL
            $t->unsignedInteger('IdCpe_cabecera')->nullable();
            $t->unsignedBigInteger('IdUsuario')->nullable();
            $t->string('IdEmpresa', 11)->default('');
            $t->unsignedBigInteger('id_empresa_negocio')->index();
            $t->timestamps();
            $t->unique(['id_empresa_negocio', 'serie', 'numero']);
        });

        Schema::create('proforma_detalle', function (Blueprint $t) {
            $t->increments('id_proforma_detalle');
            $t->unsignedInteger('id_proforma')->index();
            $t->unsignedInteger('IdProducto')->nullable();
            $t->unsignedInteger('id_presentacion')->nullable();
            $t->string('descripcion', 150);
            $t->string('umecod', 3)->default('NIU');
            $t->decimal('factor', 12, 3)->default(1);
            $t->decimal('cantidad', 12, 2);
            $t->decimal('precio', 15, 2);
            $t->decimal('total', 15, 2);
            $t->string('lote', 50)->nullable();
        });

        // Menú Ventas > Proformas para Administrador y Caja
        if (!DB::table('modulos')->where('mod_url', '/proformas')->exists()) {
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Proformas', 'mod_url' => '/proformas', 'mod_gen' => 'Ventas']);
            foreach (DB::table('role_user')->whereIn('role_id', [2, 4])->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/proformas')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        Schema::dropIfExists('proforma_detalle');
        Schema::dropIfExists('proformas');
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn(['SerProforma', 'NumProforma']));
        Schema::table('cpe_detalle', fn(Blueprint $t) => $t->decimal('cpe_det_factor', 10, 2)->default(1)->change());
        Schema::dropIfExists('producto_precio_dinamico');
        Schema::dropIfExists('producto_presentacion');
        Schema::table('productos', function (Blueprint $t) {
            $t->dropIndex(['codigo_barra']);
            $t->dropColumn(['codigo_barra', 'ume_equivalente', 'factor_equivalente']);
        });
    }
};
