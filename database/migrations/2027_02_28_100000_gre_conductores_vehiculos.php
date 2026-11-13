<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conductores y vehículos de las guías (transporte privado): antes solo vivían dentro de cada guía y se copiaban de la
 * última. Ahora tienen su lista (se ven, se editan y se eligen al emitir) y se guardan solos con cada guía nueva.
 * Se llenan con los que ya estaban en las guías emitidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gre_conductores')) {
            Schema::create('gre_conductores', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio')->index();
                $t->string('tdicod', 2)->default('1');
                $t->string('num', 15);
                $t->string('nombres', 100);
                $t->string('apellidos', 100)->nullable();
                $t->string('licencia', 15)->nullable();
                $t->string('licencia_categoria', 20)->nullable();
                $t->date('licencia_vence')->nullable();
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'num']);
            });
        }
        if (! Schema::hasTable('gre_vehiculos')) {
            Schema::create('gre_vehiculos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio')->index();
                $t->string('placa', 10);
                $t->string('marca', 60)->nullable();
                $t->string('modelo', 60)->nullable();
                $t->string('color', 60)->nullable();
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'placa']);
            });
        }
        if (! Schema::hasTable('gre_cabecera')) {
            return;
        }

        // Los que ya están en las guías (de cada uno, los datos de su guía más reciente)
        $ahora = now();
        $guias = DB::table('gre_cabecera')->whereNotNull('cond_num')->where('cond_num', '<>', '')->orderByDesc('gre_id')
            ->get(['id_empresa_negocio', 'cond_tdicod', 'cond_num', 'cond_nombres', 'cond_apellidos', 'cond_licencia']);
        $vistos = [];
        foreach ($guias as $g) {
            $clave = $g->id_empresa_negocio.'|'.$g->cond_num;
            if (isset($vistos[$clave]) || trim((string) $g->cond_nombres) === '') {
                continue;
            }
            $vistos[$clave] = true;
            DB::table('gre_conductores')->insertOrIgnore(['id_empresa_negocio' => $g->id_empresa_negocio, 'tdicod' => $g->cond_tdicod ?: '1',
                'num' => mb_substr($g->cond_num, 0, 15), 'nombres' => mb_substr((string) $g->cond_nombres, 0, 100),
                'apellidos' => mb_substr((string) $g->cond_apellidos, 0, 100) ?: null, 'licencia' => mb_substr((string) $g->cond_licencia, 0, 15) ?: null,
                'created_at' => $ahora, 'updated_at' => $ahora]);
        }
        foreach (DB::table('gre_cabecera')->whereNotNull('placa')->where('placa', '<>', '')->select('id_empresa_negocio', 'placa')->distinct()->get() as $v) {
            DB::table('gre_vehiculos')->insertOrIgnore(['id_empresa_negocio' => $v->id_empresa_negocio, 'placa' => mb_substr(strtoupper($v->placa), 0, 10),
                'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gre_conductores');
        Schema::dropIfExists('gre_vehiculos');
    }
};
