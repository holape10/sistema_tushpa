<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias cajas y barras, cada barra con su almacén:
 *  - impresion_agentes.id_almacen: de qué almacén sale lo que se cobra en esa PC (antes todo salía del predeterminado).
 *  - cpe_cabecera.equipo: en qué PC de caja se cobró cada venta (para el reporte por caja y barra).
 *    Las ventas anteriores se asignan por turno: la PC donde se imprimieron los comprobantes de ese turno.
 *  - Menú "Consumo por Caja y Barra" para los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('impresion_agentes') && ! Schema::hasColumn('impresion_agentes', 'id_almacen')) {
            Schema::table('impresion_agentes', fn (Blueprint $t) => $t->unsignedInteger('id_almacen')->nullable());
        }
        if (! Schema::hasColumn('cpe_cabecera', 'equipo')) {
            Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->string('equipo', 40)->nullable()->index());
        }
        $this->asignarVentasAnteriores();

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $id = DB::table('modulos')->where('mod_url', '/reportes/consumo-cajas')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/reportes/descuentos-cortesias')->value('mod_gen')
                ?? DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Consumo por Caja y Barra', 'mod_url' => '/reportes/consumo-cajas', 'mod_gen' => $grupo]);
        }
        $admins = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
            ->whereIn('m.mod_url', ['/comandas', '/punto-venta', '/comandas/punto-venta'])
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->where('r.role_id', 2)->pluck('mu.user_IdUsuario')->unique();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Cada turno es un cajero en una PC: la PC que más comprobantes de ese turno imprimió */
    private function asignarVentasAnteriores(): void
    {
        if (! Schema::hasTable('cola_impresion') || ! Schema::hasTable('impresion_agentes') || ! Schema::hasColumn('impresion_agentes', 'impresora_caja')) {
            return;
        }
        $pcs = DB::table('impresion_agentes')->whereNotNull('impresora_caja')->get(['id_empresa_negocio', 'pc', 'impresora_caja']);
        if ($pcs->isEmpty()) {
            return;
        }
        $porTurno = DB::table('cpe_cabecera as c')
            ->join('cola_impresion as q', fn ($j) => $j->where('q.tipo', 'COMPROBANTE')->on('q.id_empresa_negocio', '=', 'c.id_empresa_negocio')
                ->whereRaw("q.referencia = CONCAT(c.serdoc, '-', LPAD(c.numdoc, 8, '0'))"))
            ->whereNull('c.equipo')->whereNotNull('c.id_turno')
            ->groupBy('c.id_turno', 'c.id_empresa_negocio', 'q.id_impresora')
            ->get(['c.id_turno', 'c.id_empresa_negocio', 'q.id_impresora', DB::raw('COUNT(*) as n')])
            ->groupBy('id_turno');
        foreach ($porTurno as $turno => $impresoras) {
            $mas = $impresoras->sortByDesc('n')->first();
            $pc = $pcs->first(fn ($p) => (int) $p->id_empresa_negocio === (int) $mas->id_empresa_negocio && (int) $p->impresora_caja === (int) $mas->id_impresora);
            if ($pc) {
                DB::table('cpe_cabecera')->where('id_turno', $turno)->whereNull('equipo')->update(['equipo' => $pc->pc]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/reportes/consumo-cajas')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
        Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->dropColumn('equipo'));
        Schema::table('impresion_agentes', fn (Blueprint $t) => $t->dropColumn('id_almacen'));
    }
};
