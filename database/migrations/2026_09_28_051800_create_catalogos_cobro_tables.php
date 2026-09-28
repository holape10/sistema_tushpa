<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipo_documento', function (Blueprint $table) {
            $table->string('tdocod', 2)->primary();
            $table->string('tdodes', 100);
            $table->tinyInteger('caja')->default(1);
            $table->tinyInteger('ventas')->default(1);
        });

        DB::table('tipo_documento')->insert([
            ['tdocod' => '13', 'tdodes' => 'NOTA DE VENTA', 'caja' => 1, 'ventas' => 1],
            ['tdocod' => '03', 'tdodes' => 'BOLETA DE VENTA ELECTRONICA', 'caja' => 1, 'ventas' => 1],
            ['tdocod' => '01', 'tdodes' => 'FACTURA ELECTRONICA', 'caja' => 1, 'ventas' => 1],
        ]);

        Schema::create('tipo_documento_identidad', function (Blueprint $table) {
            $table->string('tdicod', 1)->primary();
            $table->string('tdides', 60);
            $table->tinyInteger('orden')->default(0);
        });

        DB::table('tipo_documento_identidad')->insert([
            ['tdicod' => '1', 'tdides' => 'DNI', 'orden' => 1],
            ['tdicod' => '6', 'tdides' => 'RUC', 'orden' => 2],
            ['tdicod' => '4', 'tdides' => 'CARNET EXTRANJERIA', 'orden' => 3],
            ['tdicod' => '7', 'tdides' => 'PASAPORTE', 'orden' => 4],
        ]);

        Schema::create('cliente', function (Blueprint $table) {
            $table->increments('clicod');
            $table->string('tdicod', 1)->nullable();
            $table->string('clinum', 20)->default('');
            $table->string('clinom', 200)->default('');
            $table->string('rucemp', 11)->default('');
            $table->string('clidir', 200)->default('--');
            $table->string('clicor', 50)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('cliest', 20)->default('Activo');
            $table->index(['rucemp', 'clinum']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('cliente');
        Schema::dropIfExists('tipo_documento_identidad');
        Schema::dropIfExists('tipo_documento');
    }
};