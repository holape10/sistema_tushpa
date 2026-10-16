<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del panel admin:
 *  - puede_crear_usuarios: el dueño se lo da a quien quiera (el dueño siempre puede)
 *  - activo: un usuario desactivado ya no entra al panel
 *  - creado_por: quién lo creó
 * El correo holapesac@gmail.com queda como dueño del sistema.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('superadmins', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('superadmins', 'puede_crear_usuarios')) {
                $t->tinyInteger('puede_crear_usuarios')->default(0);
            }
            if (! $central->hasColumn('superadmins', 'activo')) {
                $t->tinyInteger('activo')->default(1);
            }
            if (! $central->hasColumn('superadmins', 'creado_por')) {
                $t->unsignedBigInteger('creado_por')->nullable();
            }
        });

        $db = DB::connection('central')->table('superadmins');
        $db->clone()->where('email', 'holapesac@gmail.com')->update(['es_dueno' => 1]);
        $db->clone()->where('es_dueno', 1)->update(['puede_crear_usuarios' => 1, 'activo' => 1]);
    }

    public function down(): void
    {
        Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn(['puede_crear_usuarios', 'activo', 'creado_por']));
    }
};
