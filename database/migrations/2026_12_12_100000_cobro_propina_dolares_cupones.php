<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro del restaurante:
 *  - Propina: la deja el cliente al pagar; no va en el comprobante (no es venta) y se reparte a los mozos (tabla propinas).
 *  - Dólares: con el tipo de cambio de la sucursal el cliente paga en US$; la venta sigue en soles y queda anotado
 *    cuánto se recibió en dólares (venta_medio_pago.moneda / monto_usd / tipo_cambio) para cuadrar la caja.
 *  - Cupones de descuento: un código (VERANO10) con su descuento, vigencia y usos; la venta guarda el cupón usado.
 *  - Menú "Cupones" y "Propinas" para los administradores del restaurante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'permite_propina')) {
                $t->boolean('permite_propina')->default(false);
            }
            if (! Schema::hasColumn('empresa_negocios', 'tipo_cambio_usd')) {
                $t->decimal('tipo_cambio_usd', 6, 3)->nullable();
            }
        });
        Schema::table('venta_medio_pago', function (Blueprint $t) {
            if (! Schema::hasColumn('venta_medio_pago', 'moneda')) {
                $t->char('moneda', 3)->nullable();
                $t->decimal('monto_usd', 12, 2)->nullable();
                $t->decimal('tipo_cambio', 6, 3)->nullable();
            }
        });
        if (! Schema::hasTable('propinas')) {
            Schema::create('propinas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('IdCpe_cabecera')->index();
                $t->unsignedInteger('id_turno')->nullable()->index();
                $t->unsignedInteger('id_med_pag')->nullable();
                $t->decimal('monto', 10, 2);
                $t->unsignedInteger('id_empresa_negocio')->index();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (! Schema::hasTable('cupones')) {
            Schema::create('cupones', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio');
                $t->string('codigo', 30);
                $t->string('descripcion', 120)->nullable();
                $t->char('tipo', 1)->default('P');            // P = porcentaje, S = soles
                $t->decimal('valor', 10, 2);
                $t->decimal('minimo', 10, 2)->default(0);      // consumo mínimo para usarlo
                $t->date('desde')->nullable();
                $t->date('hasta')->nullable();
                $t->unsignedInteger('usos_max')->nullable();   // vacío = sin límite
                $t->unsignedInteger('usos')->default(0);
                $t->boolean('activo')->default(true);
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'codigo']);
            });
        }
        if (! Schema::hasColumn('cpe_cabecera', 'cupon_id')) {
            Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->unsignedBigInteger('cupon_id')->nullable());
        }

        $this->menu([
            ['Cupones de descuento', '/cupones'],
            ['Propinas', '/reportes/propinas'],
        ]);
    }

    /** Opciones del menú para los administradores que ya usan Comandas */
    private function menu(array $opciones): void
    {
        if (! Schema::hasTable('modulos')) {
            return;
        }
        $grupo = DB::table('modulos')->where('mod_url', '/reportes/descuentos-cortesias')->value('mod_gen')
            ?? DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
        $admins = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
            ->whereIn('m.mod_url', ['/comandas', '/comandas/punto-venta'])
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->where('r.role_id', 2)->pluck('mu.user_IdUsuario')->unique();
        foreach ($opciones as [$nombre, $url]) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $grupo]);
            foreach ($admins as $usuario) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', ['/cupones', '/reportes/propinas'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->dropColumn('cupon_id'));
        Schema::dropIfExists('cupones');
        Schema::dropIfExists('propinas');
        Schema::table('venta_medio_pago', fn (Blueprint $t) => $t->dropColumn(['moneda', 'monto_usd', 'tipo_cambio']));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['permite_propina', 'tipo_cambio_usd']));
    }
};
