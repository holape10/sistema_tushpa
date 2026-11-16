<?php

namespace App\Support\Tenancy;

use App\Http\Controllers\InventarioController;
use App\Models\Producto;
use App\Models\User;
use App\Support\Bitacora;
use App\Support\ExcelLector;
use App\Support\Kardex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de productos de una empresa desde el panel del dueño, con un Excel, sin entrar con el usuario del cliente.
 * Solo cambia productos que YA EXISTEN, buscándolos por su código: nombre, costo, precio, estado y stock (el stock con
 * un inventario, como en el sistema). Una columna que no está en el Excel, o una celda vacía, no cambia nada.
 *
 * Primero se revisa (cuántos se encuentran y qué cambiaría) y recién después se aplica; al aplicar se vuelve a revisar
 * contra la base de ese momento. Queda en la bitácora de la empresa como hecho desde el panel.
 */
class CorreccionProductos
{
    /** Columnas que se reconocen por su título (sin tildes, en minúsculas) */
    private const COLUMNAS = ['codigo' => ['codigo', 'cod'], 'nombre' => ['nombre', 'producto'], 'costo' => ['costo'],
        'precio' => ['precio venta', 'precio de venta', 'precio'], 'estado' => ['estado'], 'stock' => ['stock']];

    /** Desde cuántos productos con el mismo precio nuevo se avisa (una celda arrastrada hacia abajo) */
    public const AVISO_MISMO_PRECIO = 50;

    private static function normalizar(string $t): string
    {
        $t = strtr(mb_strtolower(trim($t)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/u', ' ', preg_split('/[(*\n]/u', $t)[0]));
    }

    private static function numero(string $v): float|false|null
    {
        $v = str_replace([' ', 'S/', 's/'], '', $v);
        $v = str_contains($v, ',') && ! str_contains($v, '.') ? str_replace(',', '.', $v) : str_replace(',', '', $v);

        return $v === '' ? null : (is_numeric($v) ? (float) $v : false);
    }

    /**
     * Lee el Excel: una fila por producto con lo que trae (null = no se cambia).
     *
     * @return array{filas: list<array{fila: int, codigo: string, nombre: ?string, costo: ?float, precio: ?float, estado: ?string, stock: ?float}>, errores: list<string>, columnas: list<string>}
     *
     * @throws \RuntimeException si el Excel no tiene la columna Código o ninguna columna que cambiar
     */
    public static function leer(string $ruta, string $extension): array
    {
        $tabla = ExcelLector::filas($ruta, $extension);
        $cab = array_map(fn ($t) => self::normalizar((string) $t), $tabla[0] ?? []);
        $col = [];
        foreach (self::COLUMNAS as $campo => $nombres) {
            foreach ($nombres as $n) {
                $i = array_search($n, $cab, true);
                if ($i === false) {
                    foreach ($cab as $j => $t) {
                        if (str_starts_with($t, $n.' ')) {
                            $i = $j;
                            break;
                        }
                    }
                }
                if ($i !== false && $i !== null) {
                    $col[$campo] = $i;
                    break;
                }
            }
        }
        if (! isset($col['codigo'])) {
            throw new \RuntimeException('El Excel debe tener la columna «Código»: con ella se busca cada producto.');
        }
        if (count(array_diff_key($col, ['codigo' => 0])) === 0) {
            throw new \RuntimeException('El Excel no tiene ninguna columna que cambiar (Nombre, Costo, Precio venta, Estado o Stock).');
        }

        $filas = $errores = [];
        foreach (array_slice($tabla, 1) as $i => $f) {
            $n = $i + 2;
            $v = fn (string $k) => isset($col[$k]) ? trim((string) ($f[$col[$k]] ?? '')) : '';
            $codigo = $v('codigo');
            if ($codigo === '') {
                if (implode('', array_map('strval', $f)) !== '') {
                    $errores[] = "Fila {$n}: sin código, se omitió.";
                }

                continue;
            }
            $costo = self::numero($v('costo'));
            $precio = self::numero($v('precio'));
            $stock = self::numero($v('stock'));
            $estado = mb_strtolower($v('estado'));
            if ($costo === false || $precio === false || $stock === false || ($precio !== null && $precio <= 0) || ($costo !== null && $costo < 0) || ($stock !== null && $stock < 0)) {
                $errores[] = "Fila {$n} ({$codigo}): costo, precio o stock no es un número válido (el precio debe ser mayor a 0).";

                continue;
            }
            if ($estado !== '' && ! in_array($estado, ['activo', 'inactivo'], true)) {
                $errores[] = "Fila {$n} ({$codigo}): el estado «{$v('estado')}» no es Activo ni Inactivo.";

                continue;
            }
            $filas[] = ['fila' => $n, 'codigo' => mb_substr($codigo, 0, 20), 'nombre' => $v('nombre') !== '' ? mb_substr(mb_strtoupper(preg_replace('/\s+/u', ' ', $v('nombre'))), 0, 150) : null,
                'costo' => $costo, 'precio' => $precio, 'estado' => $estado === '' ? null : ($estado === 'inactivo' ? 'Inactivo' : 'Activo'), 'stock' => $stock];
        }

        return ['filas' => $filas, 'errores' => $errores, 'columnas' => array_keys(array_diff_key($col, ['codigo' => 0]))];
    }

    /**
     * Lo que se cambiaría en la base de la empresa (sin cambiar nada).
     *
     * @param  list<array>  $filas  de leer()
     * @return array{plan: list<array{id: int, sucursal: int, cambios: array<string, mixed>, stock: ?float, antes: array<string, mixed>}>, resumen: array<string, int>, no_encontrados: list<string>, repetidos: list<string>, aviso: ?string}
     */
    public static function revisar(string $base, array $filas): array
    {
        return Tenancy::en($base, function () use ($filas) {
            $productos = DB::table('productos')->whereIn(DB::raw('UPPER(TRIM(procod))'), array_map(fn ($f) => mb_strtoupper(trim($f['codigo'])), $filas) ?: ['-'])
                ->get(['IdProducto', 'procod', 'pronom', 'costo', 'propun', 'proest', 'promocion', 'id_empresa_negocio'])->groupBy(fn ($p) => mb_strtoupper(trim($p->procod)));
            $plan = $noEncontrados = $repetidos = [];
            $resumen = ['filas' => count($filas), 'encontrados' => 0, 'nombre' => 0, 'costo' => 0, 'precio' => 0, 'estado' => 0, 'stock' => 0, 'sin_cambios' => 0];
            $nuevosPrecios = [];
            foreach ($filas as $f) {
                $hallados = $productos[mb_strtoupper(trim($f['codigo']))] ?? collect();
                if ($hallados->isEmpty()) {
                    $noEncontrados[] = $f['codigo'];

                    continue;
                }
                if ($hallados->count() > 1) {
                    $repetidos[] = $f['codigo'];

                    continue;
                }
                $p = $hallados->first();
                $resumen['encontrados']++;
                $cambios = [];
                if ($f['nombre'] !== null && $f['nombre'] !== mb_strtoupper(trim((string) $p->pronom))) {
                    $cambios['pronom'] = $f['nombre'];
                }
                if ($f['costo'] !== null && abs((float) $p->costo - $f['costo']) >= 0.005) {
                    $cambios['costo'] = round($f['costo'], 2);
                }
                if ($f['precio'] !== null && abs((float) $p->propun - $f['precio']) >= 0.005) {
                    $cambios['propun'] = round($f['precio'], 2);
                    $clave = number_format($f['precio'], 2, '.', '');
                    $nuevosPrecios[$clave] = ($nuevosPrecios[$clave] ?? 0) + 1;
                }
                if ($f['estado'] !== null && $f['estado'] !== $p->proest) {
                    $cambios['proest'] = $f['estado'];
                }
                // El stock solo en productos e insumos (los preparados y servicios no llevan stock)
                $stock = null;
                if ($f['stock'] !== null && in_array((int) $p->promocion, [0, 4], true)) {
                    $almacen = Kardex::almacenPredeterminado((int) $p->id_empresa_negocio);
                    $actual = $almacen ? (float) (DB::table('producto_stock')->where('IdProducto', $p->IdProducto)->where('id_almacen', $almacen->id_almacen)->value('stock') ?? 0) : null;
                    if ($actual !== null && abs($actual - $f['stock']) >= 0.0005) {
                        $stock = $f['stock'];
                    }
                }
                foreach (['pronom' => 'nombre', 'costo' => 'costo', 'propun' => 'precio', 'proest' => 'estado'] as $campo => $nombre) {
                    $resumen[$nombre] += isset($cambios[$campo]) ? 1 : 0;
                }
                $resumen['stock'] += $stock !== null ? 1 : 0;
                if (! $cambios && $stock === null) {
                    $resumen['sin_cambios']++;

                    continue;
                }
                $plan[] = ['id' => (int) $p->IdProducto, 'sucursal' => (int) $p->id_empresa_negocio, 'cambios' => $cambios, 'stock' => $stock,
                    'antes' => ['codigo' => $p->procod, 'nombre' => $p->pronom, 'precio' => (float) $p->propun]];
            }
            arsort($nuevosPrecios);
            $masRepetido = array_key_first($nuevosPrecios);
            $aviso = $masRepetido !== null && $nuevosPrecios[$masRepetido] >= self::AVISO_MISMO_PRECIO && $nuevosPrecios[$masRepetido] / max(1, array_sum($nuevosPrecios)) >= 0.9
                ? 'Ojo: este Excel le pondría el mismo precio S/ '.$masRepetido.' a '.number_format($nuevosPrecios[$masRepetido]).' productos. Revisa que sea correcto.' : null;

            return ['plan' => $plan, 'resumen' => $resumen, 'no_encontrados' => $noEncontrados, 'repetidos' => $repetidos, 'aviso' => $aviso];
        });
    }

    /**
     * Aplica los cambios (se revisan de nuevo contra la base de este momento). El stock se corrige con un inventario
     * por sucursal, a nombre del administrador de la empresa; en la bitácora queda como hecho desde el panel.
     *
     * @return array<string, int> lo que se cambió
     */
    public static function aplicar(string $base, array $filas, string $quien, string $archivo): array
    {
        $revision = self::revisar($base, $filas);

        return Tenancy::en($base, function () use ($revision, $quien, $archivo) {
            $admin = User::whereIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario'))->orderBy('IdUsuario')->first();
            if (! $admin) {
                throw new \RuntimeException('La empresa no tiene un usuario administrador: no se puede registrar el cambio.');
            }
            $guardia = Auth::getDefaultDriver();
            Auth::shouldUse('web');
            Auth::guard('web')->setUser($admin);
            try {
                DB::transaction(function () use ($revision) {
                    foreach ($revision['plan'] as $p) {
                        if ($p['cambios']) {
                            Producto::where('IdProducto', $p['id'])->update($p['cambios']);
                        }
                    }
                    // Stock: un inventario por sucursal en su almacén predeterminado (queda en el kardex)
                    foreach (collect($revision['plan'])->whereNotNull('stock')->groupBy('sucursal') as $sucursal => $items) {
                        $almacen = Kardex::almacenPredeterminado((int) $sucursal);
                        $productos = Producto::whereIn('IdProducto', $items->pluck('id'))->get()->keyBy('IdProducto');
                        InventarioController::procesar($almacen, now()->toDateString(), 'CORRECCIÓN DESDE EL PANEL (EXCEL)', 'PANEL',
                            $items->map(fn ($i) => ['IdProducto' => $i['id'], 'cantidad' => $i['stock'], 'costo' => null])->values()->all(), $productos);
                    }
                });
                $r = $revision['resumen'];
                Bitacora::registrar('producto.corregir_panel', "Corrección de productos desde el panel ({$quien}): {$r['precio']} precios, {$r['nombre']} nombres, "
                    ."{$r['costo']} costos, {$r['estado']} estados, {$r['stock']} stocks", null, [
                        'usuario' => $admin, 'nombre' => 'PANEL TUSHPA · '.$quien, 'alerta' => true,
                        'datos' => ['archivo' => mb_substr($archivo, 0, 120), 'antes' => array_slice(array_map(fn ($p) => [$p['antes']['codigo'], $p['antes']['nombre'], $p['antes']['precio']], $revision['plan']), 0, 20000)],
                    ]);
            } finally {
                Auth::guard('web')->forgetUser();
                Auth::shouldUse($guardia);
            }

            return $revision['resumen'];
        });
    }
}
