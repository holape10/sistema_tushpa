<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\User;
use App\Support\Impresion\Impresion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carta QR de la mesa: el cliente escanea el QR de su mesa y desde su celular pide platos, llama al mozo o pide la cuenta.
 * Nada entra solo a cocina: le aparece al mozo en su pantalla de mesas y él lo acepta (así nadie pide desde fuera
 * con una foto del QR). Al aceptar, los platos se suman al pedido de la mesa (o la abren) y salen a cocina como
 * cualquier comanda. El QR lleva una firma: no se puede cambiar el número de mesa en el enlace.
 */
class CartaMesa
{
    public const TIPOS = ['PEDIDO', 'MOZO', 'CUENTA'];

    /** Minutos en que una solicitud sin atender deja de mostrarse */
    public const VIGENCIA = 90;

    public static function firma(int $mesId): string
    {
        return substr(hash_hmac('sha256', 'mesa:'.$mesId, (string) config('app.key')), 0, 8);
    }

    public static function firmaValida(int $mesId, ?string $firma): bool
    {
        return $firma !== null && hash_equals(self::firma($mesId), $firma);
    }

    /** ¿Se pide desde el QR de esta mesa? (sucursal con la opción activa y QR firmado) */
    public static function permite(EmpresaNegocio $negocio, ?object $mesa, ?string $firma): bool
    {
        return $negocio->carta_pedido_mesa && $mesa && self::firmaValida((int) $mesa->mes_id, $firma);
    }

    /**
     * El cliente pide platos, llama al mozo o pide la cuenta.
     *
     * @param  array<int, array{id: int, cantidad: int, presentacion?: ?int, nota?: ?string}>  $items
     * @return array{token: string, tipo: string, total: ?float}
     *
     * @throws \RuntimeException con un mensaje para el cliente
     */
    public static function solicitar(EmpresaNegocio $negocio, object $mesa, string $tipo, array $items = [], ?string $nombre = null, ?string $nota = null): array
    {
        $suc = (int) $negocio->id_empresa_negocio;
        $lineas = null;
        $total = null;
        if ($tipo === 'PEDIDO') {
            $lineas = self::lineasDeCarta($suc, $items);
            $total = round(collect($lineas)->sum(fn ($l) => $l['precio'] * $l['cantidad']), 2);
        } else {
            // Llamar al mozo o pedir la cuenta: si ya hay una igual esperando, no se repite
            $ya = DB::table('carta_solicitudes')->where('id_empresa_negocio', $suc)->where('mes_id', $mesa->mes_id)->where('tipo', $tipo)
                ->where('estado', 'PENDIENTE')->where('created_at', '>=', now()->subMinutes(self::VIGENCIA))->value('token');
            if ($ya) {
                return ['token' => $ya, 'tipo' => $tipo, 'total' => null];
            }
        }
        $token = Str::random(32);
        DB::table('carta_solicitudes')->insert([
            'id_empresa_negocio' => $suc, 'mes_id' => $mesa->mes_id, 'tipo' => $tipo,
            'items' => $lineas !== null ? json_encode($lineas, JSON_UNESCAPED_UNICODE) : null, 'total' => $total,
            'nombre' => mb_substr(mb_strtoupper(trim((string) $nombre)), 0, 60) ?: null,
            'nota' => mb_substr(mb_strtoupper(trim((string) $nota)), 0, 150) ?: null,
            'estado' => 'PENDIENTE', 'token' => $token, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['token' => $token, 'tipo' => $tipo, 'total' => $total];
    }

    /**
     * Productos y precios de la carta (no los del navegador). También lo usa el kiosko de autoservicio.
     *
     * @return array<int, array{IdProducto: int, presentacion: ?int, factor: float, nombre: string, precio: float, cantidad: float, nota: string}>
     */
    public static function lineasDeCarta(int $suc, array $items): array
    {
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
                'IdProducto' => $producto['id'], 'presentacion' => $presentacion['id'] ?? null, 'factor' => (float) ($presentacion['factor'] ?? 1),
                'nombre' => $producto['nombre'].($presentacion ? ' ('.mb_strtoupper($presentacion['nombre']).')' : ''),
                'precio' => (float) ($presentacion['precio'] ?? $producto['precio']), 'cantidad' => (float) $item['cantidad'],
                'nota' => mb_substr(mb_strtoupper(trim((string) ($item['nota'] ?? ''))), 0, 80),
            ];
        }

        return $lineas;
    }

    /**
     * Solicitudes que esperan en la sucursal (para la pantalla de mesas), con el nombre de su mesa.
     *
     * @return Collection<int, object>
     */
    public static function pendientes(int $suc)
    {
        return DB::table('carta_solicitudes as s')->join('mesas as m', 'm.mes_id', '=', 's.mes_id')
            ->leftJoin('pisos as p', 'p.pis_id', '=', 'm.pis_id')
            ->where('s.id_empresa_negocio', $suc)->where('s.estado', 'PENDIENTE')
            ->where('s.created_at', '>=', now()->subMinutes(self::VIGENCIA))
            ->orderBy('s.id')->get(['s.id', 's.tipo', 's.items', 's.total', 's.nombre', 's.nota', 's.created_at', 'm.mes_nom', 'p.pis_nom'])
            ->map(fn ($s) => (object) [
                'id' => (int) $s->id, 'tipo' => $s->tipo, 'mesa' => Mesa::etiqueta($s->pis_nom, $s->mes_nom),
                'items' => collect(json_decode((string) $s->items, true) ?: [])->map(fn ($l) => ['cantidad' => $l['cantidad'], 'nombre' => $l['nombre'], 'nota' => $l['nota'], 'precio' => (float) ($l['precio'] ?? 0)])->all(),
                'total' => $s->total !== null ? (float) $s->total : null, 'nombre' => $s->nombre, 'nota' => $s->nota,
                'hace' => now()->diffInMinutes($s->created_at, true),
            ]);
    }

    /**
     * El mozo atiende la solicitud. Un pedido se suma a la mesa y va a cocina; llamar al mozo y pedir la cuenta solo
     * quedan atendidas.
     *
     * $cantidades: lo que el mozo dejó de cada línea antes de aceptar (puede bajar o quitar, nunca subir); null = todo.
     *
     * @param  array<int, float|int>|null  $cantidades
     * @return string mensaje para el mozo
     *
     * @throws \RuntimeException
     */
    public static function aceptar(User $usuario, int $id, ?array $cantidades = null): string
    {
        $suc = (int) $usuario->id_empresa_negocio;
        $cocina = [];
        $resultado = DB::transaction(function () use ($usuario, $suc, $id, $cantidades, &$cocina) {
            $s = DB::table('carta_solicitudes')->where('id', $id)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $s || $s->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Otra persona ya atendió esta solicitud.');
            }
            $atendida = ['IdUsuario_atiende' => $usuario->IdUsuario, 'atendido_en' => now(), 'updated_at' => now()];
            if ($s->tipo !== 'PEDIDO') {
                DB::table('carta_solicitudes')->where('id', $id)->update($atendida + ['estado' => 'ATENDIDA']);

                return ['pedido' => null, 'tipo' => $s->tipo];
            }

            // Bloquea la mesa: un mozo y la carta no abren dos pedidos a la vez en la misma mesa
            $mesa = Mesa::where('mes_id', $s->mes_id)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $mesa) {
                throw new \RuntimeException('La mesa ya no existe.');
            }
            $lineas = json_decode((string) $s->items, true) ?: [];
            if ($cantidades !== null) {
                $lineas = self::ajustarLineas($lineas, $cantidades);
                // Lo aceptado es lo que queda (el cliente lo ve así en su celular)
                DB::table('carta_solicitudes')->where('id', $id)->update(['items' => json_encode($lineas, JSON_UNESCAPED_UNICODE),
                    'total' => round(collect($lineas)->sum(fn ($l) => $l['precio'] * $l['cantidad']), 2)]);
            }
            $pedidoId = MesasUnidas::pedidoDe((int) $mesa->mes_id);
            $pedido = $pedidoId ? Pedido::where('ped_id', $pedidoId)->lockForUpdate()->first() : null;

            // Solo con stock y porciones del día que alcancen
            $porciones = [];
            foreach ($lineas as $l) {
                $porciones[$l['IdProducto']] = ($porciones[$l['IdProducto']] ?? 0) + $l['cantidad'] * $l['factor'];
            }
            if ($falta = Porciones::faltante($suc, $porciones, true)) {
                throw new \RuntimeException($falta);
            }
            $productos = Producto::whereIn('IdProducto', array_keys($porciones))->get()->keyBy('IdProducto');
            ControlStock::asegurar($suc, collect($lineas)->filter(fn ($l) => isset($productos[$l['IdProducto']]))
                ->map(fn ($l) => ['producto' => $productos[$l['IdProducto']], 'cantidad' => $l['cantidad'], 'factor' => $l['factor']])->values()->all());

            if (! $pedido) {
                $pedido = Pedido::create([
                    'ped_tip' => 'Salon', 'ped_fec' => now()->toDateString(), 'fecha_hora' => now(), 'pis_id' => $mesa->pis_id, 'mes_id' => $mesa->mes_id,
                    'IdEmpresa' => $usuario->IdEmpresa, 'id_empresa_negocio' => $suc, 'ped_est' => 'Aperturado',
                    'mozo' => $usuario->IdUsuario, 'IdUsuario' => $usuario->IdUsuario, 'ped_cli_nom' => 'CONSUMO EN SALON', 'ped_tot' => 0,
                ]);
            }
            foreach ($lineas as $l) {
                $obs = collect([$l['nota'], $s->nombre ? 'PIDIÓ '.$s->nombre : null])->filter()->implode(' · ');
                PedidoDetalle::create([
                    'ped_id' => $pedido->ped_id, 'IdProducto' => $l['IdProducto'], 'id_presentacion' => $l['presentacion'], 'IdEmpresa' => $usuario->IdEmpresa,
                    'descripcion' => $l['nombre'], 'detalle' => $l['nombre'], 'ped_det_can' => $l['cantidad'], 'ped_det_pre' => $l['precio'],
                    'item_obs' => mb_substr($obs, 0, 255) ?: null, 'estadoitem' => 'Ingresado', 'impreso' => 'imprimir', 'fecha_hora' => now(),
                ]);
                $cocina[] = ['IdProducto' => $l['IdProducto'], 'nombre' => $l['nombre'], 'cantidad' => $l['cantidad'], 'observacion' => trim('(CARTA QR) '.$obs)];
            }
            $total = (float) PedidoDetalle::where('ped_id', $pedido->ped_id)->where('estadoitem', '!=', 'Eliminado')->sum(DB::raw('ped_det_can * ped_det_pre'));
            $pedido->update(['ped_tot' => $total, 'fecha_hora_modificacion' => now()]);
            Mesa::where('mes_id', $mesa->mes_id)->update(['mes_est' => 'Ocupado']);
            DB::table('carta_solicitudes')->where('id', $id)->update($atendida + ['estado' => 'ACEPTADA', 'ped_id' => $pedido->ped_id]);

            return ['pedido' => (int) $pedido->ped_id, 'tipo' => 'PEDIDO'];
        });

        if ($resultado['tipo'] === 'MOZO') {
            return 'Listo: el cliente sabe que vas en camino.';
        }
        if ($resultado['tipo'] === 'CUENTA') {
            return 'Listo: lleva la cuenta a la mesa.';
        }

        // Comanda a cocina/bar como cualquier pedido del mozo. Un fallo aquí no deshace el pedido.
        $alCobrar = Impresion::comandaAlCobrar($suc);
        try {
            if ($alCobrar) {
                Impresion::guardarComandaPendiente($resultado['pedido'], $cocina, []);
            } else {
                Impresion::comanda($resultado['pedido'], $cocina, false, '', Impresion::barraDeEnvio($suc, $usuario));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            Cocina::registrar($resultado['pedido'], $cocina, []);
        } catch (\Throwable $e) {
            report($e);
        }

        return $alCobrar ? 'Pedido sumado a la mesa: la comanda saldrá al cobrar.' : 'Pedido sumado a la mesa y enviado a cocina.';
    }

    /**
     * Las líneas del pedido con las cantidades que dejó el mozo: solo puede bajarlas o quitarlas (0). Si no queda nada,
     * es un rechazo.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  array<int, float|int>  $cantidades  por posición de la línea
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public static function ajustarLineas(array $lineas, array $cantidades): array
    {
        $quedan = [];
        foreach (array_values($lineas) as $i => $l) {
            $cantidad = array_key_exists($i, $cantidades) ? (float) $cantidades[$i] : (float) $l['cantidad'];
            if ($cantidad < 0 || $cantidad > (float) $l['cantidad']) {
                throw new \RuntimeException('Solo puedes bajar o quitar lo que pidió el cliente, no aumentar.');
            }
            if ($cantidad > 0) {
                $quedan[] = ['cantidad' => $cantidad] + $l;
            }
        }
        if (! $quedan) {
            throw new \RuntimeException('Quitaste todo: usa "Rechazar" para avisarle al cliente.');
        }

        return $quedan;
    }

    public static function rechazar(User $usuario, int $id, ?string $motivo): void
    {
        $hecho = DB::table('carta_solicitudes')->where('id', $id)->where('id_empresa_negocio', $usuario->id_empresa_negocio)->where('estado', 'PENDIENTE')
            ->update(['estado' => 'RECHAZADA', 'nota' => mb_substr(mb_strtoupper(trim((string) $motivo)), 0, 150) ?: null,
                'IdUsuario_atiende' => $usuario->IdUsuario, 'atendido_en' => now(), 'updated_at' => now()]);
        if (! $hecho) {
            throw new \RuntimeException('Otra persona ya atendió esta solicitud.');
        }
    }

    /** Estado que ve el cliente en su celular */
    public static function estado(int $suc, string $token): ?array
    {
        $s = DB::table('carta_solicitudes')->where('id_empresa_negocio', $suc)->where('token', $token)->first();
        if (! $s) {
            return null;
        }
        $vencida = $s->estado === 'PENDIENTE' && now()->diffInMinutes($s->created_at, true) > self::VIGENCIA;

        return ['tipo' => $s->tipo, 'estado' => $vencida ? 'VENCIDA' : $s->estado, 'total' => $s->total !== null ? (float) $s->total : null,
            'motivo' => $s->estado === 'RECHAZADA' ? $s->nota : null];
    }
}
