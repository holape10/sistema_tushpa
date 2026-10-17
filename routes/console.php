<?php

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Models\User;
use App\Support\Antiguo\CargadorSql;
use App\Support\ClinicaRecordatorios;
use App\Support\DemoDatos;
use App\Support\Sunat\RescateEnvios;
use App\Support\Tenancy\Provisionador;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------- Multi-empresa ----------------

Artisan::command('central:instalar', function () {
    $base = config('database.connections.central.database');
    // La base central se crea con la conexión del .env (sin base elegida todavía)
    config(['database.connections.central_sin_base' => array_merge(config('database.connections.central'), ['database' => null])]);
    DB::connection('central_sin_base')
        ->statement("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $this->call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);
    $this->info("Base central lista: {$base}. Ahora crea tu usuario con: php artisan superadmin:crear");
})->purpose('Crea la base central del multi-empresa y sus tablas');

Artisan::command('superadmin:crear', function () {
    $nombre = $this->ask('Nombre');
    $email = $this->ask('Correo');
    $password = $this->secret('Contraseña (mínimo 10 caracteres)');

    $v = Validator::make(compact('nombre', 'email', 'password'), [
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

    Superadmin::create(compact('nombre', 'email', 'password'));
    $this->info('Listo. Entra en '.config('tenancy.esquema').'://'.config('tenancy.subdominio_admin').'.'.(config('tenancy.dominio') ?: 'TU_DOMINIO').'/'.config('tenancy.ruta_admin'));
})->purpose('Crea un usuario del panel multi-empresa');

Artisan::command('demo:cargar {cuenta=demos : Subdominio o RUC de la empresa demo}', function (string $cuenta) {
    $cliente = Cliente::where('subdominio', $cuenta)->orWhere('ruc', $cuenta)->first();
    if (! $cliente) {
        $this->error("No hay una empresa con el subdominio o RUC {$cuenta}.");

        return 1;
    }
    Tenancy::conectar($cliente->base_datos);
    $admin = User::where('estusu', 1)->orderBy('IdUsuario')->get()->first(fn ($u) => $u->esAdmin());
    if (! $admin) {
        $this->error('La empresa no tiene un usuario administrador activo.');

        return 1;
    }
    $this->info("Cargando datos de ejemplo en {$cliente->razon_social} ({$cliente->base_datos})…");
    $resumen = (new DemoDatos($admin))->cargar();
    foreach ($resumen as $que => $n) {
        $this->line("  + {$n} {$que}");
    }
    $this->info($resumen ? 'Listo.' : 'Ya tenía todos los datos de ejemplo: no se agregó nada.');
})->purpose('Carga productos, clientes y datos de todos los rubros en la empresa demo (se puede repetir sin duplicar)');

Artisan::command('clientes:migrar {--ruc= : Solo este cliente}', function () {
    $clientes = Cliente::query()
        ->when($this->option('ruc'), fn ($q, $ruc) => $q->where('ruc', $ruc))
        ->orderBy('id')->get();

    if ($clientes->isEmpty()) {
        $this->warn('No hay clientes para migrar.');

        return 0;
    }

    $fallas = 0;
    foreach ($clientes as $cliente) {
        $this->line("<info>{$cliente->ruc}</info> {$cliente->razon_social} ({$cliente->base_datos})");
        try {
            $salida = Provisionador::migrar($cliente);
            $this->line('   '.(str_contains($salida, 'Nothing to migrate') ? 'Al día' : str_replace("\n", "\n   ", $salida)));
        } catch (Throwable $e) {
            $fallas++;
            $this->error('   '.$e->getMessage());
        }
    }

    $this->newLine();
    $fallas ? $this->error("{$fallas} cliente(s) con errores.") : $this->info("{$clientes->count()} cliente(s) al día.");

    return $fallas ? 1 : 0;
})->purpose('Corre las migraciones pendientes en la base de cada cliente');

Artisan::command('antiguo:cargar {archivo : Ruta del respaldo .sql o .sql.gz del sistema antiguo} {--ruc= : RUC de la empresa que lo importará}', function () {
    $archivo = $this->argument('archivo');
    if (! is_file($archivo)) {
        $this->error("No existe el archivo {$archivo}");

        return 1;
    }
    $ruc = preg_replace('/\D/', '', (string) $this->option('ruc'));
    if ($ruc === '') {
        $this->error('Indica el RUC de la empresa con --ruc= (la base queda visible solo para esa empresa en Importar Sistema Antiguo).');

        return 1;
    }
    $bd = 'antiguo_'.$ruc.'_'.now()->format('Ymd_His');
    $this->info("Cargando en la base {$bd}…");
    $inicio = microtime(true);
    $res = CargadorSql::cargar($archivo, $bd);
    $this->info(sprintf('Listo en %.1f s: %d sentencias ejecutadas, %d omitidas.', microtime(true) - $inicio, $res['ejecutadas'], $res['omitidas']));
    $this->line('Tablas: '.implode(', ', $res['tablas']));
    foreach ($res['errores'] as $e) {
        $this->warn('  '.$e);
    }
    $this->info('Ahora entra a Mantenimiento > Importar Sistema Antiguo y elige esa base.');

    return 0;
})->purpose('Carga un respaldo del sistema antiguo en una base temporal para importarlo');

// ---------------- Mantenimiento diario ----------------

Artisan::command('impresion:limpiar', function () {
    // La base principal (.env) y la de cada cliente del multi-empresa
    $bases = collect([Tenancy::baseActual()]);
    try {
        $bases = $bases->merge(Cliente::query()->pluck('base_datos'));
    } catch (Throwable $e) {
        // Sin base central (una sola empresa): solo la principal
    }

    foreach ($bases->filter()->unique() as $bd) {
        try {
            $n = Tenancy::en($bd, function () {
                if (! Schema::hasTable('cola_impresion')) {
                    return 0;
                }

                // Lo impreso o con error no sirve más; lo pendiente solo se guarda si es de las últimas 24 horas
                return DB::table('cola_impresion')
                    ->where(fn ($q) => $q->whereIn('estado', [2, 9])->orWhere('creado', '<', now()->subDay()))
                    ->delete();
            });
            $this->line("{$bd}: {$n} trabajo(s) de impresión borrados");
        } catch (Throwable $e) {
            $this->error("{$bd}: ".$e->getMessage());
        }
    }
})->purpose('Vacía la cola de impresión (lo impreso y lo viejo) en todas las empresas');

Schedule::command('impresion:limpiar')->dailyAt('04:00');

// Advertencias de pago del panel: al pasar la fecha límite (si se marcó "suspender automáticamente") la empresa se suspende
Artisan::command('clientes:suspender-vencidos', function () {
    if (! Tenancy::activa()) {
        return;
    }
    $vencidos = Cliente::where('estado', 'ACTIVO')->where('aviso_suspender', 1)
        ->whereNotNull('aviso_fecha')->where('aviso_fecha', '<', now()->toDateString())->get();
    foreach ($vencidos as $c) {
        $c->update(['estado' => 'SUSPENDIDO', 'suspendido_el' => now()->toDateString(),
            'motivo_suspension' => 'Falta de pago (venció el '.Carbon::parse($c->aviso_fecha)->format('d/m/Y').')']);
        $this->info("Suspendido: {$c->ruc} {$c->razon_social}");
    }

    // Registro desde la web: terminó la prueba gratis y no activó un plan
    $pruebas = Cliente::where('estado', 'ACTIVO')->whereNotNull('prueba_hasta')->where('prueba_hasta', '<', now()->toDateString())->get();
    foreach ($pruebas as $c) {
        $c->update(['estado' => 'SUSPENDIDO', 'suspendido_el' => now()->toDateString(),
            'motivo_suspension' => 'Terminó la prueba gratis ('.$c->prueba_hasta->format('d/m/Y').')']);
        $this->info("Prueba terminada: {$c->ruc} {$c->razon_social}");
    }
    $this->info(($vencidos->count() + $pruebas->count()).' empresa(s) suspendida(s).');
})->purpose('Suspende las empresas cuya advertencia de pago o prueba gratis venció');

Schedule::command('clientes:suspender-vencidos')->dailyAt('00:15');

Artisan::command('sunat:rescatar {--ruc= : Solo esta empresa}', function () {
    // Una sola empresa (sin multi-empresa) o cada empresa activa del panel
    $bases = Tenancy::activa()
        ? Cliente::where('estado', 'ACTIVO')->when($this->option('ruc'), fn ($q, $ruc) => $q->where('ruc', $ruc))->pluck('base_datos', 'ruc')
        : collect(['local' => null]);
    foreach ($bases as $ruc => $base) {
        try {
            $r = $base ? Tenancy::en($base, fn () => RescateEnvios::ejecutar()) : RescateEnvios::ejecutar();
        } catch (Throwable $e) {
            $this->error("{$ruc}: ".$e->getMessage());

            continue;
        }
        if ($r['enviados'] || $r['fallidos']) {
            $this->line("<info>{$ruc}</info> enviados {$r['enviados']} · aceptados {$r['aceptados']} · con problema {$r['fallidos']}");
            foreach ($r['detalle'] as $d) {
                $this->line("   {$d}");
            }
        }
    }
})->purpose('Envía a SUNAT las facturas (desde el día 2) y boletas (desde el día 5) que nadie envió y están por vencer');

// Cada hora de 7am a 10pm: lo que está por vencer el plazo de SUNAT se envía solo
Schedule::command('sunat:rescatar')->hourlyAt(20)->between('07:00', '22:00')->withoutOverlapping(50);

Artisan::command('clinica:recordatorios {--ruc= : Solo esta empresa}', function () {
    $bases = Tenancy::activa()
        ? Cliente::where('estado', 'ACTIVO')->when($this->option('ruc'), fn ($q, $ruc) => $q->where('ruc', $ruc))->pluck('base_datos', 'ruc')
        : collect(['local' => null]);
    foreach ($bases as $ruc => $base) {
        try {
            $r = $base ? Tenancy::en($base, fn () => ClinicaRecordatorios::ejecutar()) : ClinicaRecordatorios::ejecutar();
        } catch (Throwable $e) {
            $this->error("{$ruc}: ".$e->getMessage());

            continue;
        }
        if ($r['pacientes'] || $r['doctores'] || $r['errores']) {
            $this->line("<info>{$ruc}</info> pacientes {$r['pacientes']} · doctores {$r['doctores']} · con error {$r['errores']}");
        }
    }
})->purpose('Recuerda por WhatsApp/correo las citas de mañana a los pacientes y a sus doctores');

// Cada 15 minutos de 7am a 9:45pm: cada clínica envía desde la hora que eligió (por defecto 6pm)
Schedule::command('clinica:recordatorios')->everyFifteenMinutes()->between('07:00', '21:45')->withoutOverlapping(14);
