<?php
namespace App\Support;

use App\Models\{Turno, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Socios: deuda (cargos), cobro con amortización y morosidad.
 * La deuda NO es comprobante: el comprobante (con las cuentas contables del concepto) se emite al cobrar, como hoy.
 */
class Socios
{
    public const ESTADOS = ['ACTIVO', 'SUSPENDIDO', 'RETIRADO', 'FALLECIDO'];

    public static function config(int $suc): object
    {
        $c = DB::table('socio_config')->where('id_empresa_negocio', $suc)->first();
        return $c ?: (object) ['id_empresa_negocio' => $suc, 'meses_suspension' => 3, 'edad_max_hijos' => 25,
            'parentescos' => 'CONYUGE,HIJO(A),PADRE,MADRE', 'IdProducto_ordinaria' => null, 'ultimo_periodo' => null];
    }

    public static function parentescos(object $cfg): array
    {
        return array_values(array_filter(array_map(fn($p) => mb_strtoupper(trim($p)), explode(',', (string) $cfg->parentescos))));
    }

    public static function token(): string
    {
        return Str::random(32);
    }

    public static function nombreMes(string $periodo): string
    {
        // En Perú se escribe SETIEMBRE
        return str_replace('SEPTIEMBRE', 'SETIEMBRE', mb_strtoupper(Carbon::createFromFormat('Ym', $periodo)->locale('es')->translatedFormat('F Y')));
    }

    /**
     * Todos los clientes con DNI que aún no son socios pasan a ser socios ACTIVOS de la categoría elegida
     * (luego el usuario retira a los que no lo son). Código correlativo a partir del último numérico.
     */
    public static function desdeClientes(User $user, ?int $catId): int
    {
        $suc = (int) $user->id_empresa_negocio;
        return DB::transaction(function () use ($user, $suc, $catId) {
            $ya = DB::table('socios')->where('id_empresa_negocio', $suc)->pluck('clicod')->flip();
            $ultimo = (int) DB::table('socios')->where('id_empresa_negocio', $suc)->whereRaw("codigo REGEXP '^[0-9]+$'")->max(DB::raw('CAST(codigo AS UNSIGNED)'));
            $clientes = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('tdicod', '1')
                ->whereRaw("clinum REGEXP '^[0-9]{8}$'")->where('clinum', '!=', '00000000')
                ->where(fn($q) => $q->whereNull('cliest')->orWhere('cliest', '!=', 'Inactivo'))
                ->orderBy('clinom')->get(['clicod']);
            $filas = [];
            foreach ($clientes as $c) {
                if (isset($ya[$c->clicod])) {
                    continue;
                }
                $filas[] = ['codigo' => str_pad((string) ++$ultimo, 4, '0', STR_PAD_LEFT), 'clicod' => $c->clicod, 'cat_soc_id' => $catId,
                    'fecha_ingreso' => now()->toDateString(), 'estado' => 'ACTIVO', 'token' => self::token(), 'id_empresa_negocio' => $suc, 'creado' => now()];
            }
            foreach (array_chunk($filas, 500) as $lote) {
                DB::table('socios')->insert($lote);
            }
            return count($filas);
        });
    }

    // ------------------------------------------------------------------ cargos (deuda)

    /** Cuántos socios recibirían la cuota del mes, cuánto suma y cuántos ya la tienen */
    public static function previsualizar(int $suc, string $periodo): array
    {
        $cfg = self::config($suc);
        $socios = self::sociosParaCuota($suc);
        $yaTienen = $cfg->IdProducto_ordinaria ? DB::table('socio_cargos')->where('id_empresa_negocio', $suc)
            ->where('IdProducto', $cfg->IdProducto_ordinaria)->where('periodo', $periodo)->where('estado', '!=', 'ANULADO')->pluck('soc_id')->flip() : collect();
        $nuevos = $socios->reject(fn($s) => isset($yaTienen[$s->soc_id]));
        return ['socios' => $nuevos->count(), 'total' => round($nuevos->sum('cuota'), 2), 'ya_generados' => $yaTienen->count(),
            'sin_categoria' => DB::table('socios')->where('id_empresa_negocio', $suc)->whereIn('estado', ['ACTIVO', 'SUSPENDIDO'])
                ->where(fn($q) => $q->whereNull('cat_soc_id')->orWhereNotIn('cat_soc_id', DB::table('socio_categorias')->where('cuota', '>', 0)->select('cat_soc_id')))->count()];
    }

    /** Activos y suspendidos (el suspendido sigue debiendo) con categoría de cuota > 0 */
    private static function sociosParaCuota(int $suc)
    {
        return DB::table('socios as s')->join('socio_categorias as c', 'c.cat_soc_id', '=', 's.cat_soc_id')
            ->where('s.id_empresa_negocio', $suc)->whereIn('s.estado', ['ACTIVO', 'SUSPENDIDO'])->where('c.cuota', '>', 0)
            ->get(['s.soc_id', 'c.cuota']);
    }

    /** Un clic: la cuota ordinaria del mes a cada socio según su categoría (no repite a quien ya la tiene) */
    public static function generarCuotas(User $user, string $periodo): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $cfg = self::config($suc);
        if (!$cfg->IdProducto_ordinaria) {
            throw new \RuntimeException('Primero elige en Configuración el concepto (producto) de la cuota ordinaria.');
        }
        $concepto = DB::table('productos')->where('IdProducto', $cfg->IdProducto_ordinaria)->value('pronom') ?: 'CUOTA ORDINARIA';
        $descripcion = mb_substr(mb_strtoupper($concepto) . ' - ' . self::nombreMes($periodo), 0, 150);

        return DB::transaction(function () use ($user, $suc, $cfg, $periodo, $descripcion) {
            $n = 0;
            $total = 0;
            foreach (self::sociosParaCuota($suc) as $s) {
                $nuevo = DB::table('socio_cargos')->insertOrIgnore([
                    'soc_id' => $s->soc_id, 'IdProducto' => $cfg->IdProducto_ordinaria, 'descripcion' => $descripcion, 'periodo' => $periodo,
                    'monto' => $s->cuota, 'estado' => 'PENDIENTE', 'creado' => now(), 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc,
                ]);
                if ($nuevo) {
                    $n++;
                    $total += (float) $s->cuota;
                }
            }
            DB::table('socio_config')->updateOrInsert(['id_empresa_negocio' => $suc],
                ['ultimo_periodo' => max((string) $cfg->ultimo_periodo, $periodo)] + (array) self::config($suc));
            $suspendidos = self::actualizarMorosidad($suc);
            return ['cargos' => $n, 'total' => round($total, 2), 'suspendidos' => $suspendidos];
        });
    }

    /** Cargo extraordinario, multa, cuota de ingreso... a uno, a una categoría o a todos los activos */
    public static function cargar(User $user, array $d): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $prod = DB::table('productos')->where('IdProducto', $d['IdProducto'])->where('id_empresa_negocio', $suc)->first();
        if (!$prod) {
            throw new \RuntimeException('Concepto no válido.');
        }
        $q = DB::table('socios')->where('id_empresa_negocio', $suc);
        if (!empty($d['soc_ids'])) {
            $q->whereIn('soc_id', $d['soc_ids']);
        } else {
            $q->whereIn('estado', ['ACTIVO', 'SUSPENDIDO'])->when($d['cat_soc_id'] ?? null, fn($w, $c) => $w->where('cat_soc_id', $c));
        }
        $ids = $q->pluck('soc_id');
        if ($ids->isEmpty()) {
            throw new \RuntimeException('No hay socios a quienes aplicar el cargo.');
        }
        $descripcion = mb_substr(mb_strtoupper(trim($d['descripcion'] ?? '') ?: $prod->pronom), 0, 150);
        $periodo = $d['periodo'] ?? null;

        $n = 0;
        DB::transaction(function () use ($ids, $user, $suc, $prod, $descripcion, $periodo, $d, &$n) {
            foreach ($ids as $id) {
                $n += DB::table('socio_cargos')->insertOrIgnore([
                    'soc_id' => $id, 'IdProducto' => $prod->IdProducto, 'descripcion' => $descripcion, 'periodo' => $periodo,
                    'monto' => round((float) $d['monto'], 2), 'estado' => 'PENDIENTE', 'creado' => now(), 'IdUsuario' => $user->IdUsuario,
                    'id_empresa_negocio' => $suc,
                ]);
            }
        });
        self::actualizarMorosidad($suc);
        return ['cargos' => $n, 'total' => round($n * (float) $d['monto'], 2)];
    }

    // ------------------------------------------------------------------ cobro

    /**
     * Cobra cargos del socio (completo o a cuenta): emite el comprobante con una línea por cargo y lo descuenta de la deuda.
     * @param array $montos [car_id => monto a pagar]
     * @return int IdCpe_cabecera
     */
    public static function cobrar(User $user, int $socId, array $montos, array $datos): int
    {
        $suc = (int) $user->id_empresa_negocio;
        return DB::transaction(function () use ($user, $suc, $socId, $montos, $datos) {
            $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (!$turno) {
                throw new \RuntimeException('Debes aperturar tu turno antes de cobrar.');
            }
            $socio = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
                ->where('s.soc_id', $socId)->where('s.id_empresa_negocio', $suc)->first(['s.*', 'c.clinum', 'c.clinom', 'c.tdicod', 'c.clidir', 'c.clicor', 'c.telefono']);
            if (!$socio) {
                throw new \RuntimeException('Socio no encontrado.');
            }

            $montos = array_filter(array_map(fn($m) => round((float) $m, 2), $montos), fn($m) => $m > 0);
            if (!$montos) {
                throw new \RuntimeException('Elige al menos una cuota y el monto a pagar.');
            }
            $cargos = DB::table('socio_cargos')->where('soc_id', $socId)->whereIn('car_id', array_keys($montos))
                ->where('estado', 'PENDIENTE')->orderBy('periodo')->orderBy('car_id')->lockForUpdate()->get();
            if ($cargos->count() !== count($montos)) {
                throw new \RuntimeException('Alguna cuota ya fue pagada o anulada. Actualiza la pantalla.');
            }

            $lineas = [];
            foreach ($cargos as $c) {
                $saldo = round($c->monto - $c->pagado, 2);
                $pago = $montos[$c->car_id];
                if ($pago > $saldo + 0.001) {
                    throw new \RuntimeException("{$c->descripcion}: el saldo es S/ " . number_format($saldo, 2) . '.');
                }
                $lineas[] = ['IdProducto' => $c->IdProducto, 'cantidad' => 1, 'precio' => $pago,
                    'descripcion' => mb_substr($c->descripcion . ($pago < $saldo - 0.001 ? ' (A CUENTA)' : ''), 0, 150)];
            }

            $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
            $doc = trim((string) ($datos['clinum'] ?? '')) ?: ($socio->clinum ?: '00000000');
            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => $datos['tdocod'], 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
                'tdicod' => $datos['tdicod'] ?? ($socio->tdicod ?: (strlen($doc) === 11 ? '6' : '1')), 'clinum' => $doc,
                'clinom' => $datos['clinom'] ?? $socio->clinom, 'clidir' => $datos['clidir'] ?? $socio->clidir,
                'clicor' => $socio->clicor, 'telefono' => $socio->telefono,
                'observaciones' => 'SOCIO N° ' . $socio->codigo,
                'id_med_pag' => $datos['id_med_pag'] ?? [], 'mon_med_pag' => $datos['mon_med_pag'] ?? [], 'paga' => $datos['paga'] ?? 0,
            ], $lineas, ['ped_tip' => 'SOCIO', 'IdUsuario_ven' => $user->IdUsuario]);

            foreach ($cargos as $c) {
                $pago = $montos[$c->car_id];
                DB::table('socio_pagos')->insert(['car_id' => $c->car_id, 'IdCpe_cabecera' => $cabId, 'monto' => $pago, 'fecha' => now()]);
                $pagado = round($c->pagado + $pago, 2);
                DB::table('socio_cargos')->where('car_id', $c->car_id)->update(['pagado' => $pagado, 'estado' => $pagado >= $c->monto - 0.001 ? 'PAGADO' : 'PENDIENTE']);
            }
            self::actualizarMorosidad($suc, $socId);
            return $cabId;
        });
    }

    /** Si el comprobante se anula (o tiene nota de crédito total), lo que pagó vuelve a ser deuda */
    public static function revertirComprobante(int $cabId): void
    {
        $pagos = DB::table('socio_pagos')->where('IdCpe_cabecera', $cabId)->where('anulado', 0)->get();
        foreach ($pagos as $p) {
            $c = DB::table('socio_cargos')->where('car_id', $p->car_id)->lockForUpdate()->first();
            if ($c) {
                DB::table('socio_cargos')->where('car_id', $c->car_id)->update(['pagado' => max(0, round($c->pagado - $p->monto, 2)),
                    'estado' => $c->estado === 'ANULADO' ? 'ANULADO' : 'PENDIENTE']);
            }
            DB::table('socio_pagos')->where('pag_id', $p->pag_id)->update(['anulado' => 1]);
        }
        if ($pagos->isNotEmpty() && ($c ?? null)) {
            self::actualizarMorosidad((int) $c->id_empresa_negocio, (int) $c->soc_id);
        }
    }

    // ------------------------------------------------------------------ morosidad

    /** Cuotas ordinarias vencidas sin pagar (completo) por socio */
    public static function mesesDebe(int $suc): \Illuminate\Support\Collection
    {
        $cfg = self::config($suc);
        // Cuotas del concepto configurado; sin configurar, todo cargo mensual (con periodo)
        return DB::table('socio_cargos')->where('id_empresa_negocio', $suc)
            ->when($cfg->IdProducto_ordinaria, fn($q) => $q->where('IdProducto', $cfg->IdProducto_ordinaria), fn($q) => $q->whereNotNull('periodo'))
            ->where('estado', 'PENDIENTE')->groupBy('soc_id')->select('soc_id', DB::raw('COUNT(*) as meses'))->pluck('meses', 'soc_id');
    }

    /**
     * Suspende a quien debe N cuotas ordinarias o más; reactiva a los que suspendió el sistema cuando se ponen al día.
     * Las suspensiones manuales no se tocan. Devuelve cuántos se suspendieron.
     */
    public static function actualizarMorosidad(int $suc, ?int $socId = null): int
    {
        $cfg = self::config($suc);
        $limite = (int) $cfg->meses_suspension;
        $debe = self::mesesDebe($suc);
        $suspendidos = 0;

        $q = DB::table('socios')->where('id_empresa_negocio', $suc)->whereIn('estado', ['ACTIVO', 'SUSPENDIDO'])
            ->when($socId, fn($w) => $w->where('soc_id', $socId));
        foreach ($q->get(['soc_id', 'estado', 'suspendido_auto']) as $s) {
            $meses = (int) ($debe[$s->soc_id] ?? 0);
            if ($limite > 0 && $meses >= $limite && $s->estado === 'ACTIVO') {
                DB::table('socios')->where('soc_id', $s->soc_id)->update(['estado' => 'SUSPENDIDO', 'suspendido_auto' => 1]);
                $suspendidos++;
            } elseif ($s->estado === 'SUSPENDIDO' && $s->suspendido_auto && ($limite === 0 || $meses < $limite)) {
                DB::table('socios')->where('soc_id', $s->soc_id)->update(['estado' => 'ACTIVO', 'suspendido_auto' => 0]);
            }
        }
        return $suspendidos;
    }

    /** Para el carnet / portería: AL DÍA, DEBE n meses, SUSPENDIDO... */
    public static function situacion(object $socio, int $meses, float $deuda = 0): array
    {
        if ($socio->estado === 'SUSPENDIDO') return ['SUSPENDIDO', 'red'];
        if ($socio->estado !== 'ACTIVO') return [$socio->estado, 'gray'];
        if ($meses > 0) return ['DEBE ' . $meses . ($meses === 1 ? ' MES' : ' MESES'), 'amber'];
        if ($deuda > 0.009) return ['CON DEUDA', 'amber'];
        return ['AL DÍA', 'green'];
    }
}
