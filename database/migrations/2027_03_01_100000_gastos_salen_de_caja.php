<?php

use App\Support\Gastos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los gastos al contado pagados en EFECTIVO salen de la caja del turno (como en el sistema antiguo): así los cuenta el
 * arqueo. Cada gasto guarda su salida de caja. Los ya registrados mientras su turno sigue abierto salen ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gastos') || ! Schema::hasTable('movimientoscaja')) {
            return;
        }
        if (! Schema::hasColumn('gastos', 'mov_caj_id')) {
            Schema::table('gastos', fn (Blueprint $t) => $t->unsignedInteger('mov_caj_id')->nullable());
        }

        // Los gastos en efectivo de los turnos que siguen abiertos (los de turnos cerrados ya se contaron así)
        $gastos = DB::table('gastos')->whereNull('mov_caj_id')->where('estado', 'Registrado')->where('origen', '!=', 'ANTIGUO')->get();
        foreach ($gastos as $g) {
            if (! Gastos::saleDeCaja($g)) {
                continue;
            }
            $turno = DB::table('turnos')->where('IdUsuario', $g->IdUsuario)->where('id_empresa_negocio', $g->id_empresa_negocio)
                ->where('estado', 'ABIERTO')->where('apertura', '<=', $g->created_at)->orderByDesc('id_turno')->first();
            if ($turno && $g->fecha >= substr((string) $turno->apertura, 0, 10)) {
                Gastos::sacarDeCaja($g, $turno, (string) $g->created_at);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gastos', 'mov_caj_id')) {
            Schema::table('gastos', fn (Blueprint $t) => $t->dropColumn('mov_caj_id'));
        }
    }
};
