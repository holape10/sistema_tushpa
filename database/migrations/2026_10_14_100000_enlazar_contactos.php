<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contactos: pantallas de clientes y proveedores
return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->where('mod_gen', 'Contactos')->where('mod_nom', 'Clientes')->update(['mod_url' => '/clientes']);
        DB::table('modulos')->where('mod_gen', 'Contactos')->where('mod_nom', 'Proveedores')->update(['mod_url' => '/proveedores']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_gen', 'Contactos')->whereIn('mod_nom', ['Clientes', 'Proveedores'])->update(['mod_url' => '#']);
    }
};
