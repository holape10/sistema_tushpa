<?php

namespace App\Http\Controllers;

use App\Support\ConsultaPeru;
use App\Support\Sunat\GuiaRemision;
use App\Support\Ubigeo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Guías de remisión electrónicas (remitente).
 *  - Desde una venta (Panel de ventas > ⋮ > Guía de remisión): trae el cliente, la dirección y los productos.
 *  - Sola, desde el menú Guías Remisión: se llenan los bienes a mano o buscando productos.
 * Los datos del transporte (conductor, placa, transportista) se proponen de la última guía para no escribirlos cada vez.
 */
class GuiaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja emiten guías de remisión.');
    }

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function guia(int $id): object
    {
        $g = DB::table('gre_cabecera')->where('gre_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($g, 404);

        return $g;
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $desde = $request->get('desde', now()->subDays(30)->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $estado = $request->get('estado', '');
        $q = trim((string) $request->get('q'));

        $guias = DB::table('gre_cabecera')->where('id_empresa_negocio', $this->sucursal())
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->when($estado, fn ($w) => $w->where('est_sunat', $estado))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('dest_nom', 'like', "%{$q}%")->orWhere('dest_num', 'like', "{$q}%")
                ->orWhere('doc_numero', 'like', "%{$q}%")->orWhere('placa', 'like', "%{$q}%")))
            ->orderByDesc('gre_id')->paginate(50)->withQueryString();

        return view('empresas.guias.index', compact('guias', 'desde', 'hasta', 'estado', 'q') + [
            'motivos' => GuiaRemision::MOTIVOS,
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(['produccion', 'client_id']),
        ]);
    }

    /** Formulario: vacío o con los datos de una venta (?venta=ID) */
    public function crear(Request $request)
    {
        $this->autorizar();
        $suc = DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->first();
        $ultima = DB::table('gre_cabecera')->where('id_empresa_negocio', $suc->id_empresa_negocio)->orderByDesc('gre_id')->first();

        $guia = [
            'fecha_traslado' => now()->toDateString(), 'motivo' => '01', 'motivo_desc' => '', 'modalidad' => $ultima->modalidad ?? '02',
            'peso' => null, 'unidad_peso' => 'KGM', 'bultos' => null,
            'dest_tdicod' => '6', 'dest_num' => '', 'dest_nom' => '',
            'partida_ubigeo' => (string) $suc->ubigeo, 'partida_direccion' => (string) $suc->direccion, 'partida_codlocal' => '',
            'llegada_ubigeo' => '', 'llegada_direccion' => '', 'llegada_codlocal' => '',
            // Transporte: se repite el de la última guía (casi siempre es el mismo carro y chofer)
            'transp_ruc' => $ultima->transp_ruc ?? '', 'transp_nom' => $ultima->transp_nom ?? '', 'transp_mtc' => $ultima->transp_mtc ?? '',
            'cond_tdicod' => $ultima->cond_tdicod ?? '1', 'cond_num' => $ultima->cond_num ?? '', 'cond_nombres' => $ultima->cond_nombres ?? '',
            'cond_apellidos' => $ultima->cond_apellidos ?? '', 'cond_licencia' => $ultima->cond_licencia ?? '', 'placa' => $ultima->placa ?? '',
            'vehiculo_m1l' => (bool) ($ultima->vehiculo_m1l ?? false),
            'doc_tdocod' => '', 'doc_numero' => '', 'IdCpe_cabecera' => null, 'observacion' => '',
        ];
        $items = [];

        if ($ventaId = (int) $request->get('venta')) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $ventaId)->where('id_empresa_negocio', $suc->id_empresa_negocio)->first();
            abort_unless($cab, 404);
            $guia = array_merge($guia, [
                'dest_tdicod' => (string) $cab->tdicod, 'dest_num' => (string) $cab->ccandi, 'dest_nom' => (string) $cab->ccanom,
                'llegada_direccion' => $cab->direccion && $cab->direccion !== '--' ? $cab->direccion : '',
                'doc_tdocod' => in_array($cab->tdocod, ['01', '03'], true) ? $cab->tdocod : '',
                'doc_numero' => in_array($cab->tdocod, ['01', '03'], true) ? $cab->serdoc.'-'.(int) $cab->numdoc : '',
                'IdCpe_cabecera' => $cab->IdCpe_cabecera,
            ]);
            if ($cab->id_almacen && ($alm = DB::table('almacenes')->where('id_almacen', $cab->id_almacen)->first()) && $alm->ubigeo && $alm->direccion) {
                $guia['partida_ubigeo'] = $alm->ubigeo;
                $guia['partida_direccion'] = $alm->direccion;
            }
            // Llegada: la de la última guía de este cliente, o la del padrón SUNAT si es RUC
            if ($previa = DB::table('gre_cabecera')->where('dest_num', $cab->ccandi)->orderByDesc('gre_id')->first()) {
                $guia['llegada_ubigeo'] = $previa->llegada_ubigeo;
                $guia['llegada_direccion'] = $previa->llegada_direccion;
            }
            $items = DB::table('cpe_detalle as d')->leftJoin('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
                ->where('d.IdCpe_cabecera', $cab->IdCpe_cabecera)->orderBy('d.IdCpe_detalle')
                ->get(['d.IdProducto', 'd.procod', 'd.cdedes', 'd.umecod', 'd.cdecan', 'p.umecod as ume_producto'])
                ->map(fn ($d) => ['IdProducto' => $d->IdProducto, 'codigo' => $d->procod, 'descripcion' => $d->cdedes,
                    'umecod' => $d->umecod ?: ($d->ume_producto ?: 'NIU'), 'cantidad' => (float) $d->cdecan])->values()->all();
        }

        return view('empresas.guias.crear', [
            'guia' => $guia, 'items' => $items, 'venta' => $ventaId ?: null,
            'serie' => $suc->SerGuia ?: 'T001', 'siguiente' => (int) $suc->NumGuia + 1,
            'motivos' => GuiaRemision::MOTIVOS, 'modalidades' => GuiaRemision::MODALIDADES,
            'unidades' => DB::table('unidad_medida')->where('umeest', 'Activo')->orderBy('umenom')->get(['umecod', 'umenom']),
            'almacenes' => DB::table('almacenes')->where('id_empresa_negocio', $suc->id_empresa_negocio)->whereNotNull('ubigeo')->get(['id_almacen', 'descripcion', 'ubigeo', 'direccion', 'codigo']),
            'yaTieneGuias' => $ventaId ? DB::table('gre_cabecera')->where('IdCpe_cabecera', $ventaId)->whereIn('est_sunat', ['ACEPTADO', 'ENVIADO', 'PENDIENTE'])
                ->get(['serie', 'numero', 'est_sunat']) : collect(),
        ]);
    }

    /** Distritos por nombre ("iquitos", "miraflores lima") o por código */
    public function ubigeos(Request $request)
    {
        return response()->json(Ubigeo::buscar((string) $request->get('q')));
    }

    /** Productos para agregar a una guía sin venta */
    public function productos(Request $request)
    {
        $this->autorizar();
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(DB::table('productos')->where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')
            ->where(fn ($w) => $w->where('pronom', 'like', "%{$q}%")->orWhere('procod', $q)->orWhere('codigo_barra', $q))
            ->orderBy('pronom')->limit(15)->get(['IdProducto', 'procod', 'pronom', 'umecod']));
    }

    /** Destinatario, transportista o conductor: nombre (y dirección/ubigeo del padrón SUNAT si es RUC) */
    public function documento(string $doc)
    {
        $this->autorizar();
        $doc = preg_replace('/\D/', '', $doc);
        $previa = DB::table('gre_cabecera')->where('id_empresa_negocio', $this->sucursal())->where('dest_num', $doc)->orderByDesc('gre_id')->first();
        $chofer = DB::table('gre_cabecera')->where('id_empresa_negocio', $this->sucursal())->where('cond_num', $doc)->orderByDesc('gre_id')->first();
        $r = strlen($doc) === 11 ? ConsultaPeru::ruc($doc) : (strlen($doc) === 8 ? ConsultaPeru::dni($doc) : null);
        $cli = DB::table('cliente')->where('clinum', $doc)->where('rucemp', Auth::user()->IdEmpresa)->first();

        return response()->json([
            'nom' => $r['nombre'] ?? $cli->clinom ?? $previa->dest_nom ?? null,
            'dir' => $previa->llegada_direccion ?? ($r['direccion'] ?? null) ?: ($cli && $cli->clidir !== '--' ? $cli->clidir : null),
            'ubigeo' => $previa->llegada_ubigeo ?? ($r['ubigeo'] ?? null),
            'conductor' => $chofer ? ['nombres' => $chofer->cond_nombres, 'apellidos' => $chofer->cond_apellidos, 'licencia' => $chofer->cond_licencia] : null,
        ]);
    }

    public function guardar(Request $request)
    {
        $this->autorizar();
        $publico = $request->input('modalidad') === '01';
        $privadoConConductor = ! $publico && ! $request->boolean('vehiculo_m1l');
        $d = $request->validate([
            'fecha_traslado' => 'required|date|after_or_equal:today',
            'motivo' => ['required', Rule::in(array_keys(GuiaRemision::MOTIVOS))],
            'motivo_desc' => 'nullable|required_if:motivo,13|string|max:100',
            'modalidad' => 'required|in:01,02',
            'peso' => 'required|numeric|min:0.001|max:9999999',
            'unidad_peso' => 'required|in:KGM,TNE',
            'bultos' => 'nullable|integer|min:1|max:999999',
            'dest_tdicod' => 'required|in:1,4,6,7,0',
            'dest_num' => 'required|string|max:15',
            'dest_nom' => 'required|string|min:3|max:150',
            'partida_ubigeo' => 'required|digits:6', 'partida_direccion' => 'required|string|min:5|max:200', 'partida_codlocal' => 'nullable|digits_between:1,4',
            'llegada_ubigeo' => 'required|digits:6', 'llegada_direccion' => 'required|string|min:5|max:200', 'llegada_codlocal' => 'nullable|digits_between:1,4',
            'transp_ruc' => [$publico ? 'required' : 'nullable', 'digits:11'],
            'transp_nom' => [$publico ? 'required' : 'nullable', 'string', 'max:150'],
            'transp_mtc' => 'nullable|string|max:20',
            'vehiculo_m1l' => 'nullable|boolean',
            'cond_tdicod' => [$privadoConConductor ? 'required' : 'nullable', 'in:1,4,7'],
            'cond_num' => [$privadoConConductor ? 'required' : 'nullable', 'string', 'max:15'],
            'cond_nombres' => [$privadoConConductor ? 'required' : 'nullable', 'string', 'max:100'],
            'cond_apellidos' => [$privadoConConductor ? 'required' : 'nullable', 'string', 'max:100'],
            'cond_licencia' => [$privadoConConductor ? 'required' : 'nullable', 'string', 'min:9', 'max:10'],
            'placa' => [$privadoConConductor ? 'required' : 'nullable', 'string', 'min:6', 'max:8'],
            'doc_tdocod' => 'nullable|in:01,03,09,12', 'doc_numero' => 'nullable|required_with:doc_tdocod|string|max:15',
            'IdCpe_cabecera' => 'nullable|integer',
            'observacion' => 'nullable|string|max:250',
            'items' => 'required|array|min:1|max:500',
            'items.*.IdProducto' => 'nullable|integer', 'items.*.codigo' => 'nullable|string|max:30',
            'items.*.descripcion' => 'required|string|min:2|max:250', 'items.*.umecod' => 'nullable|string|max:3',
            'items.*.cantidad' => 'required|numeric|min:0.001|max:99999999',
            'enviar' => 'nullable|boolean',
        ], [
            'fecha_traslado.after_or_equal' => 'La fecha de traslado no puede ser anterior a hoy.',
            'cond_licencia.min' => 'La licencia de conducir tiene 9 o 10 caracteres (ej. Q12345678).',
            'placa.min' => 'Escribe la placa completa (6 caracteres, sin guion).',
            'motivo_desc.required_if' => 'Describe el motivo cuando eliges OTROS.',
            'items.required' => 'Agrega al menos un producto a la guía.',
        ], [
            'peso' => 'el peso total', 'dest_num' => 'el documento del destinatario', 'dest_nom' => 'el nombre del destinatario',
            'partida_ubigeo' => 'la ciudad o distrito de partida', 'partida_direccion' => 'la dirección de partida',
            'llegada_ubigeo' => 'la ciudad o distrito de llegada', 'llegada_direccion' => 'la dirección de llegada',
            'transp_ruc' => 'el RUC del transportista', 'transp_nom' => 'la razón social del transportista',
            'cond_num' => 'el DNI del conductor', 'cond_nombres' => 'los nombres del conductor', 'cond_apellidos' => 'los apellidos del conductor',
            'cond_licencia' => 'la licencia de conducir', 'placa' => 'la placa del vehículo', 'items.*.descripcion' => 'descripción', 'items.*.cantidad' => 'cantidad',
        ]);

        if ($d['dest_tdicod'] === '6' && ! preg_match('/^\d{11}$/', $d['dest_num'])) {
            return response()->json(['ok' => false, 'mensaje' => 'El RUC del destinatario debe tener 11 dígitos.']);
        }
        if ($d['dest_tdicod'] === '1' && ! preg_match('/^\d{8}$/', $d['dest_num'])) {
            return response()->json(['ok' => false, 'mensaje' => 'El DNI del destinatario debe tener 8 dígitos.']);
        }
        if (! empty($d['IdCpe_cabecera']) && ! DB::table('cpe_cabecera')->where('IdCpe_cabecera', $d['IdCpe_cabecera'])->where('id_empresa_negocio', $this->sucursal())->exists()) {
            $d['IdCpe_cabecera'] = null;
        }

        $id = GuiaRemision::registrar(Auth::user(), $d, $d['items']);
        $g = DB::table('gre_cabecera')->where('gre_id', $id)->first();
        $numero = $g->serie.'-'.$g->numero;

        if (empty($d['enviar'])) {
            return response()->json(['ok' => true, 'id' => $id, 'estado' => 'PENDIENTE', 'mensaje' => "Guía {$numero} guardada (pendiente de envío)."]);
        }
        $r = $this->intentar(fn () => GuiaRemision::paraUsuario(Auth::user())->enviar($id));

        return response()->json(['ok' => true, 'id' => $id, 'estado' => $r['estado'] ?? 'PENDIENTE', 'enviada' => $r['ok'],
            'mensaje' => "Guía {$numero}: ".$r['mensaje']]);
    }

    /** @return array{ok: bool, estado?: string, mensaje: string} */
    private function intentar(callable $accion): array
    {
        try {
            return $accion();
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'estado' => 'ERROR', 'mensaje' => 'Error al enviar: '.$e->getMessage()];
        }
    }

    public function enviar(int $id)
    {
        $this->autorizar();
        $this->guia($id);
        $r = $this->intentar(fn () => GuiaRemision::paraUsuario(Auth::user())->enviar($id));

        return response()->json($r + ['success' => $r['ok']]);
    }

    public function consultar(int $id)
    {
        $this->autorizar();
        $this->guia($id);
        $r = $this->intentar(fn () => GuiaRemision::paraUsuario(Auth::user())->consultar($id));

        return response()->json($r + ['success' => $r['ok']]);
    }

    /** @return array<string, mixed> */
    private function datosImpresion(object $g): array
    {
        return [
            'g' => $g, 'detalle' => DB::table('gre_detalle')->where('gre_id', $g->gre_id)->orderBy('det_id')->get(),
            'empresa' => DB::table('empresa')->where('IdEmpresa', $g->IdEmpresa)->first(),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $g->id_empresa_negocio)->first(),
            'motivos' => GuiaRemision::MOTIVOS,
        ];
    }

    /** Representación impresa A4 (mismo diseño que el comprobante), siempre con QR */
    public function imprimir(int $id)
    {
        $this->autorizar();

        return view('empresas.guias.imprimir', $this->datosImpresion($this->guia($id)));
    }

    public function pdf(int $id)
    {
        $this->autorizar();
        $g = $this->guia($id);
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->setChroot(public_path());
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.guias.pdf', $this->datosImpresion($g))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $nombre = 'GUIA '.$g->serie.'-'.str_pad($g->numero, 8, '0', STR_PAD_LEFT).'.pdf';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    /** Lo abre el QR de la guía impresa (sin sesión): datos del traslado y estado en SUNAT */
    public function verificar(string $token)
    {
        $g = DB::table('gre_cabecera')->where('token', $token)->first();
        abort_unless($g, 404);

        return view('empresas.guias.verificar', $this->datosImpresion($g));
    }

    public function archivo(int $id, string $tipo)
    {
        $this->autorizar();
        abort_unless(in_array($tipo, ['xml', 'cdr'], true), 404);
        $g = $this->guia($id);
        $ruta = GuiaRemision::paraUsuario(Auth::user())->archivo($g, $tipo);
        abort_unless(is_file($ruta), 404, 'El archivo aún no existe (envía la guía primero).');

        return response()->download($ruta);
    }

    /** Una guía que nunca se envió (o que SUNAT rechazó) se puede borrar del sistema */
    public function eliminar(int $id)
    {
        $this->autorizar();
        $g = $this->guia($id);
        if (! in_array($g->est_sunat, ['PENDIENTE', 'RECHAZADO', 'ERROR'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Una guía enviada o aceptada por SUNAT no se puede borrar; se da de baja en SUNAT Operaciones en Línea.']);
        }
        DB::transaction(function () use ($id) {
            DB::table('gre_detalle')->where('gre_id', $id)->delete();
            DB::table('gre_cabecera')->where('gre_id', $id)->delete();
        });

        return response()->json(['ok' => true, 'mensaje' => 'Guía eliminada.']);
    }
}
