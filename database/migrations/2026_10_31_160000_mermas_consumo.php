<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mermas (lo que se bota, se malogra o se cae), historial de cambios de costo de los insumos y el reporte
 * de consumo de insumos. Mermas pasa al grupo Restaurante y deja de estar "en desarrollo".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mermas')) {
            Schema::create('mermas', function (Blueprint $t) {
                $t->increments('merma_id');
                $t->unsignedBigInteger('IdProducto')->index();
                $t->decimal('cantidad', 14, 4);
                $t->string('umecod', 3);
                $t->decimal('cantidad_base', 14, 4);
                $t->string('motivo', 40);
                $t->string('observacion', 200)->nullable();
                $t->decimal('costo', 12, 2)->default(0);
                $t->unsignedInteger('id_almacen')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->date('fecha')->index();
                $t->string('estado', 10)->default('ACTIVA');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('movimientos_productos', 'merma_id')) {
            Schema::table('movimientos_productos', fn (Blueprint $t) => $t->unsignedInteger('merma_id')->nullable()->index());
        }
        if (! Schema::hasTable('costo_historial')) {
            Schema::create('costo_historial', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('IdProducto')->index();
                $t->decimal('costo_anterior', 12, 2);
                $t->decimal('costo_nuevo', 12, 2);
                $t->string('origen', 20);
                $t->unsignedInteger('com_cab_id')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('created_at')->nullable()->index();
            });
        }

        // Menú: Mermas en Restaurante y los reportes nuevos (el de SUNAT faltaba en el menú)
        $existe = DB::table('modulos')->where('mod_nom', 'Mermas')->first();
        $existe
            ? DB::table('modulos')->where('mod_id', $existe->mod_id)->update(['mod_url' => '/mermas', 'mod_gen' => 'Restaurante'])
            : DB::table('modulos')->insert(['mod_nom' => 'Mermas', 'mod_url' => '/mermas', 'mod_gen' => 'Restaurante']);
        foreach ([['Reporte: Consumo de Insumos', '/reportes/consumo-insumos', 'Restaurante'], ['Reporte: SUNAT', '/reportes/sunat', 'Ventas']] as [$nom, $url, $gen]) {
            if (! DB::table('modulos')->where('mod_url', $url)->exists()) {
                DB::table('modulos')->insert(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => $gen]);
            }
        }

        // Se asignan a los administradores que ya usan algo parecido
        $this->asignar('/mesas', ['/mermas', '/reportes/consumo-insumos']);
        $this->asignar('/reportes/ventas', ['/reportes/sunat']);
    }

    /** Da los módulos nuevos a los administradores que tienen el módulo de referencia */
    private function asignar(string $referencia, array $nuevos): void
    {
        $ref = DB::table('modulos')->where('mod_url', $referencia)->value('mod_id');
        $ids = DB::table('modulos')->whereIn('mod_url', $nuevos)->pluck('mod_id');
        if (! $ref || $ids->isEmpty()) {
            return;
        }
        $admins = DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $ref)->where('r.role_id', 2)->distinct()->pluck('mu.user_IdUsuario');
        foreach ($admins as $usuario) {
            foreach ($ids as $modulo) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $modulo)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $modulo, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mermas');
        Schema::dropIfExists('costo_historial');
        Schema::table('movimientos_productos', fn (Blueprint $t) => $t->dropColumn('merma_id'));
        DB::table('modulos')->where('mod_url', '/mermas')->update(['mod_url' => '#', 'mod_gen' => 'Mantenimiento']);
        DB::table('modulos')->whereIn('mod_url', ['/reportes/consumo-insumos', '/reportes/sunat'])->delete();
    }
};
