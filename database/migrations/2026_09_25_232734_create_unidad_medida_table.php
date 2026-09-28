<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidad_medida', function (Blueprint $table) {
            $table->increments('ume_id');
            $table->string('umecod', 3)->unique();
            $table->string('umenom', 250);
            $table->string('umecin', 3)->nullable();
            $table->string('umeest', 8)->default('Activo');
        });

        DB::table('unidad_medida')->insert([
            ['umecod' => 'NIU', 'umenom' => 'Unidad', 'umecin' => 'UNI'],
            ['umecod' => 'BG', 'umenom' => 'Bolsa', 'umecin' => 'BOL'],
            ['umecod' => 'BX', 'umenom' => 'Caja', 'umecin' => 'CJA'],
            ['umecod' => 'KT', 'umenom' => 'Kit o Juego', 'umecin' => 'KIT'],
            ['umecod' => 'NMP', 'umenom' => 'Paquete', 'umecin' => 'PQT'],
            ['umecod' => 'ZZ', 'umenom' => 'Servicio', 'umecin' => 'YRD'],
            ['umecod' => 'SA', 'umenom' => 'Saco', 'umecin' => 'SAC'],
            ['umecod' => 'BO', 'umenom' => 'Botella', 'umecin' => 'BOT'],
            ['umecod' => 'FR', 'umenom' => 'Frasco o Jarra', 'umecin' => 'JR'],
            ['umecod' => 'CA', 'umenom' => 'Lata', 'umecin' => 'LAT'],
            ['umecod' => 'PK', 'umenom' => 'Pack', 'umecin' => 'PAK'],
            ['umecod' => 'KGM', 'umenom' => 'Kilogramo', 'umecin' => 'KGM'],
            ['umecod' => 'LTR', 'umenom' => 'Litros', 'umecin' => 'LT'],
            ['umecod' => 'GRM', 'umenom' => 'Gramos', 'umecin' => 'GRM'],
            ['umecod' => 'TAP', 'umenom' => 'Taper', 'umecin' => 'TAP'],
            ['umecod' => 'POR', 'umenom' => 'Porción', 'umecin' => 'POR'],
            ['umecod' => 'SOB', 'umenom' => 'Sobre', 'umecin' => 'SOB'],
            ['umecod' => 'MLT', 'umenom' => 'Mililitro', 'umecin' => 'MLT'],
        ]);
    }
    public function down(): void { Schema::dropIfExists('unidad_medida'); }
};