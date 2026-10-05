<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------- Multi-empresa ----------------

Artisan::command('central:instalar', function () {
    $base = config('database.connections.central.database');
    // La base central se crea con la conexión del .env (sin base elegida todavía)
    config(['database.connections.central_sin_base' => array_merge(config('database.connections.central'), ['database' => null])]);
    \Illuminate\Support\Facades\DB::connection('central_sin_base')
        ->statement("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $this->call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);
    $this->info("Base central lista: {$base}. Ahora crea tu usuario con: php artisan superadmin:crear");
})->purpose('Crea la base central del multi-empresa y sus tablas');

Artisan::command('superadmin:crear', function () {
    $nombre = $this->ask('Nombre');
    $email = $this->ask('Correo');
    $password = $this->secret('Contraseña (mínimo 10 caracteres)');

    $v = \Illuminate\Support\Facades\Validator::make(compact('nombre', 'email', 'password'), [
        'nombre' => 'required|string|max:120',
        'email' => 'required|email|unique:central.superadmins,email',
        'password' => 'required|string|min:10',
    ]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $e) {
            $this->error($e);
        }
        return 1;
    }

    \App\Models\Central\Superadmin::create(compact('nombre', 'email', 'password'));
    $this->info("Listo. Entra en " . config('tenancy.esquema') . '://' . config('tenancy.subdominio_admin') . '.' . (config('tenancy.dominio') ?: 'TU_DOMINIO') . '/' . config('tenancy.ruta_admin'));
})->purpose('Crea un usuario del panel multi-empresa');

Artisan::command('clientes:migrar {--ruc= : Solo este cliente}', function () {
    $clientes = \App\Models\Central\Cliente::query()
        ->when($this->option('ruc'), fn($q, $ruc) => $q->where('ruc', $ruc))
        ->orderBy('id')->get();

    if ($clientes->isEmpty()) {
        $this->warn('No hay clientes para migrar.');
        return 0;
    }

    $fallas = 0;
    foreach ($clientes as $cliente) {
        $this->line("<info>{$cliente->ruc}</info> {$cliente->razon_social} ({$cliente->base_datos})");
        try {
            $salida = \App\Support\Tenancy\Provisionador::migrar($cliente);
            $this->line('   ' . (str_contains($salida, 'Nothing to migrate') ? 'Al día' : str_replace("\n", "\n   ", $salida)));
        } catch (\Throwable $e) {
            $fallas++;
            $this->error('   ' . $e->getMessage());
        }
    }

    $this->newLine();
    $fallas ? $this->error("{$fallas} cliente(s) con errores.") : $this->info("{$clientes->count()} cliente(s) al día.");
    return $fallas ? 1 : 0;
})->purpose('Corre las migraciones pendientes en la base de cada cliente');

Artisan::command('antiguo:cargar {archivo : Ruta del respaldo .sql o .sql.gz del sistema antiguo} {--ruc= : RUC de la empresa que lo importará}', function () {
    $archivo = $this->argument('archivo');
    if (!is_file($archivo)) {
        $this->error("No existe el archivo {$archivo}");
        return 1;
    }
    $ruc = preg_replace('/\D/', '', (string) $this->option('ruc'));
    if ($ruc === '') {
        $this->error('Indica el RUC de la empresa con --ruc= (la base queda visible solo para esa empresa en Importar Sistema Antiguo).');
        return 1;
    }
    $bd = 'antiguo_' . $ruc . '_' . now()->format('Ymd_His');
    $this->info("Cargando en la base {$bd}…");
    $inicio = microtime(true);
    $res = \App\Support\Antiguo\CargadorSql::cargar($archivo, $bd);
    $this->info(sprintf('Listo en %.1f s: %d sentencias ejecutadas, %d omitidas.', microtime(true) - $inicio, $res['ejecutadas'], $res['omitidas']));
    $this->line('Tablas: ' . implode(', ', $res['tablas']));
    foreach ($res['errores'] as $e) {
        $this->warn('  ' . $e);
    }
    $this->info('Ahora entra a Mantenimiento > Importar Sistema Antiguo y elige esa base.');
    return 0;
})->purpose('Carga un respaldo del sistema antiguo en una base temporal para importarlo');
