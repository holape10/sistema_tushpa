<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avícolas: venta de pollo vivo por peso (jabas, tara, aves y mortalidad) con precio del día, cuenta corriente y
 * control de jabas por cliente; ingreso de pollo de la granja (compra por peso); beneficio (pollo vivo → pollo pelado y
 * sus partes, con su rendimiento); lotes de granja (engorde y postura); mermas propias del rubro; y el menú "Avícola".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('avi_config')) {
            Schema::create('avi_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->decimal('tara_jaba', 8, 3)->default(6.5);          // kg de una jaba vacía
                $t->unsignedSmallInteger('aves_jaba')->default(10);     // aves que entran en una jaba
                $t->timestamps();
            });
        }
        // Precio del día por kilo de cada producto avícola (historial; el precio vigente queda en el producto)
        if (! Schema::hasTable('avi_precios')) {
            Schema::create('avi_precios', function (Blueprint $t) {
                $t->increments('id');
                $t->date('fecha');
                $t->unsignedInteger('IdProducto');
                $t->decimal('precio', 10, 2);
                $t->decimal('anterior', 10, 2)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('created_at')->nullable();
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }
        // Venta por peso (pollo vivo): cada venta tiene sus pesadas (jabas con aves, peso bruto) y emite su comprobante
        if (! Schema::hasTable('avi_ventas')) {
            Schema::create('avi_ventas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->unsignedInteger('clicod')->nullable()->index();
                $t->string('clinom', 150)->nullable();
                $t->unsignedInteger('IdProducto');
                $t->unsignedInteger('jabas')->default(0);
                $t->unsignedInteger('aves')->default(0);
                $t->decimal('peso_bruto', 12, 3)->default(0);
                $t->decimal('tara', 12, 3)->default(0);
                $t->unsignedInteger('aves_muertas')->default(0);
                $t->decimal('peso_muertas', 12, 3)->default(0);
                $t->decimal('peso_neto', 12, 3)->default(0);
                $t->decimal('precio', 10, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->boolean('credito')->default(false);
                $t->unsignedInteger('jabas_devueltas')->default(0);
                $t->string('ruta', 60)->nullable();
                $t->string('estado', 10)->default('ACTIVA');           // ACTIVA | ANULADA
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('fecha');
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }
        if (! Schema::hasTable('avi_pesadas')) {
            Schema::create('avi_pesadas', function (Blueprint $t) {
                $t->increments('id');
                $t->string('origen', 8);                                // VENTA | INGRESO
                $t->unsignedInteger('ref_id')->index();
                $t->unsignedInteger('jabas')->default(0);
                $t->unsignedInteger('aves')->default(0);
                $t->decimal('peso_bruto', 12, 3);
                $t->index(['origen', 'ref_id']);
            });
        }
        // Jabas prestadas a cada cliente: salen con la venta y vuelven después (saldo = salidas − devoluciones ± ajustes)
        if (! Schema::hasTable('avi_jabas')) {
            Schema::create('avi_jabas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('clicod')->index();
                $t->string('tipo', 10);                                 // SALIDA | DEVOLUCION | AJUSTE
                $t->integer('cantidad');                                // con su signo: salida +, devolución −
                $t->unsignedInteger('venta_id')->nullable();
                $t->string('observacion', 150)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('fecha');
                $t->index(['id_empresa_negocio', 'clicod']);
            });
        }
        // Ingreso de pollo comprado a la granja o al proveedor: por peso, con sus jabas y la mortalidad en el viaje
        if (! Schema::hasTable('avi_ingresos')) {
            Schema::create('avi_ingresos', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('com_cab_id')->nullable()->index();
                $t->string('proveedor', 150)->nullable();
                $t->unsignedInteger('IdProducto');
                $t->unsignedInteger('jabas')->default(0);
                $t->unsignedInteger('aves')->default(0);
                $t->decimal('peso_bruto', 12, 3)->default(0);
                $t->decimal('tara', 12, 3)->default(0);
                $t->unsignedInteger('aves_muertas')->default(0);
                $t->decimal('peso_muertas', 12, 3)->default(0);
                $t->decimal('peso_neto', 12, 3)->default(0);
                $t->decimal('costo_kg', 10, 4)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->unsignedInteger('lote_id')->nullable();             // si sale de un lote de la granja propia
                $t->string('estado', 10)->default('ACTIVO');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('fecha');
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }
        // Beneficio: pollo vivo que se pela y se trozea; sale el vivo y entran las partes (el resto es la merma: pluma, sangre)
        if (! Schema::hasTable('avi_beneficios')) {
            Schema::create('avi_beneficios', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('IdProducto');                      // el pollo vivo que entra
                $t->unsignedInteger('aves')->default(0);
                $t->decimal('kg_vivo', 12, 3);
                $t->decimal('kg_salida', 12, 3)->default(0);
                $t->decimal('rendimiento', 6, 2)->default(0);           // % del vivo que salió como producto
                $t->decimal('costo_total', 12, 2)->default(0);
                $t->string('observacion', 150)->nullable();
                $t->string('estado', 10)->default('ACTIVO');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('fecha');
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }
        if (! Schema::hasTable('avi_beneficio_partes')) {
            Schema::create('avi_beneficio_partes', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('beneficio_id')->index();
                $t->unsignedInteger('IdProducto');
                $t->decimal('kg', 12, 3);
                $t->decimal('costo_kg', 10, 4)->default(0);
            });
        }
        // Granja: lotes (galpones) de engorde o de postura y su registro diario
        if (! Schema::hasTable('avi_lotes')) {
            Schema::create('avi_lotes', function (Blueprint $t) {
                $t->increments('id');
                $t->string('codigo', 30);
                $t->string('galpon', 60)->nullable();
                $t->string('tipo', 10)->default('ENGORDE');             // ENGORDE | POSTURA
                $t->string('linea', 60)->nullable();                    // raza o línea genética
                $t->date('fecha_ingreso');
                $t->unsignedInteger('aves_iniciales');
                $t->decimal('costo_ave', 10, 2)->default(0);            // pollito BB o pollona
                $t->string('estado', 10)->default('ABIERTO');           // ABIERTO | CERRADO
                $t->date('fecha_cierre')->nullable();
                $t->string('observacion', 200)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('avi_lote_registros')) {
            Schema::create('avi_lote_registros', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('lote_id')->index();
                $t->date('fecha');
                $t->unsignedInteger('mortalidad')->default(0);
                $t->decimal('alimento_kg', 10, 2)->default(0);
                $t->decimal('costo_alimento', 10, 2)->default(0);
                $t->decimal('otros_gastos', 10, 2)->default(0);         // vacunas, medicinas, cama, luz…
                $t->decimal('peso_promedio', 8, 3)->nullable();         // kg por ave (muestra pesada)
                $t->unsignedInteger('huevos')->default(0);              // postura
                $t->string('observacion', 150)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
            });
        }
        // Mermas del rubro: mortalidad, pérdida de peso (deshidratación), beneficio y decomiso
        if (! Schema::hasTable('avi_mermas')) {
            Schema::create('avi_mermas', function (Blueprint $t) {
                $t->increments('id');
                $t->date('fecha');
                $t->string('tipo', 15);                                 // MORTALIDAD | DESHIDRATACION | BENEFICIO | DECOMISO | OTRO
                $t->string('origen', 10)->default('MANUAL');            // MANUAL | VENTA | INGRESO | BENEFICIO | GRANJA
                $t->unsignedInteger('ref_id')->nullable();
                $t->unsignedInteger('IdProducto')->nullable();
                $t->unsignedInteger('aves')->default(0);
                $t->decimal('kg', 12, 3)->default(0);
                $t->decimal('costo', 12, 2)->default(0);
                $t->boolean('sale_stock')->default(false);              // si sacó stock del almacén
                $t->string('observacion', 150)->nullable();
                $t->string('estado', 10)->default('ACTIVA');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Venta por peso', '/avicola'], ['Clientes y jabas', '/avicola/clientes'], ['Ingreso de pollo', '/avicola/ingresos'],
            ['Beneficio y partes', '/avicola/beneficio'], ['Granja', '/avicola/granja'], ['Mermas avícolas', '/avicola/mermas'],
            ['Reportes avícolas', '/avicola/reportes'], ['Configuración avícola', '/avicola/configuracion']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Avícola']);
        }
        // Administradores con el menú de varios rubros (la demo y "Todo el menú") reciben el nuevo menú
        $amplio = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/clinica', '/hotel', '/estacionamiento', '/socios', '/pv-grifo', '/pv-farmacia', '/prestamos', '/colegio', '/courier'])->pluck('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = DB::table('modulos_usuario')->whereIn('user_IdUsuario', $admins)->whereIn('mod_id', $amplio)
            ->groupBy('user_IdUsuario')->havingRaw('COUNT(DISTINCT mod_id) >= 3')->pluck('user_IdUsuario');
        foreach ($usuarios as $usuario) {
            foreach ($nuevos as $mod) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->where('mod_gen', 'Avícola')->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['avi_mermas', 'avi_lote_registros', 'avi_lotes', 'avi_beneficio_partes', 'avi_beneficios', 'avi_ingresos', 'avi_jabas',
            'avi_pesadas', 'avi_ventas', 'avi_precios', 'avi_config'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
