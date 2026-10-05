<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// SIRE (Registro de Ventas RVIE y de Compras RCE): credenciales API de SUNAT y solicitudes de descarga de propuestas
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Mismos nombres que el sistema antiguo; secreto y clave se guardan cifrados (cast 'encrypted' en el modelo)
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('sire_usuario', 30)->nullable();   // usuario SOL con permiso SIRE (vacío = el de facturación)
            $table->text('sire_clave')->nullable();
        });

        Schema::create('sire_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('IdEmpresa', 11);
            $table->string('libro', 6);                   // 140000 RVIE ventas | 080000 RCE compras
            $table->string('periodo', 6);                 // AAAAMM
            $table->string('num_ticket', 20)->nullable();
            $table->string('cod_estado', 2)->nullable();  // Anexo III: 01 cargado ... 06 terminado
            $table->string('des_estado', 60)->nullable();
            $table->string('archivo')->nullable();        // zip descargado (storage/app)
            $table->unsignedInteger('filas')->nullable();
            $table->string('mensaje')->nullable();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
            $table->index(['IdEmpresa', 'libro', 'periodo']);
        });

        // Menú SIRE: ventas, compras y credenciales
        DB::table('modulos')->where('mod_gen', 'SIRE')->where('mod_nom', 'Consultar SIRE')
            ->update(['mod_nom' => 'SIRE Ventas (RVIE)', 'mod_url' => '/sire/ventas']);
        DB::table('modulos')->where('mod_gen', 'SIRE')->where('mod_nom', 'Generar TXT')
            ->update(['mod_nom' => 'SIRE Compras (RCE)', 'mod_url' => '/sire/compras']);
        $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Credenciales SIRE', 'mod_url' => '/sire/credenciales', 'mod_gen' => 'SIRE']);

        foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario') as $userId) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_gen', 'SIRE')->where('mod_nom', 'Credenciales SIRE')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        DB::table('modulos')->where('mod_gen', 'SIRE')->where('mod_nom', 'SIRE Ventas (RVIE)')->update(['mod_nom' => 'Consultar SIRE', 'mod_url' => '#']);
        DB::table('modulos')->where('mod_gen', 'SIRE')->where('mod_nom', 'SIRE Compras (RCE)')->update(['mod_nom' => 'Generar TXT', 'mod_url' => '#']);
        Schema::dropIfExists('sire_solicitudes');
        Schema::table('empresa', fn(Blueprint $t) => $t->dropColumn(['client_id', 'client_secret', 'sire_usuario', 'sire_clave']));
    }
};
