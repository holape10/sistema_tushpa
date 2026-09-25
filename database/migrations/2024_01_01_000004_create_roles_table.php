<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description');
            $table->timestamps();
        });

        //DB_INSERT: // sembramos los roles base
        \Illuminate\Support\Facades\DB::table('roles')->insert([
            ['id' => 2, 'name' => 'admin', 'description' => 'Administrador'],
            ['id' => 4, 'name' => 'caja', 'description' => 'Cajero'],
            ['id' => 8, 'name' => 'mozo', 'description' => 'Mozo']//,
        ]);
    }
    public function down(): void { Schema::dropIfExists('roles'); }
};