<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Concar;
use App\Support\Contabilidad\SireDbf;
use App\Support\Sunat\CompraXml;
use App\Support\VentasDetalle;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ventas del SIRE con su detalle: la propuesta de ventas editable (montos, cuentas, cuáles se llevan) como en
 * Contabilidad (DBF), con lo que se vendió en cada comprobante (de su XML, que sube el contador), su PDF y las
 * descargas del Excel y del tienfact.dbf.
 */
class SireVentasDetalleController extends Controller
{
    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->contadorCon('/sire/ventas'), 403, 'Solo el Administrador o el contador pueden usar el SIRE.');

        return Auth::user()->IdEmpresa;
    }

    private function periodo(Request $request): string
    {
        return preg_match('/^\d{6}$/', (string) $request->get('periodo')) ? $request->get('periodo') : now()->subMonth()->format('Ym');
    }

    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        $datos = SireDbf::delPeriodo($ruc, 'ventas', $periodo);
        $comprobantes = $datos['comprobantes'];

        return view('empresas.sire.ventas_detalle', ['periodo' => $periodo, 'libro' => 'ventas', 'propuesta' => $datos['propuesta'],
            'dbf' => $comprobantes, 'totales' => SireDbf::totales('ventas', $comprobantes), 'cfg' => Concar::config($ruc),
            'conDetalle' => collect($comprobantes)->filter(fn ($c) => $c['lineas'])->count()]);
    }

    /** Sube los XML de las ventas (sueltos o en un .zip): solo se guardan los que emitió esta empresa */
    public function subir(Request $request): RedirectResponse
    {
        $ruc = $this->autorizar();
        $request->validate(['archivos' => 'required|array|max:500', 'archivos.*' => 'file|max:51200|extensions:xml,zip'],
            ['archivos.*.extensions' => 'Sube archivos .xml o .zip.', 'archivos.*.max' => 'Cada archivo puede pesar hasta 50 MB.'], ['archivos' => 'archivos']);
        set_time_limit(300);

        $leidos = CompraXml::desdeArchivos(collect($request->file('archivos'))
            ->map(fn ($f) => ['nombre' => $f->getClientOriginalName(), 'ruta' => $f->getRealPath()])->all());
        if (! $leidos['comprobantes']) {
            return back()->with('error', 'No encontré comprobantes en esos archivos. Sube los .xml de tus ventas (o un .zip con ellos).');
        }
        $r = VentasDetalle::guardar($ruc, $leidos['comprobantes']);

        $partes = ["✔ {$r['nuevos']} venta(s) con su detalle"];
        if ($r['repetidos']) {
            $partes[] = "{$r['repetidos']} ya estaban";
        }
        if ($r['ajenos']) {
            $partes[] = "{$r['ajenos']} no los emitió esta empresa (son compras u otra empresa) y no se guardaron";
        }

        return back()->with('success', implode(' · ', $partes).'.');
    }

    /** El comprobante de venta en PDF (A4), armado con lo que dice su XML */
    public function pdf(int $id)
    {
        $ruc = $this->autorizar();
        $xml = DB::table('ventas_xml')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        abort_unless($xml, 404);
        $empresa = Empresa::find($ruc);
        $lineas = DB::table('ventas_xml_lineas')->where('venta_xml_id', $xml->id)->orderBy('orden')->get();
        $numero = $xml->serie.'-'.str_pad($xml->numero, 8, '0', STR_PAD_LEFT);

        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->setChroot(public_path());
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.sire.compra_pdf', ['x' => $xml, 'lineas' => $lineas, 'numero' => $numero,
            'emisor' => ['ruc' => $ruc, 'nombre' => (string) ($empresa->NomEmpresa ?? ''), 'nota' => 'Comprobante emitido'],
            'receptor' => ['doc' => (string) $xml->doc_cliente, 'nombre' => (string) $xml->razon_cliente, 'direccion' => '']])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $nombre = mb_substr(trim($numero.' '.preg_replace('/[^A-Za-z0-9 &.-]/', '', (string) $xml->razon_cliente)), 0, 90).'.pdf';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }
}
