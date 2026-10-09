<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Inicio" deja de abrir el dashboard: ahora abre su propia pantalla con los accesos del menú del usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->where('mod_nom', 'Inicio')->where('mod_url', '/dashboard')->update(['mod_url' => '/inicio']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'Inicio')->where('mod_url', '/inicio')->update(['mod_url' => '/dashboard']);
    }
};
