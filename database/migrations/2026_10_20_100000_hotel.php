<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel / hospedaje.
 *  - habitaciones: como las mesas de Comandas (Libre, Ocupado, Limpieza, Mantenimiento).
 *  - hospedajes: cada ingreso a una habitación; su consumo es un pedido normal (ped_tip 'Hotel') que se cobra igual que una mesa.
 *  - productos.minutos: tiempo que da un servicio (HABITACION 2 HORAS = 120, 1 DIA = 1440, HORA EXTRA = 60).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('habitaciones')) {
            Schema::create('habitaciones', function (Blueprint $table) {
                $table->increments('hab_id');
                $table->string('hab_nom', 50);
                $table->string('hab_tip', 40)->nullable();           // SIMPLE, DOBLE, MATRIMONIAL...
                $table->string('hab_piso', 30)->default('PISO 1');
                $table->string('hab_est', 20)->default('Libre');      // Libre | Ocupado | Limpieza | Mantenimiento
                $table->string('hab_obs', 150)->nullable();
                $table->string('IdEmpresa', 11)->nullable();
                $table->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (!Schema::hasTable('hospedajes')) {
            Schema::create('hospedajes', function (Blueprint $table) {
                $table->increments('hos_id');
                $table->unsignedInteger('ped_id')->index();
                $table->unsignedInteger('hab_id')->index();
                $table->unsignedBigInteger('id_empresa_negocio')->index();
                $table->string('cliente', 150)->nullable();
                $table->string('documento', 15)->nullable();
                $table->unsignedTinyInteger('personas')->default(1);
                $table->dateTime('inicio');
                $table->dateTime('fin');                              // sube con cada hora extra
                $table->dateTime('salida')->nullable();
                $table->string('hos_est', 12)->default('ACTIVO');     // ACTIVO | FINALIZADO | ANULADO
                $table->unsignedInteger('IdUsuario')->nullable();
            });
        }

        if (!Schema::hasColumn('productos', 'minutos')) {
            Schema::table('productos', fn(Blueprint $t) => $t->unsignedInteger('minutos')->nullable());
        }

        // Menú: se activa por usuario en Usuarios (solo lo necesitan los hoteles)
        if (!DB::table('modulos')->where('mod_url', '/hotel')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Hotel', 'mod_url' => '/hotel', 'mod_gen' => 'Principal']);
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/hotel')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        Schema::table('productos', fn(Blueprint $t) => $t->dropColumn('minutos'));
        Schema::dropIfExists('hospedajes');
        Schema::dropIfExists('habitaciones');
    }
};
