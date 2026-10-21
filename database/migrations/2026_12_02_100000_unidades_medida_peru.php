<?php

use App\Support\Unidades;
use Illuminate\Database\Migrations\Migration;

/**
 * Unidades de medida más usadas en el Perú (catálogo 03 de SUNAT): metro, millar, docena, rollo, tonelada, hora, día…
 * Solo agrega las que faltan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Unidades::completar();
    }

    public function down(): void
    {
        //
    }
};
