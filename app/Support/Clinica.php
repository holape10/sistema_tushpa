<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Historias clínicas: permisos, códigos de historia y el pedido que recepción cobra en caja.
 * Roles: 2 administrador (todo) · 10 doctor (atiende y escribe) · 4 caja/recepción (agenda y cobra, sin detalle clínico).
 */
class Clinica
{
    public const ROL_DOCTOR = 10;

    public const ESTADOS_CITA = ['PROGRAMADA', 'CONFIRMADA', 'EN_ESPERA', 'ATENDIDA', 'NO_ASISTIO', 'CANCELADA'];

    // Odontograma (notación FDI, norma peruana: rojo = por tratar, azul = tratamiento en buen estado)
    public const PIEZAS_ADULTO = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28, 31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48];

    public const PIEZAS_NINO = [51, 52, 53, 54, 55, 61, 62, 63, 64, 65, 71, 72, 73, 74, 75, 81, 82, 83, 84, 85];

    public const CARAS = ['V' => 'vestibular', 'L' => 'lingual/palatino', 'M' => 'mesial', 'D' => 'distal', 'O' => 'oclusal/incisal'];

    public const HALLAZGOS_CARA = ['CARIES', 'RESTAURACION', 'RESTAURACION DEFECTUOSA', 'SELLANTE', 'FRACTURA'];

    public const HALLAZGOS_PIEZA = ['POR EXTRAER', 'AUSENTE', 'CORONA', 'CORONA DEFECTUOSA', 'ENDODONCIA', 'IMPLANTE', 'REMANENTE RADICULAR',
        'PROTESIS FIJA', 'PROTESIS FIJA DEFECTUOSA', 'PROTESIS REMOVIBLE', 'PROTESIS REMOVIBLE DEFECTUOSA', 'MOVILIDAD', 'EXTRUSION', 'INTRUSION',
        'EN ERUPCION', 'GIROVERSION', 'DIASTEMA', 'SUPERNUMERARIA', 'ECTOPICA', 'CLAVIJA', 'FOSAS Y FISURAS PROFUNDAS', 'MANCHA BLANCA',
        'HIPOMINERALIZACION', 'HIPOPLASIA', 'OPACIDAD DEL ESMALTE', 'EDENTULO TOTAL'];

    public const ORIGENES = ['RECOMENDACION', 'FACEBOOK', 'INSTAGRAM', 'TIKTOK', 'GOOGLE / PAGINA WEB', 'PASABA POR AQUI', 'SEGURO / CONVENIO', 'OTRO'];

    /**
     * Deja solo piezas, caras y hallazgos válidos. Una pieza puede tener varios hallazgos de pieza completa
     * (ej. ENDODONCIA y PROTESIS FIJA) y uno por cara (ej. CARIES oclusal):
     * {"14": {"e": ["ENDODONCIA", "PROTESIS FIJA"], "c": {"O": "CARIES"}}}.
     * Acepta los formatos antiguos: "36": "CARIES" y "e": "CORONA" (un solo hallazgo).
     */
    public static function limpiarOdontograma(?array $piezas): array
    {
        $validas = array_flip(array_merge(self::PIEZAS_ADULTO, self::PIEZAS_NINO));
        $res = [];
        foreach ($piezas ?? [] as $n => $p) {
            if (! isset($validas[(int) $n])) {
                continue;
            }
            if (is_string($p)) {
                $p = in_array($p, self::HALLAZGOS_PIEZA, true) ? ['e' => [$p]] : ['c' => ['O' => $p]];
            }
            // Orden fijo (el del catálogo) para que el mismo diente siempre se lea igual
            $e = array_values(array_intersect(self::HALLAZGOS_PIEZA, (array) ($p['e'] ?? [])));
            $caras = array_filter((array) ($p['c'] ?? []), fn ($h, $cara) => isset(self::CARAS[$cara]) && in_array($h, self::HALLAZGOS_CARA, true), ARRAY_FILTER_USE_BOTH);
            if ($e || $caras) {
                $res[(int) $n] = array_filter(['e' => $e ?: null, 'c' => $caras ?: null]);
            }
        }
        ksort($res);

        return $res;
    }

    /**
     * Periodontograma (dientes permanentes): por diente ausente, movilidad (0-3), furca (0-3) y, por cara vestibular (v) y
     * palatina/lingual (l), en 3 sitios (en el orden en que se ven en pantalla): profundidad de sondaje "ps" (mm), margen gingival "mg"
     * (mm; positivo = recesión, negativo = agrandamiento), sangrado "s" y placa "p". El nivel de inserción es PS + MG.
     *
     * @return array<int, array{aus?: bool, mov?: int, fur?: int, v?: array, l?: array}>
     */
    public static function limpiarPeriodontograma(?array $datos): array
    {
        $num = fn ($v, int $min, int $max) => is_numeric($v) ? max($min, min($max, (int) $v)) : null;
        $res = [];
        foreach ($datos ?? [] as $n => $d) {
            if (! in_array((int) $n, self::PIEZAS_ADULTO, true) || ! is_array($d)) {
                continue;
            }
            $diente = [];
            if (! empty($d['aus'])) {
                $res[(int) $n] = ['aus' => true];

                continue;
            }
            foreach (['mov' => 3, 'fur' => 3] as $k => $max) {
                if (($v = $num($d[$k] ?? null, 0, $max)) !== null && $v > 0) {
                    $diente[$k] = $v;
                }
            }
            foreach (['v', 'l'] as $cara) {
                $c = (array) ($d[$cara] ?? []);
                $lado = [
                    'ps' => array_map(fn ($v) => $num($v, 0, 15), array_pad(array_slice(array_values((array) ($c['ps'] ?? [])), 0, 3), 3, null)),
                    'mg' => array_map(fn ($v) => $num($v, -10, 15), array_pad(array_slice(array_values((array) ($c['mg'] ?? [])), 0, 3), 3, null)),
                    's' => array_map(fn ($v) => (bool) $v, array_pad(array_slice(array_values((array) ($c['s'] ?? [])), 0, 3), 3, false)),
                    'p' => array_map(fn ($v) => (bool) $v, array_pad(array_slice(array_values((array) ($c['p'] ?? [])), 0, 3), 3, false)),
                ];
                if (array_filter($lado['ps'], fn ($v) => $v !== null) || array_filter($lado['mg'], fn ($v) => $v !== null) || in_array(true, $lado['s'], true) || in_array(true, $lado['p'], true)) {
                    $diente[$cara] = $lado;
                }
            }
            if ($diente) {
                $res[(int) $n] = $diente;
            }
        }
        ksort($res);

        return $res;
    }

    /**
     * Indicadores del periodontograma: % de sangrado y de placa, sondaje promedio, bolsas de 4-5 y de 6+ mm y nivel de inserción.
     *
     * @return array{dientes: int, sitios: int, sangrado: ?float, placa: ?float, ps_promedio: ?float, bolsas_4: int, bolsas_6: int, nic_promedio: ?float, nic_max: ?int, movilidad: int, furcas: int}|null
     */
    public static function resumenPeriodontograma(?string $json): ?array
    {
        $datos = self::limpiarPeriodontograma(json_decode((string) $json, true) ?: []);
        $presentes = array_filter($datos, fn ($d) => empty($d['aus']));
        if (! $presentes) {
            return null;
        }
        $ps = $nic = [];
        $sangra = $placa = $sitios = 0;
        foreach ($presentes as $d) {
            foreach (['v', 'l'] as $cara) {
                if (! isset($d[$cara])) {
                    continue;
                }
                foreach ([0, 1, 2] as $i) {
                    $sitios++;
                    $sangra += $d[$cara]['s'][$i] ? 1 : 0;
                    $placa += $d[$cara]['p'][$i] ? 1 : 0;
                    if ($d[$cara]['ps'][$i] !== null) {
                        $ps[] = $d[$cara]['ps'][$i];
                        $nic[] = $d[$cara]['ps'][$i] + (int) ($d[$cara]['mg'][$i] ?? 0);
                    }
                }
            }
        }

        return [
            'dientes' => count($presentes), 'sitios' => $sitios,
            'sangrado' => $sitios ? round($sangra / $sitios * 100, 1) : null, 'placa' => $sitios ? round($placa / $sitios * 100, 1) : null,
            'ps_promedio' => $ps ? round(array_sum($ps) / count($ps), 1) : null,
            'bolsas_4' => count(array_filter($ps, fn ($v) => $v >= 4 && $v <= 5)), 'bolsas_6' => count(array_filter($ps, fn ($v) => $v >= 6)),
            'nic_promedio' => $nic ? round(array_sum($nic) / count($nic), 1) : null, 'nic_max' => $nic ? max($nic) : null,
            'movilidad' => count(array_filter($presentes, fn ($d) => ($d['mov'] ?? 0) > 0)), 'furcas' => count(array_filter($presentes, fn ($d) => ($d['fur'] ?? 0) > 0)),
        ];
    }

    /** "14: ENDODONCIA, PROTESIS FIJA, CARIES (oclusal/incisal)" para imprimir */
    public static function resumenOdontograma(?string $json): array
    {
        $lineas = [];
        foreach (self::limpiarOdontograma(json_decode((string) $json, true) ?: []) as $n => $p) {
            $partes = $p['e'] ?? [];
            foreach ($p['c'] ?? [] as $cara => $h) {
                $partes[] = $h.' ('.self::CARAS[$cara].')';
            }
            $lineas[] = $n.': '.implode(', ', $partes);
        }

        return $lineas;
    }

    /** Lo más pesado que acepta una subida de archivos (20 MB o menos si el servidor PHP permite menos) */
    public static function limiteSubida(): int
    {
        $bytes = function (string $v): int {
            $n = (float) $v;

            return (int) match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n
            };
        };

        return min(20 * 1048576, $bytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, $bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
    }

    public static function esDoctor(User $u): bool
    {
        return $u->tieneRol([self::ROL_DOCTOR]);
    }

    /** Puede ver y escribir el contenido clínico (diagnósticos, recetas) */
    public static function veClinico(User $u): bool
    {
        return $u->tieneRol([2, self::ROL_DOCTOR]);
    }

    /** Agenda y pacientes: recepción, doctores y administrador */
    public static function usaClinica(User $u): bool
    {
        return $u->tieneRol([2, 4, self::ROL_DOCTOR]);
    }

    public static function doctores(int $suc)
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', self::ROL_DOCTOR)->where('u.id_empresa_negocio', $suc)->where('u.estusu', 1)
            ->orderBy('u.apeusu')->get(['u.IdUsuario', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as nombre")]);
    }

    public static function nuevoCodigo(int $suc): string
    {
        $ultimo = (int) DB::table('historia_clinica')->where('id_empresa_negocio', $suc)
            ->max(DB::raw('CAST(SUBSTRING(his_cli_cod, 4) AS UNSIGNED)'));

        return 'HC-'.str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }

    public static function edad(?string $fecha): ?string
    {
        if (! $fecha) {
            return null;
        }
        $d = Carbon::parse($fecha)->diff(now());

        return $d->y >= 1 ? $d->y.' año'.($d->y === 1 ? '' : 's') : $d->m.' mes'.($d->m === 1 ? '' : 'es');
    }

    /** Nombre que se muestra: la persona, o "MASCOTA (dueño)" */
    public static function nombre(object $h): string
    {
        return $h->tipo === 'MASCOTA' ? trim(($h->mascota ?: 'MASCOTA').' · '.($h->clinom ?? '')) : (string) ($h->clinom ?? '');
    }

    /**
     * El pedido 'Clinica' de la atención (se crea la primera vez): sus líneas son lo que recepción cobra en caja.
     * La consulta de la especialidad entra sola; el doctor puede agregar procedimientos o productos.
     */
    public static function pedidoDe(object $ate, User $user): Pedido
    {
        if ($ate->ped_id && ($p = Pedido::find($ate->ped_id))) {
            return $p;
        }
        $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')->where('h.his_cli_id', $ate->his_cli_id)
            ->first(['h.*', 'c.clinom', 'c.clinum']);
        $p = Pedido::create([
            'ped_tip' => 'Clinica', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(), 'ped_est' => 'Aperturado',
            'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $ate->id_empresa_negocio, 'mozo' => $ate->doctor ?: $user->IdUsuario,
            'IdUsuario' => $user->IdUsuario, 'ped_cli_nom' => $h->clinom, 'ped_num_doc' => $h->clinum,
            'ped_obs' => mb_substr($h->his_cli_cod.' · '.self::nombre($h), 0, 255), 'ped_tot' => 0,
        ]);
        DB::table('atencion_clinica')->where('ate_cli_id', $ate->ate_cli_id)->update(['ped_id' => $p->ped_id]);

        // La consulta de la especialidad
        $esp = $ate->esp_id ? DB::table('especialidad')->where('esp_id', $ate->esp_id)->first() : null;
        if ($esp && $esp->IdProducto && ($prod = DB::table('productos')->where('IdProducto', $esp->IdProducto)->first())) {
            self::agregarLinea($p->ped_id, $prod, 1, (float) $prod->propun, $user);
        }

        return $p;
    }

    public static function agregarLinea(int $pedId, object $prod, float $cantidad, float $precio, User $user): void
    {
        PedidoDetalle::create([
            'ped_id' => $pedId, 'IdProducto' => $prod->IdProducto, 'IdEmpresa' => $user->IdEmpresa,
            'descripcion' => $prod->pronom, 'detalle' => $prod->pronom, 'ped_det_can' => $cantidad, 'ped_det_pre' => $precio,
            'estadoitem' => 'Ingresado', 'impreso' => 'impreso', 'fecha_hora' => now(),
        ]);
        self::recalcular($pedId);
    }

    public static function recalcular(int $pedId): void
    {
        $total = PedidoDetalle::where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()->sum(fn ($d) => $d->ped_det_can * $d->ped_det_pre);
        Pedido::where('ped_id', $pedId)->update(['ped_tot' => round($total, 2), 'fecha_hora_modificacion' => now()]);
    }

    public const REGLAS_PACIENTE = [
        'tipo' => 'nullable|in:PERSONA,MASCOTA', 'tdicod' => 'nullable|string|size:1', 'clinum' => 'required|string|max:15',
        'clinom' => 'required|string|max:150', 'telefono' => 'nullable|string|max:20', 'clidir' => 'nullable|string|max:150',
        'mascota' => 'nullable|required_if:tipo,MASCOTA|string|max:100', 'especie' => 'nullable|string|max:40', 'raza' => 'nullable|string|max:60',
        'sexo' => 'nullable|in:M,F', 'fecha_nac' => 'nullable|date|before_or_equal:today', 'grupo_sanguineo' => 'nullable|string|max:5',
        'ocupacion' => 'nullable|string|max:100', 'contacto_emergencia' => 'nullable|string|max:150', 'origen' => 'nullable|string|max:30',
    ];

    /**
     * Crea (o reutiliza) la historia: persona = una historia por documento; mascota = una por dueño y nombre de mascota.
     * El paciente / dueño queda como cliente para que el comprobante salga a su nombre.
     */
    public static function crearPaciente(User $user, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $doc = trim($d['clinum']);
        $tipo = ($d['tipo'] ?? 'PERSONA') === 'MASCOTA' ? 'MASCOTA' : 'PERSONA';
        $cli = Cliente::updateOrCreate(['clinum' => $doc, 'rucemp' => $user->IdEmpresa], array_filter([
            'clinom' => mb_strtoupper(trim($d['clinom'])),
            'tdicod' => $d['tdicod'] ?? (strlen($doc) === 11 ? '6' : '1'),
            'telefono' => $d['telefono'] ?? null, 'clidir' => ($d['clidir'] ?? null) ?: null,
        ], fn ($v) => $v !== null) + ['clidir' => '--']);

        $existe = DB::table('historia_clinica')->where('id_empresa_negocio', $suc)->where('clicod', $cli->clicod)->where('tipo', $tipo)
            ->when($tipo === 'MASCOTA', fn ($q) => $q->where('mascota', mb_strtoupper(trim($d['mascota']))))->value('his_cli_id');
        $datos = array_filter([
            'mascota' => $tipo === 'MASCOTA' ? mb_strtoupper(trim($d['mascota'])) : null,
            'especie' => isset($d['especie']) ? mb_strtoupper(trim($d['especie'])) : null, 'raza' => isset($d['raza']) ? mb_strtoupper(trim($d['raza'])) : null,
            'sexo' => $d['sexo'] ?? null, 'fecha_nac' => $d['fecha_nac'] ?? null, 'grupo_sanguineo' => $d['grupo_sanguineo'] ?? null,
            'ocupacion' => $d['ocupacion'] ?? null, 'contacto_emergencia' => $d['contacto_emergencia'] ?? null, 'origen' => $d['origen'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($existe) {
            if ($datos) {
                DB::table('historia_clinica')->where('his_cli_id', $existe)->update($datos);
            }

            return (int) $existe;
        }

        return DB::table('historia_clinica')->insertGetId($datos + [
            'his_cli_cod' => self::nuevoCodigo($suc), 'clicod' => $cli->clicod, 'tipo' => $tipo, 'id_empresa_negocio' => $suc, 'his_cli_fec' => now(),
        ]);
    }

    /**
     * Lo que recepción tiene por cobrar: atenciones terminadas y adelantos de tratamientos.
     * Las atenciones muestran al doctor; los adelantos, el tratamiento.
     */
    public static function porCobrar(int $suc)
    {
        $pendiente = fn ($q) => $q->where('p.ped_est', 'Aperturado')
            ->whereExists(fn ($s) => $s->from('pedidos_detalle as d')->whereColumn('d.ped_id', 'p.ped_id')->where('d.estadoitem', '!=', 'Eliminado')
                ->whereColumn('d.item_facturado', '<', 'd.ped_det_can'));

        $atenciones = DB::table('atencion_clinica as a')->join('pedidos as p', 'p.ped_id', '=', 'a.ped_id')
            ->join('historia_clinica as h', 'h.his_cli_id', '=', 'a.his_cli_id')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'a.doctor')
            ->where('a.id_empresa_negocio', $suc)->where('a.ate_cli_est', 'ATENDIDA')->tap($pendiente)
            ->get(['a.ate_cli_id', 'a.ped_id', 'a.ate_cli_fec', 'p.ped_tot', 'h.tipo', 'h.mascota', 'h.his_cli_cod',
                'c.clinom', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as doctor_nom")]);

        $adelantos = DB::table('clinica_tratamiento_registros as r')->join('clinica_tratamientos as t', 't.tra_id', '=', 'r.tra_id')
            ->join('pedidos as p', 'p.ped_id', '=', 'r.ped_id')
            ->join('historia_clinica as h', 'h.his_cli_id', '=', 't.his_cli_id')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('t.id_empresa_negocio', $suc)->tap($pendiente)
            ->get([DB::raw('NULL as ate_cli_id'), 'r.ped_id', DB::raw('DATE(r.fecha_hora) as ate_cli_fec'), 'p.ped_tot', 'h.tipo', 'h.mascota', 'h.his_cli_cod',
                'c.clinom', DB::raw("CONCAT('Adelanto · ', t.descripcion) as doctor_nom")]);

        return $atenciones->concat($adelantos)->sortBy('ate_cli_fec')->values();
    }

    // ------------------------------------------------------------------ registro de tratamientos

    /** Servicio con el que se cobran en caja los adelantos (sin receta: no mueve stock). Se crea la primera vez */
    public static function productoAdelanto(User $user): object
    {
        $suc = (int) $user->id_empresa_negocio;
        $prod = DB::table('productos')->where('id_empresa_negocio', $suc)->where('procod', 'ADELANTO-TRAT')->first();
        if ($prod) {
            return $prod;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', 'CLINICA')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'CLINICA', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'tip_pro_id' => DB::table('tipo_producto')->where('id_empresa_negocio', $suc)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);
        $id = Producto::create(['pronom' => 'ADELANTO DE TRATAMIENTO', 'procod' => 'ADELANTO-TRAT', 'propun' => 0, 'umecod' => 'ZZ',
            'costo' => 0, 'promocion' => 2, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa,
            'id_empresa_negocio' => $suc])->IdProducto;

        return DB::table('productos')->where('IdProducto', $id)->first();
    }

    /**
     * Registra una sesión del tratamiento: lo realizado, el adelanto y el saldo que queda. El adelanto se cobra en caja
     * (pedido 'Clinica' que recepción ve en "Por cobrar"). Con saldo 0 el tratamiento queda CANCELADO.
     *
     * @param  array{detalle?: ?string, adelanto?: float|string|null, fecha_hora?: ?string}  $d
     * @return int reg_id
     */
    public static function registrarSesion(object $tra, array $d, User $user): int
    {
        $pagado = (float) DB::table('clinica_tratamiento_registros')->where('tra_id', $tra->tra_id)->sum('adelanto');
        $pendiente = round((float) $tra->costo - $pagado, 2);
        $adelanto = round((float) ($d['adelanto'] ?? 0), 2);
        if ($adelanto < 0 || $adelanto > $pendiente + 0.001) {
            throw new \RuntimeException('El adelanto no puede pasar del saldo pendiente (S/ '.number_format($pendiente, 2).').');
        }
        if ($adelanto <= 0 && trim((string) ($d['detalle'] ?? '')) === '') {
            throw new \RuntimeException('Escribe lo que se realizó o las indicaciones, o el adelanto que paga.');
        }
        $saldo = round($pendiente - $adelanto, 2);

        $pedId = null;
        if ($adelanto > 0) {
            $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')->where('h.his_cli_id', $tra->his_cli_id)
                ->first(['h.*', 'c.clinom', 'c.clinum']);
            $pedido = Pedido::create([
                'ped_tip' => 'Clinica', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(), 'ped_est' => 'Aperturado',
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $tra->id_empresa_negocio, 'mozo' => $tra->doctor ?: $user->IdUsuario,
                'IdUsuario' => $user->IdUsuario, 'ped_cli_nom' => $h->clinom, 'ped_num_doc' => $h->clinum,
                'ped_obs' => mb_substr($h->his_cli_cod.' · '.self::nombre($h).' · '.$tra->descripcion, 0, 255), 'ped_tot' => 0,
            ]);
            $prod = (object) ['IdProducto' => self::productoAdelanto($user)->IdProducto,
                'pronom' => mb_substr(($saldo <= 0 ? 'CANCELACION' : 'ADELANTO').' DE TRATAMIENTO: '.$tra->descripcion, 0, 150)];
            self::agregarLinea($pedido->ped_id, $prod, 1, $adelanto, $user);
            $pedId = $pedido->ped_id;
        }

        $regId = DB::table('clinica_tratamiento_registros')->insertGetId([
            'tra_id' => $tra->tra_id, 'fecha_hora' => $d['fecha_hora'] ?? now(), 'detalle' => trim((string) ($d['detalle'] ?? '')) ?: null,
            'adelanto' => $adelanto, 'saldo' => $saldo, 'ped_id' => $pedId, 'IdUsuario' => $user->IdUsuario, 'creado' => now(),
        ]);
        DB::table('clinica_tratamientos')->where('tra_id', $tra->tra_id)->update($saldo <= 0 && (float) $tra->costo > 0
            ? ['estado' => 'CANCELADO', 'cancelado' => now()] : ['estado' => 'EN CURSO', 'cancelado' => null]);

        return $regId;
    }

    /** Guarda la firma (imagen PNG que manda la tablet) en storage/app/clinica/firmas */
    public static function guardarFirma(int $regId, int $suc, string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new \RuntimeException('La firma no es una imagen válida.');
        }
        $png = base64_decode($m[1], true);
        if ($png === false || ! str_starts_with($png, "\x89PNG") || strlen($png) > 400_000) {
            throw new \RuntimeException('La firma no es una imagen válida.');
        }
        $ruta = 'clinica/firmas/'.$suc.'/'.$regId.'.png';
        Storage::disk('local')->put($ruta, $png);
        DB::table('clinica_tratamiento_registros')->where('reg_id', $regId)->update(['firma' => $ruta]);

        return $ruta;
    }

    /**
     * Tratamientos de un paciente con sus sesiones (lo pagado, el saldo y si caja ya cobró cada adelanto).
     *
     * @return Collection<int, object>
     */
    public static function tratamientosDe(int $hisId)
    {
        $tratamientos = DB::table('clinica_tratamientos as t')->leftJoin('especialidad as e', 'e.esp_id', '=', 't.esp_id')
            ->where('t.his_cli_id', $hisId)->orderByRaw("t.estado = 'CANCELADO'")->orderByDesc('t.tra_id')
            ->get(['t.*', 'e.esp_des']);
        $registros = DB::table('clinica_tratamiento_registros as r')->leftJoin('pedidos as p', 'p.ped_id', '=', 'r.ped_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
            ->whereIn('r.tra_id', $tratamientos->pluck('tra_id'))->orderBy('r.fecha_hora')->orderBy('r.reg_id')
            ->get(['r.*', 'p.ped_est', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as usuario")])->groupBy('tra_id');

        return $tratamientos->map(function ($t) use ($registros) {
            $t->registros = $registros[$t->tra_id] ?? collect();
            $t->pagado = round((float) $t->registros->sum('adelanto'), 2);
            $t->saldo = round((float) $t->costo - $t->pagado, 2);

            return self::conCuotas($t);
        });
    }

    /**
     * Plan mensual (ortodoncia): las cuotas salen de la cuota inicial, el número de cuotas, su monto y la fecha de la primera.
     * Lo pagado se aplica en orden (inicial, cuota 1, cuota 2…). Cada cuota queda PAGADA, PARCIAL, VENCIDA o PENDIENTE.
     *
     * @return array<int, array{numero: int, etiqueta: string, vence: string, monto: float, pagado: float, estado: string}>
     */
    public static function cuotasDe(object $t, float $pagado): array
    {
        if (($t->modalidad ?? 'UNICO') !== 'MENSUAL') {
            return [];
        }
        $hoy = now()->toDateString();
        $plan = [];
        if ((float) $t->cuota_inicial > 0) {
            $plan[] = ['numero' => 0, 'etiqueta' => 'Inicial', 'vence' => Carbon::parse($t->creado)->toDateString(), 'monto' => (float) $t->cuota_inicial];
        }
        $primera = Carbon::parse($t->primer_vencimiento ?: $t->creado);
        for ($i = 1; $i <= (int) $t->cuotas; $i++) {
            $vence = $primera->copy()->addMonthsNoOverflow($i - 1);
            $plan[] = ['numero' => $i, 'etiqueta' => 'Cuota '.$i.' · '.ucfirst($vence->locale('es')->isoFormat('MMM YYYY')), 'vence' => $vence->toDateString(), 'monto' => (float) $t->cuota];
        }
        $resto = $pagado;
        foreach ($plan as &$c) {
            $c['pagado'] = round(min($c['monto'], max(0, $resto)), 2);
            $resto = round($resto - $c['pagado'], 2);
            $c['estado'] = $c['pagado'] >= $c['monto'] - 0.001 ? 'PAGADA' : ($c['vence'] < $hoy ? 'VENCIDA' : ($c['pagado'] > 0 ? 'PARCIAL' : 'PENDIENTE'));
        }

        return $plan;
    }

    /** Agrega al tratamiento sus cuotas, lo vencido (lo que debía pagar hasta hoy) y la próxima cuota por pagar */
    public static function conCuotas(object $t): object
    {
        $t->cuotas_plan = self::cuotasDe($t, (float) $t->pagado);
        $pendientes = collect($t->cuotas_plan)->where('estado', '!=', 'PAGADA');
        $t->vencido = round((float) $pendientes->where('estado', 'VENCIDA')->sum(fn ($c) => $c['monto'] - $c['pagado']), 2);
        $proxima = $pendientes->first();
        $t->proxima = $proxima ? ['etiqueta' => $proxima['etiqueta'], 'vence' => $proxima['vence'], 'falta' => round($proxima['monto'] - $proxima['pagado'], 2)] : null;

        return $t;
    }
}
