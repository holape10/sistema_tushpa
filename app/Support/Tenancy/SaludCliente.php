<?php

namespace App\Support\Tenancy;

use App\Http\Controllers\SucursalController;
use App\Models\Central\Cliente;
use App\Support\Contingencia;
use App\Support\Sunat\Certificado;
use App\Support\Sunat\SunatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico de una empresa en un clic (panel › 🩺): junta en una sola lista lo que hoy se busca en varias pantallas
 * (SUNAT, certificado, caja, correlativos, equipos, errores, tickets, alertas y pago), cada cosa con su semáforo y,
 * cuando se puede, el arreglo de un clic. El mismo diagnóstico arma el parte de cada mañana por Telegram (panel:parte).
 *
 * Cada problema: nivel (rojo | naranja | amarillo), título, detalle, a dónde ir (`ir`) y sus arreglos de un clic.
 */
class SaludCliente
{
    public const NIVELES = ['rojo' => 0, 'naranja' => 1, 'amarillo' => 2];

    /** Días que SUNAT da para enviar: boletas (en resumen) y facturas */
    private const PLAZO_BOLETAS = 7;

    private const PLAZO_FACTURAS = 3;

    /** Turnos de caja abiertos hace más de estas horas: seguramente se olvidaron de cerrarlos */
    public const HORAS_TURNO = 24;

    /**
     * Diagnóstico completo de la empresa: lo de su base y lo del panel (errores, tickets, alertas, pago).
     *
     * @return array{problemas: list<array<string, mixed>>, info: array<string, mixed>}
     */
    public static function revisar(Cliente $cliente): array
    {
        try {
            $base = Tenancy::en($cliente->base_datos, fn () => self::revisarBase());
        } catch (\Throwable $e) {
            $base = ['problemas' => [self::problema('rojo', 'No se pudo abrir su base de datos', mb_substr($e->getMessage(), 0, 200))], 'info' => []];
        }

        return ['problemas' => self::ordenar(array_merge($base['problemas'], self::revisarPanel($cliente))), 'info' => $base['info']];
    }

    /**
     * Lo que se revisa dentro de la base de la empresa (con la conexión ya abierta). Cada revisión va aparte: si una falla
     * (una tabla que esa empresa aún no tiene), las demás siguen.
     *
     * @return array{problemas: list<array<string, mixed>>, info: array<string, mixed>}
     */
    public static function revisarBase(): array
    {
        $problemas = [];
        foreach (['rechazados', 'boletas', 'facturas', 'resumenes', 'certificado', 'turnos', 'correlativos', 'equipos', 'stockNegativo'] as $revision) {
            try {
                array_push($problemas, ...self::$revision());
            } catch (\Throwable $e) {
                report($e);
            }
        }
        try {
            $info = self::info();
        } catch (\Throwable) {
            $info = [];
        }

        return ['problemas' => self::ordenar($problemas), 'info' => $info];
    }

    /** @return array<string, mixed> */
    private static function problema(string $nivel, string $titulo, ?string $detalle = null, ?string $ir = null, array $arreglos = []): array
    {
        return compact('nivel', 'titulo', 'detalle', 'ir', 'arreglos');
    }

    /** @param list<array<string, mixed>> $problemas */
    private static function ordenar(array $problemas): array
    {
        usort($problemas, fn ($a, $b) => self::NIVELES[$a['nivel']] <=> self::NIVELES[$b['nivel']]);

        return $problemas;
    }

    private static function electronicos()
    {
        return DB::table('cpe_cabecera')->whereIn('tdocod', SunatService::TIPOS_ELECTRONICOS)->whereNull('ccabaj');
    }

    private static function rechazados(): array
    {
        $n = self::electronicos()->where('ccafem', '>=', now()->subDays(30)->toDateString())->whereRaw("UPPER(TRIM(est_sunat)) REGEXP '^RECHAZAD'")->count();

        return $n ? [self::problema('rojo', "{$n} comprobante(s) rechazado(s) por SUNAT", 'En los últimos 30 días. Hay que corregirlos y volver a emitirlos.', 'rechazados')] : [];
    }

    /** Boletas (y sus notas) sin resumen: SUNAT da 7 días desde la emisión */
    private static function boletas(): array
    {
        $fila = DB::table('cpe_cabecera')->whereNull('ccabaj')
            ->where(fn ($q) => $q->where('tdocod', '03')->orWhere(fn ($n) => $n->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'B%')))
            ->whereIn('est_sunat', SunatService::ESTADOS_REENVIABLES)->where('serdoc', 'not regexp', Contingencia::PATRON_SQL)
            ->where('ccafem', '<', now()->toDateString())
            ->selectRaw('COUNT(*) AS n, MIN(ccafem) AS desde')->first();
        if (! $fila || ! (int) $fila->n) {
            return [];
        }
        $quedan = self::PLAZO_BOLETAS - (int) now()->parse($fila->desde)->startOfDay()->diffInDays(now()->startOfDay());
        $detalle = $quedan < 0 ? 'Las más antiguas ya pasaron el plazo de SUNAT (7 días).' : ($quedan === 0 ? 'Hoy vence el plazo de las más antiguas.' : "Las más antiguas vencen en {$quedan} día(s).");

        return [self::problema($quedan <= 2 ? 'rojo' : 'naranja', "{$fila->n} boleta(s) sin resumen desde el ".now()->parse($fila->desde)->format('d/m/Y'), $detalle, 'pendientes')];
    }

    /** Facturas (y sus notas) sin enviar: SUNAT da 3 días */
    private static function facturas(): array
    {
        $fila = self::electronicos()->where(fn ($q) => $q->where('tdocod', '01')->orWhere(fn ($n) => $n->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'F%')))
            ->whereIn('est_sunat', ['PENDIENTE', 'ERROR'])->where('serdoc', 'not regexp', Contingencia::PATRON_SQL)
            ->where('ccafem', '<', now()->toDateString())
            ->selectRaw('COUNT(*) AS n, MIN(ccafem) AS desde')->first();
        if (! $fila || ! (int) $fila->n) {
            return [];
        }
        $dias = (int) now()->parse($fila->desde)->startOfDay()->diffInDays(now()->startOfDay());

        return [self::problema($dias >= self::PLAZO_FACTURAS - 1 ? 'rojo' : 'naranja', "{$fila->n} factura(s) sin enviar a SUNAT",
            'La más antigua es del '.now()->parse($fila->desde)->format('d/m/Y').' (SUNAT da 3 días).', 'pendientes')];
    }

    /** Resúmenes que SUNAT no responde hace horas (la consulta automática no los destraba) */
    private static function resumenes(): array
    {
        if (! Schema::hasTable('resumenes')) {
            return [];
        }
        $n = DB::table('resumenes')->whereIn('est_sunat', ['ENVIADO', 'EN PROCESO'])->whereNotNull('res_ticket')
            ->where('fecha_hora', '<=', now()->subHours(3))->count();

        return $n ? [self::problema('naranja', "{$n} resumen(es) sin respuesta de SUNAT hace más de 3 horas",
            'Consúltalos ya o usa «Revisar y liberar» en su sistema para reenviar las boletas.', 'resumenes')] : [];
    }

    private static function certificado(): array
    {
        $empresa = DB::table('empresa')->first();
        if (! $empresa || (string) ($empresa->produccion ?? '') !== '1') {
            return [];
        }
        $dias = Certificado::diasRestantes($empresa->fec_fin_cer ?? null);
        if ($dias === null) {
            return [self::problema('naranja', 'No tiene la fecha de vencimiento del certificado digital', 'Revisa que el certificado esté subido.')];
        }
        if ($dias < 0) {
            return [self::problema('rojo', 'Certificado digital vencido', 'Venció hace '.abs($dias).' día(s): no puede enviar a SUNAT hasta subir el nuevo.')];
        }

        return $dias <= 15 ? [self::problema('naranja', "El certificado digital vence en {$dias} día(s)", 'Avísale para que lo renueve a tiempo.')] : [];
    }

    /** Turnos de caja abiertos hace más de un día (se olvidaron de cerrarlos y el cajero no puede abrir otro) */
    private static function turnos(): array
    {
        $turnos = DB::table('turnos as t')->leftJoin('users as u', 'u.IdUsuario', '=', 't.IdUsuario')
            ->where('t.estado', 'ABIERTO')->where('t.apertura', '<=', now()->subHours(self::HORAS_TURNO))
            ->orderBy('t.apertura')->limit(10)->get(['t.id_turno', 't.apertura', 'u.apeusu', 'u.name']);

        return $turnos->map(fn ($t) => self::problema('amarillo', 'Caja abierta desde el '.now()->parse($t->apertura)->format('d/m/Y H:i'),
            'Turno de '.(trim((string) ($t->apeusu ?: $t->name)) ?: 'un usuario').'. Si se olvidaron de cerrarlo, ciérralo aquí (queda sin contar el efectivo).', null,
            [['accion' => 'cerrar_turno', 'datos' => ['turno' => (int) $t->id_turno], 'texto' => 'Cerrar este turno',
                'confirmar' => '¿Cerrar el turno abierto el '.now()->parse($t->apertura)->format('d/m/Y H:i').'? Queda cerrado sin contar el efectivo.']]))->all();
    }

    /**
     * Series cuyo correlativo quedó por debajo del último comprobante emitido: el próximo saldría con un número repetido.
     *
     * @return list<array<string, mixed>>
     */
    public static function correlativosAtrasados(): array
    {
        $atrasados = [];
        foreach (DB::table('empresa_negocios')->where('estado', 'Activo')->get() as $s) {
            foreach (SucursalController::SERIES as $clave => $c) {
                $tdocod = (string) ($c['tdocod'] ?? $clave);
                $serie = $s->{$c['serie']} ?? null;
                if (in_array($tdocod, ['PR', '09'], true) || ! $serie) {
                    continue;
                }
                $emitido = (int) DB::table('cpe_cabecera')->where('id_empresa_negocio', $s->id_empresa_negocio)->where('tdocod', $tdocod)->where('serdoc', $serie)->max('numdoc');
                if ($emitido > (int) ($s->{$c['numero']} ?? 0)) {
                    $atrasados[] = ['sucursal' => (int) $s->id_empresa_negocio, 'nombre_sucursal' => $s->nombre_comercial, 'clave' => (string) $clave,
                        'comprobante' => $c['nombre'], 'serie' => $serie, 'correlativo' => (int) $s->{$c['numero']}, 'emitido' => $emitido];
                }
            }
        }

        return $atrasados;
    }

    private static function correlativos(): array
    {
        return array_map(fn ($a) => self::problema('rojo', "{$a['comprobante']} {$a['serie']}: el correlativo va en {$a['correlativo']} y ya se emitió el {$a['emitido']}",
            'Sucursal '.$a['nombre_sucursal'].'. El próximo comprobante saldría con un número repetido.', null,
            [['accion' => 'igualar_correlativo', 'datos' => ['sucursal' => $a['sucursal'], 'clave' => $a['clave']], 'texto' => "Ponerlo en {$a['emitido']}",
                'confirmar' => "¿Poner el correlativo de {$a['serie']} en {$a['emitido']}? El próximo saldrá con el ".($a['emitido'] + 1).'.']]), self::correlativosAtrasados());
    }

    private static function equipos(): array
    {
        if (! Schema::hasTable('dispositivos')) {
            return [];
        }
        $n = DB::table('dispositivos')->where('por_revisar', true)->whereNull('bloqueado_el')->count();

        return $n ? [self::problema('amarillo', "{$n} equipo(s) nuevo(s) por revisar", 'Mientras no se autoricen, no pueden hacer acciones delicadas.', 'equipos')] : [];
    }

    private static function stockNegativo(): array
    {
        $n = DB::table('producto_stock as s')->join('productos as p', 'p.IdProducto', '=', 's.IdProducto')->where('s.stock', '<', 0)->where('p.proest', 'Activo')->count();

        return $n ? [self::problema('amarillo', "{$n} producto(s) con stock negativo", 'Se vendió más de lo registrado: falta registrar compras o hacer un inventario.')] : [];
    }

    /** @return array<string, mixed> último ingreso, quién y cuánto vendió hoy */
    private static function info(): array
    {
        $ingreso = Schema::hasTable('bitacora') ? DB::table('bitacora')->where('accion', 'sesion.ingreso')->orderByDesc('id')->first(['fecha', 'usuario']) : null;
        $hoy = DB::table('cpe_cabecera')->where('ccafem', now()->toDateString())->whereNull('ccabaj')->selectRaw('COUNT(*) AS n, COALESCE(SUM(ccaitv), 0) AS total')->first();

        return ['ultimo_ingreso' => $ingreso?->fecha, 'ultimo_usuario' => $ingreso?->usuario, 'ventas_hoy' => (int) ($hoy->n ?? 0), 'total_hoy' => (float) ($hoy->total ?? 0),
            'usuarios_activos' => DB::table('users')->where('estusu', 1)->count()];
    }

    /**
     * Lo que se revisa en la base central: pago, errores del sistema, tickets abiertos y alertas sin revisar.
     *
     * @return list<array<string, mixed>>
     */
    public static function revisarPanel(Cliente $cliente): array
    {
        $problemas = [];
        $central = DB::connection('central');
        if ($cliente->estado === 'SUSPENDIDO') {
            $problemas[] = self::problema('rojo', 'Empresa suspendida', $cliente->motivo_suspension ?? null);
        } elseif ($cliente->vence_el && $cliente->vence_el->lt(now()->startOfDay())) {
            $problemas[] = self::problema('naranja', 'Pago vencido desde el '.$cliente->vence_el->format('d/m/Y'));
        }
        if (($dias = $cliente->diasPrueba()) !== null && $dias < 0) {
            $problemas[] = self::problema('naranja', 'Su prueba gratis ya venció');
        }
        try {
            $errores = $central->table('errores_sistema')->where('base_datos', $cliente->base_datos)->where('resuelto', false)
                ->where('ultima_vez', '>=', now()->subDays(7))->selectRaw('COUNT(*) AS n, COALESCE(SUM(veces), 0) AS veces')->first();
            if ((int) $errores->n) {
                $problemas[] = self::problema('naranja', "{$errores->n} error(es) del sistema en los últimos 7 días", "Pasaron {$errores->veces} vez/veces en su sistema.", 'errores');
            }
            $tickets = $central->table('soporte_tickets')->where('cliente_id', $cliente->id)->where('estado', '!=', 'CERRADO')->count();
            if ($tickets) {
                $problemas[] = self::problema('naranja', "{$tickets} ticket(s) de soporte abierto(s)", null, 'tickets');
            }
            $alertas = AlertasPanel::pendientes([$cliente->id])[$cliente->id] ?? 0;
            if ($alertas) {
                $problemas[] = self::problema('amarillo', "{$alertas} cambio(s) delicado(s) sin revisar", null, 'alertas');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $problemas;
    }
}
