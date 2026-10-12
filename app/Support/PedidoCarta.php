<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Support\Impresion\Impresion;
use Illuminate\Support\Facades\DB;

/**
 * Pedido delivery que hace el cliente desde la carta QR: entra como un pedido "Delivery" más (lo ve caja en
 * Comandas y en Delivery en vivo), la comanda sale sola en la ticketera de cada categoría con los datos de entrega
 * y el cliente recibe su enlace de seguimiento. Los precios y productos se toman de la carta, no de lo que manda el navegador.
 */
class PedidoCarta
{
    /**
     * @param  array<int, array{id: int, cantidad: float, presentacion?: ?int, nota?: ?string}>  $items
     * @param  array{cliente: string, telefono: string, direccion: string, referencia?: ?string, lat?: ?float, lng?: ?float, pago: string, paga_con?: ?float, nota?: ?string}  $datos
     * @return array{ped_id: int, total: float, envio: ?float, seguimiento: string}
     *
     * @throws \RuntimeException con un mensaje para el cliente
     */
    public static function registrar(EmpresaNegocio $negocio, array $items, array $datos): array
    {
        $suc = (int) $negocio->id_empresa_negocio;
        if (! $negocio->carta_delivery) {
            throw new \RuntimeException('Por ahora no recibimos pedidos delivery por la carta. Llámanos o escríbenos.');
        }

        // Productos y precios de la carta (lo único que se puede pedir)
        $carta = collect(Carta::categorias($suc))->flatMap(fn ($c) => $c['productos'])->keyBy('id');
        $lineas = [];
        foreach ($items as $item) {
            $producto = $carta[(int) $item['id']] ?? null;
            if (! $producto) {
                throw new \RuntimeException('Uno de los productos ya no está en la carta. Actualiza la página y vuelve a armar tu pedido.');
            }
            $presentacion = ! empty($item['presentacion']) ? collect($producto['presentaciones'])->firstWhere('id', (int) $item['presentacion']) : null;
            if (! empty($item['presentacion']) && ! $presentacion) {
                throw new \RuntimeException("La presentación elegida de {$producto['nombre']} ya no está disponible.");
            }
            $lineas[] = [
                'IdProducto' => $producto['id'], 'presentacion' => $presentacion['id'] ?? null, 'factor' => $presentacion['factor'] ?? 1,
                'nombre' => $producto['nombre'].($presentacion ? ' ('.mb_strtoupper($presentacion['nombre']).')' : ''),
                'precio' => $presentacion['precio'] ?? $producto['precio'], 'cantidad' => (float) $item['cantidad'],
                'nota' => mb_strtoupper(trim((string) ($item['nota'] ?? ''))),
            ];
        }
        $subtotal = round(collect($lineas)->sum(fn ($l) => $l['precio'] * $l['cantidad']), 2);
        if ((float) $negocio->delivery_minimo > 0 && $subtotal < (float) $negocio->delivery_minimo) {
            throw new \RuntimeException('El pedido mínimo para delivery es S/ '.number_format((float) $negocio->delivery_minimo, 2).'.');
        }

        $lat = isset($datos['lat']) ? (float) $datos['lat'] : null;
        $lng = isset($datos['lng']) ? (float) $datos['lng'] : null;
        $envio = Delivery::costoEnvio($suc, $lat, $lng);
        if ($envio['fuera']) {
            throw new \RuntimeException('Lo sentimos, tu ubicación está fuera de nuestra zona de reparto ('.number_format($envio['km'], 1).' km).');
        }
        $aPagar = $subtotal + (float) ($envio['costo'] ?? 0);
        if ($datos['pago'] === 'EFECTIVO' && ! empty($datos['paga_con']) && (float) $datos['paga_con'] < $aPagar) {
            throw new \RuntimeException('El monto con el que pagas (S/ '.number_format((float) $datos['paga_con'], 2).') es menor al total S/ '.number_format($aPagar, 2).'.');
        }

        $pedId = DB::transaction(function () use ($negocio, $suc, $lineas, $datos, $lat, $lng) {
            $porciones = [];
            foreach ($lineas as $l) {
                $porciones[$l['IdProducto']] = ($porciones[$l['IdProducto']] ?? 0) + $l['cantidad'] * $l['factor'];
            }
            if ($falta = Porciones::faltante($suc, $porciones, true)) {
                throw new \RuntimeException('Lo sentimos: '.strtok($falta, '.').'. Quítalo de tu pedido.');
            }
            $productos = Producto::whereIn('IdProducto', array_keys($porciones))->get()->keyBy('IdProducto');
            try {
                ControlStock::asegurar($suc, collect($lineas)->map(fn ($l) => ['producto' => $productos[$l['IdProducto']], 'cantidad' => $l['cantidad'], 'factor' => $l['factor']])->all());
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Lo sentimos: '.strtok($e->getMessage(), '.').'. Quítalo o cambia la cantidad.');
            }

            $pedido = Pedido::create([
                'ped_tip' => 'Delivery', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(), 'IdEmpresa' => $negocio->IdEmpresa,
                'id_empresa_negocio' => $suc, 'ped_est' => 'Aperturado', 'ped_cli_nom' => mb_strtoupper(trim($datos['cliente'])),
                'ped_tel' => trim($datos['telefono']), 'ped_dir' => mb_strtoupper(trim($datos['direccion'])),
                'ped_obs' => mb_substr(mb_strtoupper(trim((string) ($datos['nota'] ?? ''))), 0, 255) ?: null, 'ped_tot' => 0,
            ]);
            DB::table('pedidos')->where('ped_id', $pedido->ped_id)->update([
                'entrega_ref' => mb_strtoupper(trim((string) ($datos['referencia'] ?? ''))) ?: null, 'entrega_lat' => $lat, 'entrega_lng' => $lng,
                'entrega_pago' => $datos['pago'], 'entrega_paga_con' => $datos['pago'] === 'EFECTIVO' && ! empty($datos['paga_con']) ? $datos['paga_con'] : null,
                'entrega_origen' => 'CARTA',
            ]);
            foreach ($lineas as $l) {
                PedidoDetalle::create([
                    'ped_id' => $pedido->ped_id, 'IdProducto' => $l['IdProducto'], 'id_presentacion' => $l['presentacion'], 'IdEmpresa' => $negocio->IdEmpresa,
                    'descripcion' => $l['nombre'], 'detalle' => $l['nombre'], 'ped_det_can' => $l['cantidad'], 'ped_det_pre' => $l['precio'],
                    'item_obs' => $l['nota'] ?: null, 'estadoitem' => 'Ingresado', 'impreso' => 'imprimir', 'fecha_hora' => now(),
                ]);
            }
            $total = round(collect($lineas)->sum(fn ($l) => $l['precio'] * $l['cantidad']), 2);
            DB::table('pedidos')->where('ped_id', $pedido->ped_id)->update(['ped_tot' => $total]);

            return (int) $pedido->ped_id;
        });

        Delivery::iniciar($pedId);
        if ($envio['costo'] !== null) {
            Delivery::ponerEnvio($pedId, $envio['costo']);
        }

        // Comanda en la ticketera de cada categoría y en la pantalla de cocina. Un fallo aquí no pierde el pedido.
        $cocina = collect($lineas)->map(fn ($l) => ['IdProducto' => $l['IdProducto'], 'nombre' => $l['nombre'], 'cantidad' => $l['cantidad'], 'observacion' => $l['nota']])->all();
        try {
            Impresion::comanda($pedId, $cocina);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            Cocina::registrar($pedId, $cocina);
        } catch (\Throwable $e) {
            report($e);
        }

        $pedido = DB::table('pedidos')->where('ped_id', $pedId)->first(['ped_tot', 'entrega_envio', 'entrega_token']);

        return ['ped_id' => $pedId, 'total' => (float) $pedido->ped_tot, 'envio' => $pedido->entrega_envio !== null ? (float) $pedido->entrega_envio : null,
            'seguimiento' => route('pedido.seguimiento', $pedido->entrega_token)];
    }
}
