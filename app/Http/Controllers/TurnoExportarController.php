<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turnos de caja en Excel y PDF: un turno completo (resumen, medios de pago, productos, categorías, cortesías y
 * movimientos de caja) o el listado de turnos del rango con sus totales.
 */
class TurnoExportarController extends Controller
{
    private function turno(int $id): Turno
    {
        $user = Auth::user();
        abort_unless($user->esAdminOCaja(), 403, 'Solo Administrador o Caja manejan turnos.');
        $turno = Turno::with('usuario')->where('id_turno', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->firstOrFail();
        abort_unless($user->esAdmin() || (int) $turno->IdUsuario === (int) $user->IdUsuario, 403);

        return $turno;
    }

    /** Lo que se exporta de un turno (lo mismo en Excel y en PDF) */
    private function datos(Turno $turno): array
    {
        $r = TurnoController::resumen($turno);
        $contado = $turno->montocierre !== null ? (float) $turno->montocierre : null;

        return [
            'turno' => $turno, 'r' => $r, 'productos' => TurnoController::vendidos($turno), 'cortesias' => TurnoController::cortesias($turno),
            'resumen' => [
                ['Cajero', $turno->usuario->apeusu ?? ''], ['Turno N°', $turno->turno],
                ['Apertura', $turno->apertura?->format('d/m/Y H:i')], ['Cierre', $turno->cierre?->format('d/m/Y H:i') ?? 'ABIERTO'],
                ['Fondo inicial', (float) $turno->monto], ['Ventas del turno', $r['ventasTotal']], ['Ventas a crédito', $r['ventasCredito']],
                ['Ventas en efectivo', $r['ventasEfectivo']], ['Otros ingresos', $r['ingresos']], ['Egresos / gastos', $r['egresos']],
                ['Efectivo esperado', $r['efectivoEsperado']], ['Efectivo contado', $contado ?? 'No se contó'],
                ['Diferencia (contado - esperado)', $contado !== null ? round($contado - $r['efectivoEsperado'], 2) : ''],
            ],
        ];
    }

    private function nombre(Turno $turno): string
    {
        return 'turno_'.$turno->turno.'_'.($turno->apertura?->format('Ymd') ?? $turno->id_turno).'_'.preg_replace('/[^A-Za-z0-9]+/', '_', $turno->usuario->apeusu ?? '');
    }

    public function turnoExcel(int $id): Response
    {
        $d = $this->datos($this->turno($id));
        $r = $d['r'];
        $ruta = (new Excel)
            ->hoja('Resumen', ['Concepto', 'Valor'], $d['resumen'])
            ->hoja('Medios de pago', ['Medio de pago', 'Operaciones', 'Monto S/'], $r['ventasPorMedio']->map(fn ($v) => [$v->nom_med_pag, (int) $v->operaciones, (float) $v->monto])->all())
            ->hoja('Comprobantes', ['Tipo', 'Cantidad', 'Total S/'], $r['comprobantes']->map(fn ($c) => [$c->tdodes, (int) $c->cantidad, (float) $c->total])->all())
            ->hoja('Productos', ['Producto', 'Categoría', 'Cantidad', 'Precio S/', 'Total S/'], $d['productos']->map(fn ($p) => [$p->nombre, $p->categoria, $p->cantidad, $p->precio, $p->total])->all())
            ->hoja('Cortesías', ['Producto', 'Cantidad', 'Valor S/'], $d['cortesias']->map(fn ($c) => [$c->nombre, $c->cantidad, $c->valor])->all())
            ->hoja('Movimientos de caja', ['Tipo', 'Concepto', 'Descripción', 'Importe S/', 'Estado', 'Usuario'],
                $r['movimientos']->map(fn ($m) => [$m->tipo, $m->tip_caj_nom, $m->mov_com, (float) $m->importe, $m->estado, $m->apeusu])->all())
            ->guardar();

        return response()->download($ruta, $this->nombre($d['turno']).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function turnoPdf(int $id): Response
    {
        $d = $this->datos($this->turno($id));

        return $this->pdf(view('empresas.turnos.pdf', $d + ['negocio' => $this->negocio()])->render(), $this->nombre($d['turno']));
    }

    /** Turnos del rango del listado con sus totales */
    private function listado(Request $request): Collection
    {
        $user = Auth::user();
        abort_unless($user->esAdminOCaja(), 403, 'Solo Administrador o Caja manejan turnos.');

        return Turno::with('usuario')->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->when(! $user->esAdmin(), fn ($q) => $q->where('IdUsuario', $user->IdUsuario))
            ->whereDate('apertura', '>=', $request->get('desde', now()->subDays(30)->toDateString()))
            ->whereDate('apertura', '<=', $request->get('hasta', now()->toDateString()))
            ->orderByDesc('id_turno')->limit(500)->get()
            ->map(function (Turno $t) {
                $r = TurnoController::resumen($t);
                $contado = $t->montocierre !== null ? (float) $t->montocierre : null;

                return (object) ['turno' => $t->turno, 'cajero' => $t->usuario->apeusu ?? '', 'apertura' => $t->apertura?->format('d/m/Y H:i'),
                    'cierre' => $t->cierre?->format('d/m/Y H:i') ?? '—', 'fondo' => (float) $t->monto, 'ventas' => $r['ventasTotal'],
                    'esperado' => $r['efectivoEsperado'], 'contado' => $contado, 'diferencia' => $contado !== null ? round($contado - $r['efectivoEsperado'], 2) : null,
                    'estado' => $t->estado];
            });
    }

    public function listadoExcel(Request $request): Response
    {
        $filas = $this->listado($request)->map(fn ($t) => [$t->turno, $t->cajero, $t->apertura, $t->cierre, $t->fondo, $t->ventas, $t->esperado,
            $t->contado ?? 'No se contó', $t->diferencia, $t->estado])->all();
        $ruta = (new Excel)->hoja('Turnos', ['N°', 'Cajero', 'Apertura', 'Cierre', 'Fondo S/', 'Ventas S/', 'Efectivo esperado S/', 'Contado S/', 'Diferencia S/', 'Estado'], $filas)->guardar();

        return response()->download($ruta, 'turnos_'.$request->get('desde').'_'.$request->get('hasta').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function listadoPdf(Request $request): Response
    {
        return $this->pdf(view('empresas.turnos.listado_pdf', ['turnos' => $this->listado($request), 'negocio' => $this->negocio(),
            'desde' => $request->get('desde', now()->subDays(30)->toDateString()), 'hasta' => $request->get('hasta', now()->toDateString())])->render(),
            'turnos_'.$request->get('desde').'_'.$request->get('hasta'), 'landscape');
    }

    private function pdf(string $html, string $nombre, string $orientacion = 'portrait'): Response
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', $orientacion);
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'.pdf"']);
    }

    private function negocio(): string
    {
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first(['nombre_comercial', 'IdEmpresa']);

        return (string) ($n->nombre_comercial ?: DB::table('empresa')->where('IdEmpresa', $n->IdEmpresa)->value('NomEmpresa'));
    }
}
