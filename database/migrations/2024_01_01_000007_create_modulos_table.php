<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->increments('mod_id');
            $table->string('mod_nom')->nullable();
            $table->string('mod_url')->nullable();
            $table->string('mod_gen')->nullable(); // grupo del menú
            $table->string('descripcion')->nullable();
        });

        DB::table('modulos')->insert([
            ['mod_nom' => 'Punto Venta', 'mod_url' => '/pos', 'mod_gen' => 'Punto Venta'],
            ['mod_nom' => 'Comandas', 'mod_url' => '/comandas', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Pedidos', 'mod_url' => '/pedidos', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Mesas', 'mod_url' => '/mesas', 'mod_gen' => 'REST-BAR'],
            ['mod_nom' => 'Usuarios', 'mod_url' => '/usuarios', 'mod_gen' => 'Contactos'],
            ['mod_nom' => 'Sucursales', 'mod_url' => '/sucursales', 'mod_gen' => 'Mantenimiento'],
            // agrega el resto de tu tabla `modulos` cuando toque ese módulo
        ]);
    }
    public function down(): void { Schema::dropIfExists('modulos'); }
};