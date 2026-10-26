<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\Impresion\ComprobantePdf;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Venta masiva: emite de una vez los comprobantes mensuales de los clientes marcados "Facturación mensual"
 * (cliente.mensual = 1, con su comprobante y monto). Un cliente puede tener más de un comprobante al mes
 * (ej. uno por sucursal): los otros van en cliente_mensual_extras, cada uno con su detalle ("LOCAL PUNCHANA").
 * El navegador emite por lotes con barra de progreso: si uno falla (RUC inválido, etc.) los demás siguen.
 * Al final se descargan todos en PDF dentro de un ZIP.
 */
class VentaMasivaController extends Controller
{
    public const CONCEPTO = 'SISTEMA DE FACTURACION ELECTRONICA';

    private const MESES = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SETIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    /** Comprobantes por pedido: menos viajes al servidor sin que un lote tarde demasiado */
    public const POR_LOTE = 5;

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden emitir comprobantes.');
    }

    /** Descripción del detalle: el concepto del mes y, si tiene, el detalle de la sucursal */
    private static function descripcion(string $concepto, ?string $etiqueta): string
    {
        $etiqueta = mb_strtoupper(trim((string) $etiqueta));

        return mb_substr(mb_strtoupper(trim($concepto)).($etiqueta !== '' ? ' - '.$etiqueta : ''), 0, 250);
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        if (! Turno::abiertoDe($user)) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de emitir.');
        }

        $fecha = Carbon::parse($request->get('fecha', now()->toDateString()));
        $clientes = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('mensual', 1)->where('cliest', 'Activo')
            ->orderBy('clinom')->get(['clicod', 'tdicod', 'clinum', 'clinom', 'clidir', 'comprobante', 'monto', 'etiqueta_mensual']);
        $extras = DB::table('cliente_mensual_extras')->whereIn('clicod', $clientes->pluck('clicod'))->orderBy('id')->get()->groupBy('clicod');

        // Comprobantes de venta masiva ya emitidos a cada cliente en el mes de la fecha (para no duplicar), con su descripción
        $emitidos = DB::table('cpe_cabecera')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('ped_tip', 'MASIVO')
            ->whereYear('ccafem', $fecha->year)->whereMonth('ccafem', $fecha->month)->whereNull('ccabaj')
            ->orderBy('IdCpe_cabecera')->get(['ccandi', 'serdoc', 'numdoc', 'IdCpe_cabecera']);
        $detalles = DB::table('cpe_detalle')->whereIn('IdCpe_cabecera', $emitidos->pluck('IdCpe_cabecera'))
            ->orderBy('IdCpe_detalle')->get(['IdCpe_cabecera', 'cdedes'])->unique('IdCpe_cabecera')->pluck('cdedes', 'IdCpe_cabecera');
        $emitidos = $emitidos->groupBy('ccandi');

        $filas = [];
        foreach ($clientes as $c) {
            $base = [
                'clicod' => $c->clicod, 'tdicod' => $c->tdicod, 'doc' => $c->clinum, 'nombre' => $c->clinom,
                'dir' => $c->clidir && $c->clidir !== '--' ? $c->clidir : '',
            ];
            $tipo = fn ($t) => in_array($t, ['01', '03', '13'], true) ? $t : '13';
            $delCliente = [$base + ['key' => 'c'.$c->clicod, 'extra' => null, 'etiqueta' => (string) $c->etiqueta_mensual, 'tdocod' => $tipo($c->comprobante), 'monto' => (float) $c->monto]];
            foreach ($extras[$c->clicod] ?? [] as $e) {
                $delCliente[] = $base + ['key' => 'e'.$e->id, 'extra' => $e->id, 'etiqueta' => (string) $e->etiqueta, 'tdocod' => $tipo($e->comprobante), 'monto' => (float) $e->monto];
            }

            // Qué comprobante ya emitido corresponde a cada fila: primero por el detalle de la sucursal, el resto en orden
            $pendientes = collect($emitidos[$c->clinum] ?? []);
            foreach ($delCliente as $i => $fila) {
                $sufijo = $fila['etiqueta'] !== '' ? ' - '.mb_strtoupper(trim($fila['etiqueta'])) : null;
                $hallado = $sufijo ? $pendientes->search(fn ($e) => str_ends_with((string) ($detalles[$e->IdCpe_cabecera] ?? ''), $sufijo)) : false;
                if ($hallado !== false) {
                    $delCliente[$i]['emitido'] = $pendientes->pull($hallado);
                }
            }
            foreach ($delCliente as $i => $fila) {
                $delCliente[$i]['emitido'] ??= $pendientes->shift();
                $e = $delCliente[$i]['emitido'];
                $delCliente[$i]['emitido'] = $e ? $e->serdoc.'-'.str_pad($e->numdoc, 8, '0', STR_PAD_LEFT) : null;
            }
            array_push($filas, ...$delCliente);
        }

        $mes = self::MESES[$fecha->month].' '.$fecha->year;
        $anterior = $fecha->copy()->subMonthNoOverflow();

        return view('empresas.ventas.masiva', [
            'filas' => $filas,
            'fecha' => $fecha->toDateString(),
            'concepto' => self::CONCEPTO,
            'meses' => [$mes, self::MESES[$anterior->month].' '.$anterior->year],
            'comprobantes' => DB::table('tipo_documento')->where('caja', 1)->pluck('tdodes', 'tdocod'),
            'estadopagos' => DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']),
        ]);
    }

    /** Emite un lote de comprobantes (el navegador manda varios por pedido); cada uno responde por separado */
    public function emitir(Request $request)
    {
        $this->autorizar();
        $d = $request->validate([
            'lineas' => 'required|array|min:1|max:'.self::POR_LOTE,
            'lineas.*.key' => 'required|string|max:20', 'lineas.*.clicod' => 'required|integer', 'lineas.*.extra' => 'nullable|integer',
            'lineas.*.etiqueta' => 'nullable|string|max:80', 'lineas.*.tdocod' => 'required|in:01,03,13',
            'lineas.*.monto' => 'required|numeric|min:0.01|max:999999', 'lineas.*.guardar' => 'boolean',
            'fecha' => 'required|date', 'concepto' => 'required|string|max:150', 'estadopago' => 'required|integer',
            'fecVen' => 'nullable|date',
        ], [], ['lineas.*.monto' => 'Monto', 'concepto' => 'Concepto']);
        $user = Auth::user();

        $clientes = DB::table('cliente')->whereIn('clicod', array_column($d['lineas'], 'clicod'))->where('rucemp', $user->IdEmpresa)->get()->keyBy('clicod');
        $resultados = [];
        foreach ($d['lineas'] as $l) {
            $resultados[$l['key']] = $this->emitirUno($user, $d, $l, $clientes[$l['clicod']] ?? null);
        }

        return response()->json(['resultados' => $resultados]);
    }

    private function emitirUno($user, array $d, array $l, ?object $cli): array
    {
        if (! $cli) {
            return ['estado' => 'error', 'mensaje' => 'El cliente ya no existe.'];
        }
        $monto = round((float) $l['monto'], 2);
        try {
            $cabId = VentaDirecta::registrar($user, [
                'tdocod' => $l['tdocod'], 'estadopago' => $d['estadopago'], 'fecEmi' => $d['fecha'], 'fecVen' => $d['fecVen'] ?? null,
                'tdicod' => $cli->tdicod ?: (strlen($cli->clinum) === 11 ? '6' : '1'), 'clinum' => $cli->clinum, 'clinom' => $cli->clinom,
                'clidir' => $cli->clidir, 'clicor' => $cli->clicor, 'telefono' => $cli->telefono,
                'items' => [['descripcion' => self::descripcion($d['concepto'], $l['etiqueta'] ?? null), 'cantidad' => 1, 'precio' => $monto]],
            ], 'MASIVO');
        } catch (\RuntimeException $e) {
            return ['estado' => 'error', 'mensaje' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al emitir.'];
        }

        // "Guardar como predeterminado": el próximo mes ya sale con este comprobante, monto y detalle
        if (! empty($l['guardar'])) {
            $cambios = ['comprobante' => $l['tdocod'], 'monto' => $monto];
            $etiqueta = mb_strtoupper(trim((string) ($l['etiqueta'] ?? ''))) ?: null;
            empty($l['extra'])
                ? DB::table('cliente')->where('clicod', $cli->clicod)->update($cambios + ['etiqueta_mensual' => $etiqueta])
                : DB::table('cliente_mensual_extras')->where('id', $l['extra'])->where('clicod', $cli->clicod)->update($cambios + ['etiqueta' => $etiqueta, 'updated_at' => now()]);
        }

        return VentaDirecta::respuesta($cabId) + ['pdf' => route('ventas.masiva.pdf', $cabId)];
    }

    /** Agrega otro comprobante mensual a un cliente (ej. su segunda sucursal) */
    public function extraAgregar(Request $request)
    {
        $this->autorizar();
        $d = $request->validate([
            'clicod' => 'required|integer', 'etiqueta' => 'nullable|string|max:80', 'tdocod' => 'required|in:01,03,13',
            'monto' => 'required|numeric|min:0|max:999999',
        ]);
        abort_unless(DB::table('cliente')->where('clicod', $d['clicod'])->where('rucemp', Auth::user()->IdEmpresa)->exists(), 404);

        $id = DB::table('cliente_mensual_extras')->insertGetId([
            'clicod' => $d['clicod'], 'etiqueta' => mb_strtoupper(trim((string) ($d['etiqueta'] ?? ''))) ?: null,
            'comprobante' => $d['tdocod'], 'monto' => round((float) $d['monto'], 2), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['id' => $id]);
    }

    /** Quita un comprobante mensual extra (los ya emitidos no se tocan) */
    public function extraQuitar(int $id)
    {
        $this->autorizar();
        DB::table('cliente_mensual_extras')->where('id', $id)
            ->whereIn('clicod', DB::table('cliente')->where('rucemp', Auth::user()->IdEmpresa)->select('clicod'))->delete();

        return response()->json(['ok' => true]);
    }

    private function generarPdf(int $id): array
    {
        return ComprobantePdf::generar($id, (int) Auth::user()->id_empresa_negocio);
    }

    /** PDF A4 de un comprobante */
    public function pdf($id)
    {
        $this->autorizar();
        [$nombre, $contenido] = $this->generarPdf((int) $id);

        return response($contenido, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    /** ZIP con el PDF de cada comprobante emitido */
    public function zip(Request $request)
    {
        $this->autorizar();
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->get('ids')))), 0, 500);
        abort_if(! $ids, 404);

        set_time_limit(0);
        $ruta = tempnam(sys_get_temp_dir(), 'masiva');
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        foreach ($ids as $id) {
            [$nombre, $contenido] = $this->generarPdf($id);
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return response()->download($ruta, 'comprobantes_'.now()->format('Ymd_His').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
