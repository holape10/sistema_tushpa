<?php

namespace App\Http\Controllers;

use App\Support\Carta;
use App\Support\Courier;
use App\Support\CourierWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Web pública del courier (sin sesión): cotizar un envío, pre-registrarlo (en la agencia solo se dicta el código) y
 * presentar un reclamo de un envío.
 */
class CourierWebController extends Controller
{
    private function config(): object
    {
        $cfg = Courier::config(CourierWeb::ruc());
        abort_unless($cfg->web_publico, 404);

        return $cfg;
    }

    /** Nombre, logo y teléfono para la cabecera */
    private function marca(): array
    {
        $empresa = DB::table('empresa')->first(['NomEmpresa', 'LogEmpresa']);
        $negocio = DB::table('empresa_negocios')->orderBy('id_empresa_negocio')->first(['nombre_comercial', 'telefono']);

        return [
            'nombre' => $negocio->nombre_comercial ?? $empresa->NomEmpresa ?? 'Envíos',
            'logo' => $empresa && $empresa->LogEmpresa && is_file(public_path($empresa->LogEmpresa)) ? asset($empresa->LogEmpresa) : null,
            'telefono' => $negocio->telefono ?? null,
        ];
    }

    public function index(): View
    {
        $cfg = $this->config();

        return view('courier.envios', $this->marca() + [
            'agencias' => Courier::agencias(CourierWeb::ruc()), 'cfg' => $cfg, 'dias' => CourierWeb::DIAS_PRERREGISTRO,
        ]);
    }

    private function reglasEnvio(): array
    {
        return ['origen_id' => 'required|integer', 'destino_id' => 'required|integer|different:origen_id', 'entrega' => 'required|in:AGENCIA,DOMICILIO',
            'valor_declarado' => 'nullable|numeric|min:0|max:9999999',
            'paquetes' => 'required|array|min:1|max:20', 'paquetes.*.tipo' => 'required|in:'.implode(',', Courier::TIPOS_PAQUETE),
            'paquetes.*.cantidad' => 'required|integer|min:1|max:100', 'paquetes.*.peso' => 'nullable|numeric|min:0|max:9999',
            'paquetes.*.largo' => 'nullable|numeric|min:0|max:999', 'paquetes.*.ancho' => 'nullable|numeric|min:0|max:999',
            'paquetes.*.alto' => 'nullable|numeric|min:0|max:999', 'paquetes.*.descripcion' => 'nullable|string|max:100'];
    }

    /** Precio referencial (el final se confirma al pesar en la agencia) */
    public function cotizar(Request $request): JsonResponse
    {
        $cfg = $this->config();
        $d = $request->validate($this->reglasEnvio(), ['destino_id.different' => 'Elige un destino distinto al origen.']);
        $ids = DB::table('cou_agencias')->where('IdEmpresa', $cfg->IdEmpresa)->where('activo', 1)->whereIn('age_id', [$d['origen_id'], $d['destino_id']])->count();
        abort_unless($ids === 2, 422);
        $tarifa = Courier::tarifa($cfg, (int) $d['origen_id'], (int) $d['destino_id']);

        return response()->json(Courier::cotizar($tarifa, $d['paquetes'], (float) ($d['valor_declarado'] ?? 0), $d['entrega'] === 'DOMICILIO',
            (int) $cfg->divisor_vol, (float) $cfg->seguro_pct, (float) $cfg->seguro_min) + ['dias' => $tarifa['dias']]);
    }

    public function prerregistrar(Request $request): JsonResponse
    {
        $cfg = $this->config();
        $d = $request->validate($this->reglasEnvio() + [
            'rem_doc' => 'required|string|min:8|max:11', 'rem_nombre' => 'required|string|max:150', 'rem_telefono' => 'required|string|max:20',
            'des_doc' => 'nullable|string|max:11', 'des_nombre' => 'required|string|max:150', 'des_telefono' => 'required|string|max:20',
            'direccion_entrega' => 'nullable|required_if:entrega,DOMICILIO|string|max:200', 'contenido' => 'required|string|max:200',
        ], ['destino_id.different' => 'Elige un destino distinto al origen.', 'direccion_entrega.required_if' => 'Escribe la dirección de entrega.'],
            ['rem_doc' => 'tu DNI o RUC', 'rem_nombre' => 'tu nombre', 'rem_telefono' => 'tu celular', 'des_nombre' => 'el nombre de quien recibe',
                'des_telefono' => 'el celular de quien recibe', 'contenido' => 'lo que envías']);
        abort_unless(DB::table('cou_agencias')->where('IdEmpresa', $cfg->IdEmpresa)->where('activo', 1)->whereIn('age_id', [$d['origen_id'], $d['destino_id']])->count() === 2, 422);
        $codigo = CourierWeb::prerregistrar($d, $request->ip());

        return response()->json(['ok' => true, 'codigo' => $codigo, 'qr' => Carta::qr($codigo, 200)]);
    }

    // ------------------------------------------------------------------ reclamos

    private function envio(string $codigo): object
    {
        $codigo = strtoupper($codigo);
        $e = Courier::esCodigo($codigo) ? DB::table('cou_envios')->where('codigo', $codigo)->first() : null;
        abort_unless($e, 404);

        return $e;
    }

    public function reclamo(string $codigo): View
    {
        $this->config();
        $e = $this->envio($codigo);

        return view('courier.reclamo', $this->marca() + ['e' => $e, 'tipos' => CourierWeb::TIPOS_RECLAMO, 'estados' => CourierWeb::ESTADOS_RECLAMO]);
    }

    /** Presentar un reclamo (quien reclama demuestra que el envío es suyo con su DNI o celular) o ver cómo va el suyo */
    public function reclamar(Request $request, string $codigo): JsonResponse
    {
        $this->config();
        $e = $this->envio($codigo);
        $d = $request->validate(['verificacion' => 'required|string|max:20', 'consultar' => 'nullable|boolean',
            'tipo' => 'exclude_if:consultar,true|required|in:'.implode(',', array_keys(CourierWeb::TIPOS_RECLAMO)),
            'detalle' => 'exclude_if:consultar,true|required|string|min:10|max:2000', 'pedido' => 'nullable|string|max:255',
            'nombre' => 'exclude_if:consultar,true|required|string|max:150', 'correo' => 'nullable|email|max:120'],
            [], ['verificacion' => 'tu DNI o celular', 'detalle' => 'qué pasó', 'nombre' => 'tu nombre']);
        if (! CourierWeb::esDelCliente($e, $d['verificacion'])) {
            return response()->json(['ok' => false, 'mensaje' => 'El DNI o celular no coincide con el remitente ni con el destinatario de este envío.']);
        }
        if (! empty($d['consultar'])) {
            $lista = DB::table('cou_reclamos')->where('env_id', $e->env_id)->orderByDesc('rec_id')->get(['numero', 'tipo', 'estado', 'respuesta', 'creado', 'resuelto']);

            return response()->json(['ok' => true, 'reclamos' => $lista->map(fn ($r) => ['numero' => $r->numero, 'tipo' => CourierWeb::TIPOS_RECLAMO[$r->tipo] ?? $r->tipo,
                'estado' => CourierWeb::ESTADOS_RECLAMO[$r->estado] ?? $r->estado, 'respuesta' => $r->respuesta, 'fecha' => substr((string) $r->creado, 0, 10)])]);
        }
        $verificado = preg_replace('/\D/', '', $d['verificacion']);
        // 8 u 11 dígitos = DNI o RUC; 9 = celular
        $esCelular = strlen($verificado) === 9;
        $numero = CourierWeb::reclamar($e, $d + ['documento' => $esCelular ? null : $verificado, 'telefono' => $esCelular ? $verificado : null]);

        return response()->json(['ok' => true, 'numero' => $numero]);
    }
}
