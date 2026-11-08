<?php

namespace App\Support;

use App\Models\Empresa;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireSolicitudes;
use App\Support\Sunat\XmlJsonPe;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Descarga automática del estudio contable: el día del mes que elige el administrador, el sistema trae solo del mes
 * anterior, empresa por empresa, lo mismo que hoy se pide a mano: la propuesta del SIRE de compras y de ventas, y los XML
 * de las compras (estos necesitan la propuesta de compras ya descargada). Avanza de a poco en cada vuelta (cada 10 minutos
 * por el programador de tareas, con el trabajador de colas): SUNAT tarda en preparar las propuestas y cada XML demora.
 */
class EstudioDescargas
{
    public const TAREAS = [
        'sire_compras' => 'SIRE Compras (propuesta)',
        'sire_ventas' => 'SIRE Ventas (propuesta)',
        'xml' => 'XML de las compras',
    ];

    public const ESTADOS = [
        'PENDIENTE' => ['En cola', 'slate'],
        'PROCESO' => ['En proceso', 'sky'],
        'LISTO' => ['Listo', 'emerald'],
        'ERROR' => ['Con error', 'rose'],
        'OMITIDO' => ['Sin credenciales', 'amber'],
    ];

    /** Intentos de pedir una propuesta antes de darla por fallida */
    private const INTENTOS = 3;

    /** Minutos entre consultas de un ticket del SIRE (SUNAT tarda en prepararlo) */
    private const ESPERA_TICKET = 2;

    /** XML por empresa en cada paso (para repartir el tiempo entre todas) */
    private const XML_POR_PASO = 10;

    public static function config(): object
    {
        return DB::table('estudio_programacion')->first()
            ?? (object) ['activo' => false, 'dia' => 5, 'hora' => 1, 'sire_compras' => true, 'sire_ventas' => true, 'xml' => true, 'avisado_periodo' => null];
    }

    /** @param  array{activo: bool, dia: int, hora: int, sire_compras: bool, sire_ventas: bool, xml: bool}  $d */
    public static function guardarConfig(array $d): void
    {
        $datos = ['activo' => (bool) $d['activo'], 'dia' => (int) $d['dia'], 'hora' => (int) $d['hora'], 'sire_compras' => (bool) $d['sire_compras'],
            'sire_ventas' => (bool) $d['sire_ventas'], 'xml' => (bool) $d['xml'], 'updated_at' => now()];
        DB::table('estudio_programacion')->exists()
            ? DB::table('estudio_programacion')->update($datos)
            : DB::table('estudio_programacion')->insert($datos + ['created_at' => now()]);
    }

    /** El periodo que se descarga: el mes anterior */
    public static function periodo(?CarbonImmutable $hoy = null): string
    {
        return ($hoy ?? CarbonImmutable::now())->startOfMonth()->subMonth()->format('Ym');
    }

    /** ¿Ya es el día (y la hora) programado y aún no se arrancó la descarga de este mes? */
    public static function toca(?CarbonImmutable $ahora = null): bool
    {
        $ahora ??= CarbonImmutable::now();
        $c = self::config();

        return $c->activo && $ahora->day >= (int) $c->dia && ($ahora->day > (int) $c->dia || $ahora->hour >= (int) $c->hora)
            && ! DB::table('estudio_descargas')->where('periodo', self::periodo($ahora))->exists();
    }

    /**
     * Arma lo que hay que traer de cada empresa del estudio (las que faltan, y vuelve a intentar las que fallaron).
     * Lo que no se puede traer por falta de credenciales queda anotado para que el contador lo complete.
     */
    public static function programar(string $periodo): int
    {
        $c = self::config();
        $tareas = array_keys(array_filter(['sire_compras' => $c->sire_compras, 'sire_ventas' => $c->sire_ventas, 'xml' => $c->xml]));
        $empresas = Empresa::whereIn('IdEmpresa', DB::table('estudio_empresas')->where(fn ($q) => $q->where('activa', true)->orWhere('propia', true))->select('IdEmpresa'))->get();
        $sinToken = ! config('services.jsonpe.token');
        $n = 0;
        foreach ($empresas as $e) {
            foreach ($tareas as $tarea) {
                $falta = match ($tarea) {
                    'xml' => $sinToken ? 'Falta el token de json.pe en el servidor.' : (self::conClaveSol($e) ? null : 'Falta la Clave SOL principal (Empresas del estudio › Configurar).'),
                    default => Sire::configurado($e) ? null : 'Faltan las credenciales del API SIRE (Empresas del estudio › Configurar).',
                };
                $fila = DB::table('estudio_descargas')->where(['periodo' => $periodo, 'IdEmpresa' => $e->IdEmpresa, 'tarea' => $tarea])->first();
                if ($fila && ! in_array($fila->estado, ['ERROR', 'OMITIDO'], true)) {
                    continue;
                }
                $datos = ['estado' => $falta ? 'OMITIDO' : 'PENDIENTE', 'mensaje' => $falta, 'intentos' => 0, 'solicitud_id' => null, 'fin_at' => null, 'updated_at' => now()];
                $fila ? DB::table('estudio_descargas')->where('id', $fila->id)->update($datos)
                    : DB::table('estudio_descargas')->insert($datos + ['periodo' => $periodo, 'IdEmpresa' => $e->IdEmpresa, 'tarea' => $tarea, 'cantidad' => 0, 'created_at' => now()]);
                $n++;
            }
        }
        DB::table('estudio_programacion')->update(['avisado_periodo' => null]);

        return $n;
    }

    private static function conClaveSol(Empresa $e): bool
    {
        if ($e->IdEmpresa === Estudio::ruc()) {
            $cliente = Tenancy::cliente();

            return (bool) ($cliente?->sol_usuario && $cliente?->sol_clave);
        }

        return (bool) ($e->sol_usuario && $e->sol_clave);
    }

    /**
     * Da un paso con todo lo pendiente hasta agotar el tiempo. Devuelve cuántos pasos dio.
     */
    public static function avanzar(int $segundos = 240): int
    {
        $hasta = microtime(true) + $segundos;
        $pasos = 0;
        $filas = DB::table('estudio_descargas')->whereIn('estado', ['PENDIENTE', 'PROCESO'])
            ->orderByRaw("FIELD(tarea, 'sire_compras', 'sire_ventas', 'xml')")->orderBy('id')->get();
        foreach ($filas as $f) {
            if (microtime(true) >= $hasta) {
                break;
            }
            try {
                $f->tarea === 'xml' ? self::pasoXml($f) : self::pasoSire($f);
            } catch (\Throwable $e) {
                report($e);
                self::marcar($f, 'ERROR', 'Error inesperado: '.mb_substr($e->getMessage(), 0, 200));
            }
            $pasos++;
        }
        self::avisarSiTermino();

        return $pasos;
    }

    private static function pasoSire(object $f): void
    {
        $empresa = Empresa::find($f->IdEmpresa);
        if (! $empresa) {
            self::marcar($f, 'ERROR', 'La empresa ya no existe.');

            return;
        }
        $libro = $f->tarea === 'sire_compras' ? Sire::COMPRAS : Sire::VENTAS;
        if (! $f->solicitud_id) {
            $r = SireSolicitudes::pedir($empresa, $libro, $f->periodo);
            if ($r['estado'] === 'ok') {
                DB::table('estudio_descargas')->where('id', $f->id)->update(['estado' => 'PROCESO', 'solicitud_id' => $r['id'], 'mensaje' => 'SUNAT está preparando la propuesta…', 'updated_at' => now()]);
            } else {
                $intentos = $f->intentos + 1;
                DB::table('estudio_descargas')->where('id', $f->id)->update(['intentos' => $intentos, 'mensaje' => mb_substr((string) ($r['mensaje'] ?? ''), 0, 255), 'updated_at' => now()]);
                if ($intentos >= self::INTENTOS) {
                    self::marcar($f, 'ERROR', $r['mensaje'] ?? 'SUNAT no aceptó el pedido.');
                }
            }

            return;
        }
        // Esperar un poco entre consultas: SUNAT tarda en preparar el archivo
        if (CarbonImmutable::parse($f->updated_at)->gt(now()->subMinutes(self::ESPERA_TICKET))) {
            return;
        }
        $s = DB::table('sire_solicitudes')->find($f->solicitud_id);
        $r = $s ? SireSolicitudes::revisar($empresa, $s) : ['estado' => 'error', 'mensaje' => 'Se perdió el pedido.'];
        match ($r['estado']) {
            'listo' => self::marcar($f, 'LISTO', ($r['filas'] ?? 0).' comprobante(s) en la propuesta.', (int) ($r['filas'] ?? 0)),
            'error' => self::marcar($f, 'ERROR', $r['mensaje'] ?? 'SUNAT respondió con error.'),
            default => DB::table('estudio_descargas')->where('id', $f->id)->update(['mensaje' => mb_substr((string) ($r['descripcion'] ?? 'En proceso'), 0, 255), 'updated_at' => now()]),
        };
    }

    private static function pasoXml(object $f): void
    {
        // Los XML se piden de los comprobantes de la propuesta de compras: primero tiene que estar lista
        $compras = DB::table('estudio_descargas')->where(['periodo' => $f->periodo, 'IdEmpresa' => $f->IdEmpresa, 'tarea' => 'sire_compras'])->first();
        if ($compras && in_array($compras->estado, ['PENDIENTE', 'PROCESO'], true)) {
            return;
        }
        if ($compras && $compras->estado !== 'LISTO') {
            self::marcar($f, 'ERROR', 'No se pudo traer la propuesta del SIRE de compras: sin ella no se saben qué XML pedir.');

            return;
        }
        $fallidos = json_decode((string) $f->fallidos, true) ?: [];
        $datos = ComprasDetalle::conPropuesta($f->IdEmpresa, $f->periodo);
        if (! $datos['propuesta']) {
            self::marcar($f, 'ERROR', 'No hay propuesta del SIRE de compras de este periodo.');

            return;
        }
        $pendientes = collect($datos['comprobantes'])
            ->filter(fn ($c) => ! $c['xml'] && in_array($c['tipo'], XmlJsonPe::TIPOS, true) && ! isset($fallidos["{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"]))
            ->values();
        $traidos = 0;
        foreach ($pendientes->take(self::XML_POR_PASO) as $c) {
            $r = XmlJsonPe::traer($f->IdEmpresa, $c['ruc'], $c['tipo'], $c['serie'], $c['numero']);
            if ($r['xml'] && ($leido = CompraXml::leer($r['xml'])) && ComprasDetalle::guardar($f->IdEmpresa, [$leido])['nuevos']) {
                $traidos++;

                continue;
            }
            if ($r['detener']) {
                self::marcar($f, 'ERROR', $r['error'] ?? 'json.pe no respondió.', $f->cantidad + $traidos);

                return;
            }
            $fallidos["{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"] = true;
        }
        $quedan = max(0, $pendientes->count() - self::XML_POR_PASO);
        $cantidad = $f->cantidad + $traidos;
        if ($quedan === 0) {
            self::marcar($f, 'LISTO', "{$cantidad} XML traído(s)".(count($fallidos) ? ', '.count($fallidos).' no están en SUNAT' : '').'.', $cantidad, $fallidos);

            return;
        }
        DB::table('estudio_descargas')->where('id', $f->id)->update(['estado' => 'PROCESO', 'cantidad' => $cantidad, 'fallidos' => json_encode($fallidos),
            'mensaje' => "{$cantidad} traído(s), faltan {$quedan}…", 'updated_at' => now()]);
    }

    private static function marcar(object $f, string $estado, ?string $mensaje, ?int $cantidad = null, ?array $fallidos = null): void
    {
        DB::table('estudio_descargas')->where('id', $f->id)->update(array_filter([
            'estado' => $estado, 'mensaje' => $mensaje !== null ? mb_substr($mensaje, 0, 255) : null, 'cantidad' => $cantidad,
            'fallidos' => $fallidos !== null ? json_encode($fallidos) : null, 'fin_at' => now(), 'updated_at' => now(),
        ], fn ($v) => $v !== null));
    }

    /** Cuando ya no queda nada en cola del periodo, avisa al celular del administrador (una vez) */
    private static function avisarSiTermino(): void
    {
        $c = self::config();
        $periodo = DB::table('estudio_descargas')->max('periodo');
        if (! $periodo || $c->avisado_periodo === $periodo || DB::table('estudio_descargas')->where('periodo', $periodo)->whereIn('estado', ['PENDIENTE', 'PROCESO'])->exists()) {
            return;
        }
        $r = self::resumen($periodo);
        DB::table('estudio_programacion')->update(['avisado_periodo' => $periodo]);
        if (($sucursal = Estudio::sucursalDe((string) Estudio::ruc())) && Estudio::activo()) {
            AvisosCelular::alDueno($sucursal, '📥 Descarga de '.Contabilidad\Contabilidad::nombrePeriodo($periodo).' lista',
                "{$r['listas']} tarea(s) listas en {$r['empresas']} empresa(s)".($r['errores'] ? ", {$r['errores']} con error" : '').($r['omitidas'] ? ", {$r['omitidas']} sin credenciales" : '').'.',
                route('estudio.descargas', ['periodo' => $periodo]));
            AvisosCelular::enviar();
        }
    }

    /** @return array{empresas: int, listas: int, errores: int, omitidas: int, pendientes: int} */
    public static function resumen(string $periodo): array
    {
        $filas = DB::table('estudio_descargas')->where('periodo', $periodo)->get(['IdEmpresa', 'estado']);

        return ['empresas' => $filas->unique('IdEmpresa')->count(), 'listas' => $filas->where('estado', 'LISTO')->count(),
            'errores' => $filas->where('estado', 'ERROR')->count(), 'omitidas' => $filas->where('estado', 'OMITIDO')->count(),
            'pendientes' => $filas->whereIn('estado', ['PENDIENTE', 'PROCESO'])->count()];
    }

    /** @return Collection<string, Collection<int, object>> por empresa: sus tareas del periodo */
    public static function delPeriodo(string $periodo): Collection
    {
        return DB::table('estudio_descargas as d')->join('empresa as e', 'e.IdEmpresa', '=', 'd.IdEmpresa')->where('d.periodo', $periodo)
            ->orderBy('e.NomEmpresa')->get(['d.*', 'e.NomEmpresa'])->groupBy('IdEmpresa');
    }
}
