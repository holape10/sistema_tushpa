<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificados digitales para facturación electrónica que se venden en tushpa.app (como partner de LLAMA.PE, a sus
 * mismos precios). El dueño cambia precios y textos en el panel (Certificados).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_certificados')) {
            return;
        }
        Schema::connection('central')->create('web_certificados', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 60);                     // "1 año"
            $t->unsignedTinyInteger('anios')->default(1);
            $t->decimal('precio', 10, 2);
            $t->decimal('precio_antes', 10, 2)->nullable(); // precio tachado (opcional)
            $t->string('detalle', 200)->nullable();
            $t->boolean('destacado')->default(false);
            $t->boolean('activo')->default(true);
            $t->unsignedInteger('orden')->default(0);
            $t->timestamps();
        });
        DB::connection('central')->table('web_certificados')->insert([
            ['nombre' => '1 año', 'anios' => 1, 'precio' => 295.00, 'detalle' => 'Ideal para empezar a facturar', 'destacado' => false, 'orden' => 1, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => '2 años', 'anios' => 2, 'precio' => 501.50, 'detalle' => 'Te olvidas de renovar por 2 años', 'destacado' => true, 'orden' => 2, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => '3 años', 'anios' => 3, 'precio' => 708.00, 'detalle' => 'El precio por año más bajo', 'destacado' => false, 'orden' => 3, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_certificados');
    }
};
