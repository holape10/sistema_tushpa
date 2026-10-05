<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->where('mod_nom', 'PV Móvil')->update(['mod_url' => '/pv-movil']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'PV Móvil')->update(['mod_url' => '#']);
    }
};
