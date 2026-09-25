<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modulos_usuario', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_IdUsuario');
            $table->unsignedInteger('mod_id');
            $table->timestamps();

            $table->foreign('user_IdUsuario')->references('IdUsuario')->on('users')->onDelete('cascade');
            $table->foreign('mod_id')->references('mod_id')->on('modulos')->onDelete('cascade');
            $table->unique(['user_IdUsuario', 'mod_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('modulos_usuario'); }
};