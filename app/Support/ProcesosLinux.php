<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Los programas que corren en el VPS, leídos de /proc (solo lectura: no ejecuta comandos ni detiene nada), agrupados
 * por programa (htop repite una línea por cada hilo) y explicados en palabras simples: qué es, cuánta memoria y
 * procesador usa, y si es necesario, opcional o hay que revisarlo.
 */
class ProcesosLinux
{
    /** Programa => [nombre simple, qué hace, necesario | opcional] */
    public const CONOCIDOS = [
        'mysqld' => ['MySQL (base de datos)', 'Guarda los datos de todos tus clientes. Usa mucha RAM a propósito (su caché) para ir rápido.', 'necesario'],
        'mariadbd' => ['MariaDB (base de datos)', 'Guarda los datos de todos tus clientes.', 'necesario'],
        'httpd' => ['Apache (servidor web)', 'Recibe las visitas a las páginas del sistema y se las pasa a PHP.', 'necesario'],
        'nginx' => ['Nginx (servidor web)', 'Recibe las visitas a las páginas del sistema.', 'necesario'],
        'php-fpm' => ['PHP (el sistema TUSHPA)', 'Arma cada página del sistema. Hay varios para atender a muchos usuarios a la vez.', 'necesario'],
        'systemd' => ['Systemd (corazón de Linux)', 'Arranca y vigila todos los servicios del servidor.', 'necesario'],
        'systemd-journald' => ['Registros del sistema', 'Guarda los mensajes y errores de Linux.', 'necesario'],
        'systemd-udevd' => ['Detector de dispositivos', 'Reconoce discos y tarjetas de red.', 'necesario'],
        'systemd-logind' => ['Control de sesiones', 'Lleva quién entra al servidor.', 'necesario'],
        'systemd-userdbd' => ['Usuarios del sistema', 'Consulta los usuarios de Linux.', 'necesario'],
        'systemd-resolved' => ['Resolución de nombres', 'Traduce dominios (sunat.gob.pe) a direcciones.', 'necesario'],
        'dbus-broker' => ['Correo interno de Linux', 'Los programas de Linux se comunican por aquí.', 'necesario'],
        'dbus-broker-lau' => ['Correo interno de Linux', 'Los programas de Linux se comunican por aquí.', 'necesario'],
        'dbus-daemon' => ['Correo interno de Linux', 'Los programas de Linux se comunican por aquí.', 'necesario'],
        'sshd' => ['SSH (tu entrada por consola)', 'Te deja entrar al servidor por consola. Si se detiene, te quedas afuera.', 'necesario'],
        'crond' => ['Reloj de tareas', 'Corre las tareas programadas: envíos automáticos a SUNAT, respaldos, padrón.', 'necesario'],
        'cron' => ['Reloj de tareas', 'Corre las tareas programadas: envíos automáticos a SUNAT, respaldos, padrón.', 'necesario'],
        'chronyd' => ['Hora exacta', 'Mantiene el reloj en hora: SUNAT revisa la hora de las firmas.', 'necesario'],
        'firewalld' => ['Cortafuegos', 'Decide qué puertos del servidor se abren a internet.', 'necesario'],
        'fail2ban-server' => ['Fail2ban (vigilante)', 'Bloquea a quien intenta adivinar claves por SSH o el sistema.', 'necesario'],
        'NetworkManager' => ['Conexión a internet', 'Maneja la red del VPS.', 'necesario'],
        'irqbalance' => ['Reparto entre núcleos', 'Reparte el trabajo entre los núcleos del procesador.', 'necesario'],
        'auditd' => ['Auditoría de seguridad', 'Anota lo que pasa en el sistema por seguridad.', 'necesario'],
        'agetty' => ['Consola de emergencia', 'La consola del panel de Contabo, por si se cae la red.', 'necesario'],
        'polkitd' => ['Permisos del sistema', 'Decide quién puede hacer tareas de administrador.', 'necesario'],
        'rsyslogd' => ['Registros del sistema', 'Guarda los registros de Linux en archivos.', 'necesario'],
        'redis-server' => ['Redis (memoria rápida)', 'Guarda datos temporales en memoria.', 'necesario'],
        'supervisord' => ['Supervisor', 'Mantiene vivos los trabajadores en segundo plano.', 'necesario'],
        'tuned' => ['Ajuste de rendimiento', 'Ajusta el servidor según el uso. Gasta poco.', 'opcional'],
        'gssproxy' => ['GSS Proxy (redes de empresa)', 'Para redes con Kerberos o NFS: TUSHPA no lo usa. Se puede apagar, pero ahorra muy poco.', 'opcional'],
        'qemu-ga' => ['Agente del VPS', 'Deja que el panel de Contabo hable con el servidor.', 'opcional'],
        'htop' => ['htop (monitor)', 'El monitor de procesos de la consola. Se cierra al salir (F10).', 'opcional'],
        'top' => ['top (monitor)', 'Un monitor de procesos de la consola.', 'opcional'],
        'bash' => ['Consola abierta', 'Una sesión de consola abierta (la tuya por SSH).', 'opcional'],
        'sftp-server' => ['Transferencia de archivos', 'Se abre al copiar archivos al servidor (WinSCP, FileZilla).', 'opcional'],
        'certbot' => ['Certificados https', 'Renueva los certificados de seguridad de los dominios.', 'necesario'],
    ];

    /** Cuánto es «mucho» para avisar de algo desconocido o de un PHP de consola */
    private const MB_REVISAR = 300;

    private const CPU_REVISAR = 50.0;

    /**
     * Los programas agrupados, de mayor a menor memoria. CPU = % del procesador entero medido en $esperaMs.
     *
     * @return Collection<int, object{clave: string, nombre: string, que: string, nivel: string, procesos: int, hilos: int, memoria: int, cpu: float, usuario: string, pids: list<int>}>|null null si no hay /proc (Windows)
     */
    public static function agrupados(string $proc = '/proc', int $esperaMs = 400): ?Collection
    {
        if (! is_dir($proc) || ! is_readable($proc.'/stat')) {
            return null;
        }
        $antes = self::muestra($proc);
        if ($esperaMs > 0) {
            usleep($esperaMs * 1000);
        }
        $despues = self::muestra($proc);
        $totalCpu = max(1, $despues['total'] - $antes['total']);
        $usuarios = [];
        $grupos = [];
        foreach ($despues['procesos'] as $pid => $p) {
            $clave = self::clave($p['comm'], $p['cmdline']);
            $ticks = max(0, $p['ticks'] - ($antes['procesos'][$pid]['ticks'] ?? $p['ticks']));
            $usuarios[$p['uid']] ??= self::usuario($p['uid']);
            $g = &$grupos[$clave];
            $g ??= ['clave' => $clave, 'procesos' => 0, 'hilos' => 0, 'memoria' => 0, 'ticks' => 0, 'usuarios' => [], 'pids' => []];
            $g['procesos']++;
            $g['hilos'] += $p['hilos'];
            $g['memoria'] += $p['rss'];
            $g['ticks'] += $ticks;
            $g['usuarios'][$usuarios[$p['uid']]] = true;
            $g['pids'][] = $pid;
            unset($g);
        }

        return collect($grupos)->map(function ($g) use ($totalCpu) {
            [$nombre, $que, $nivel] = self::explicar($g['clave']);
            $cpu = round($g['ticks'] / $totalCpu * 100, 1);
            $mb = $g['memoria'] / 1048576;
            // Lo que no conocemos y gasta mucho, o un PHP de consola pegado: a revisar
            if (($nivel === 'desconocido' && ($mb >= self::MB_REVISAR || $cpu >= self::CPU_REVISAR)) || (str_starts_with($g['clave'], 'php:') && $cpu >= self::CPU_REVISAR)) {
                $nivel = 'revisar';
            }

            return (object) ['clave' => $g['clave'], 'nombre' => $nombre, 'que' => $que, 'nivel' => $nivel, 'procesos' => $g['procesos'], 'hilos' => $g['hilos'],
                'memoria' => $g['memoria'], 'cpu' => $cpu, 'usuario' => implode(', ', array_keys($g['usuarios'])), 'pids' => $g['pids']];
        })->sortByDesc('memoria')->values();
    }

    /** [nombre, qué hace, nivel] de un programa (los PHP de consola se reconocen por lo que corren) */
    public static function explicar(string $clave): array
    {
        return match (true) {
            $clave === 'php:colas' => ['Trabajador de colas (TUSHPA)', 'Hace los trabajos en segundo plano: importaciones, XML de json.pe, envíos. Debe estar siempre.', 'necesario'],
            $clave === 'php:programadas' => ['Tareas programadas (TUSHPA)', 'Lo lanza el reloj de tareas cada minuto y termina solo.', 'necesario'],
            $clave === 'php:artisan' => ['Comando del sistema (TUSHPA)', 'Un comando de mantenimiento (actualizar, migrar, importar…). Termina solo.', 'opcional'],
            $clave === 'php:otro' => ['PHP de consola', 'Un script de PHP corriendo en la consola.', 'opcional'],
            isset(self::CONOCIDOS[$clave]) => self::CONOCIDOS[$clave],
            default => [$clave, 'No está en la lista de programas conocidos. Si gasta mucho, pregúntale a soporte qué es antes de tocarlo.', 'desconocido'],
        };
    }

    /** Cómo se agrupa: por programa; los PHP de consola, por lo que corren */
    private static function clave(string $comm, string $cmdline): string
    {
        if ($comm === 'php' || str_starts_with($comm, 'php8') || str_starts_with($comm, 'php7')) {
            return match (true) {
                str_contains($cmdline, 'queue:work') || str_contains($cmdline, 'colas:trabajar') => 'php:colas',
                str_contains($cmdline, 'schedule:') => 'php:programadas',
                str_contains($cmdline, 'artisan') => 'php:artisan',
                default => 'php:otro',
            };
        }

        // El nombre de php-fpm es "php-fpm: pool www"; el de systemd-userdbd va recortado a 15 letras
        return str_starts_with($comm, 'php-fpm') ? 'php-fpm' : $comm;
    }

    /** @return array{total: int, procesos: array<int, array{comm: string, cmdline: string, ticks: int, rss: int, hilos: int, uid: int}>} */
    private static function muestra(string $proc): array
    {
        $linea = strtok((string) @file_get_contents($proc.'/stat'), "\n");
        $total = array_sum(array_map('intval', array_slice(preg_split('/\s+/', trim((string) $linea)), 1)));
        $procesos = [];
        foreach ((array) @scandir($proc) as $pid) {
            if (! ctype_digit((string) $pid)) {
                continue;
            }
            $stat = @file_get_contents("{$proc}/{$pid}/stat");
            $status = @file_get_contents("{$proc}/{$pid}/status");
            if ($stat === false || $status === false) {
                continue;
            }
            // El nombre va entre paréntesis y puede tener espacios: lo de después se cuenta desde el último ")"
            $cierre = strrpos($stat, ')');
            $campos = preg_split('/\s+/', trim(substr($stat, $cierre + 1)));
            preg_match('/^Name:\s*(.+)$/m', $status, $nombre);
            preg_match('/^VmRSS:\s*(\d+)/m', $status, $rss);
            preg_match('/^Threads:\s*(\d+)/m', $status, $hilos);
            preg_match('/^Uid:\s*(\d+)/m', $status, $uid);
            $procesos[(int) $pid] = [
                'comm' => trim($nombre[1] ?? substr($stat, strpos($stat, '(') + 1, $cierre - strpos($stat, '(') - 1)),
                'cmdline' => str_replace("\0", ' ', (string) @file_get_contents("{$proc}/{$pid}/cmdline")),
                // utime + stime (campos 14 y 15 del stat; aquí 12 y 13 contando desde después del nombre)
                'ticks' => (int) ($campos[11] ?? 0) + (int) ($campos[12] ?? 0),
                'rss' => (int) ($rss[1] ?? 0) * 1024, 'hilos' => (int) ($hilos[1] ?? 1), 'uid' => (int) ($uid[1] ?? 0),
            ];
        }

        return ['total' => $total, 'procesos' => $procesos];
    }

    private static function usuario(int $uid): string
    {
        if (function_exists('posix_getpwuid') && ($u = @posix_getpwuid($uid))) {
            return (string) $u['name'];
        }

        return $uid === 0 ? 'root' : (string) $uid;
    }
}
