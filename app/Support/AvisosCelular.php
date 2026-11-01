<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Avisos al celular de los mozos (Web Push): pedido del QR, llaman al mozo, piden la cuenta y plato listo. Llegan a
 * todos los equipos de la sucursal aunque la pantalla esté apagada, solo en las sucursales que usan el QR de mesas
 * (carta_pedido_mesa). Se mandan después de responder y si algo falla no se rompe nada.
 */
class AvisosCelular
{
    /** @var array<int, array{sucursal: int, aviso: array<string, mixed>}> */
    private static array $pendientes = [];

    public static function configurado(): bool
    {
        return (bool) config('services.push.publica') && (bool) config('services.push.privada');
    }

    /** @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $suscripcion */
    public static function suscribir(int $usuario, int $sucursal, array $suscripcion, ?string $equipo): void
    {
        DB::table('push_suscripciones')->updateOrInsert(['huella' => hash('sha256', $suscripcion['endpoint'])], [
            'IdUsuario' => $usuario, 'id_empresa_negocio' => $sucursal, 'endpoint' => $suscripcion['endpoint'],
            'p256dh' => $suscripcion['keys']['p256dh'], 'auth' => $suscripcion['keys']['auth'],
            'equipo' => mb_substr((string) $equipo, 0, 150) ?: null, 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public static function quitar(string $endpoint): void
    {
        DB::table('push_suscripciones')->where('huella', hash('sha256', $endpoint))->delete();
    }

    /** Pedido, mozo o cuenta desde la carta QR: a todos los equipos con avisos de la sucursal */
    public static function solicitudCarta(int $sucursal, string $mesa, string $tipo, ?float $total = null): void
    {
        [$titulo, $cuerpo] = match ($tipo) {
            'PEDIDO' => ['🛎️ Pedido nuevo · '.$mesa, 'Pidieron desde la carta'.($total ? ' · S/ '.number_format($total, 2) : '').'. Toca para revisarlo.'],
            'CUENTA' => ['🧾 Piden la cuenta · '.$mesa, 'El cliente quiere pagar.'],
            default => ['🙋 Llaman al mozo · '.$mesa, 'El cliente necesita atención.'],
        };
        self::encolar($sucursal, ['titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => route('comandas.seleccion'), 'etiqueta' => 'carta-'.$tipo.'-'.$mesa]);
    }

    /** Cocina despachó platos: a todos los mozos de la sucursal */
    public static function platoListo(int $ticketId, array $itemIds): void
    {
        $ticket = DB::table('cocina_tickets')->where('id', $ticketId)->first();
        if (! $ticket || ! $itemIds) {
            return;
        }
        $platos = DB::table('cocina_items')->whereIn('id', $itemIds)->where('anulado', 0)->get(['descripcion', 'cantidad'])
            ->map(fn ($i) => rtrim(rtrim(number_format((float) $i->cantidad, 2, '.', ''), '0'), '.').' '.$i->descripcion)->implode(', ');
        if ($platos === '') {
            return;
        }
        self::encolar((int) $ticket->id_empresa_negocio, ['titulo' => '🍽️ Listo para llevar · '.($ticket->destino ?: 'Pedido'),
            'cuerpo' => mb_strimwidth($platos, 0, 160, '…'), 'url' => route('comandas.seleccion'), 'etiqueta' => 'listo-'.$ticketId]);
    }

    /** @param  array{titulo: string, cuerpo: string, url: string, etiqueta: string}  $aviso */
    private static function encolar(int $sucursal, array $aviso): void
    {
        if (! self::configurado() || ! self::usaQrDeMesas($sucursal)) {
            return;
        }
        if (! self::$pendientes) {
            app()->terminating(fn () => self::enviar());
        }
        self::$pendientes[] = ['sucursal' => $sucursal, 'aviso' => $aviso];
    }

    /** Solo las sucursales que dejan pedir desde el QR de la mesa tienen avisos al celular */
    public static function usaQrDeMesas(int $sucursal): bool
    {
        return $sucursal && (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('carta_pedido_mesa');
    }

    /** Manda lo acumulado y borra los equipos que ya no aceptan avisos (desinstalaron o quitaron el permiso) */
    public static function enviar(): void
    {
        $pendientes = self::$pendientes;
        self::$pendientes = [];
        if (! $pendientes) {
            return;
        }
        try {
            if (! Schema::hasTable('push_suscripciones')) {
                return;
            }
            $push = null;
            foreach ($pendientes as $p) {
                $equipos = DB::table('push_suscripciones')->where('id_empresa_negocio', $p['sucursal'])->get();
                if ($equipos->isEmpty()) {
                    continue;
                }
                $push ??= new WebPush(['VAPID' => ['subject' => config('services.push.contacto'),
                    'publicKey' => config('services.push.publica'), 'privateKey' => config('services.push.privada')]], ['TTL' => 600, 'urgency' => 'high'], logger: app('log'));
                foreach ($equipos as $e) {
                    $push->queueNotification(Subscription::create(['endpoint' => $e->endpoint, 'keys' => ['p256dh' => $e->p256dh, 'auth' => $e->auth]]),
                        json_encode($p['aviso'], JSON_UNESCAPED_UNICODE));
                }
            }
            if (! $push) {
                return;
            }
            foreach ($push->flush() as $reporte) {
                if ($reporte->isSubscriptionExpired()) {
                    self::quitar($reporte->getEndpoint());
                }
            }
        } catch (\Throwable) {
            // Sin aviso al celular igual queda la campanita en la pantalla de mesas
        }
    }
}
