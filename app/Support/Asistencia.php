<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de asistencia. Cada día un trabajador tiene hasta 4 marcaciones:
 *   check_in_1 (entrada) → check_out_1 (salida / refrigerio) → check_in_2 (retorno) → check_out_2 (salida final)
 * Si su turno es corrido (sin bloque 2), la jornada termina en check_out_1.
 *
 * Reglas:
 *  - Sin horario asignado ese día: puede marcar libremente (no se calcula tardanza).
 *  - Turno de DESCANSO o LEYENDA (vacaciones, descanso médico): solo con autorización del administrador.
 *  - Entrada fuera de la tolerancia: solo con autorización del administrador (queda la tardanza y el motivo).
 *  - Turnos que cruzan la medianoche: la salida de madrugada se guarda en la jornada del día anterior.
 *  - Doble lectura: una segunda marcación del mismo trabajador en menos de 1 minuto se ignora.
 */
class Asistencia
{
    public const ACCIONES = ['check_in_1', 'check_out_1', 'check_in_2', 'check_out_2'];
    public const NOMBRES = [
        'check_in_1' => 'Entrada', 'check_out_1' => 'Salida', 'check_in_2' => 'Retorno de refrigerio',
        'check_out_2' => 'Salida final', 'completado' => 'Jornada completa',
    ];
    public const SEGUNDOS_DOBLE_LECTURA = 60;
    public const JORNADA_MINUTOS = 480;

    public const TURNOS_INICIALES = [
        ['codigo' => 'M', 'descripcion' => 'MAÑANA (CON REFRIGERIO)', 'tipo' => 'TRABAJO', 'hora_entrada_1' => '08:00', 'hora_salida_1' => '13:00', 'hora_entrada_2' => '14:00', 'hora_salida_2' => '17:00', 'tolerancia_minutos' => 10, 'color' => '#2563eb'],
        ['codigo' => 'D', 'descripcion' => 'DESCANSO', 'tipo' => 'DESCANSO', 'tolerancia_minutos' => 0, 'color' => '#64748b'],
        ['codigo' => 'V', 'descripcion' => 'VACACIONES', 'tipo' => 'LEYENDA', 'tolerancia_minutos' => 0, 'color' => '#0d9488'],
    ];

    /** Sucursales nuevas: crea turnos básicos la primera vez */
    public static function asegurarTurnos(int $sucursal): void
    {
        if (DB::table('asistencia_turnos')->where('id_empresa_negocio', $sucursal)->exists()) {
            return;
        }
        foreach (self::TURNOS_INICIALES as $t) {
            DB::table('asistencia_turnos')->insert($t + ['id_empresa_negocio' => $sucursal, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function empleados(int $sucursal)
    {
        return DB::table('empleado')->where('id_empresa_negocio', $sucursal)->where('asistencia', 1)
            ->where(fn($w) => $w->whereNull('est_cod')->orWhere('est_cod', '!=', '0'))
            ->orderBy('emp_nom')->orderBy('emp_ape_pat')->get();
    }

    public static function nombre(object $e): string
    {
        return trim(preg_replace('/\s+/', ' ', ($e->emp_nom ?? '') . ' ' . ($e->emp_ape_pat ?? '') . ' ' . ($e->emp_ape_mat ?? '')));
    }

    public static function horario(int $empId, string $fecha): ?object
    {
        return DB::table('asistencia_horarios as h')->join('asistencia_turnos as t', 't.id', '=', 'h.turno_id')
            ->where('h.emp_id', $empId)->where('h.fecha', $fecha)->select('t.*')->first();
    }

    /** Siguiente marcación de un registro (o 'completado') */
    public static function siguiente(?object $a, ?object $turno): string
    {
        if (!$a || !$a->check_in_1) {
            return 'check_in_1';
        }
        if (!$a->check_out_1) {
            return 'check_out_1';
        }
        // Turno corrido: termina en la primera salida
        if ($turno && $turno->tipo === 'TRABAJO' && !$turno->hora_entrada_2) {
            return 'completado';
        }
        if (!$a->check_in_2) {
            return 'check_in_2';
        }
        return $a->check_out_2 ? 'completado' : 'check_out_2';
    }

    /** Hora esperada de una marcación según el turno (el día que corresponde, cruzando la medianoche) */
    public static function horaEsperada(object $turno, string $fecha, string $accion): ?Carbon
    {
        $campo = ['check_in_1' => 'hora_entrada_1', 'check_out_1' => 'hora_salida_1', 'check_in_2' => 'hora_entrada_2', 'check_out_2' => 'hora_salida_2'][$accion];
        if (!$turno->$campo) {
            return null;
        }
        $hora = Carbon::parse($fecha . ' ' . $turno->$campo);
        $inicio = Carbon::parse($fecha . ' ' . $turno->hora_entrada_1);
        return $hora->lt($inicio) ? $hora->addDay() : $hora;
    }

    /**
     * Estado de hoy de un trabajador: qué registro usar (el de hoy o el de un turno nocturno de ayer), turno y siguiente acción.
     * @return array{fecha: string, registro: ?object, turno: ?object, accion: string}
     */
    public static function situacion(int $empId, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $hoy = $ahora->toDateString();
        $registro = DB::table('asistencias')->where('emp_id', $empId)->where('fecha', $hoy)->first();
        $fecha = $hoy;

        // Turno de ayer que sigue abierto (ej. 22:00 → 06:00): la salida de madrugada pertenece a ayer
        if (!$registro) {
            $ayer = $ahora->copy()->subDay()->toDateString();
            $previo = DB::table('asistencias')->where('emp_id', $empId)->where('fecha', $ayer)->first();
            if ($previo) {
                $turnoAyer = self::horario($empId, $ayer);
                $ultima = collect(self::ACCIONES)->map(fn($c) => $previo->$c)->filter()->max();
                if (self::siguiente($previo, $turnoAyer) !== 'completado' && self::siguiente($previo, $turnoAyer) !== 'check_in_1'
                    && $ultima && Carbon::parse($ultima)->diffInHours($ahora) < 14) {
                    return ['fecha' => $ayer, 'registro' => $previo, 'turno' => $turnoAyer, 'accion' => self::siguiente($previo, $turnoAyer)];
                }
            }
        }

        $turno = self::horario($empId, $fecha);
        return ['fecha' => $fecha, 'registro' => $registro, 'turno' => $turno, 'accion' => self::siguiente($registro, $turno)];
    }

    /**
     * Revisa si la marcación necesita autorización del administrador.
     * @return ?string motivo por el que se requiere autorización (null = puede marcar)
     */
    public static function requiereAutorizacion(array $s, ?Carbon $ahora = null): ?string
    {
        $ahora ??= now();
        $turno = $s['turno'];
        if (!$turno) {
            return null;
        }
        if ($turno->tipo === 'DESCANSO') {
            return 'Hoy es tu día de DESCANSO. Llama al administrador para autorizar tu ingreso.';
        }
        if ($turno->tipo === 'LEYENDA') {
            return "Hoy tienes asignado: {$turno->descripcion}. Llama al administrador para autorizar tu ingreso.";
        }
        if (in_array($s['accion'], ['check_in_1', 'check_in_2'], true)) {
            $esperada = self::horaEsperada($turno, $s['fecha'], $s['accion']);
            if ($esperada && $ahora->gt($esperada->copy()->addMinutes((int) $turno->tolerancia_minutos))) {
                $limite = $esperada->copy()->addMinutes((int) $turno->tolerancia_minutos)->format('H:i');
                return ($s['accion'] === 'check_in_1' ? 'Llegaste fuera de la tolerancia' : 'Retorno de refrigerio fuera de tolerancia')
                    . " (límite {$limite}). Llama al administrador para autorizar.";
            }
        }
        return null;
    }

    /**
     * Registra la siguiente marcación. Debe llamarse con la situación recién leída.
     * @param array $extra autorizado_por, motivo, hora (Carbon a registrar si el admin la corrigió), ip
     * @return array{accion: string, hora: Carbon, mensaje: string, tardanza: int}
     */
    public static function marcar(object $empleado, string $origen, array $extra = []): array
    {
        return DB::transaction(function () use ($empleado, $origen, $extra) {
            // Bloquea al trabajador para que dos lecturas simultáneas no registren dos veces
            DB::table('empleado')->where('emp_id', $empleado->emp_id)->lockForUpdate()->first();
            $ahora = now();
            $hora = $extra['hora'] ?? $ahora;
            $s = self::situacion($empleado->emp_id, $ahora);
            $accion = $s['accion'];

            if ($accion === 'completado') {
                throw new \RuntimeException('Ya completaste todas tus marcaciones de hoy.');
            }
            // Doble lectura del lector o doble toque: se ignora
            if ($s['registro']) {
                $ultima = collect(self::ACCIONES)->map(fn($c) => $s['registro']->$c)->filter()->max();
                if ($ultima && Carbon::parse($ultima)->diffInSeconds($ahora) < self::SEGUNDOS_DOBLE_LECTURA && empty($extra['autorizado_por'])) {
                    throw new \RuntimeException('Ya marcaste hace un momento (' . Carbon::parse($ultima)->format('H:i:s') . '). Espera un minuto para la siguiente marcación.');
                }
            }

            $tardanza = 0;
            if ($s['turno'] && $s['turno']->tipo === 'TRABAJO' && in_array($accion, ['check_in_1', 'check_in_2'], true)) {
                $esperada = self::horaEsperada($s['turno'], $s['fecha'], $accion);
                if ($esperada && $hora->gt($esperada->copy()->addMinutes((int) $s['turno']->tolerancia_minutos))) {
                    $tardanza = (int) $esperada->diffInMinutes($hora);
                }
            }

            $motivo = trim((string) ($extra['motivo'] ?? ''));
            $origenTxt = $origen . (empty($extra['autorizado_por']) ? '' : '*');
            if ($s['registro']) {
                $r = $s['registro'];
                DB::table('asistencias')->where('id', $r->id)->update([
                    $accion => $hora,
                    'tardanza_minutos' => (int) $r->tardanza_minutos + $tardanza,
                    'autorizado_por' => empty($extra['autorizado_por']) ? $r->autorizado_por
                        : trim(($r->autorizado_por ? $r->autorizado_por . ', ' : '') . $extra['autorizado_por']),
                    'motivo' => $motivo === '' ? $r->motivo : mb_substr(trim(($r->motivo ? $r->motivo . ' | ' : '') . self::NOMBRES[$accion] . ': ' . $motivo), 0, 255),
                    'origen' => mb_substr(trim(($r->origen ? $r->origen . ',' : '') . $origenTxt), 0, 30),
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('asistencias')->insert([
                    'emp_id' => $empleado->emp_id, 'id_empresa_negocio' => $empleado->id_empresa_negocio, 'fecha' => $s['fecha'],
                    'turno_id' => $s['turno']->id ?? null, $accion => $hora, 'tardanza_minutos' => $tardanza,
                    'autorizado_por' => $extra['autorizado_por'] ?? null,
                    'motivo' => $motivo === '' ? null : mb_substr(self::NOMBRES[$accion] . ': ' . $motivo, 0, 255),
                    'origen' => $origenTxt, 'ip' => $extra['ip'] ?? null, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }

            $nombre = $empleado->emp_nom;
            $mensaje = match ($accion) {
                'check_in_1' => "¡Bienvenido(a) {$nombre}! Entrada registrada a las " . $hora->format('H:i:s'),
                'check_out_1' => ($s['turno'] && $s['turno']->tipo === 'TRABAJO' && !$s['turno']->hora_entrada_2
                    ? "¡Buen trabajo {$nombre}! Jornada finalizada a las " : "¡Buen provecho {$nombre}! Salida registrada a las ") . $hora->format('H:i:s'),
                'check_in_2' => "¡Bienvenido(a) de nuevo {$nombre}! Retorno registrado a las " . $hora->format('H:i:s'),
                'check_out_2' => "¡Buen trabajo {$nombre}! Jornada finalizada a las " . $hora->format('H:i:s'),
            };
            if ($tardanza > 0) {
                $mensaje .= " (tardanza: {$tardanza} min)";
            }

            return ['accion' => $accion, 'hora' => $hora, 'mensaje' => $mensaje, 'tardanza' => $tardanza];
        });
    }

    // ------------------------------------------------------------------ IP permitida

    public static function ipPermitida(int $sucursal, string $ip): bool
    {
        $config = trim((string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('ip_asistencia'));
        if ($config === '' || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }
        return in_array($ip, array_filter(array_map('trim', explode(',', $config))), true);
    }

    // ------------------------------------------------------------------ reportes

    /** Feriados nacionales (Perú) + Jueves y Viernes Santo + feriados registrados por la empresa */
    public static function feriados(string $desde, string $hasta, int $sucursal): array
    {
        $res = [];
        for ($anio = (int) substr($desde, 0, 4); $anio <= (int) substr($hasta, 0, 4); $anio++) {
            foreach (['01-01' => 'Año Nuevo', '05-01' => 'Día del Trabajo', '06-07' => 'Batalla de Arica y Día de la Bandera',
                      '06-29' => 'San Pedro y San Pablo', '07-23' => 'Día de la Fuerza Aérea', '07-28' => 'Fiestas Patrias', '07-29' => 'Fiestas Patrias',
                      '08-06' => 'Batalla de Junín', '08-30' => 'Santa Rosa de Lima', '10-08' => 'Combate de Angamos', '11-01' => 'Todos los Santos',
                      '12-08' => 'Inmaculada Concepción', '12-09' => 'Batalla de Ayacucho', '12-25' => 'Navidad'] as $md => $nom) {
                $res["$anio-$md"] = $nom;
            }
            $pascua = Carbon::create($anio, 3, 21)->addDays(easter_days($anio));
            $res[$pascua->copy()->subDays(3)->toDateString()] = 'Jueves Santo';
            $res[$pascua->copy()->subDays(2)->toDateString()] = 'Viernes Santo';
        }
        foreach (DB::table('feriados')->whereBetween('fecha', [$desde, $hasta])
                     ->where(fn($w) => $w->whereNull('id_empresa_negocio')->orWhere('id_empresa_negocio', $sucursal))->get() as $f) {
            $res[$f->fecha] = $f->descripcion;
        }
        return array_filter($res, fn($k) => $k >= $desde && $k <= $hasta, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Cálculo de una jornada: minutos laborados (dentro del horario), presentes (todo lo marcado), tardanza,
     * extra (presente por encima de 8 h) y si está conforme. Sin turno, cuenta todo lo marcado.
     */
    public static function jornada(object $a, ?object $turno): array
    {
        $bloques = [['check_in_1', 'check_out_1', 'hora_entrada_1', 'hora_salida_1'], ['check_in_2', 'check_out_2', 'hora_entrada_2', 'hora_salida_2']];
        $laborado = $presente = $tardanza = 0;
        $programado = 0;

        foreach ($bloques as [$ci, $co, $he, $hs]) {
            if ($a->$ci && $a->$co) {
                $presente += Carbon::parse($a->$ci)->diffInMinutes(Carbon::parse($a->$co));
            }
            if ($turno && $turno->tipo === 'TRABAJO' && $turno->$he && $turno->$hs) {
                $iniT = self::horaEsperada($turno, $a->fecha, $ci);
                $finT = self::horaEsperada($turno, $a->fecha, $co);
                $programado += $iniT->diffInMinutes($finT);
                if ($a->$ci) {
                    $entrada = Carbon::parse($a->$ci);
                    if ($entrada->gt($iniT->copy()->addMinutes((int) $turno->tolerancia_minutos))) {
                        $tardanza += $iniT->diffInMinutes($entrada);
                    }
                    if ($a->$co) {
                        // Solo cuenta dentro del horario: desde la hora programada (o la real si llegó tarde) hasta la salida programada (o la real si salió antes)
                        $ini = $entrada->gt($iniT->copy()->addMinutes((int) $turno->tolerancia_minutos)) ? $entrada : $iniT;
                        $fin = Carbon::parse($a->$co)->lt($finT) ? Carbon::parse($a->$co) : $finT;
                        $laborado += $fin->gt($ini) ? $ini->diffInMinutes($fin) : 0;
                    }
                }
            }
        }
        if (!$turno || $turno->tipo !== 'TRABAJO') {
            $laborado = $presente;
        }
        $meta = $programado ?: self::JORNADA_MINUTOS;

        return [
            'laborado' => (int) $laborado, 'presente' => (int) $presente, 'tardanza' => (int) $tardanza,
            'extra' => (int) max(0, $presente - max($meta, self::JORNADA_MINUTOS)), 'programado' => (int) $programado,
            'conforme' => $laborado + $tardanza >= $meta * 0.98,
            'abierta' => self::siguiente($a, $turno) !== 'completado',
        ];
    }

    public static function horas(int $minutos): string
    {
        return $minutos > 0 ? intdiv($minutos, 60) . 'h ' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) . 'm' : '—';
    }
}
