<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->where('mod_nom', 'Punto Venta')->update(['mod_url' => '/punto-venta']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'Punto Venta')->update(['mod_url' => '#']);
    }
};
