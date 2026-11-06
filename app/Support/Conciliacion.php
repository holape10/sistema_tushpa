<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Conciliación bancaria: el extracto del banco contra el libro bancos del sistema.
 *
 *  - Extracto: se sube en Excel (xlsx, xls, ods, csv); las columnas se reconocen por su título (fecha, descripción,
 *    operación, cargo/abono o monto, saldo). Volver a subir el mismo archivo no duplica nada.
 *  - Automático: 1) mismo N° de operación y monto; 2) mismo monto con fechas a 3 días o menos (solo si hay uno solo
 *    posible); 3) un abono del banco igual a la suma de los cobros del mismo día (el depósito diario del POS o Yape).
 *  - A mano: se marcan líneas del banco y del sistema que suman lo mismo. Lo que solo está en el banco (comisión, ITF…)
 *    se registra con un clic como movimiento propio (con su asiento) y queda conciliado.
 *  - Reporte del mes: saldo según banco = saldo según libros − lo del sistema que el banco aún no refleja + lo del banco
 *    que el sistema aún no tiene.
 */
class Conciliacion
{
    /** Días de diferencia que se aceptan entre la fecha del sistema y la del banco */
    private const DIAS = 3;

    // ============================================================== extracto

    /**
     * Importa las líneas del estado de cuenta.
     *
     * @return array{nuevas: int, repetidas: int, ignoradas: int}
     */
    public static function importar(User $user, object $cuenta, string $ruta, string $extension): array
    {
        $filas = ExcelLector::filas($ruta, $extension);
        [$inicio, $col] = self::columnas($filas);

        $r = ['nuevas' => 0, 'repetidas' => 0, 'ignoradas' => 0];
        $vistas = [];
        DB::transaction(function () use ($filas, $inicio, $col, $cuenta, $user, &$r, &$vistas) {
            foreach (array_slice($filas, $inicio + 1) as $f) {
                $fecha = self::fecha($f[$col['fecha']] ?? '');
                if (isset($col['monto'])) {
                    $monto = self::numero($f[$col['monto']] ?? '');
                } else {
                    $abono = self::numero($f[$col['abono']] ?? '');
                    $cargo = self::numero($f[$col['cargo']] ?? '');
                    $monto = $abono !== null || $cargo !== null ? round(abs((float) $abono) - abs((float) $cargo), 2) : null;
                }
                if (! $fecha || $monto === null || abs($monto) < 0.005) {
                    $r['ignoradas']++;   // títulos repetidos, totales, filas vacías

                    continue;
                }
                $descripcion = isset($col['descripcion']) ? mb_substr(trim((string) ($f[$col['descripcion']] ?? '')), 0, 255) : '';
                $operacion = isset($col['operacion']) ? mb_substr(trim((string) ($f[$col['operacion']] ?? '')), 0, 40) : '';
                $saldo = isset($col['saldo']) ? self::numero($f[$col['saldo']] ?? '') : null;
                // La misma línea dos veces en el archivo es válida (dos depósitos iguales); en otro archivo, es repetida
                $clave = $fecha.'|'.number_format($monto, 2, '.', '').'|'.$operacion.'|'.mb_strtoupper($descripcion);
                $vistas[$clave] = ($vistas[$clave] ?? 0) + 1;
                $huella = sha1($clave.'|'.$vistas[$clave]);
                $nueva = DB::table('bancos_extracto')->insertOrIgnore([
                    'cuenta_id' => $cuenta->id, 'fecha' => $fecha, 'descripcion' => $descripcion ?: null, 'operacion' => $operacion ?: null,
                    'monto' => $monto, 'saldo' => $saldo, 'huella' => $huella, 'IdUsuario' => $user->IdUsuario, 'created_at' => now(),
                ]);
                $nueva ? $r['nuevas']++ : $r['repetidas']++;
            }
        });

        return $r;
    }

    /** Excel de ejemplo para quien no tiene el formato del banco a la mano */
    public static function formato(): string
    {
        return (new Excel)->hoja('Extracto', ['Fecha', 'Descripción', 'N° operación', 'Cargo', 'Abono', 'Saldo'], [
            ['01/11/2026', 'DEPOSITO EFECTIVO', '000123', '', '500.00', '1500.00'],
            ['02/11/2026', 'COMISION MANTENIMIENTO', '', '12.00', '', '1488.00'],
            ['02/11/2026', 'ABONO POS VISA', '884512', '', '320.50', '1808.50'],
        ])->guardar();
    }

    // ============================================================== conciliar

    /**
     * Pendientes del mes: líneas del banco y movimientos del sistema que aún no se juntaron.
     *
     * @return array{banco: Collection, sistema: Collection}
     */
    public static function pendientes(object $cuenta, string $desde, string $hasta): array
    {
        $conciliados = DB::table('bancos_conciliados')->where('cuenta_id', $cuenta->id)->whereNotNull('origen')->get(['origen', 'origen_id'])
            ->map(fn ($c) => $c->origen.':'.$c->origen_id)->flip();

        return [
            'banco' => DB::table('bancos_extracto as e')->leftJoin('bancos_conciliados as c', 'c.extracto_id', '=', 'e.id')
                ->where('e.cuenta_id', $cuenta->id)->whereBetween('e.fecha', [$desde, $hasta])->whereNull('c.id')
                ->orderBy('e.fecha')->orderBy('e.id')->get(['e.*']),
            'sistema' => Bancos::movimientos($cuenta, $desde, $hasta)->reject(fn ($m) => isset($conciliados[$m['origen'].':'.$m['id']]))->values(),
        ];
    }

    /** Junta solo lo que coincide sin dudas. Devuelve cuántas líneas del banco quedaron conciliadas. */
    public static function automatico(User $user, object $cuenta, string $desde, string $hasta): int
    {
        return DB::transaction(function () use ($user, $cuenta, $desde, $hasta) {
            // Se mira unos días antes y después para lo que el banco registra con retraso
            $p = self::pendientes($cuenta, Carbon::parse($desde)->subDays(self::DIAS)->toDateString(), Carbon::parse($hasta)->addDays(self::DIAS)->toDateString());
            $banco = $p['banco']->filter(fn ($e) => $e->fecha >= $desde && $e->fecha <= $hasta)->values();
            $sistema = $p['sistema']->keyBy(fn ($m) => $m['origen'].':'.$m['id']);
            $usadas = 0;
            $soloDigitos = fn ($x) => ltrim(preg_replace('/\D/', '', (string) $x), '0');

            foreach ($banco as $e) {
                $candidatos = $sistema->filter(fn ($m) => abs($m['monto'] - (float) $e->monto) < 0.005);
                // 1) Mismo número de operación
                $op = $soloDigitos($e->operacion);
                $elegido = strlen($op) >= 4 ? $candidatos->first(fn ($m) => $soloDigitos($m['operacion']) === $op) : null;
                // 2) Mismo monto, fecha cercana, y que no haya otro igual en el banco ni en el sistema que se preste a confusión
                if (! $elegido) {
                    $cerca = $candidatos->filter(fn ($m) => abs(Carbon::parse($m['fecha'])->diffInDays(Carbon::parse($e->fecha), false)) <= self::DIAS);
                    $gemelos = $banco->filter(fn ($x) => abs((float) $x->monto - (float) $e->monto) < 0.005
                        && abs(Carbon::parse($x->fecha)->diffInDays(Carbon::parse($e->fecha), false)) <= self::DIAS * 2)->count();
                    $elegido = $cerca->count() === 1 && $gemelos === 1 ? $cerca->first() : null;
                }
                if ($elegido) {
                    self::juntar($user, $cuenta, [$e->id], [$elegido]);
                    $sistema->forget($elegido['origen'].':'.$elegido['id']);
                    $usadas++;

                    continue;
                }
                // 3) Un abono igual a la suma de los cobros de ese día (o del día anterior): el depósito diario del POS/Yape
                if ((float) $e->monto > 0) {
                    foreach ([0, 1] as $atras) {
                        $dia = Carbon::parse($e->fecha)->subDays($atras)->toDateString();
                        $delDia = $sistema->filter(fn ($m) => $m['fecha'] === $dia && $m['monto'] > 0 && $m['origen'] !== 'banco');
                        if ($delDia->count() > 1 && $delDia->count() <= 200 && abs($delDia->sum('monto') - (float) $e->monto) < 0.005) {
                            self::juntar($user, $cuenta, [$e->id], $delDia->values()->all());
                            foreach ($delDia->keys() as $k) {
                                $sistema->forget($k);
                            }
                            $usadas++;
                            break;
                        }
                    }
                }
            }

            return $usadas;
        });
    }

    /**
     * Junta a mano líneas del banco con movimientos del sistema: tienen que sumar lo mismo.
     *
     * @param  array<int, int>  $extractoIds
     * @param  array<int, string>  $sistemaClaves  "origen:id"
     */
    public static function manual(User $user, object $cuenta, array $extractoIds, array $sistemaClaves): void
    {
        DB::transaction(function () use ($user, $cuenta, $extractoIds, $sistemaClaves) {
            $lineas = DB::table('bancos_extracto as e')->leftJoin('bancos_conciliados as c', 'c.extracto_id', '=', 'e.id')
                ->where('e.cuenta_id', $cuenta->id)->whereIn('e.id', $extractoIds)->lockForUpdate()->get(['e.*', 'c.id as ya']);
            if ($lineas->count() !== count(array_unique($extractoIds)) || $lineas->whereNotNull('ya')->isNotEmpty()) {
                throw new \RuntimeException('Alguna línea del banco ya está conciliada o no es de esta cuenta. Actualiza la pantalla.');
            }
            // Todo el libro bancos desde el inicio de la cuenta (lo marcado puede ser de otro mes)
            $todos = Bancos::movimientos($cuenta, (string) $cuenta->fecha_inicio, now()->addYear()->toDateString())->keyBy(fn ($m) => $m['origen'].':'.$m['id']);
            $movs = collect($sistemaClaves)->unique()->map(fn ($k) => $todos[$k] ?? throw new \RuntimeException('Un movimiento del sistema ya no existe. Actualiza la pantalla.'));
            if ($lineas->isEmpty() || $movs->isEmpty()) {
                throw new \RuntimeException('Marca al menos una línea del banco y un movimiento del sistema.');
            }
            $diferencia = round($lineas->sum('monto') - $movs->sum('monto'), 2);
            if (abs($diferencia) >= 0.005) {
                throw new \RuntimeException('No suman lo mismo: el banco '.number_format($lineas->sum('monto'), 2).' y el sistema '.number_format($movs->sum('monto'), 2)
                    .' (diferencia '.number_format($diferencia, 2).'). Si la diferencia es una comisión, regístrala primero.');
            }
            self::juntar($user, $cuenta, $lineas->pluck('id')->all(), $movs->values()->all());
        });
    }

    /** Deshace un grupo conciliado */
    public static function deshacer(object $cuenta, string $grupo): void
    {
        DB::table('bancos_conciliados')->where('cuenta_id', $cuenta->id)->where('grupo', $grupo)->delete();
    }

    /** Saca de la conciliación un movimiento del sistema (se anuló): su grupo entero vuelve a pendientes */
    public static function desconciliarOrigen(string $origen, int $id): void
    {
        $grupo = DB::table('bancos_conciliados')->where('origen', $origen)->where('origen_id', $id)->value('grupo');
        if ($grupo) {
            DB::table('bancos_conciliados')->where('grupo', $grupo)->delete();
        }
    }

    /**
     * Lo que solo está en el banco (comisión, ITF, interés…): se registra como movimiento propio con su asiento y queda
     * conciliado con esa línea.
     */
    public static function registrarDesdeBanco(User $user, object $cuenta, int $extractoId, string $tipo, ?string $contrapartida = null): int
    {
        return DB::transaction(function () use ($user, $cuenta, $extractoId, $tipo, $contrapartida) {
            $e = DB::table('bancos_extracto')->where('id', $extractoId)->where('cuenta_id', $cuenta->id)->lockForUpdate()->first();
            if (! $e || DB::table('bancos_conciliados')->where('extracto_id', $extractoId)->exists()) {
                throw new \RuntimeException('Esa línea del banco ya está conciliada.');
            }
            $signo = Bancos::TIPOS[$tipo][1] ?? 0;
            if ($signo !== 0 && $signo * (float) $e->monto < 0 && ! in_array($tipo, ['OTRO_INGRESO', 'OTRO_EGRESO'], true)) {
                throw new \RuntimeException('Esa línea es un '.((float) $e->monto > 0 ? 'abono' : 'cargo').': elige un tipo que '.((float) $e->monto > 0 ? 'entre' : 'salga').' del banco.');
            }
            $id = Bancos::registrarMovimiento($user, $cuenta, ['tipo' => $tipo, 'fecha' => $e->fecha, 'monto' => abs((float) $e->monto),
                'descripcion' => $e->descripcion, 'operacion' => $e->operacion, 'contrapartida' => $contrapartida,
                'sentido' => (float) $e->monto > 0 ? 'entra' : 'sale']);
            $mov = DB::table('bancos_movimientos')->where('id', $id)->first();
            self::juntar($user, $cuenta, [$e->id], [['origen' => 'banco', 'id' => $id, 'monto' => (float) $mov->monto]]);

            return $id;
        });
    }

    // ============================================================== reporte

    /**
     * Conciliación al cierre del mes.
     *
     * @return array<string, mixed>
     */
    public static function reporte(object $cuenta, string $periodo): array
    {
        $desde = Carbon::createFromFormat('!Ym', $periodo)->toDateString();
        $hasta = Carbon::createFromFormat('!Ym', $periodo)->endOfMonth()->toDateString();
        $saldoLibros = Bancos::saldoLibros($cuenta, $hasta);
        $p = self::pendientes($cuenta, (string) $cuenta->fecha_inicio, $hasta);

        // Saldo del banco: el último saldo que trae el extracto o, si no trae, el inicial más sus movimientos
        $conSaldo = DB::table('bancos_extracto')->where('cuenta_id', $cuenta->id)->where('fecha', '<=', $hasta)->whereNotNull('saldo')
            ->orderByDesc('fecha')->orderByDesc('id')->first();
        $sumaBanco = (float) DB::table('bancos_extracto')->where('cuenta_id', $cuenta->id)->whereBetween('fecha', [$cuenta->fecha_inicio, $hasta])->sum('monto');
        $saldoBanco = $conSaldo ? (float) $conSaldo->saldo : round((float) $cuenta->saldo_inicial + $sumaBanco, 2);

        $sistemaPendiente = round($p['sistema']->sum('monto'), 2);
        $bancoPendiente = round($p['banco']->sum('monto'), 2);
        $calculado = round($saldoLibros - $sistemaPendiente + $bancoPendiente, 2);

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'saldo_libros' => $saldoLibros, 'saldo_banco' => $saldoBanco, 'saldo_banco_del_extracto' => (bool) $conSaldo,
            'sistema_pendiente' => $p['sistema'], 'banco_pendiente' => $p['banco'],
            'total_sistema_pendiente' => $sistemaPendiente, 'total_banco_pendiente' => $bancoPendiente,
            'calculado' => $calculado, 'diferencia' => round($saldoBanco - $calculado, 2),
            'hay_extracto' => DB::table('bancos_extracto')->where('cuenta_id', $cuenta->id)->whereBetween('fecha', [$desde, $hasta])->exists(),
            'saldo_mayor' => Bancos::saldoMayor((string) $cuenta->IdEmpresa, (string) $cuenta->cuenta_contable, $hasta),
        ];
    }

    // ============================================================== internos

    /** Graba el grupo conciliado: las líneas del banco y los movimientos del sistema que lo forman */
    private static function juntar(User $user, object $cuenta, array $extractoIds, array $movs): void
    {
        $grupo = (string) Str::uuid();
        $montos = DB::table('bancos_extracto')->whereIn('id', $extractoIds)->pluck('monto', 'id');
        foreach ($extractoIds as $id) {
            DB::table('bancos_conciliados')->insert(['cuenta_id' => $cuenta->id, 'grupo' => $grupo, 'extracto_id' => $id, 'monto' => (float) $montos[$id],
                'IdUsuario' => $user->IdUsuario, 'created_at' => now()]);
        }
        foreach ($movs as $m) {
            DB::table('bancos_conciliados')->insert(['cuenta_id' => $cuenta->id, 'grupo' => $grupo, 'origen' => $m['origen'], 'origen_id' => $m['id'],
                'monto' => $m['monto'], 'IdUsuario' => $user->IdUsuario, 'created_at' => now()]);
        }
    }

    /**
     * Dónde empiezan los datos y qué columna es qué (por el título).
     *
     * @return array{0: int, 1: array<string, int>}
     */
    private static function columnas(array $filas): array
    {
        $buscar = [
            'fecha' => ['fecha'],
            'descripcion' => ['descripcion', 'concepto', 'detalle', 'glosa', 'movimiento', 'transaccion'],
            'operacion' => ['operacion', 'n op', 'nro op', 'num op', 'numero', 'nro', 'documento', 'referencia', 'n°'],
            'cargo' => ['cargo', 'debito', 'retiro', 'egreso', 'salida', 'debe'],
            'abono' => ['abono', 'credito', 'deposito', 'ingreso', 'entrada', 'haber'],
            'monto' => ['monto', 'importe'],
            'saldo' => ['saldo'],
        ];
        foreach (array_slice($filas, 0, 25, true) as $i => $fila) {
            $col = [];
            foreach ($fila as $j => $titulo) {
                $t = Str::of((string) $titulo)->lower()->ascii()->replace(['.', ':', 'º'], ' ')->squish()->value();
                if ($t === '') {
                    continue;
                }
                foreach ($buscar as $campo => $palabras) {
                    // "Fecha valor" no gana a "Fecha"; la primera que aparece se queda
                    if (! isset($col[$campo]) && collect($palabras)->contains(fn ($p) => str_contains($t, $p))
                        && ! ($campo === 'fecha' && str_contains($t, 'valor') && collect($fila)->contains(fn ($x) => Str::of((string) $x)->lower()->ascii()->trim()->value() === 'fecha'))) {
                        $col[$campo] = $j;
                        break;
                    }
                }
            }
            if (isset($col['fecha']) && (isset($col['monto']) || (isset($col['cargo']) && isset($col['abono'])))) {
                if (isset($col['cargo'], $col['abono'])) {
                    unset($col['monto']);
                }

                return [$i, $col];
            }
        }
        throw new \RuntimeException('No reconocí las columnas del extracto. La primera fila con títulos debe tener: Fecha, Descripción y '
            .'Cargo y Abono (o un solo Monto, en negativo lo que sale). Descarga el formato de ejemplo.');
    }

    /** "05/11/2026", "2026-11-05", "05-11-26" o el número de fecha de Excel → "2026-11-05" */
    private static function fecha(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $v)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})#', $v, $m)) {
            $anio = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $anio) ? sprintf('%04d-%02d-%02d', $anio, $m[2], $m[1]) : null;
        }

        return null;
    }

    /** "1,234.56", "1.234,56", "S/ -50.00", "(50.00)" → float; vacío → null */
    private static function numero(string $v): ?float
    {
        $v = trim(str_ireplace(['S/', 'US$', '$', 'PEN', 'USD', ' '], '', $v));
        if ($v === '' || $v === '-') {
            return null;
        }
        $negativo = str_starts_with($v, '(') && str_ends_with($v, ')') || str_starts_with($v, '-') || str_ends_with($v, '-');
        $v = trim($v, '()-+');
        // El separador decimal es el último que aparece (punto o coma) si le siguen 1 o 2 dígitos
        if (preg_match('/[.,](\d{1,2})$/', $v, $m)) {
            $entero = preg_replace('/[^\d]/', '', substr($v, 0, -strlen($m[0])));
            $v = $entero.'.'.$m[1];
        } else {
            $v = preg_replace('/[^\d]/', '', $v);
        }
        if ($v === '' || ! is_numeric($v)) {
            return null;
        }

        return round(($negativo ? -1 : 1) * (float) $v, 2);
    }
}
