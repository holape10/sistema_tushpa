<?php

namespace App\Http\Controllers;

use App\Support\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Delivery en vivo (caja y administrador): mapa con los motorizados, pedidos delivery con su estado y tiempos,
 * datos de entrega con su ubicación, y el enlace de seguimiento para el cliente.
 */
class DeliveryController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $local = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['nombre_comercial', 'local_lat', 'local_lng', 'direccion']);

        return view('empresas.delivery.index', [
            'local' => $local,
            'motorizados' => MotorizadoController::activos($suc),
            'abrir' => (int) $request->get('pedido') ?: null,
            'mediosPago' => Delivery::MEDIOS_PAGO,
            'tarifas' => Delivery::tarifas($suc),
        ]);
    }

    /** Lo que muestra el panel (se consulta cada 10 s) */
    public function estado(): JsonResponse
    {
        $suc = $this->sucursal();
        $pedidos = DB::table('pedidos as p')->leftJoin('motorizados as m', 'm.mot_id', '=', 'p.mot_id')
            ->where('p.id_empresa_negocio', $suc)->where('p.ped_tip', 'Delivery')->whereNotIn('p.ped_est', ['Anulado', 'Unido'])
            ->where('p.fecha_hora', '>=', now()->subDays(2))
            ->where(fn ($q) => $q->whereNull('p.entrega_estado')->orWhere('p.entrega_estado', '!=', 'ENTREGADO')
                ->orWhere('p.entrega_fin', '>=', now()->startOfDay()))
            ->orderByRaw("p.entrega_estado = 'ENTREGADO'")->orderBy('p.fecha_hora')
            ->get(['p.*', 'm.nombre as motorizado', 'm.telefono as motorizado_tel']);

        // Pedidos de antes de esta función: arrancan "Preparando" y con su enlace
        foreach ($pedidos->whereNull('entrega_estado') as $p) {
            Delivery::iniciar($p->ped_id);
            $fila = DB::table('pedidos')->where('ped_id', $p->ped_id)->first(['entrega_estado', 'entrega_token']);
            $p->entrega_estado = $fila->entrega_estado;
            $p->entrega_token = $fila->entrega_token;
        }
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');

        return response()->json([
            'pedidos' => $pedidos->map(fn ($p) => $this->fila($p, $negocio))->values(),
            'motorizados' => DB::table('motorizados')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('nombre')
                ->get(['mot_id', 'nombre', 'telefono', 'lat', 'lng', 'posicion_at', 'token'])
                ->map(fn ($m) => ['id' => $m->mot_id, 'nombre' => $m->nombre, 'telefono' => $m->telefono, 'tiene_enlace' => (bool) $m->token,
                    'lat' => $m->lat !== null ? (float) $m->lat : null, 'lng' => $m->lng !== null ? (float) $m->lng : null,
                    'hace' => $m->posicion_at ? (int) Carbon::parse($m->posicion_at)->diffInSeconds(now()) : null]),
        ]);
    }

    private function fila(object $p, string $negocio): array
    {
        $url = route('pedido.seguimiento', $p->entrega_token);
        $tel = preg_replace('/\D/', '', (string) $p->ped_tel);

        return [
            'id' => $p->ped_id, 'cliente' => $p->ped_cli_nom !== 'DELIVERY' ? $p->ped_cli_nom : '', 'telefono' => $p->ped_tel, 'direccion' => $p->ped_dir,
            'referencia' => $p->entrega_ref, 'lat' => $p->entrega_lat !== null ? (float) $p->entrega_lat : null, 'lng' => $p->entrega_lng !== null ? (float) $p->entrega_lng : null,
            'total' => (float) $p->ped_tot, 'cobrado' => $p->ped_est === 'Cerrado', 'estado' => $p->entrega_estado,
            'mot_id' => $p->mot_id, 'motorizado' => $p->motorizado,
            'pago' => $p->entrega_pago, 'paga_con' => $p->entrega_paga_con !== null ? (float) $p->entrega_paga_con : null, 'pago_texto' => Delivery::textoPago($p),
            'envio' => $p->entrega_envio !== null ? (float) $p->entrega_envio : null, 'web' => $p->entrega_origen === 'CARTA', 'nota' => $p->entrega_origen === 'CARTA' ? $p->ped_obs : null,
            'cobrar' => $p->ped_est === 'Aperturado' ? route('cobros.cobrar', $p->ped_id) : null,
            'minutos' => (int) Carbon::parse($p->fecha_hora)->diffInMinutes($p->entrega_fin ?? now()),
            'horas' => collect(['pedido' => $p->fecha_hora, 'listo' => $p->entrega_listo, 'local' => $p->entrega_local, 'salida' => $p->entrega_salida, 'fin' => $p->entrega_fin])
                ->map(fn ($h) => $h ? Carbon::parse($h)->format('H:i') : null),
            'seguimiento' => $url,
            'whatsapp' => strlen($tel) >= 9 ? 'https://wa.me/'.(strlen($tel) === 9 ? '51'.$tel : $tel).'?text='.rawurlencode(Delivery::mensajeCliente($p, $negocio, $url)) : null,
        ];
    }

    /** Datos de entrega: cliente, teléfono, dirección, referencia y ubicación en el mapa */
    public function datos(Request $request, int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'cliente' => 'nullable|string|max:120', 'telefono' => 'nullable|string|max:20', 'direccion' => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:255', 'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
            'mot_id' => 'nullable|integer',
            'pago' => 'nullable|in:'.implode(',', array_keys(Delivery::MEDIOS_PAGO)),
            'paga_con' => 'nullable|numeric|min:0|max:99999',
            'envio' => 'nullable|numeric|min:0|max:9999',
        ]);
        $pedido = DB::table('pedidos')->where('ped_id', $id)->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->first();
        abort_unless($pedido, 404);
        $motId = ! empty($d['mot_id']) && DB::table('motorizados')->where('mot_id', $d['mot_id'])->where('id_empresa_negocio', $suc)->exists() ? (int) $d['mot_id'] : null;

        DB::table('pedidos')->where('ped_id', $id)->update([
            'ped_cli_nom' => mb_strtoupper(trim((string) ($d['cliente'] ?? ''))) ?: 'DELIVERY',
            'ped_tel' => trim((string) ($d['telefono'] ?? '')) ?: null, 'ped_dir' => trim((string) ($d['direccion'] ?? '')) ?: null,
            'entrega_ref' => trim((string) ($d['referencia'] ?? '')) ?: null,
            'entrega_lat' => $d['lat'] ?? null, 'entrega_lng' => $d['lng'] ?? null, 'mot_id' => $motId,
            'entrega_pago' => $d['pago'] ?? null, 'entrega_paga_con' => ($d['pago'] ?? null) === 'EFECTIVO' && ! empty($d['paga_con']) ? $d['paga_con'] : null,
        ]);
        // Costo de envío: lo escrito o, si se dejó vacío, el de las tarifas por distancia
        $envio = isset($d['envio']) ? (float) $d['envio']
            : Delivery::costoEnvio($suc, isset($d['lat']) ? (float) $d['lat'] : null, isset($d['lng']) ? (float) $d['lng'] : null)['costo'];
        if ($envio !== null || $pedido->entrega_envio !== null) {
            Delivery::ponerEnvio($id, (float) $envio);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Datos de entrega guardados.']);
    }

    /** Caja marca el estado (por ejemplo "Listo" cuando sale de cocina, o "Entregado" si el motorizado no lo marcó) */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $estado = (string) $request->validate(['estado' => 'required|in:'.implode(',', array_keys(Delivery::ESTADOS))])['estado'];
        abort_unless(DB::table('pedidos')->where('ped_id', $id)->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->exists(), 404);
        Delivery::cambiarEstado($id, $estado);

        return response()->json(['ok' => true]);
    }

    public function recorrido(int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $pedido = DB::table('pedidos')->where('ped_id', $id)->where('id_empresa_negocio', $suc)->first();
        abort_unless($pedido, 404);

        return response()->json(['puntos' => Delivery::recorrido($pedido)]);
    }

    /** Ubicación del local (centro del mapa y punto de partida) */
    public function local(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update(['local_lat' => $d['lat'], 'local_lng' => $d['lng']]);

        return response()->json(['ok' => true, 'mensaje' => 'Ubicación del local guardada.']);
    }

    /**
     * Ubica lo que se escribe o pega: enlace de Google Maps, ubicación de WhatsApp, "lat, lng" o una dirección
     * ("Jr. Próspero 450"), que se busca en el mapa cerca del local. Con varias coincidencias se devuelven para elegir.
     */
    public function ubicacion(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $texto = (string) $request->validate(['texto' => 'required|string|max:500'])['texto'];
        if ($c = Delivery::coordenadas($texto)) {
            return response()->json($c + ['resultados' => [$c + ['nombre' => 'Ubicación del enlace']]]);
        }
        $resultados = Delivery::buscarDireccion($suc, $texto);

        return $resultados
            ? response()->json($resultados[0] + ['resultados' => $resultados])
            : response()->json(['error' => 'No encontré esa dirección en el mapa. Prueba solo la calle (ej. "Jr. Próspero") o toca el punto en el mapa.'], 422);
    }

    /** Dirección aproximada del punto marcado en el mapa (para llenarla si aún está vacía) */
    public function direccion(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);

        return response()->json(['direccion' => Delivery::direccionDe((float) $d['lat'], (float) $d['lng'])]);
    }

    /** Reporte de tiempos: cuánto tarda cada paso (cocina, espera del motorizado, viaje) por pedido y por motorizado */
    public function reporte(Request $request): View
    {
        $suc = $this->sucursal();
        $desde = Carbon::parse($request->get('desde', now()->subDays(6)->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        $minutos = fn ($a, $b) => $a && $b ? max(0, (int) round(Carbon::parse($a)->diffInSeconds(Carbon::parse($b)) / 60)) : null;

        $pedidos = DB::table('pedidos as p')->leftJoin('motorizados as m', 'm.mot_id', '=', 'p.mot_id')
            ->where('p.id_empresa_negocio', $suc)->where('p.ped_tip', 'Delivery')->where('p.entrega_estado', 'ENTREGADO')
            ->whereNotIn('p.ped_est', ['Anulado', 'Unido'])->whereBetween('p.entrega_fin', [$desde, $hasta])->orderBy('p.entrega_fin')
            ->get(['p.ped_id', 'p.ped_cli_nom', 'p.ped_tot', 'p.entrega_envio', 'p.entrega_origen', 'p.fecha_hora', 'p.entrega_listo', 'p.entrega_local',
                'p.entrega_salida', 'p.entrega_fin', 'p.mot_id', 'm.nombre as motorizado'])
            ->map(fn ($p) => (object) [
                'id' => $p->ped_id, 'cliente' => $p->ped_cli_nom !== 'DELIVERY' ? $p->ped_cli_nom : '', 'motorizado' => $p->motorizado ?? 'Sin motorizado',
                'web' => $p->entrega_origen === 'CARTA', 'total' => (float) $p->ped_tot, 'envio' => (float) $p->entrega_envio,
                'fecha' => Carbon::parse($p->entrega_fin)->format('d/m H:i'),
                'cocina' => $minutos($p->fecha_hora, $p->entrega_listo ?? $p->entrega_salida),
                'espera' => $minutos($p->entrega_listo ?? $p->entrega_local, $p->entrega_salida),
                'viaje' => $minutos($p->entrega_salida, $p->entrega_fin),
                'total_min' => $minutos($p->fecha_hora, $p->entrega_fin),
            ]);
        $promedio = fn ($lista, $campo) => ($v = $lista->pluck($campo)->filter(fn ($x) => $x !== null))->count() ? (int) round($v->avg()) : null;
        $resumen = fn ($lista) => ['pedidos' => $lista->count(), 'cocina' => $promedio($lista, 'cocina'), 'espera' => $promedio($lista, 'espera'),
            'viaje' => $promedio($lista, 'viaje'), 'total_min' => $promedio($lista, 'total_min'), 'ventas' => round($lista->sum('total'), 2),
            'envios' => round($lista->sum('envio'), 2), 'tarde' => $lista->where('total_min', '>', 45)->count()];

        return view('empresas.delivery.reporte', [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'pedidos' => $pedidos,
            'general' => $resumen($pedidos), 'web' => $pedidos->where('web', true)->count(),
            'porMotorizado' => $pedidos->groupBy('motorizado')->map($resumen)->sortByDesc('pedidos'),
        ]);
    }

    /** Costo de envío según la distancia (para llenar el campo al marcar la ubicación) */
    public function envio(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);

        return response()->json(Delivery::costoEnvio($suc, (float) $d['lat'], (float) $d['lng']));
    }

    /** Costos de envío por distancia: "hasta 2 km S/ 3", "hasta 4 km S/ 5"… (más lejos: fuera de zona) */
    public function tarifas(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'tarifas' => 'present|array|max:20',
            'tarifas.*.hasta_km' => 'required|numeric|min:0.1|max:200|distinct',
            'tarifas.*.costo' => 'required|numeric|min:0|max:9999',
        ], ['tarifas.*.hasta_km.distinct' => 'Hay dos tarifas con la misma distancia.']);
        DB::transaction(function () use ($suc, $d) {
            DB::table('delivery_tarifas')->where('id_empresa_negocio', $suc)->delete();
            DB::table('delivery_tarifas')->insert(collect($d['tarifas'])->map(fn ($t) => ['id_empresa_negocio' => $suc, 'hasta_km' => $t['hasta_km'], 'costo' => $t['costo']])->all());
        });

        return response()->json(['ok' => true, 'tarifas' => Delivery::tarifas($suc), 'mensaje' => 'Costos de envío guardados.']);
    }

    /** Motorizados para el pedido, el más cercano y libre primero */
    public function sugerir(int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $pedido = DB::table('pedidos')->where('ped_id', $id)->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->first();
        abort_unless($pedido, 404);

        return response()->json(['motorizados' => Delivery::sugerirMotorizados($suc, $pedido)]);
    }

    /** Asigna el motorizado (sin mot_id: el primero de la sugerencia, el más cercano) */
    public function asignar(Request $request, int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $pedido = DB::table('pedidos')->where('ped_id', $id)->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->first();
        abort_unless($pedido, 404);
        $motId = $request->validate(['mot_id' => 'nullable|integer'])['mot_id'] ?? null;
        $motId ??= Delivery::sugerirMotorizados($suc, $pedido)[0]['id'] ?? null;
        $mot = $motId ? DB::table('motorizados')->where('mot_id', $motId)->where('id_empresa_negocio', $suc)->where('activo', 1)->first() : null;
        if (! $mot) {
            return response()->json(['error' => 'No hay motorizados activos para asignar.'], 422);
        }
        DB::table('pedidos')->where('ped_id', $id)->update(['mot_id' => $mot->mot_id]);

        return response()->json(['ok' => true, 'mensaje' => "Pedido #{$id} asignado a {$mot->nombre}."]);
    }

    /** Enlace personal del motorizado (con "nuevo", el anterior deja de servir) */
    public function enlaceMotorizado(Request $request, int $motId): JsonResponse
    {
        $suc = $this->sucursal();
        $m = DB::table('motorizados')->where('mot_id', $motId)->where('id_empresa_negocio', $suc)->first();
        abort_unless($m, 404);
        $url = route('repartidor.index', Delivery::tokenMotorizado($motId, $request->boolean('nuevo')));
        $tel = preg_replace('/\D/', '', (string) $m->telefono);
        $texto = "Hola {$m->nombre}, este es tu enlace de repartidor. Ábrelo en tu celular, acepta la ubicación y mantenlo abierto mientras repartes: {$url}";

        return response()->json(['url' => $url, 'whatsapp' => 'https://wa.me/'.(strlen($tel) === 9 ? '51'.$tel : $tel).'?text='.rawurlencode($texto)]);
    }
}
