<?php
namespace App\Support;

use App\Models\{Pedido, PedidoDetalle, User};
use Illuminate\Support\Facades\DB;

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
        'PROTESIS FIJA', 'MOVILIDAD', 'EXTRUSION'];

    public const ORIGENES = ['RECOMENDACION', 'FACEBOOK', 'INSTAGRAM', 'TIKTOK', 'GOOGLE / PAGINA WEB', 'PASABA POR AQUI', 'SEGURO / CONVENIO', 'OTRO'];

    /** Deja solo piezas, caras y hallazgos válidos: {"36": {"e": "CORONA", "c": {"O": "CARIES"}}} (acepta el formato antiguo "36": "CARIES") */
    public static function limpiarOdontograma(?array $piezas): array
    {
        $validas = array_flip(array_merge(self::PIEZAS_ADULTO, self::PIEZAS_NINO));
        $res = [];
        foreach ($piezas ?? [] as $n => $p) {
            if (!isset($validas[(int) $n])) {
                continue;
            }
            if (is_string($p)) {
                $p = in_array($p, self::HALLAZGOS_PIEZA, true) ? ['e' => $p] : ['c' => ['O' => $p]];
            }
            $e = isset($p['e']) && in_array($p['e'], self::HALLAZGOS_PIEZA, true) ? $p['e'] : null;
            $caras = array_filter((array) ($p['c'] ?? []), fn($h, $cara) => isset(self::CARAS[$cara]) && in_array($h, self::HALLAZGOS_CARA, true), ARRAY_FILTER_USE_BOTH);
            if ($e || $caras) {
                $res[(int) $n] = array_filter(['e' => $e, 'c' => $caras ?: null]);
            }
        }
        ksort($res);
        return $res;
    }

    /** "36: CORONA, CARIES (oclusal)" para imprimir */
    public static function resumenOdontograma(?string $json): array
    {
        $lineas = [];
        foreach (self::limpiarOdontograma(json_decode((string) $json, true) ?: []) as $n => $p) {
            $partes = array_filter([$p['e'] ?? null]);
            foreach ($p['c'] ?? [] as $cara => $h) {
                $partes[] = $h . ' (' . self::CARAS[$cara] . ')';
            }
            $lineas[] = $n . ': ' . implode(', ', $partes);
        }
        return $lineas;
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
            ->max(DB::raw("CAST(SUBSTRING(his_cli_cod, 4) AS UNSIGNED)"));
        return 'HC-' . str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }

    public static function edad(?string $fecha): ?string
    {
        if (!$fecha) {
            return null;
        }
        $d = \Carbon\Carbon::parse($fecha)->diff(now());
        return $d->y >= 1 ? $d->y . ' año' . ($d->y === 1 ? '' : 's') : $d->m . ' mes' . ($d->m === 1 ? '' : 'es');
    }

    /** Nombre que se muestra: la persona, o "MASCOTA (dueño)" */
    public static function nombre(object $h): string
    {
        return $h->tipo === 'MASCOTA' ? trim(($h->mascota ?: 'MASCOTA') . ' · ' . ($h->clinom ?? '')) : (string) ($h->clinom ?? '');
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
            'ped_obs' => mb_substr($h->his_cli_cod . ' · ' . self::nombre($h), 0, 255), 'ped_tot' => 0,
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
        $total = PedidoDetalle::where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()->sum(fn($d) => $d->ped_det_can * $d->ped_det_pre);
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
        $cli = \App\Models\Cliente::updateOrCreate(['clinum' => $doc, 'rucemp' => $user->IdEmpresa], array_filter([
            'clinom' => mb_strtoupper(trim($d['clinom'])),
            'tdicod' => $d['tdicod'] ?? (strlen($doc) === 11 ? '6' : '1'),
            'telefono' => $d['telefono'] ?? null, 'clidir' => ($d['clidir'] ?? null) ?: null,
        ], fn($v) => $v !== null) + ['clidir' => '--']);

        $existe = DB::table('historia_clinica')->where('id_empresa_negocio', $suc)->where('clicod', $cli->clicod)->where('tipo', $tipo)
            ->when($tipo === 'MASCOTA', fn($q) => $q->where('mascota', mb_strtoupper(trim($d['mascota']))))->value('his_cli_id');
        $datos = array_filter([
            'mascota' => $tipo === 'MASCOTA' ? mb_strtoupper(trim($d['mascota'])) : null,
            'especie' => isset($d['especie']) ? mb_strtoupper(trim($d['especie'])) : null, 'raza' => isset($d['raza']) ? mb_strtoupper(trim($d['raza'])) : null,
            'sexo' => $d['sexo'] ?? null, 'fecha_nac' => $d['fecha_nac'] ?? null, 'grupo_sanguineo' => $d['grupo_sanguineo'] ?? null,
            'ocupacion' => $d['ocupacion'] ?? null, 'contacto_emergencia' => $d['contacto_emergencia'] ?? null, 'origen' => $d['origen'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
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

    /** Atenciones terminadas con algo por cobrar (lo que ve recepción) */
    public static function porCobrar(int $suc)
    {
        return DB::table('atencion_clinica as a')->join('pedidos as p', 'p.ped_id', '=', 'a.ped_id')
            ->join('historia_clinica as h', 'h.his_cli_id', '=', 'a.his_cli_id')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'a.doctor')
            ->where('a.id_empresa_negocio', $suc)->where('a.ate_cli_est', 'ATENDIDA')->where('p.ped_est', 'Aperturado')
            ->whereExists(fn($q) => $q->from('pedidos_detalle as d')->whereColumn('d.ped_id', 'p.ped_id')->where('d.estadoitem', '!=', 'Eliminado')
                ->whereColumn('d.item_facturado', '<', 'd.ped_det_can'))
            ->orderBy('a.ate_cli_fec')->get(['a.ate_cli_id', 'a.ped_id', 'a.ate_cli_fec', 'p.ped_tot', 'h.tipo', 'h.mascota', 'h.his_cli_cod',
                'c.clinom', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as doctor_nom")]);
    }
}
