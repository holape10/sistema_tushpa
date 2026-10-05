<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Notas de crédito (07) y débito (08) electrónicas.
 * Cada nota tiene su propia serie y correlativo por sucursal, una para facturas (F...) y otra para boletas (B...),
 * como exige SUNAT: la nota de una factura empieza con F y la de una boleta con B.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->string('SerNCF', 4)->default('FC01');          // nota de crédito de facturas
            $table->unsignedInteger('NumNCF')->default(0);
            $table->string('SerNCB', 4)->default('BC01');          // nota de crédito de boletas
            $table->unsignedInteger('NumNCB')->default(0);
            $table->string('SerNDF', 4)->default('FD01');          // nota de débito de facturas
            $table->unsignedInteger('NumNDF')->default(0);
            $table->string('SerNDB', 4)->default('BD01');          // nota de débito de boletas
            $table->unsignedInteger('NumNDB')->default(0);
        });

        // Comprobante anulado o modificado por una nota (se sigue viendo, pero marcado)
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            $table->string('anulado_nc', 30)->nullable();           // ej. "FC01-00000001" cuando una NC lo anuló por completo
            $table->index('IdCpe_cabecera_ref');
        });
        // Línea del comprobante original que corrige cada línea de la nota (para no devolver más de lo vendido)
        Schema::table('cpe_detalle', function (Blueprint $table) {
            $table->unsignedInteger('IdCpe_detalle_ref')->nullable();
        });

        // Las sucursales con varias series deben quedar distintas: FC01, FC02, ...
        $usadas = [];
        foreach (DB::table('empresa_negocios')->orderBy('id_empresa_negocio')->get() as $n) {
            $i = ($usadas[$n->IdEmpresa] ?? 0) + 1;
            $usadas[$n->IdEmpresa] = $i;
            if ($i > 1) {
                $sufijo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                DB::table('empresa_negocios')->where('id_empresa_negocio', $n->id_empresa_negocio)->update([
                    'SerNCF' => 'FC' . $sufijo, 'SerNCB' => 'BC' . $sufijo, 'SerNDF' => 'FD' . $sufijo, 'SerNDB' => 'BD' . $sufijo,
                ]);
            }
        }

        // Menú: Ventas > Notas de Crédito
        $mod = DB::table('modulos')->whereIn('mod_nom', ['Notas de Créditos', 'Notas de Crédito'])->where('mod_gen', 'Ventas')->first();
        if ($mod) {
            DB::table('modulos')->where('mod_id', $mod->mod_id)->update(['mod_nom' => 'Notas de Crédito/Débito', 'mod_url' => '/notas']);
        } elseif (!DB::table('modulos')->where('mod_url', '/notas')->exists()) {
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Notas de Crédito/Débito', 'mod_url' => '/notas', 'mod_gen' => 'Ventas']);
            foreach (DB::table('role_user')->whereIn('role_id', [2, 4])->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_url', '/notas')->update(['mod_nom' => 'Notas de Créditos', 'mod_url' => '#']);
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            $table->dropIndex(['IdCpe_cabecera_ref']);
            $table->dropColumn('anulado_nc');
        });
        Schema::table('cpe_detalle', fn(Blueprint $t) => $t->dropColumn('IdCpe_detalle_ref'));
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->dropColumn(['SerNCF', 'NumNCF', 'SerNCB', 'NumNCB', 'SerNDF', 'NumNDF', 'SerNDB', 'NumNDB']);
        });
    }
};
