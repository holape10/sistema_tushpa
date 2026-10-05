<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Asistencia del personal: turnos, matriz de horarios, marcaciones (hasta 2 bloques por día: entrada/salida y
 * retorno de refrigerio/salida final), motivos de tardanza, feriados e IP permitida por sucursal.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('asistencia_turnos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('id_empresa_negocio')->index();
            $table->string('codigo', 5);
            $table->string('descripcion', 100)->nullable();
            // TRABAJO: tiene horas | DESCANSO: día libre | LEYENDA: vacaciones, descanso médico, licencia... (no se marca)
            $table->string('tipo', 10)->default('TRABAJO');
            $table->time('hora_entrada_1')->nullable();
            $table->time('hora_salida_1')->nullable();
            $table->time('hora_entrada_2')->nullable();   // retorno de refrigerio (opcional)
            $table->time('hora_salida_2')->nullable();
            $table->unsignedSmallInteger('tolerancia_minutos')->default(10);
            $table->string('color', 7)->default('#6366f1');
            $table->timestamps();
            $table->unique(['id_empresa_negocio', 'codigo']);
        });

        Schema::create('asistencia_horarios', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('emp_id');
            $table->date('fecha');
            $table->unsignedInteger('turno_id');
            $table->timestamps();
            $table->unique(['emp_id', 'fecha']);
            $table->index('fecha');
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('emp_id');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->date('fecha');
            $table->unsignedInteger('turno_id')->nullable();
            $table->dateTime('check_in_1')->nullable();
            $table->dateTime('check_out_1')->nullable();
            $table->dateTime('check_in_2')->nullable();
            $table->dateTime('check_out_2')->nullable();
            $table->unsignedInteger('tardanza_minutos')->default(0);
            $table->string('autorizado_por', 100)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->string('origen', 30)->nullable();     // LECTOR, QR, AUTORIZADO (de cada marcación, separados por coma)
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['emp_id', 'fecha']);
            $table->index(['id_empresa_negocio', 'fecha']);
        });

        Schema::create('asistencia_motivos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('id_empresa_negocio')->index();
            $table->string('descripcion', 100);
            $table->string('estado', 10)->default('Activo');
            $table->timestamps();
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable()->index();
            $table->date('fecha');
            $table->string('descripcion', 100);
            $table->timestamps();
        });

        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->string('ip_asistencia', 255)->nullable();   // una o varias IP separadas por coma; vacío = sin restricción
        });

        // Turnos y motivos de ejemplo por sucursal
        foreach (DB::table('empresa_negocios')->pluck('id_empresa_negocio') as $suc) {
            $ahora = now();
            DB::table('asistencia_turnos')->insert([
                ['id_empresa_negocio' => $suc, 'codigo' => 'M', 'descripcion' => 'MAÑANA (CON REFRIGERIO)', 'tipo' => 'TRABAJO', 'hora_entrada_1' => '08:00', 'hora_salida_1' => '13:00', 'hora_entrada_2' => '14:00', 'hora_salida_2' => '17:00', 'tolerancia_minutos' => 10, 'color' => '#2563eb', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['id_empresa_negocio' => $suc, 'codigo' => 'T', 'descripcion' => 'TARDE CORRIDO', 'tipo' => 'TRABAJO', 'hora_entrada_1' => '15:00', 'hora_salida_1' => '23:00', 'hora_entrada_2' => null, 'hora_salida_2' => null, 'tolerancia_minutos' => 10, 'color' => '#7c3aed', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['id_empresa_negocio' => $suc, 'codigo' => 'D', 'descripcion' => 'DESCANSO', 'tipo' => 'DESCANSO', 'hora_entrada_1' => null, 'hora_salida_1' => null, 'hora_entrada_2' => null, 'hora_salida_2' => null, 'tolerancia_minutos' => 0, 'color' => '#64748b', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['id_empresa_negocio' => $suc, 'codigo' => 'V', 'descripcion' => 'VACACIONES', 'tipo' => 'LEYENDA', 'hora_entrada_1' => null, 'hora_salida_1' => null, 'hora_entrada_2' => null, 'hora_salida_2' => null, 'tolerancia_minutos' => 0, 'color' => '#0d9488', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['id_empresa_negocio' => $suc, 'codigo' => 'DM', 'descripcion' => 'DESCANSO MÉDICO', 'tipo' => 'LEYENDA', 'hora_entrada_1' => null, 'hora_salida_1' => null, 'hora_entrada_2' => null, 'hora_salida_2' => null, 'tolerancia_minutos' => 0, 'color' => '#db2777', 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
            foreach (['TRÁFICO / TRANSPORTE', 'SALUD', 'PERMISO PERSONAL', 'COMISIÓN DE SERVICIO', 'OTRO'] as $m) {
                DB::table('asistencia_motivos')->insert(['id_empresa_negocio' => $suc, 'descripcion' => $m, 'estado' => 'Activo', 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }

        // Menú
        $urls = [
            'Marcar Asistencia' => '/asistencia', 'Motivos' => '/asistencia/motivos', 'Reporte Tareo' => '/asistencia/tareo',
            'Reporte de Jornadas (8h)' => '/asistencia/jornadas', 'Matriz de Turnos' => '/asistencia/matriz',
            'Gestionar Turnos' => '/asistencia/turnos', 'Configurar IP Local' => '/asistencia/configuracion',
        ];
        foreach ($urls as $nom => $url) {
            DB::table('modulos')->where('mod_nom', $nom)->where('mod_gen', 'Asistencia')->update(['mod_url' => $url]);
        }
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_gen', 'Asistencia')->update(['mod_url' => '#']);
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn('ip_asistencia'));
        foreach (['feriados', 'asistencia_motivos', 'asistencias', 'asistencia_horarios', 'asistencia_turnos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
