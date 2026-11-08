<?php

use App\Jobs\AvanzarDescargasEstudio;
use App\Jobs\ProbarCola;
use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Models\Central\SuperadminCobro;
use App\Models\User;
use App\Support\Antiguo\CargadorSql;
use App\Support\AvisosCelular;
use App\Support\ClinicaRecordatorios;
use App\Support\Colas;
use App\Support\DemoDatos;
use App\Support\Estudio;
use App\Support\EstudioDescargas;
use App\Support\GeoIp;
use App\Support\PadronRuc;
use App\Support\PanelDueno;
use App\Support\Procesos;
use App\Support\Promociones;
use App\Support\Sunat\RescateEnvios;
use App\Support\Tenancy\CertificadosClientes;
use App\Support\Tenancy\DatosCobro;
use App\Support\Tenancy\Inventario;
use App\Support\Tenancy\Provisionador;
use App\Support\Tenancy\Respaldos;
use App\Support\Tenancy\Tenancy;
use App\Support\Vencimientos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

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
    // Una sucursal por rubro con solo sus datos: al elegir el rubro en el login se entra ahí
    $this->info('Sucursales por rubro:');
    foreach (DemoDatos::porRubros($admin) as $sucursal => $nuevos) {
        $this->line("  · {$sucursal}".($nuevos ? ': '.collect($nuevos)->map(fn ($n, $que) => "{$n} {$que}")->implode(', ') : ': al día'));
    }
    $this->info('Listo.');
})->purpose('Carga productos, clientes y datos de todos los rubros en la empresa demo, y una sucursal con solo sus datos por cada rubro (se puede repetir sin duplicar)');

Artisan::command('estudio:activar {ruc : RUC de la empresa que será el estudio contable} {--cliente= : RUC del cliente TUSHPA en cuya base (vacío = la base actual)}', function () {
    $activar = function () {
        Estudio::activar($this->argument('ruc'));
        $this->info('Estudio contable activado con la empresa '.$this->argument('ruc').'. Su administrador ya ve "Empresas del estudio".');
    };
    if ($ruc = $this->option('cliente')) {
        $cliente = Cliente::where('ruc', $ruc)->firstOrFail();
        Tenancy::en($cliente->base_datos, $activar);
    } else {
        $activar();
    }
})->purpose('Convierte una base en estudio contable: su empresa es el estudio y registra dentro las empresas de sus clientes');

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

// Copias de seguridad automáticas: cada noche, de todas las bases (sistema, panel y empresas); quedan las últimas 7 de cada una
Artisan::command('respaldos:todas', function () {
    $r = Respaldos::todas('automatica');
    foreach ($r['ok'] as $ruta) {
        $this->line('✔ '.basename($ruta));
    }
    foreach ($r['fallas'] as $base => $error) {
        $this->error("✘ {$base}: {$error}");
    }
    $borradas = collect(array_keys(Respaldos::bases()))->sum(fn ($base) => Respaldos::limpiarAutomaticas($base));
    $this->info(count($r['ok']).' copia(s) listas; '.$borradas.' automática(s) vieja(s) borradas.');
})->purpose('Saca una copia de seguridad de todas las bases y borra las automáticas más viejas');

Schedule::command('respaldos:todas')->dailyAt('03:00')->withoutOverlapping(120);

// Padrón reducido del RUC (SUNAT lo publica ~3:08 a. m.): consultas de RUC y DNI propias, sin depender de otra API.
// En el servidor tarda unas 2 h 30 min: empieza 3:15 para terminar antes de que abran los negocios
Artisan::command('padron:actualizar {--limite= : Solo para pruebas: carga las primeras N filas}', function () {
    $this->info('Descargando el padrón de SUNAT (unos 380 MB)…');
    $limite = $this->option('limite') ? (int) $this->option('limite') : null;
    try {
        $r = PadronRuc::actualizar(fn (int $n) => $this->line('  '.number_format($n).' RUC cargados…'), $limite, fn (string $m) => $this->line($m));
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info('Listo: '.number_format($r['filas']).' RUC en '.gmdate('H:i:s', $r['segundos']).'.');
})->purpose('Descarga el padrón reducido del RUC de SUNAT y lo carga para las consultas de RUC y DNI');

Schedule::command('padron:actualizar')->dailyAt('03:15')->withoutOverlapping(240)->runInBackground();

// Ubicación aproximada por IP (DB-IP Lite, sale cada mes): para mostrar en la web el contacto del partner de cada región
Artisan::command('geoip:actualizar', function () {
    try {
        $r = GeoIp::actualizar();
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info("Listo: {$r['filas']} rangos de IP del Perú ({$r['archivo']}).");
})->purpose('Descarga la base de ubicación por IP (DB-IP Lite) y carga los rangos del Perú');

Schedule::command('geoip:actualizar')->monthlyOn(6, '05:30')->withoutOverlapping(60);

// Las imágenes de promociones subidas antes de achicarlas: pasan a WEBP chico para que tushpa.app cargue rápido
Artisan::command('promos:optimizar', function () {
    $n = 0;
    foreach (Promociones::db()->whereNotNull('imagen')->where('imagen', 'not like', '%.webp')->get(['id', 'imagen']) as $p) {
        $nueva = Promociones::optimizarImagen($p->imagen);
        if ($nueva !== $p->imagen) {
            Promociones::db()->where('id', $p->id)->update(['imagen' => $nueva]);
            $this->line("{$p->imagen} → {$nueva} (".round(filesize(public_path($nueva)) / 1024).' KB)');
            $n++;
        }
    }
    $this->info("{$n} imagen(es) optimizada(s).");
})->purpose('Achica y pasa a WEBP las imágenes de las promociones de la web');

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

Artisan::command('push:claves {--forzar : Cambiarlas aunque ya existan (los celulares tendrán que activar avisos de nuevo)}', function () {
    $env = base_path('.env');
    $texto = file_get_contents($env);
    if (preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $texto) && ! $this->option('forzar')) {
        $this->info('Las claves de avisos al celular ya existen.');

        return;
    }
    $claves = VAPID::createVapidKeys();
    foreach (['VAPID_PUBLIC_KEY' => $claves['publicKey'], 'VAPID_PRIVATE_KEY' => $claves['privateKey']] as $nombre => $valor) {
        $texto = preg_match("/^{$nombre}=.*$/m", $texto)
            ? preg_replace("/^{$nombre}=.*$/m", "{$nombre}={$valor}", $texto)
            : rtrim($texto, "\n")."\n{$nombre}={$valor}\n";
    }
    file_put_contents($env, $texto);
    $this->info('Claves de avisos al celular creadas en el .env.');
})->purpose('Crea las claves VAPID de los avisos al celular del mozo (una sola vez)');

Artisan::command('dueno:resumen {--noche : Negocios que atienden de noche (resumen de la noche que terminó)} {--ruc= : Solo esta empresa}', function () {
    $noche = (bool) $this->option('noche');
    // De día: el resumen de hoy (a las 11 pm) · de noche: el de la noche que empezó ayer a mediodía (a las 12:05 pm)
    $fecha = $noche ? now()->subDay() : now();
    $clientes = Tenancy::activa()
        ? Cliente::where('estado', 'ACTIVO')->when($this->option('ruc'), fn ($q, $ruc) => $q->where('ruc', $ruc))->get()
        : collect([null]);
    $enviados = 0;
    foreach ($clientes as $cliente) {
        $enviar = function () use ($noche, $fecha, &$enviados) {
            if (! Schema::hasTable('push_suscripciones') || ! Schema::hasColumn('push_suscripciones', 'dueno')) {
                return;
            }
            $sucursales = DB::table('empresa_negocios')->where('atiende_de_noche', $noche)
                ->whereIn('id_empresa_negocio', DB::table('push_suscripciones')->where('dueno', true)->select('id_empresa_negocio'))
                ->pluck('id_empresa_negocio');
            foreach ($sucursales as $suc) {
                $enviados += (int) PanelDueno::avisarCierre((int) $suc, $fecha);
            }
            AvisosCelular::enviar();   // ahora, con la base de esta empresa (no al terminar el comando)
        };
        try {
            if ($cliente) {
                // Los enlaces del aviso abren el sistema de esta empresa (su subdominio)
                URL::forceRootUrl($cliente->url());
                Tenancy::en($cliente->base_datos, $enviar);
            } else {
                $enviar();
            }
        } catch (Throwable $e) {
            $this->error(($cliente?->ruc ?? 'local').': '.$e->getMessage());
        }
    }
    URL::forceRootUrl(null);
    $this->info("Resúmenes enviados: {$enviados}");
})->purpose('Manda al celular de los dueños y socios el resumen del día (o de la noche)');

// Resumen al celular del dueño: los negocios de día a las 11 pm, los de noche a las 12:05 pm (cuando cierra la noche)
Schedule::command('dueno:resumen')->dailyAt('23:00')->withoutOverlapping(30);
Schedule::command('dueno:resumen --noche')->dailyAt('12:05')->withoutOverlapping(30);

Artisan::command('cobros:optimizar-qr', function () {
    if (! Tenancy::activa() || ! Schema::connection('central')->hasTable('superadmin_cobros')) {
        return;
    }
    $n = 0;
    foreach (SuperadminCobro::whereNotNull('qr')->get() as $cobro) {
        // Solo los pesados (más de 150 KB): los demás ya cargan rápido
        if (! is_file(public_path($cobro->qr)) || filesize(public_path($cobro->qr)) < 150 * 1024) {
            continue;
        }
        $nueva = DatosCobro::optimizar($cobro->qr);
        if ($nueva !== $cobro->qr) {
            $cobro->update(['qr' => $nueva]);
            $n++;
        }
    }
    $this->info("QR optimizados: {$n}");
})->purpose('Achica los QR de pago pesados (pantalla de suspendido) a WebP');

Artisan::command('certificados:revisar', function () {
    if (! Tenancy::activa()) {
        return;
    }
    $n = CertificadosClientes::avisar();
    $this->info($n ? "Aviso por Telegram: {$n} empresa(s) con el certificado por vencer o vencido." : 'Ningún certificado por vencer.');
})->purpose('Copia al panel el vencimiento del certificado digital de cada empresa y avisa por Telegram los que están por vencer');

// Cada mañana: certificados digitales por vencer (aviso al Telegram del dueño)
Schedule::command('certificados:revisar')->dailyAt('08:00')->withoutOverlapping(30);

// ---------------- Limpieza de lo de prueba (a mano, con cuidado) ----------------

Artisan::command('clientes:inventario', function () {
    $empresas = Inventario::empresas();
    $this->info(count($empresas).' empresa(s) en el panel:');
    $this->table(['ID', 'RUC', 'Empresa', 'Estado', 'Marca', 'Base', 'MB', 'Comprobantes', 'Último', 'Creada'], array_map(fn ($e) => [
        $e['id'], $e['ruc'], mb_strimwidth($e['nombre'], 0, 34, '…'), $e['estado'], $e['marca'], $e['existe'] ? $e['base'] : $e['base'].' (no existe)',
        $e['mb'], $e['comprobantes'] ?? '-', $e['ultimo'] ?? '-', $e['creada'],
    ], $empresas));

    $tamanos = Inventario::tamanos();
    $huerfanas = Inventario::huerfanas();
    $this->newLine();
    if (! $huerfanas) {
        $this->info('No hay bases huérfanas en el servidor.');

        return 0;
    }
    $this->warn(count($huerfanas).' base(s) en el servidor que no usa ninguna empresa (huérfanas):');
    $this->table(['Base', 'MB'], array_map(fn ($b) => [$b, $tamanos[$b] ?? 0], $huerfanas));
    $this->warn('OJO: "huérfana" = este sistema no la usa. Si en este servidor tienes OTROS sistemas, sus bases también salen aquí: no las borres.');
    $this->line('Para borrar una (saca su respaldo antes): php artisan bases:eliminar-huerfana NOMBRE_DE_LA_BASE');

    return 0;
})->purpose('Lista las empresas (tamaño, comprobantes, último uso) y las bases que no usa nadie. Solo lee: no cambia nada');

Artisan::command('bases:eliminar-huerfana {base : Nombre exacto de la base} {--si : Confirmar (sin esto solo muestra lo que haría)}', function (string $base) {
    if ($motivo = Inventario::motivoParaNoBorrar($base, Inventario::protegidas(), Inventario::basesDelServidor())) {
        $this->error("No se borra {$base}: {$motivo}");

        return 1;
    }
    $mb = Inventario::tamanos()[$base] ?? 0;
    if (! $this->option('si')) {
        $this->warn("Se sacaría un respaldo de {$base} ({$mb} MB) y luego se borraría. Para hacerlo de verdad agrega --si");

        return 0;
    }
    try {
        $respaldo = Inventario::eliminarHuerfana($base);
    } catch (Throwable $e) {
        $this->error('No se borró nada: '.$e->getMessage());

        return 1;
    }
    $this->info("Base {$base} borrada. Respaldo: {$respaldo}");

    return 0;
})->purpose('Borra una base que no usa ninguna empresa, siempre con su respaldo antes (nunca las del sistema, del panel ni de una empresa)');

// ---------------------------------------------------------------- Trabajador de colas (deploy/colas-instalar.sh)
Artisan::command('colas:estado', function () {
    $e = Colas::estado();
    $this->line($e['vivo'] ? '<info>✔ El trabajador de colas está trabajando</info> (último latido hace '.$e['latido'].' s)'
        : '<comment>✘ No hay trabajador de colas corriendo</comment>'.($e['latido'] !== null ? ' (último latido hace '.$e['latido'].' s)' : ' (nunca ha corrido)')
            ."\n  No pasa nada: las tareas se hacen en el momento, como siempre.");
    $this->line('  Tareas esperando en la cola: '.($e['pendientes'] ?? 'la cola aún no existe (falta migrar la base central)'));
    $this->line('  Tareas que fallaron: '.($e['fallidas'] ?? '-'));
})->purpose('Dice si el trabajador de colas está corriendo y cuántas tareas esperan');

Artisan::command('colas:probar', function () {
    $marca = (string) Str::uuid();
    @unlink(storage_path('framework/cola-prueba'));
    Bus::dispatchToQueue((new ProbarCola($marca))->onConnection('database'));
    $this->line('Mandé una tarea de prueba a la cola. Esperando que el trabajador la atienda (hasta 30 segundos)…');
    for ($i = 0; $i < 30; $i++) {
        sleep(1);
        if (@file_get_contents(storage_path('framework/cola-prueba')) === $marca) {
            $this->info('✔ El trabajador la atendió en '.($i + 1).' s: la cola funciona.');

            return 0;
        }
    }
    $this->error('✘ Nadie atendió la tarea en 30 s. Revisa: systemctl status tushpa-colas');

    return 1;
})->purpose('Manda una tarea de prueba y confirma que el trabajador de colas la atiende');

Artisan::command('procesos:limpiar', function () {
    $this->info(Procesos::limpiar().' archivo(s) de procesos vencidos borrados.');
})->purpose('Borra los archivos listos para descargar que ya pasaron sus 24 horas');

Schedule::command('procesos:limpiar')->dailyAt('04:30');

// Cronograma de vencimientos SUNAT: el del año y, desde octubre, el del siguiente (la SUNAT lo publica en diciembre)
Artisan::command('sunat:cronograma {anio? : Año a descargar (por defecto el actual y, desde octubre, el siguiente)}', function () {
    $anios = $this->argument('anio') ? [(int) $this->argument('anio')]
        : array_filter([now()->year, now()->month >= 10 ? now()->year + 1 : null]);
    $fallo = 0;
    foreach ($anios as $anio) {
        if (! $this->argument('anio') && Vencimientos::tieneOficial($anio)) {
            $this->line("{$anio}: ya está el cronograma oficial.");

            continue;
        }
        try {
            $this->line(Vencimientos::descargar($anio)
                ? "<info>{$anio}: cronograma oficial descargado de la SUNAT.</info>"
                : "{$anio}: la SUNAT aún no lo publica; se usan las fechas calculadas.");
        } catch (Throwable $e) {
            $this->error("{$anio}: {$e->getMessage()}");
            $fallo = 1;
        }
    }

    return $fallo;
})->purpose('Descarga el cronograma de vencimientos mensuales de la web de la SUNAT');

Schedule::command('sunat:cronograma')->dailyAt('06:20')->withoutOverlapping(10);

// Estudio contable: descarga automática del SIRE y de los XML de compras (el día del mes que elige el estudio)
Artisan::command('estudio:descargas {--ruc= : Solo este estudio}', function () {
    $clientes = Tenancy::activa()
        ? Cliente::where('estado', 'ACTIVO')->when($this->option('ruc'), fn ($q, $ruc) => $q->where('ruc', $ruc))->get()
        : collect([null]);
    $mandados = 0;
    foreach ($clientes as $cliente) {
        $hayQueHacer = function () {
            if (! Schema::hasTable('estudio_programacion') || ! Estudio::activo()) {
                return false;
            }

            return EstudioDescargas::toca() || DB::table('estudio_descargas')->whereIn('estado', ['PENDIENTE', 'PROCESO'])->exists();
        };
        try {
            $toca = $cliente ? Tenancy::en($cliente->base_datos, $hayQueHacer) : $hayQueHacer();
            Estudio::olvidar();
            if ($toca) {
                Colas::despachar(new AvanzarDescargasEstudio($cliente?->base_datos ?? Tenancy::baseActual(), $cliente?->id));
                $mandados++;
            }
        } catch (Throwable $e) {
            $this->error(($cliente?->ruc ?? 'local').': '.$e->getMessage());
        }
    }
    $this->info("{$mandados} estudio(s) con descargas en curso.");
})->purpose('Avanza la descarga automática del SIRE y los XML de los estudios contables');

Schedule::command('estudio:descargas')->everyTenMinutes()->withoutOverlapping(30);
