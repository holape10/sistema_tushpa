<?php
namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Venta masiva: emite de una vez los comprobantes mensuales de los clientes marcados "Facturación mensual"
 * (cliente.mensual = 1, con su comprobante y monto). El navegador emite uno por uno con barra de progreso:
 * si uno falla (RUC inválido, etc.) los demás siguen. Al final se descargan todos en PDF dentro de un ZIP.
 */
class VentaMasivaController extends Controller
{
    public const CONCEPTO = 'SISTEMA DE FACTURACION ELECTRONICA';
    private const MESES = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SETIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden emitir comprobantes.');
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        if (!Turno::abiertoDe($user)) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de emitir.');
        }

        $fecha = Carbon::parse($request->get('fecha', now()->toDateString()));
        // Comprobantes de venta masiva ya emitidos a cada cliente en el mes de la fecha (para no duplicar)
        $emitidos = DB::table('cpe_cabecera')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('ped_tip', 'MASIVO')
            ->whereYear('ccafem', $fecha->year)->whereMonth('ccafem', $fecha->month)->whereNull('ccabaj')
            ->get(['ccandi', 'serdoc', 'numdoc', 'IdCpe_cabecera'])->groupBy('ccandi');

        $clientes = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('mensual', 1)->where('cliest', 'Activo')
            ->orderBy('clinom')->get(['clicod', 'tdicod', 'clinum', 'clinom', 'clidir', 'comprobante', 'monto'])
            ->map(fn($c) => [
                'id' => $c->clicod, 'tdicod' => $c->tdicod, 'doc' => $c->clinum, 'nombre' => $c->clinom,
                'dir' => $c->clidir && $c->clidir !== '--' ? $c->clidir : '',
                'tdocod' => in_array($c->comprobante, ['01', '03', '13'], true) ? $c->comprobante : '13', 'monto' => (float) $c->monto,
                'emitido' => isset($emitidos[$c->clinum])
                    ? $emitidos[$c->clinum]->map(fn($e) => $e->serdoc . '-' . str_pad($e->numdoc, 8, '0', STR_PAD_LEFT))->implode(', ') : null,
            ]);

        $mes = self::MESES[$fecha->month] . ' ' . $fecha->year;
        $anterior = $fecha->copy()->subMonthNoOverflow();

        return view('empresas.ventas.masiva', [
            'clientes' => $clientes,
            'fecha' => $fecha->toDateString(),
            'concepto' => self::CONCEPTO,
            'meses' => [$mes, self::MESES[$anterior->month] . ' ' . $anterior->year],
            'comprobantes' => DB::table('tipo_documento')->where('caja', 1)->pluck('tdodes', 'tdocod'),
            'estadopagos' => DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']),
        ]);
    }

    /** Emite el comprobante de un cliente (el navegador llama una vez por cliente) */
    public function emitir(Request $request)
    {
        $this->autorizar();
        $d = $request->validate([
            'clicod' => 'required|integer', 'tdocod' => 'required|in:01,03,13', 'monto' => 'required|numeric|min:0.01|max:999999',
            'fecha' => 'required|date', 'concepto' => 'required|string|max:150', 'estadopago' => 'required|integer',
            'fecVen' => 'nullable|date', 'guardar' => 'boolean',
        ], [], ['monto' => 'Monto', 'concepto' => 'Concepto']);
        $user = Auth::user();

        $cli = DB::table('cliente')->where('clicod', $d['clicod'])->where('rucemp', $user->IdEmpresa)->first();
        if (!$cli) {
            return response()->json(['estado' => 'error', 'mensaje' => 'El cliente ya no existe.']);
        }

        try {
            $cabId = VentaDirecta::registrar($user, [
                'tdocod' => $d['tdocod'], 'estadopago' => $d['estadopago'], 'fecEmi' => $d['fecha'], 'fecVen' => $d['fecVen'] ?? null,
                'tdicod' => $cli->tdicod ?: (strlen($cli->clinum) === 11 ? '6' : '1'), 'clinum' => $cli->clinum, 'clinom' => $cli->clinom,
                'clidir' => $cli->clidir, 'clicor' => $cli->clicor, 'telefono' => $cli->telefono,
                'items' => [['descripcion' => mb_strtoupper(trim($d['concepto'])), 'cantidad' => 1, 'precio' => round((float) $d['monto'], 2)]],
            ], 'MASIVO');
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al emitir.']);
        }

        // "Guardar como predeterminado": el próximo mes ya sale con este comprobante y monto
        if (!empty($d['guardar'])) {
            DB::table('cliente')->where('clicod', $cli->clicod)->update(['comprobante' => $d['tdocod'], 'monto' => round((float) $d['monto'], 2)]);
        }

        return response()->json(VentaDirecta::respuesta($cabId) + ['pdf' => route('ventas.masiva.pdf', $cabId)]);
    }

    private function generarPdf(int $id): array
    {
        return \App\Support\Impresion\ComprobantePdf::generar($id, (int) Auth::user()->id_empresa_negocio);
    }

    /** PDF A4 de un comprobante */
    public function pdf($id)
    {
        $this->autorizar();
        [$nombre, $contenido] = $this->generarPdf((int) $id);
        return response($contenido, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $nombre . '"']);
    }

    /** ZIP con el PDF de cada comprobante emitido */
    public function zip(Request $request)
    {
        $this->autorizar();
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->get('ids')))), 0, 500);
        abort_if(!$ids, 404);

        set_time_limit(0);
        $ruta = tempnam(sys_get_temp_dir(), 'masiva');
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        foreach ($ids as $id) {
            [$nombre, $contenido] = $this->generarPdf($id);
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return response()->download($ruta, 'comprobantes_' . now()->format('Ymd_His') . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
