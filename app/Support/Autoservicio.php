<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\User;
use App\Support\Impresion\Impresion;
use Illuminate\Support\Facades\DB;

/**
 * Kiosko de autoservicio: una tablet o pantalla táctil en el local donde el cliente elige sus platos, pone su nombre
 * y recibe un número. El pedido entra como "Para llevar" y caja lo cobra al llamarlo por su número.
 * La comanda sale a cocina como cualquier pedido (o al cobrar, si la sucursal imprime las comandas al cobrar).
 */
class Autoservicio
{
    public const ORIGEN = 'KIOSKO';

    /**
     * @param  array<int, array{id: int, cantidad: int, presentacion?: ?int, nota?: ?string}>  $items
     * @return array{numero: int, ped_id: int, total: float, alCobrar: bool}
     *
     * @throws \RuntimeException con un mensaje para el cliente
     */
    public static function registrar(User $usuario, array $items, string $nombre, bool $paraLlevar): array
    {
        $suc = (int) $usuario->id_empresa_negocio;
        $lineas = CartaMesa::lineasDeCarta($suc, $items);
        $nombre = mb_strtoupper(trim($nombre));

        [$pedId, $numero, $total] = DB::transaction(function () use ($usuario, $suc, $lineas, $nombre, $paraLlevar) {
            // El número del día: bloqueando la sucursal, dos kioskos no dan el mismo número
            EmpresaNegocio::where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            $numero = DB::table('pedidos')->where('id_empresa_negocio', $suc)->where('entrega_origen', self::ORIGEN)
                ->whereDate('fecha_hora', now()->toDateString())->count() + 1;

            $porciones = [];
            foreach ($lineas as $l) {
                $porciones[$l['IdProducto']] = ($porciones[$l['IdProducto']] ?? 0) + $l['cantidad'] * $l['factor'];
            }
            if ($falta = Porciones::faltante($suc, $porciones, true)) {
                throw new \RuntimeException('Lo sentimos: '.strtok($falta, '.').'. Quítalo de tu pedido.');
            }
            $productos = Producto::whereIn('IdProducto', array_keys($porciones))->get()->keyBy('IdProducto');
            try {
                ControlStock::asegurar($suc, collect($lineas)->filter(fn ($l) => isset($productos[$l['IdProducto']]))
                    ->map(fn ($l) => ['producto' => $productos[$l['IdProducto']], 'cantidad' => $l['cantidad'], 'factor' => $l['factor']])->values()->all());
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Lo sentimos: '.strtok($e->getMessage(), '.').'. Quítalo o cambia la cantidad.');
            }

            $pedido = Pedido::create([
                'ped_tip' => 'Llevar', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(), 'IdEmpresa' => $usuario->IdEmpresa,
                'id_empresa_negocio' => $suc, 'ped_est' => 'Aperturado', 'IdUsuario' => $usuario->IdUsuario,
                'ped_cli_nom' => mb_substr('KIOSKO N° '.$numero.' · '.$nombre.($paraLlevar ? ' (PARA LLEVAR)' : ' (COMER AQUÍ)'), 0, 100),
                'ped_tot' => 0,
            ]);
            DB::table('pedidos')->where('ped_id', $pedido->ped_id)->update(['entrega_origen' => self::ORIGEN]);
            foreach ($lineas as $l) {
                PedidoDetalle::create([
                    'ped_id' => $pedido->ped_id, 'IdProducto' => $l['IdProducto'], 'id_presentacion' => $l['presentacion'], 'IdEmpresa' => $usuario->IdEmpresa,
                    'descripcion' => $l['nombre'], 'detalle' => $l['nombre'], 'ped_det_can' => $l['cantidad'], 'ped_det_pre' => $l['precio'],
                    'item_obs' => $l['nota'] ?: null, 'estadoitem' => 'Ingresado', 'impreso' => 'imprimir', 'fecha_hora' => now(),
                ]);
            }
            $total = round(collect($lineas)->sum(fn ($l) => $l['precio'] * $l['cantidad']), 2);
            $pedido->update(['ped_tot' => $total]);

            return [(int) $pedido->ped_id, $numero, $total];
        });

        // Comanda: a cocina ya, o al cobrar si la sucursal así lo tiene. Un fallo aquí no pierde el pedido.
        $cocina = collect($lineas)->map(fn ($l) => ['IdProducto' => $l['IdProducto'], 'nombre' => $l['nombre'], 'cantidad' => $l['cantidad'],
            'observacion' => trim('KIOSKO N° '.$numero.' '.$nombre.($paraLlevar ? ' · PARA LLEVAR' : '').($l['nota'] ? ' · '.$l['nota'] : ''))])->all();
        $alCobrar = Impresion::comandaAlCobrar($suc);
        try {
            if ($alCobrar) {
                Impresion::guardarComandaPendiente($pedId, $cocina, []);
            } else {
                Impresion::comanda($pedId, $cocina);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            Cocina::registrar($pedId, $cocina);
        } catch (\Throwable $e) {
            report($e);
        }

        return ['numero' => $numero, 'ped_id' => $pedId, 'total' => $total, 'alCobrar' => $alCobrar];
    }
}
