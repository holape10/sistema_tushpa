<?php

namespace App\Http\Controllers;

use App\Support\Contingencia;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Comprobantes de contingencia (en papel) pasados al sistema: la lista del mes y su Excel para declararlos a SUNAT */
class ContingenciaController extends Controller
{
    private function mes(Request $request): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/contingencia'), 403, 'No tienes permiso para los comprobantes de contingencia.');
        $mes = (string) $request->get('mes', now()->format('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : now()->format('Y-m');
    }

    public function index(Request $request)
    {
        $mes = $this->mes($request);

        return view('empresas.contingencia.index', ['mes' => $mes, 'lista' => Contingencia::delMes((int) Auth::user()->id_empresa_negocio, $mes),
            'tipos' => DB::table('tipo_documento')->pluck('tdodes', 'tdocod')]);
    }

    public function excel(Request $request)
    {
        $mes = $this->mes($request);
        $tipos = DB::table('tipo_documento')->pluck('tdodes', 'tdocod');
        $filas = Contingencia::delMes((int) Auth::user()->id_empresa_negocio, $mes)->map(fn ($c) => [
            Carbon::parse($c->ccafem)->format('d/m/Y'), $c->tdocod, $tipos[$c->tdocod] ?? '', $c->serdoc, (int) $c->numdoc, $c->tdicod, $c->ccandi, $c->ccanom,
            (float) $c->ccatvg, (float) $c->ccatexo, (float) $c->ccatinaf, (float) $c->ccaigv, (float) $c->ccaitv, $c->moncod ?: 'PEN', $c->ccabaj ? 'ANULADO' : '',
        ])->all();
        $titulo = 'Comprobantes de contingencia · '.Carbon::parse($mes.'-01')->locale('es')->isoFormat('MMMM YYYY');
        $ruta = (new Excel)->hoja('Contingencia', [[$titulo], ['Fecha', 'Tipo', 'Comprobante', 'Serie', 'Número', 'Tipo doc. cliente', 'N° doc. cliente', 'Cliente',
            'Op. gravada', 'Op. exonerada', 'Op. inafecta', 'IGV', 'Total', 'Moneda', 'Estado']], $filas)->guardar();

        return response()->download($ruta, 'contingencia_'.str_replace('-', '', $mes).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
