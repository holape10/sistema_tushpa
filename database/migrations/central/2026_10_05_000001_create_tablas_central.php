<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Base central del multi-empresa. Se ejecuta aparte de las migraciones de cada cliente:
// php artisan central:instalar
return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('razon_social', 255);
            $table->string('nombre_comercial', 255)->nullable();
            $table->string('base_datos', 64)->unique();
            $table->string('estado', 15)->default('ACTIVO'); // ACTIVO | SUSPENDIDO
            $table->string('motivo_suspension', 150)->nullable();
            $table->string('plan', 50)->nullable();
            $table->date('vence_el')->nullable();
            $table->string('contacto_nombre', 120)->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->string('contacto_correo', 120)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('superadmins', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('email', 120)->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('ultimo_acceso')->nullable();
            $table->string('ultima_ip', 45)->nullable();
            $table->timestamps();
        });

        // Sesiones y caché del panel (en cada cliente viven en su propia base)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('superadmins');
        Schema::dropIfExists('clientes');
    }
};
