<?php

namespace App\Http\Controllers;

use App\Support\Concar;
use App\Support\Contabilidad\SireARegistro;
use App\Support\Contabilidad\SireDbf;
use App\Support\Contabilidad\SireManuales;
use App\Support\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SIRE → sistema contable en FoxPro: el contador revisa la propuesta de ventas y compras (cuentas, montos y cuáles no
 * se llevan), lo guarda y descarga tienfact.dbf (ventas) y almaingr.dbf (compras).
 */
class SireDbfController extends Controller
{
    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->contadorCon('/sire/ventas', '/sire/compras'), 403, 'Solo el Administrador o el contador pueden usar el SIRE.');

        return Auth::user()->IdEmpresa;
    }

    private function periodo(Request $request): string
    {
        return preg_match('/^\d{6}$/', (string) $request->input('periodo')) ? $request->input('periodo') : now()->subMonth()->format('Ym');
    }

    private function libro(Request $request): string
    {
        return $request->input('libro') === 'ventas' ? 'ventas' : 'compras';
    }

    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $libro = $this->libro($request);
        $periodo = $this->periodo($request);
        $datos = SireDbf::delPeriodo($ruc, $libro, $periodo);

        return view('empresas.sire.dbf', $datos + [
            'libro' => $libro, 'periodo' => $periodo, 'totales' => SireDbf::totales($libro, $datos['comprobantes']), 'cfg' => Concar::config($ruc),
        ]);
    }

    /** Busca el comprobante en la propuesta (así no se guarda nada que no esté en ella) */
    private function comprobante(string $ruc, string $libro, string $periodo, string $clave): array
    {
        $c = collect(SireDbf::delPeriodo($ruc, $libro, $periodo)['comprobantes'])->firstWhere('clave', $clave);
        abort_unless($c, 404, 'Ese comprobante no está en la propuesta del periodo.');

        return $c;
    }

    /** Guarda lo que se cambió de un comprobante: si se lleva, sus cuentas o sus montos */
    public function guardar(Request $request): JsonResponse
    {
        $ruc = $this->autorizar();
        $cuenta = ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Za-z]*$/'];
        $d = $request->validate([
            'libro' => ['required', Rule::in(array_keys(SireDbf::LIBROS))], 'periodo' => 'required|digits:6', 'clave' => 'required|string|max:40',
            'excluido' => 'sometimes|boolean', 'ctacargo' => $cuenta, 'ctaabono' => $cuenta, 'ccosto' => 'nullable|string|max:6|regex:/^[0-9A-Za-z]*$/',
            'montos' => 'sometimes|nullable|array', 'montos.*' => 'nullable|numeric|between:-99999999,99999999',
        ], ['regex' => 'La cuenta va solo con números (sin puntos ni espacios).']);
        $c = $this->comprobante($ruc, $d['libro'], $d['periodo'], $d['clave']);

        $cambios = array_intersect_key($d, array_flip(['excluido', 'ctacargo', 'ctaabono', 'ccosto']));
        if ($request->has('montos')) {
            $cambios['montos'] = $d['montos'] ? $this->montos($d['libro'], $d['montos']) : null;
        }
        SireDbf::guardar($ruc, $d['libro'], $d['periodo'], $d['clave'], $c['ruc'], $cambios);
        $c = $this->comprobante($ruc, $d['libro'], $d['periodo'], $d['clave']);

        return response()->json(['ok' => true, 'diferencia' => $c['diferencia'], 'alerta' => $c['alerta'] ?? null, 'montos' => $c['montos'], 'editado' => $c['editado'],
            'totales' => SireDbf::totales($d['libro'], SireDbf::delPeriodo($ruc, $d['libro'], $d['periodo'])['comprobantes'])]);
    }

    /**
     * Los montos que escribió el contador. En compras, la base y el IGV de cada destino (IGV_1 gravadas, IGV_2 mixtas,
     * IGV_3 no gravadas) y el ISC dentro de "otros".
     *
     * @return array<string, float>
     */
    private function montos(string $libro, array $m): array
    {
        $claves = $libro === 'ventas'
            ? ['exporta', 'gravada', 'igv', 'exonerada', 'inafecta', 'isc', 'icbper', 'otros', 'total']
            : SireDbf::MONTOS_COMPRA;

        return collect($claves)->mapWithKeys(fn ($k) => [$k => round((float) ($m[$k] ?? 0), 2)])->all();
    }

    /** Las mismas cuentas a todos los comprobantes del periodo que se llevan (por ejemplo, todas las ventas 1212 / 70001) */
    public function aplicar(Request $request): RedirectResponse
    {
        $ruc = $this->autorizar();
        $cuenta = ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Za-z]*$/'];
        $d = $request->validate(['libro' => ['required', Rule::in(array_keys(SireDbf::LIBROS))], 'periodo' => 'required|digits:6',
            'ctacargo' => $cuenta, 'ctaabono' => $cuenta], ['regex' => 'La cuenta va solo con números (sin puntos ni espacios).']);
        $cambios = array_filter(array_intersect_key($d, array_flip(['ctacargo', 'ctaabono'])), fn ($v) => trim((string) $v) !== '');
        if (! $cambios) {
            return back()->with('error', 'Escribe la cuenta de cargo, la de abono o las dos.');
        }
        $n = 0;
        foreach (SireDbf::delPeriodo($ruc, $d['libro'], $d['periodo'])['comprobantes'] as $c) {
            if (! $c['excluido']) {
                SireDbf::guardar($ruc, $d['libro'], $d['periodo'], $c['clave'], $c['ruc'], $cambios);
                $n++;
            }
        }

        return back()->with('success', "Cuentas puestas a {$n} comprobante(s).");
    }

    /** ¿Puede pasar el SIRE al Registro de Ventas / Compras de Contabilidad? */
    public static function puedeRegistrar(string $libro): bool
    {
        return Auth::user()->esAdmin() || Auth::user()->tieneModulo('/contabilidad/registro/'.$libro);
    }

    /** Pasa lo revisado al Registro de Ventas o Compras de Contabilidad (con su asiento), sin digitar de nuevo */
    public function registrar(Request $request, string $libro): RedirectResponse
    {
        $ruc = $this->autorizar();
        abort_unless(isset(SireDbf::LIBROS[$libro]), 404);
        abort_unless(self::puedeRegistrar($libro), 403, 'No tienes acceso al registro contable.');
        $periodo = $this->periodo($request);
        $comprobantes = SireDbf::delPeriodo($ruc, $libro, $periodo)['comprobantes'];
        $totales = SireDbf::totales($libro, $comprobantes);
        if (! $totales['registros']) {
            return back()->with('error', 'No hay comprobantes para pasar al registro en ese periodo.');
        }
        if ($totales['descuadrados']) {
            return back()->with('error', "Hay {$totales['descuadrados']} comprobante(s) que no cuadran o tienen el IGV en otra casilla: corrígelos o no los lleves antes de pasarlos al registro.");
        }
        set_time_limit(300);
        $r = SireARegistro::importar($ruc, $libro, $periodo, $comprobantes);

        $partes = ["✔ {$r['nuevos']} comprobante(s) pasados al Registro de ".($libro === 'ventas' ? 'Ventas' : 'Compras').' con su asiento'];
        if ($r['repetidos']) {
            $partes[] = "{$r['repetidos']} ya estaban registrados";
        }
        if ($r['cuentaDefecto']) {
            $partes[] = "{$r['cuentaDefecto']} con la cuenta por defecto (su cuenta no está en el plan contable)";
        }
        $mensaje = implode(' · ', $partes).'.';

        return $r['errores']
            ? back()->with('error', $mensaje.' No se pudieron pasar: '.implode(' | ', array_slice($r['errores'], 0, 5)))
            : back()->with('success', $mensaje);
    }

    /** Lo mismo que va al .dbf, en Excel: un comprobante por fila con sus montos y cuentas, y los totales al final */
    public function excel(Request $request, string $libro)
    {
        $ruc = $this->autorizar();
        abort_unless(isset(SireDbf::LIBROS[$libro]), 404);
        $periodo = $this->periodo($request);
        $comprobantes = array_values(array_filter(SireDbf::delPeriodo($ruc, $libro, $periodo)['comprobantes'], fn ($c) => ! $c['excluido']));
        if (! $comprobantes) {
            return back()->with('error', 'No hay comprobantes para descargar en ese periodo.');
        }
        $montos = $libro === 'compras'
            ? ['base_dg' => 'B.I. gravadas', 'igv_dg' => 'IGV 1', 'base_dgng' => 'B.I. gravadas y no grav.', 'igv_dgng' => 'IGV 2',
                'base_dng' => 'B.I. no gravadas', 'igv_dng' => 'IGV 3', 'no_gravado' => 'Valor no gravado', 'isc' => 'ISC', 'otros' => 'Otros tributos', 'total' => 'Total']
            : ['exporta' => 'Exportación', 'gravada' => 'Gravada', 'igv' => 'IGV', 'exonerada' => 'Exonerada', 'inafecta' => 'Inafecta',
                'isc' => 'ISC', 'icbper' => 'ICBPER', 'otros' => 'Otros tributos', 'total' => 'Total'];
        $cabecera = array_merge(['Fecha', 'Tipo', 'Serie', 'Número', $libro === 'compras' ? 'RUC proveedor' : 'Doc. cliente', $libro === 'compras' ? 'Proveedor' : 'Cliente'],
            array_values($montos), ['Moneda', 'T.C.', 'Cta. cargo', 'Cta. abono', 'Doc. que modifica']);
        $filas = [];
        foreach ($comprobantes as $c) {
            $filas[] = array_merge([$c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '', $c['tipo'], $c['serie'], $c['numero'], $c['ruc'], $c['nombre']],
                array_map(fn ($k) => (float) ($c['montos'][$k] ?? 0), array_keys($montos)),
                [$c['moneda'], $c['tc'], (string) $c['ctacargo'], (string) $c['ctaabono'], $c['ref'] ? $c['ref']['serie'].'-'.$c['ref']['numero'] : '']);
        }
        $filas[] = array_merge(['TOTAL', '', '', '', '', count($comprobantes).' comprobante(s)'],
            array_map(fn ($k) => round(array_sum(array_map(fn ($c) => (float) ($c['montos'][$k] ?? 0), $comprobantes)), 2), array_keys($montos)), ['', '', '', '', '']);
        $tabla = $libro === 'ventas' ? 'tienfact' : 'almaingr';
        $ruta = (new Excel)->hoja(ucfirst($libro).' '.$periodo, $cabecera, $filas, range(6, 5 + count($montos)))->guardar();

        return response()->download($ruta, "{$tabla}_{$ruc}_{$periodo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Agrega a mano un comprobante que SUNAT aún no tiene en la propuesta del mes (el proveedor mandó tarde la factura) */
    public function agregar(Request $request): JsonResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate([
            'libro' => ['required', Rule::in(array_keys(SireDbf::LIBROS))], 'periodo' => 'required|regex:/^\d{6}$/',
            'tipo' => 'required|string|max:2', 'serie' => 'required|string|max:20', 'numero' => 'required|string|max:20',
            'fecha' => 'required|date', 'vence' => 'nullable|date', 'ruc' => 'required|string|max:15', 'nombre' => 'required|string|max:200',
            'moneda' => 'nullable|in:PEN,USD', 'tc' => 'nullable|numeric|min:0|max:99', 'montos' => 'required|array',
            'montos.*' => 'nullable|numeric|min:0|max:99999999', 'ref_tipo' => 'nullable|string|max:2', 'ref_serie' => 'nullable|string|max:20',
            'ref_numero' => 'nullable|string|max:20', 'ref_fecha' => 'nullable|date',
        ], [], ['numero' => 'número', 'nombre' => 'nombre o razón social', 'tc' => 'tipo de cambio']);

        try {
            $id = SireManuales::guardar($ruc, $d['libro'], $d['periodo'], $d, (int) Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /** Borra un comprobante agregado a mano (los de la propuesta de SUNAT no se borran: se desmarca «Llevar») */
    public function quitarManual(int $id): JsonResponse
    {
        $ruc = $this->autorizar();
        abort_unless(SireManuales::eliminar($ruc, $id), 404);

        return response()->json(['ok' => true]);
    }

    /**
     * Reporte para imprimir (PDF horizontal): solo la cabecera de cada comprobante que se lleva al .dbf, con sus montos
     * y cuentas, y los totales. El detalle de los XML no va: sirve solo para elegir las cuentas.
     */
    public function imprimir(Request $request, string $libro): Response|RedirectResponse
    {
        $ruc = $this->autorizar();
        abort_unless(isset(SireDbf::LIBROS[$libro]), 404);
        $periodo = $this->periodo($request);
        $todos = SireDbf::delPeriodo($ruc, $libro, $periodo)['comprobantes'];
        $comprobantes = array_values(array_filter($todos, fn ($c) => ! $c['excluido']));
        if (! $comprobantes) {
            return back()->with('error', 'No hay comprobantes para imprimir en ese periodo.');
        }

        $html = view('empresas.sire.reporte_pdf', [
            'libro' => $libro, 'periodo' => $periodo, 'comprobantes' => $comprobantes, 'excluidos' => count($todos) - count($comprobantes),
            'totales' => SireDbf::totales($libro, $todos), 'ruc' => $ruc,
            'empresa' => DB::table('empresa')->where('IdEmpresa', $ruc)->value('NomEmpresa'),
        ])->render();
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($libro === 'ventas' ? 'registro_ventas' : 'registro_compras')."_{$periodo}.pdf\""]);
    }

    /** tienfact.dbf (ventas) o almaingr.dbf (compras), solo con los comprobantes que se llevan y si todos cuadran */
    public function descargar(Request $request, string $libro)
    {
        $ruc = $this->autorizar();
        abort_unless(isset(SireDbf::LIBROS[$libro]), 404);
        $periodo = $this->periodo($request);
        $comprobantes = SireDbf::delPeriodo($ruc, $libro, $periodo)['comprobantes'];
        $totales = SireDbf::totales($libro, $comprobantes);
        if (! $totales['registros']) {
            return back()->with('error', 'No hay comprobantes para descargar en ese periodo.');
        }
        if ($totales['descuadrados']) {
            return back()->with('error', "Hay {$totales['descuadrados']} comprobante(s) que no cuadran o tienen el IGV en otra casilla: revisa sus montos (están marcados en rojo) o no los lleves.");
        }
        // Las cuentas con que se descargó quedan como las de cada proveedor o cliente para los próximos meses
        foreach ($comprobantes as $c) {
            if (! $c['excluido']) {
                SireDbf::guardar($ruc, $libro, $periodo, $c['clave'], $c['ruc'], ['ctacargo' => $c['ctacargo'], 'ctaabono' => $c['ctaabono']]);
            }
        }
        $tabla = $libro === 'ventas' ? 'tienfact' : 'almaingr';
        $contenido = $libro === 'ventas' ? SireDbf::tienfact($ruc, $periodo, $comprobantes) : SireDbf::almaingr($periodo, $comprobantes);

        return response($contenido, 200, [
            'Content-Type' => 'application/x-dbf',
            'Content-Disposition' => 'attachment; filename="'.$tabla.'_'.$ruc.'_'.$periodo.'.dbf"',
        ]);
    }
}
