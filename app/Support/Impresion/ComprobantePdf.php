<?php
namespace App\Support\Impresion;

use App\Models\{Empresa, EmpresaNegocio};
use Dompdf\{Dompdf, Options};
use Illuminate\Support\Facades\DB;

/** PDF A4 de un comprobante (mismo diseño de la vista A4): para descargar o para mandarlo a una impresora A4 */
class ComprobantePdf
{
    /** @return array [nombre del archivo, bytes del PDF] */
    public static function generar(int $idCpe, ?int $sucursal = null): array
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $idCpe)
            ->when($sucursal, fn($q) => $q->where('id_empresa_negocio', $sucursal))->first();
        abort_unless($cab, 404);
        $datos = [
            'cab' => $cab,
            'detalle' => DB::table('cpe_detalle')->where('IdCpe_cabecera', $idCpe)->get(),
            'empresa' => Empresa::find($cab->IdEmpresa),
            'negocio' => EmpresaNegocio::find($cab->id_empresa_negocio),
            'tdodes' => DB::table('tipo_documento')->where('tdocod', $cab->tdocod)->value('tdodes'),
        ];

        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->setChroot(public_path());
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.cobros.comprobante_pdf', $datos)->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $nombre = $cab->serdoc . '-' . str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) . ' ' . preg_replace('/[^A-Za-z0-9 &.-]/', '', $cab->ccanom);
        return [mb_substr(trim($nombre), 0, 90) . '.pdf', $pdf->output()];
    }
}
