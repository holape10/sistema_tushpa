<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->where('mod_nom', 'Sucursales')->update(['mod_url' => '/sucursales']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'Sucursales')->update(['mod_url' => '#']);
    }
};
