<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pasa al sistema los clientes de un gimnasio que llevaba todo en Excel: cada persona con SU MISMO código (con él marca
 * su ingreso), su DNI, teléfono, dirección y cumpleaños, y su última membresía con las fechas reales (si sigue vigente,
 * puede entrar desde el primer día; si venció, aparece en "clientes para recuperar").
 *
 * Se adapta a las columnas por su título (CODIGO, NOMBRE, DNI, TELEFONO, MEMBRESIA, FECHA DE INICIO, FECHA DE
 * VENCIMIENTO, MONTO PAGADO, OBSERVACIÓN…). Primero se revisa (nada se guarda) y luego se confirma. Volver a importar el
 * mismo Excel no duplica: la persona se reconoce por su DNI y la membresía por sus fechas.
 */
class GimnasioImportar
{
    /** La hoja con la lista de clientes (si el Excel tiene una hoja por día, esta es la base) */
    public const HOJA = 'BASE DE DATOS';

    /** Títulos de columna que se reconocen (sin tildes, en mayúsculas) */
    private const COLUMNAS = [
        'codigo' => ['CODIGO', 'COD', 'N°'], 'nombre' => ['NOMBRE', 'CLIENTE', 'APELLIDOS'], 'dni' => ['DNI', 'DOCUMENTO'],
        'direccion' => ['DIRECCION'], 'telefono' => ['TELEFONO', 'CELULAR'], 'monto' => ['MONTO', 'PAGO', 'PRECIO'],
        'cumple' => ['CUMPLE', 'NACIMIENTO'], 'membresia' => ['MEMBRESIA', 'PLAN'], 'inicio' => ['INICIO'], 'fin' => ['VENCIMIENTO', 'FIN'],
        'obs' => ['OBSERVACION'],
    ];

    private static function normal(string $t): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']))));
    }

    /** Fecha de Excel (número de serie o dd/mm/aaaa); null si está mal escrita */
    public static function fecha(?string $v): ?string
    {
        $v = trim((string) $v);
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    /** Documento limpio. Excel quita el cero de adelante a los DNI guardados como número: 5410478 => 05410478 */
    public static function documento(string $doc): string
    {
        $doc = mb_strtoupper(preg_replace('/\s+/', '', $doc));
        if (preg_match('/^\d{7}$/', $doc)) {
            return '0'.$doc;
        }

        return preg_match('/^[0-9A-Z-]{6,15}$/', $doc) ? $doc : '';
    }

    /** El plan escrito parejo para que los reportes lo agrupen: "3MESES" => "3 MESES", "1mes" => "1 MES", "2 MES" => "2 MESES" */
    public static function plan(string $texto): string
    {
        $p = preg_replace('/^(\d+)\s*([A-Z])/u', '$1 $2', self::normal($texto));

        return preg_replace_callback('/^(\d+) (MES|MESES|SEMANA|SEMANAS|DIA|DIAS)\b/u', function ($m) {
            $base = ['MES' => 'MES', 'MESES' => 'MES', 'SEMANA' => 'SEMANA', 'SEMANAS' => 'SEMANA', 'DIA' => 'DIA', 'DIAS' => 'DIA'][$m[2]];
            $plural = ['MES' => 'MESES', 'SEMANA' => 'SEMANAS', 'DIA' => 'DIAS'][$base];

            return $m[1].' '.((int) $m[1] === 1 ? $base : $plural);
        }, $p);
    }

    /** "1 MES", "3MESES", "INTERDIARIO", "1 SEMANA"… => cuántos meses (para calcular el inicio si está mal escrito) */
    private static function meses(string $plan): ?int
    {
        return preg_match('/(\d+)\s*MES/u', $plan, $m) ? (int) $m[1] : (str_contains($plan, 'MES') ? 1 : null);
    }

    /**
     * Revisa el Excel sin guardar nada: los clientes que trae y lo que tiene cada uno.
     *
     * @param  array<int, array<int, string>>  $filas
     * @return array{clientes: array, problemas: array, resumen: array}
     */
    public static function revisar(array $filas, int $suc): array
    {
        // La fila de títulos: la primera (de las 20 primeras) que tiene DNI y la membresía o el vencimiento
        $titulos = null;
        foreach (array_slice($filas, 0, 20, true) as $n => $fila) {
            $textos = array_map(fn ($v) => self::normal((string) $v), $fila);
            if (collect($textos)->contains(fn ($t) => str_contains($t, 'DNI')) && collect($textos)->contains(fn ($t) => str_contains($t, 'MEMBRES') || str_contains($t, 'VENCIM'))) {
                $titulos = $n;
                break;
            }
        }
        if ($titulos === null) {
            throw new \RuntimeException('No se encontró la fila de títulos (con DNI y MEMBRESÍA o FECHA DE VENCIMIENTO). Revisa que sea la lista de clientes.');
        }

        $col = [];
        $obs = [];
        foreach ($filas[$titulos] as $i => $titulo) {
            $t = self::normal((string) $titulo);
            foreach (self::COLUMNAS as $campo => $claves) {
                if (collect($claves)->contains(fn ($c) => str_contains($t, $c))) {
                    if ($campo === 'obs') {
                        $obs[] = $i;
                    } elseif (! isset($col[$campo]) && ! ($campo === 'fin' && str_contains($t, 'INICIO'))) {
                        $col[$campo] = $i;
                    }
                    break;
                }
            }
        }
        // Columnas sin título claro: el nombre va antes del DNI y el código antes del nombre
        $col['nombre'] ??= isset($col['dni']) ? $col['dni'] - 1 : null;
        $col['codigo'] ??= isset($col['nombre']) && $col['nombre'] > 0 ? $col['nombre'] - 1 : null;
        // Las columnas después de las observaciones (quién vendió/registró) también se guardan como nota
        $extra = $obs ? range(max($obs) + 1, max(max($obs) + 1, count($filas[$titulos]) - 1)) : [];

        $existentes = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')->where('s.id_empresa_negocio', $suc)
            ->get(['s.soc_id', 's.codigo', 'c.clinum']);
        $porDni = $existentes->keyBy('clinum');
        $codigosUsados = $existentes->pluck('codigo')->map(fn ($c) => (string) $c)->flip();

        $clientes = [];
        $problemas = [];
        $vistosCodigo = [];
        $vistosDni = [];
        $hoy = now()->toDateString();
        foreach (array_slice($filas, $titulos + 1, null, true) as $n => $f) {
            $v = fn (string $campo) => isset($col[$campo]) ? trim((string) ($f[$col[$campo]] ?? '')) : '';
            $nombre = mb_strtoupper(preg_replace('/\s+/u', ' ', $v('nombre')));
            if ($nombre === '' || mb_strlen($nombre) < 3) {
                continue;
            }
            $fila = $n + 1;
            $codigo = preg_replace('/\D/', '', $v('codigo'));
            $dni = preg_replace('/\s+/', '', $v('dni'));
            $dni = self::documento($dni);
            $telefono = mb_substr(preg_replace('/[^\d+]/', '', $v('telefono')), 0, 20);
            $plan = self::plan($v('membresia'));
            $inicio = self::fecha($v('inicio'));
            $fin = self::fecha($v('fin'));
            $monto = is_numeric($v('monto')) ? round((float) $v('monto'), 2) : 0.0;
            $notas = collect(array_merge($obs, $extra))->map(fn ($i) => trim((string) ($f[$i] ?? '')))
                ->reject(fn ($t) => $t === '' || preg_match('/^(#\w+!?|0|X)$/i', $t))->unique()->implode(' · ');

            if ($fin && ! $inicio) {
                // Inicio mal escrito: se calcula desde el plan (1 mes antes del vencimiento, si no se sabe)
                $inicio = Carbon::parse($fin)->subMonthsNoOverflow(self::meses($plan) ?? 1)->addDay()->toDateString();
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => 'La fecha de inicio está mal escrita ("'.$v('inicio').'"): se tomó '.Carbon::parse($inicio)->format('d/m/Y').'.'];
            }
            if ($inicio && $fin && $inicio > $fin) {
                [$inicio, $fin] = [$fin, $inicio];
            }
            if (! $fin) {
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => 'Sin fecha de vencimiento válida: se registra el cliente, sin membresía.'];
            }

            // Sin DNI, la persona se reconoce por su código del Excel (así volver a importar no la duplica)
            $codigoRepetido = $codigo !== '' && isset($vistosCodigo[$codigo]);
            $doc = $dni !== '' ? $dni : ($codigo !== '' && ! $codigoRepetido ? 'GYM'.$codigo : 'GYMF'.$fila);
            $existe = $porDni[$doc] ?? null;
            if ($dni !== '' && isset($vistosDni[$dni])) {
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => "El DNI {$dni} se repite en el Excel (fila {$vistosDni[$dni]}): se junta con esa persona."];
            }
            if ($codigoRepetido && ! $existe) {
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => "El código {$codigo} se repite en el Excel: se le dará un código nuevo."];
                $codigo = '';
            } elseif (! $existe && $codigo !== '' && isset($codigosUsados[$codigo])) {
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => "El código {$codigo} ya lo tiene otro cliente del sistema: se le dará un código nuevo."];
                $codigo = '';
            }
            if ($dni === '') {
                $problemas[] = ['fila' => $fila, 'nombre' => $nombre, 'detalle' => 'Sin DNI: se registra solo con su código.'];
            }
            if ($codigo !== '') {
                $vistosCodigo[$codigo] = true;
            }
            if ($dni !== '') {
                $vistosDni[$dni] ??= $fila;
            }

            $clientes[] = [
                'fila' => $fila, 'codigo' => $codigo, 'nombre' => mb_substr($nombre, 0, 200), 'dni' => $dni, 'doc' => $doc, 'telefono' => $telefono,
                'direccion' => mb_substr(trim($v('direccion'), " .-\t"), 0, 200), 'cumple' => self::fecha($v('cumple')),
                'plan' => mb_substr($plan ?: 'MEMBRESÍA', 0, 80), 'inicio' => $fin ? $inicio : null, 'fin' => $fin, 'monto' => $monto,
                'nota' => mb_substr($notas, 0, 200), 'existe' => (bool) $existe, 'vigente' => $fin && $fin >= $hoy,
            ];
        }

        $c = collect($clientes);

        return ['clientes' => $clientes, 'problemas' => $problemas, 'resumen' => [
            'total' => $c->count(), 'nuevos' => $c->where('existe', false)->count(), 'existentes' => $c->where('existe', true)->count(),
            'vigentes' => $c->where('vigente', true)->count(), 'con_membresia' => $c->whereNotNull('fin')->count(),
            'vencidos_60' => $c->filter(fn ($x) => $x['fin'] && ! $x['vigente'] && $x['fin'] >= now()->subDays(60)->toDateString())->count(),
            'sin_dni' => $c->where('dni', '')->count(), 'con_cumple' => $c->whereNotNull('cumple')->count(),
        ]];
    }

    /**
     * Guarda lo revisado. Devuelve cuántos clientes se crearon, se actualizaron y cuántas membresías se registraron.
     *
     * @return array{creados: int, actualizados: int, membresias: int}
     */
    public static function importar(User $user, array $clientes): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $res = ['creados' => 0, 'actualizados' => 0, 'membresias' => 0];

        DB::transaction(function () use ($user, $suc, $clientes, &$res) {
            $siguiente = (int) DB::table('socios')->where('id_empresa_negocio', $suc)->whereRaw("codigo REGEXP '^[0-9]+$'")->max(DB::raw('CAST(codigo AS UNSIGNED)'));
            $siguiente = max($siguiente, (int) collect($clientes)->max(fn ($c) => (int) $c['codigo']));
            foreach ($clientes as $c) {
                // Sin DNI: el código del gimnasio hace de documento (para que no se mezcle con otra persona)
                $doc = $c['doc'];
                $cliente = Cliente::firstOrNew(['clinum' => $doc, 'rucemp' => $user->IdEmpresa]);
                $cliente->clinom = $c['nombre'];
                $cliente->tdicod ??= $c['dni'] === '' ? '0' : (strlen($doc) === 8 && ctype_digit($doc) ? '1' : (strlen($doc) === 11 && ctype_digit($doc) ? '6' : '4'));
                $cliente->telefono = $cliente->telefono ?: ($c['telefono'] ?: null);
                if ($c['direccion'] !== '' && (! $cliente->clidir || $cliente->clidir === '--')) {
                    $cliente->clidir = $c['direccion'];
                }
                $cliente->save();

                $socio = DB::table('socios')->where('id_empresa_negocio', $suc)->where('clicod', $cliente->clicod)->first();
                if ($socio) {
                    $completar = array_filter(['fecha_nac' => $socio->fecha_nac ? null : $c['cumple'], 'obs' => $socio->obs ? null : ($c['nota'] ?: null)], fn ($x) => $x !== null);
                    if ($completar) {
                        DB::table('socios')->where('soc_id', $socio->soc_id)->update($completar);
                    }
                    $socId = (int) $socio->soc_id;
                    $res['actualizados']++;
                } else {
                    $codigo = $c['codigo'] !== '' && ! DB::table('socios')->where('id_empresa_negocio', $suc)->where('codigo', $c['codigo'])->exists()
                        ? $c['codigo'] : str_pad((string) ++$siguiente, 4, '0', STR_PAD_LEFT);
                    $socId = (int) DB::table('socios')->insertGetId([
                        'codigo' => $codigo, 'clicod' => $cliente->clicod, 'fecha_ingreso' => $c['inicio'] ?? now()->toDateString(),
                        'fecha_nac' => $c['cumple'], 'estado' => 'ACTIVO', 'token' => Socios::token(), 'obs' => $c['nota'] ?: null,
                        'id_empresa_negocio' => $suc, 'creado' => now(),
                    ]);
                    $res['creados']++;
                }

                // Su última membresía, con las fechas del Excel (sin comprobante: ya se cobró antes del sistema)
                if ($c['fin'] && ! DB::table('gym_membresias')->where('soc_id', $socId)->where('inicio', $c['inicio'])->where('fin', $c['fin'])->exists()) {
                    DB::table('gym_membresias')->insert([
                        'soc_id' => $socId, 'plan_id' => null, 'plan' => $c['plan'], 'dias' => Carbon::parse($c['inicio'])->diffInDays(Carbon::parse($c['fin'])) + 1,
                        'congelar_max' => 0, 'inicio' => $c['inicio'], 'fin' => $c['fin'], 'precio' => $c['monto'], 'IdCpe_cabecera' => null,
                        'estado' => 'ACTIVA', 'IdUsuario' => $user->IdUsuario, 'creado' => Carbon::parse($c['inicio'])->setTime(12, 0), 'id_empresa_negocio' => $suc,
                    ]);
                    $res['membresias']++;
                }
            }
        });

        return $res;
    }
}
