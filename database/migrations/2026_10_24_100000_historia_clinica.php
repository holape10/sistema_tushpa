<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historias clínicas (mismas tablas del sistema antiguo + lo que faltaba).
 *  - especialidad: con su servicio (producto que se cobra) y secciones extra: odontograma, veterinaria (mascota + vacunas).
 *  - historia_clinica: una por paciente. Persona = el cliente; mascota = el dueño es el cliente y la mascota va aquí.
 *  - atencion_clinica: cada consulta (signos vitales, motivo, examen, diagnóstico CIE-10, tratamiento, próxima cita).
 *  - receta_detalle: medicamentos de la receta. historia_vacunas: vacunas y desparasitaciones.
 *  - citas: agenda por doctor. El cobro es un pedido 'Clinica' que se cobra con la pantalla de caja.
 *  - Rol 10 = Doctor (escribe la historia). Recepción/caja agenda y cobra, pero no ve el detalle clínico.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('especialidad')) {
            Schema::create('especialidad', function (Blueprint $t) {
                $t->increments('esp_id');
                $t->string('esp_cod', 4)->nullable();
                $t->string('esp_des', 100);
                $t->unsignedInteger('IdProducto')->nullable();      // servicio que se cobra (CONSULTA MEDICINA GENERAL)
                $t->tinyInteger('odontograma')->default(0);
                $t->tinyInteger('veterinaria')->default(0);
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (!Schema::hasTable('historia_clinica')) {
            Schema::create('historia_clinica', function (Blueprint $t) {
                $t->increments('his_cli_id');
                $t->string('his_cli_cod', 20);                       // HC-000001
                $t->unsignedInteger('clicod')->index();              // paciente (o dueño de la mascota)
                $t->timestamp('his_cli_fec')->nullable()->useCurrent();
                $t->string('his_cli_est', 20)->default('REGISTRADO');
                $t->string('tipo', 10)->default('PERSONA');          // PERSONA | MASCOTA
                $t->string('mascota', 100)->nullable();
                $t->string('especie', 40)->nullable();
                $t->string('raza', 60)->nullable();
                $t->string('sexo', 1)->nullable();                   // M | F
                $t->date('fecha_nac')->nullable();
                $t->string('grupo_sanguineo', 5)->nullable();
                $t->string('ocupacion', 100)->nullable();
                $t->string('contacto_emergencia', 150)->nullable();
                $t->text('antecedentes')->nullable();                // personales y familiares (permanentes)
                $t->text('alergias')->nullable();
                $t->text('odontograma')->nullable();                 // estado actual de las piezas (JSON)
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unique(['id_empresa_negocio', 'his_cli_cod']);
            });
        }

        if (!Schema::hasTable('atencion_clinica')) {
            Schema::create('atencion_clinica', function (Blueprint $t) {
                $t->increments('ate_cli_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->date('ate_cli_fec');
                $t->time('ate_cli_hor')->nullable();
                $t->unsignedInteger('esp_id')->nullable();
                $t->unsignedInteger('doctor')->nullable();           // users.IdUsuario
                $t->string('pre_art', 10)->nullable();               // presión arterial 120/80
                $t->string('fre_car', 10)->nullable();               // frecuencia cardiaca
                $t->string('fre_res', 10)->nullable();               // frecuencia respiratoria
                $t->decimal('temperatura', 4, 1)->nullable();
                $t->unsignedTinyInteger('saturacion')->nullable();
                $t->decimal('peso', 6, 2)->nullable();
                $t->decimal('talla', 5, 2)->nullable();              // metros
                $t->text('mot_con')->nullable();                     // motivo de consulta
                $t->text('antecedente')->nullable();                 // enfermedad actual / anamnesis
                $t->text('alergia')->nullable();
                $t->text('int_qui')->nullable();                     // intervenciones quirúrgicas
                $t->text('exa_fis')->nullable();
                $t->string('cie10', 100)->nullable();                // códigos: J00, K29.7
                $t->text('diagnostico')->nullable();
                $t->text('tratamiento')->nullable();
                $t->text('examenes')->nullable();                    // exámenes auxiliares pedidos
                $t->text('indicaciones')->nullable();
                $t->text('odontograma')->nullable();                 // piezas trabajadas en esta atención (JSON)
                $t->date('pro_cit')->nullable();
                $t->string('ate_cli_est', 20)->default('PENDIENTE'); // PENDIENTE (borrador) | ATENDIDA
                $t->unsignedInteger('cit_id')->nullable();
                $t->unsignedInteger('ped_id')->nullable();           // pedido para cobrar en caja
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('creado')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('receta_detalle')) {
            Schema::create('receta_detalle', function (Blueprint $t) {
                $t->increments('rec_id');
                $t->unsignedInteger('ate_cli_id')->index();
                $t->string('medicamento', 150);
                $t->string('dosis', 80)->nullable();                 // 500 mg
                $t->string('frecuencia', 80)->nullable();            // cada 8 horas
                $t->string('duracion', 60)->nullable();              // 5 días
                $t->string('cantidad', 30)->nullable();              // 15 tabletas
                $t->string('indicaciones', 200)->nullable();
            });
        }

        if (!Schema::hasTable('historia_vacunas')) {
            Schema::create('historia_vacunas', function (Blueprint $t) {
                $t->increments('vac_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->unsignedInteger('ate_cli_id')->nullable();
                $t->date('fecha');
                $t->string('tipo', 15)->default('VACUNA');           // VACUNA | DESPARASITACION
                $t->string('nombre', 120);
                $t->string('lote', 40)->nullable();
                $t->date('proxima')->nullable();
            });
        }

        if (!Schema::hasTable('citas')) {
            Schema::create('citas', function (Blueprint $t) {
                $t->increments('cit_id');
                $t->unsignedInteger('his_cli_id')->nullable()->index();
                $t->unsignedInteger('clicod')->nullable();
                $t->string('paciente', 150);                         // nombre mostrado (también si aún no tiene historia)
                $t->string('telefono', 20)->nullable();
                $t->unsignedInteger('doctor')->nullable();
                $t->unsignedInteger('esp_id')->nullable();
                $t->date('fecha')->index();
                $t->time('hora');
                $t->unsignedSmallInteger('duracion')->default(30);   // minutos
                $t->string('motivo', 200)->nullable();
                $t->string('estado', 12)->default('PROGRAMADA');     // PROGRAMADA | CONFIRMADA | EN_ESPERA | ATENDIDA | NO_ASISTIO | CANCELADA
                $t->unsignedInteger('ate_cli_id')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamp('creado')->nullable()->useCurrent();
            });
        }

        if (!DB::table('roles')->where('id', 10)->exists()) {
            DB::table('roles')->insert(['id' => 10, 'name' => 'doctor', 'description' => 'Doctor']);
        }
        foreach ([['Historias Clínicas', '/clinica'], ['Agenda de Citas', '/clinica/agenda']] as [$nom, $url]) {
            if (!DB::table('modulos')->where('mod_url', $url)->exists()) {
                DB::table('modulos')->insert(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Clínica']);
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/clinica', '/clinica/agenda'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['citas', 'historia_vacunas', 'receta_detalle', 'atencion_clinica', 'historia_clinica', 'especialidad'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
