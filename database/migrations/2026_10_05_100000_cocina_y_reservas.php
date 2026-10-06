<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla de cocina (KDS) y reservas.
 *  - cocina_tickets / cocina_items: cada envío de comanda es una tarjeta en la pantalla de cocina.
 *  - reservas / reserva_detalle: mismas tablas y campos del sistema antiguo + nombre y teléfono del cliente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cocina_tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ped_id')->index();
            $table->unsignedBigInteger('id_empresa_negocio')->index();
            $table->string('destino', 60)->nullable();            // "PISO 01 / MESA 03", "LLEVAR - JOSE"
            $table->string('ped_tip', 20)->nullable();
            $table->string('mozo', 100)->nullable();
            $table->string('tipo', 12)->default('NUEVO');          // NUEVO | ADICIONAL | ANULACION
            $table->timestamp('creado')->nullable()->useCurrent();
            $table->timestamp('listo')->nullable();                // todas sus líneas listas
            $table->timestamp('entregado')->nullable();            // el mozo lo llevó a la mesa
        });

        Schema::create('cocina_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id')->index();
            $table->unsignedInteger('IdProducto')->nullable();
            $table->string('descripcion', 150);
            $table->decimal('cantidad', 10, 2);
            $table->string('observacion', 255)->nullable();
            $table->unsignedInteger('estacion')->nullable()->index(); // configuracion_impresoras.Id (cocina, bar...)
            $table->tinyInteger('anulado')->default(0);            // el cliente lo canceló: no prepararlo
            $table->timestamp('listo')->nullable();
            $table->foreign('ticket_id')->references('id')->on('cocina_tickets')->onDelete('cascade');
        });

        // Minutos para pasar a amarillo y a rojo en la pantalla de cocina
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->unsignedSmallInteger('kds_amarillo')->default(10);
            $table->unsignedSmallInteger('kds_rojo')->default(20);
        });

        if (!Schema::hasTable('reservas')) {
            Schema::create('reservas', function (Blueprint $table) {
                $table->increments('res_id');
                $table->string('IdEmpresa', 11)->nullable();
                $table->unsignedInteger('clicod')->nullable()->index();
                $table->unsignedInteger('pis_id')->nullable()->index();
                $table->unsignedInteger('mes_id')->nullable()->index();
                $table->date('fecha_reserva');
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->integer('cantidad_personas')->default(1);
                $table->string('estado', 20)->default('Pendiente'); // Pendiente | Confirmada | Atendida | Cancelada | No asistió
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('total_estimado', 15, 2)->default(0);
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->unsignedBigInteger('id_empresa_negocio')->nullable();
                $table->unsignedInteger('id_almacen')->nullable();
                // campos propios del sistema nuevo (las reservas por teléfono no siempre traen DNI)
                $table->string('nombre_cliente', 150)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->unsignedInteger('ped_id')->nullable();      // pedido con el que se atendió
                $table->unsignedInteger('IdUsuario')->nullable();   // quién registró la reserva
                $table->index(['id_empresa_negocio', 'fecha_reserva']);
            });

            Schema::create('reserva_detalle', function (Blueprint $table) {
                $table->increments('det_id');
                $table->unsignedInteger('res_id')->index();
                $table->unsignedInteger('IdProducto')->index();
                $table->decimal('cantidad', 10, 2)->default(1);
                $table->decimal('precio', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->string('nota_producto')->nullable();
                $table->decimal('precio_unitario', 10, 2)->default(0);
                $table->string('observacion_producto', 100)->nullable();
                $table->unsignedBigInteger('id_empresa_negocio')->nullable();
                $table->unsignedInteger('id_almacen')->nullable();
                $table->foreign('res_id')->references('res_id')->on('reservas')->onDelete('cascade');
            });
        }

        // Menú: Cocina (pantalla) y Reservas, en el grupo Restaurante, visibles para los administradores
        foreach ([['Pantalla Cocina', '/cocina'], ['Reservas', '/reservas']] as [$nom, $url]) {
            if (DB::table('modulos')->where('mod_nom', $nom)->exists()) {
                continue;
            }
            $id = DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Restaurante']);
            foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario') as $u) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_nom', ['Pantalla Cocina', 'Reservas'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        Schema::dropIfExists('reserva_detalle');
        Schema::dropIfExists('reservas');
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn(['kds_amarillo', 'kds_rojo']));
        Schema::dropIfExists('cocina_items');
        Schema::dropIfExists('cocina_tickets');
    }
};
