<?php

namespace App\Http\Controllers;

use App\Models\Central\Cliente;
use App\Support\Tenancy\PagosOnline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Página de pago del servicio en tushpa.app (fuera del sistema de la empresa: así se puede pagar aunque esté
 * suspendida). Se entra con un enlace firmado desde la pantalla de suspendido o desde el aviso de vencimiento.
 */
class PagoOnlineController extends Controller
{
    private function cliente(Cliente $cliente): Cliente
    {
        abort_unless(in_array($cliente->estado, ['ACTIVO', 'SUSPENDIDO'], true) && PagosOnline::disponiblePara($cliente), 404);

        return $cliente;
    }

    public function pagina(Cliente $cliente): View
    {
        $cliente = $this->cliente($cliente);

        return view('sitio.pagar', ['cliente' => $cliente, 'opciones' => PagosOnline::opciones($cliente), 'publica' => config('services.culqi.publica')]);
    }

    /** Elige 1 mes o el año: se anota el pago y se crea su orden en Culqi */
    public function preparar(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente = $this->cliente($cliente);
        $d = $request->validate(['meses' => 'required|integer|in:1,12']);
        try {
            $pago = PagosOnline::preparar($cliente, (int) $d['meses']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['pago' => $pago->id, 'monto' => (int) round($pago->monto * 100), 'orden' => $pago->orden, 'texto' => $pago->texto]);
    }

    /** Tarjeta o Yape: el token del checkout se convierte en cobro */
    public function cobrar(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente = $this->cliente($cliente);
        $d = $request->validate(['pago' => 'required|integer', 'token' => 'required|string|max:80', 'correo' => 'nullable|email|max:120',
            'dispositivo' => 'nullable|string|max:80', 'autenticacion' => 'nullable|array']);
        abort_unless(DB::connection('central')->table('pagos_online')->where('id', $d['pago'])->where('cliente_id', $cliente->id)->exists(), 404);
        $r = PagosOnline::cobrar((int) $d['pago'], $d['token'], $d['correo'] ?? null, $d['dispositivo'] ?? null, $d['autenticacion'] ?? null);

        // 3D Secure: no es un error, la página abre la verificación del banco
        return response()->json($r + ['url' => $r['ok'] ? $cliente->url() : null], $r['ok'] || ! empty($r['tresds']) ? 200 : 422);
    }

    /** Billeteras con QR, agentes o banca móvil: la página pregunta cada pocos segundos si ya se pagó */
    public function estado(Cliente $cliente, int $pago): JsonResponse
    {
        $fila = DB::connection('central')->table('pagos_online')->where('id', $pago)->where('cliente_id', $cliente->id)->first();
        abort_unless($fila, 404);
        $pagado = $fila->estado === 'PAGADO' || ($fila->culqi_orden && PagosOnline::revisarOrden($fila->culqi_orden));

        return response()->json(['pagado' => $pagado, 'url' => $pagado ? $cliente->url() : null]);
    }

    /**
     * Aviso de Culqi (webhook "order.status.changed"). No se confía en lo que trae: se le pregunta a Culqi por la orden.
     */
    public function webhook(Request $request): JsonResponse
    {
        $datos = $request->input('data');
        $orden = is_string($datos) ? (json_decode($datos, true)['id'] ?? null) : ($datos['id'] ?? null);
        if ($request->input('type') === 'order.status.changed' && is_string($orden) && str_starts_with($orden, 'ord_')) {
            PagosOnline::revisarOrden($orden);
        }

        return response()->json(['ok' => true]);
    }
}
