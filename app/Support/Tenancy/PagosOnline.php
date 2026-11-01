<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pago en línea del servicio con Culqi. La empresa elige 1 mes o el año de su plan y paga con:
 *  - tarjeta o Yape: el checkout devuelve un token y aquí se hace el cargo; si sale bien, se aplica al instante.
 *  - billeteras con QR (Yape, Plin, bancos), agentes o banca móvil: se crea una "orden"; cuando el cliente la paga,
 *    Culqi avisa (webhook) o la página pregunta, y se aplica. El estado siempre se confirma preguntándole a Culqi.
 * Aplicar el pago = la empresa queda ACTIVA y su vencimiento se corre los meses pagados (desde hoy si ya venció).
 * Solo para las empresas que le pagan al dueño del sistema (las de un revendedor le pagan a él).
 */
class PagosOnline
{
    private const API = 'https://api.culqi.com/v2';

    /** Horas que dura una orden de billetera/agente antes de vencer */
    private const HORAS_ORDEN = 24;

    public static function configurado(): bool
    {
        return (bool) config('services.culqi.publica') && (bool) config('services.culqi.privada');
    }

    /** ¿Puede pagar en línea? Con llaves puestas, con plan con precio, y si le paga al dueño del sistema */
    public static function disponiblePara(Cliente $cliente): bool
    {
        return self::configurado() && self::precioMensual($cliente) > 0 && DatosCobro::cobraElDueno($cliente);
    }

    public static function precioMensual(Cliente $cliente): float
    {
        return (float) ($cliente->planContratado?->precio ?? 0);
    }

    /** @return array<int, array{meses: int, monto: float, texto: string}> 1 mes y el año (con sus meses gratis) */
    public static function opciones(Cliente $cliente): array
    {
        $plan = $cliente->planContratado;
        if (! $plan || $plan->precio <= 0) {
            return [];
        }

        return [
            ['meses' => 1, 'monto' => round($plan->precio, 2), 'texto' => '1 mes'],
            ['meses' => 12, 'monto' => $plan->precioAnual(), 'texto' => '1 año'.($plan->ahorroAnual() > 0 ? ' (ahorras S/ '.number_format($plan->ahorroAnual(), 2).')' : '')],
        ];
    }

    private static function api(): PendingRequest
    {
        return Http::withToken((string) config('services.culqi.privada'))->acceptJson()->timeout(30);
    }

    /**
     * Prepara un pago: lo anota y crea su orden en Culqi (la necesitan las billeteras con QR, agentes y banca móvil).
     * Si la orden no se puede crear, igual se puede pagar con tarjeta o Yape.
     */
    public static function preparar(Cliente $cliente, int $meses): object
    {
        $opcion = collect(self::opciones($cliente))->firstWhere('meses', $meses);
        if (! $opcion) {
            throw new \RuntimeException('Elige 1 mes o 1 año.');
        }
        // Vuelve a intentar el mismo pago: se usa la orden que ya estaba esperando (no se llena Culqi de órdenes pendientes)
        $anterior = DB::connection('central')->table('pagos_online')->where('cliente_id', $cliente->id)->where('meses', $meses)
            ->where('monto', $opcion['monto'])->where('estado', 'PENDIENTE')->whereNotNull('culqi_orden')
            ->where('created_at', '>=', now()->subHours(self::HORAS_ORDEN - 1))->latest('id')->first();
        if ($anterior) {
            return (object) ['id' => (int) $anterior->id, 'monto' => $opcion['monto'], 'texto' => $opcion['texto'], 'orden' => $anterior->culqi_orden];
        }
        $id = DB::connection('central')->table('pagos_online')->insertGetId([
            'cliente_id' => $cliente->id, 'meses' => $meses, 'monto' => $opcion['monto'], 'estado' => 'PENDIENTE',
            'correo' => self::correo($cliente), 'vence_antes' => $cliente->vence_el?->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $orden = null;
        $respuesta = self::api()->post(self::API.'/orders', [
            'amount' => (int) round($opcion['monto'] * 100), 'currency_code' => 'PEN',
            'description' => mb_substr('TUSHPA '.$opcion['texto'].' · '.$cliente->razon_social, 0, 80),
            'order_number' => 'tushpa-'.$id, 'expiration_date' => (string) now()->addHours(self::HORAS_ORDEN)->timestamp,
            'client_details' => self::datosCliente($cliente),
            'metadata' => ['pago' => (string) $id, 'ruc' => $cliente->ruc],
        ]);
        if ($respuesta->successful() && $respuesta->json('id')) {
            $orden = (string) $respuesta->json('id');
            DB::connection('central')->table('pagos_online')->where('id', $id)->update(['culqi_orden' => $orden]);
        }

        return (object) ['id' => $id, 'monto' => $opcion['monto'], 'texto' => $opcion['texto'], 'orden' => $orden];
    }

    /**
     * Cobra con el token del checkout (tarjeta o Yape). Si el banco pide verificar al titular (3D Secure), responde
     * tresds = true: la página muestra la verificación del banco y vuelve a llamar con sus parámetros ($autenticacion).
     *
     * @param  array<string, mixed>|null  $autenticacion  parámetros que devuelve Culqi3DS (eci, xid, cavv…)
     * @return array{ok: bool, mensaje: string, tresds?: bool}
     */
    public static function cobrar(int $pagoId, string $token, ?string $correo, ?string $dispositivo = null, ?array $autenticacion = null): array
    {
        $pago = DB::connection('central')->table('pagos_online')->where('id', $pagoId)->first();
        if (! $pago || $pago->estado === 'PAGADO') {
            return ['ok' => (bool) $pago, 'mensaje' => $pago ? 'Este pago ya estaba confirmado.' : 'No se encontró el pago.'];
        }
        $correo = filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : $pago->correo;
        $cliente = Cliente::find($pago->cliente_id);
        $datos = $cliente ? self::datosCliente($cliente) : [];
        $respuesta = self::api()->post(self::API.'/charges', array_filter([
            'amount' => (int) round($pago->monto * 100), 'currency_code' => 'PEN', 'email' => $correo, 'source_id' => $token,
            'description' => 'TUSHPA '.$pago->meses.' mes(es)', 'metadata' => ['pago' => (string) $pago->id],
            // Datos para el motor antifraude y, en el segundo intento, la verificación del banco (mismo token y dispositivo)
            'antifraud_details' => array_filter(['first_name' => $datos['first_name'] ?? null, 'last_name' => $datos['last_name'] ?? null,
                'phone_number' => $datos['phone_number'] ?? null, 'device_finger_print_id' => $dispositivo]),
            'authentication_3DS' => $autenticacion,
        ]));

        // El banco pide verificar al titular (3D Secure): la página abre la verificación y vuelve a cobrar
        if ($respuesta->status() === 200 && $respuesta->json('action_code') === 'REVIEW') {
            if ($autenticacion) {
                return self::fallo($pago->id, 'El banco no confirmó la verificación. Intenta otra vez, con otra tarjeta, con Yape o con el QR.');
            }

            return ['ok' => false, 'tresds' => true, 'mensaje' => 'Tu banco pide confirmar que eres tú.'];
        }
        if (! $respuesta->successful() || ! $respuesta->json('id')) {
            return self::fallo($pago->id, self::mensajeError($respuesta));
        }
        $medio = str_starts_with($token, 'ype_') ? 'yape' : 'tarjeta';
        DB::connection('central')->table('pagos_online')->where('id', $pago->id)
            ->update(['culqi_cargo' => (string) $respuesta->json('id'), 'medio' => $medio, 'correo' => $correo, 'updated_at' => now()]);
        self::aplicar($pago->id, (string) ($respuesta->json('outcome.user_message') ?: $respuesta->json('user_message') ?: 'Pago aceptado.'));

        return ['ok' => true, 'mensaje' => '¡Pago recibido! Tu sistema ya está activo.'];
    }

    /** Pregunta a Culqi si la orden ya se pagó (lo usan la página, mientras espera, y el webhook) */
    public static function revisarOrden(string $ordenId): bool
    {
        $pago = DB::connection('central')->table('pagos_online')->where('culqi_orden', $ordenId)->first();
        if (! $pago) {
            return false;
        }
        if ($pago->estado === 'PAGADO') {
            return true;
        }
        $orden = self::api()->get(self::API.'/orders/'.$ordenId);
        if (! $orden->successful() || $orden->json('state') !== 'paid') {
            return false;
        }
        DB::connection('central')->table('pagos_online')->where('id', $pago->id)->update(['medio' => 'billetera', 'updated_at' => now()]);
        self::aplicar($pago->id, 'Orden pagada.');

        return true;
    }

    /**
     * El pago se confirmó: la empresa queda activa y su vencimiento se corre los meses pagados (una sola vez aunque
     * llegue el aviso de Culqi y la página pregunte al mismo tiempo)
     */
    public static function aplicar(int $pagoId, string $detalle): void
    {
        DB::connection('central')->transaction(function () use ($pagoId, $detalle) {
            $pago = DB::connection('central')->table('pagos_online')->where('id', $pagoId)->lockForUpdate()->first();
            if (! $pago || $pago->estado === 'PAGADO') {
                return;
            }
            $cliente = Cliente::lockForUpdate()->findOrFail($pago->cliente_id);
            $desde = $cliente->vence_el && $cliente->vence_el->isFuture() ? $cliente->vence_el->copy() : now()->startOfDay();
            $vence = $desde->addMonthsNoOverflow((int) $pago->meses);
            $cliente->update(['estado' => 'ACTIVO', 'vence_el' => $vence->toDateString(), 'motivo_suspension' => null, 'suspendido_el' => null,
                'aviso_mensaje' => null, 'aviso_fecha' => null, 'aviso_suspender' => 0, 'prueba_hasta' => null,
                'notas' => trim($cliente->notas.PHP_EOL.'Pagó en línea S/ '.number_format((float) $pago->monto, 2).' ('.$pago->meses.' mes(es)) el '
                    .now()->format('d/m/Y H:i').'. Vence el '.$vence->format('d/m/Y').'.')]);
            DB::connection('central')->table('pagos_online')->where('id', $pagoId)->update([
                'estado' => 'PAGADO', 'pagado_el' => now(), 'vence_despues' => $vence->toDateString(),
                'detalle' => mb_substr($detalle, 0, 255), 'updated_at' => now(),
            ]);
        });
    }

    /** @return array{ok: false, mensaje: string} */
    private static function fallo(int $pagoId, string $mensaje): array
    {
        DB::connection('central')->table('pagos_online')->where('id', $pagoId)->where('estado', '!=', 'PAGADO')
            ->update(['estado' => 'FALLIDO', 'detalle' => mb_substr($mensaje, 0, 255), 'updated_at' => now()]);

        return ['ok' => false, 'mensaje' => $mensaje];
    }

    private static function mensajeError(Response $r): string
    {
        return (string) ($r->json('user_message') ?: $r->json('merchant_message') ?: 'No se pudo cobrar. Intenta con otro medio de pago.');
    }

    private static function correo(Cliente $cliente): string
    {
        return filter_var($cliente->contacto_correo, FILTER_VALIDATE_EMAIL) ? (string) $cliente->contacto_correo : (string) config('soporte.correo');
    }

    /** Datos del que paga (Culqi los pide para la orden y le manda las instrucciones a ese correo) */
    private static function datosCliente(Cliente $cliente): array
    {
        $nombre = trim((string) ($cliente->contacto_nombre ?: $cliente->nombre_comercial ?: $cliente->razon_social));
        $partes = preg_split('/\s+/', $nombre, 2);
        $telefono = preg_replace('/\D/', '', (string) $cliente->contacto_telefono);

        return [
            'first_name' => mb_substr(str_pad($partes[0] ?? 'Cliente', 2, '.'), 0, 50), 'last_name' => mb_substr(str_pad($partes[1] ?? 'TUSHPA', 2, '.'), 0, 50),
            'email' => self::correo($cliente), 'phone_number' => strlen($telefono) >= 9 ? substr($telefono, -9) : (string) config('soporte.telefono'),
        ];
    }
}
