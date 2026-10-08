<?php

namespace App\Http\Controllers;

use App\Support\Sunat\SunatService;
use App\View\Composers\MenuComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SunatController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja envían comprobantes a SUNAT.');
    }

    private function sunat(): SunatService
    {
        return SunatService::paraUsuario(Auth::user());
    }

    // ------------------------------------------------------------------ envío individual

    public function envios(Request $request)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;

        $desde = $request->get('desde', now()->subDays(7)->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $tipo = $request->get('tipo', '');
        $estado = $request->get('estado', 'pendientes');

        $base = DB::table('cpe_cabecera as c')
            ->where('c.id_empresa_negocio', $sucursal)
            ->whereIn('c.tdocod', SunatService::TIPOS_ELECTRONICOS)
            ->whereBetween('c.ccafem', [$desde, $hasta]);

        // Contadores por estado (sin filtrar por estado)
        $conteo = (clone $base)->when($tipo, fn ($q) => $q->where('c.tdocod', $tipo))
            ->groupBy('c.est_sunat')->pluck(DB::raw('COUNT(*)'), 'c.est_sunat');

        $comprobantes = (clone $base)
            ->leftJoin('tipo_documento as t', 't.tdocod', '=', 'c.tdocod')
            ->when($tipo, fn ($q) => $q->where('c.tdocod', $tipo))
            ->when($estado === 'pendientes', fn ($q) => $q->whereIn('c.est_sunat', SunatService::ESTADOS_REENVIABLES))
            ->when($estado && $estado !== 'pendientes' && $estado !== 'todos', fn ($q) => $q->where('c.est_sunat', $estado))
            ->orderByDesc('c.ccafem')->orderByDesc('c.IdCpe_cabecera')
            ->select('c.IdCpe_cabecera', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccafem', 'c.ccandi', 'c.ccanom', 'c.ccaitv',
                'c.est_sunat', 'c.ccasunrescod', 'c.ccadessun', 'c.res_id', 'c.serie_ref', 'c.num_ref', 't.tdodes')
            ->paginate(50)->withQueryString();

        $empresa = DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first();
        $tieneCertificado = is_file(storage_path('app/certificados/'.Auth::user()->IdEmpresa.'.pem'));

        return view('empresas.sunat.envios', compact('comprobantes', 'conteo', 'desde', 'hasta', 'tipo', 'estado', 'empresa', 'tieneCertificado'));
    }

    /** Campanita de pendientes ya actualizada (la pide el layout después de cada envío a SUNAT) */
    public function campana()
    {
        $user = Auth::user();

        return view('layouts._campana_sunat', [
            'notifSunat' => $user->esAdminOCaja() ? MenuComposer::pendientesSunat($user) : null,
        ]);
    }

    public function enviar($id)
    {
        $this->autorizar();
        try {
            $r = $this->sunat()->enviarComprobante((int) $id);

            return response()->json(['success' => $r['ok'], 'estado' => $r['estado'], 'codigo' => $r['codigo'], 'mensaje' => $r['mensaje']]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'estado' => null, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'estado' => 'ERROR', 'mensaje' => 'Error al enviar: '.$e->getMessage()]);
        }
    }

    public function descargar($id, $tipo)
    {
        $this->autorizar();
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
        abort_unless($cab, 404);

        $nombre = $cab->IdEmpresa.'-'.$cab->tdocod.'-'.$cab->serdoc.'-'.$cab->numdoc;

        return $this->bajarArchivo($nombre, $tipo);
    }

    private function bajarArchivo(string $nombre, string $tipo)
    {
        abort_unless(in_array($tipo, ['xml', 'cdr'], true), 404);
        $ruta = $this->sunat()->rutaArchivo($nombre, $tipo);
        abort_unless(is_file($ruta), 404, 'El archivo aún no existe (envía el comprobante primero).');

        return response()->download($ruta);
    }

    // ------------------------------------------------------------------ resumen diario

    public function resumenes(Request $request)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;
        $fecha = $request->get('fecha') ?: now()->toDateString();

        $pendientes = $this->sunat()->pendientesResumen($fecha);

        // Fechas con boletas aún pendientes (para avisar si quedó algún día sin resumen)
        $diasPendientes = DB::table('cpe_cabecera')
            ->where('id_empresa_negocio', $sucursal)
            ->where(fn ($q) => $q->where('tdocod', '03')->orWhere(fn ($q2) => $q2->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'B%')))
            ->whereIn('est_sunat', SunatService::ESTADOS_REENVIABLES)
            ->whereNull('ccabaj')
            ->groupBy('ccafem')->orderBy('ccafem')
            ->select('ccafem', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(ccaitv) as total'))
            ->get();

        $resumenes = DB::table('resumenes as r')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
            ->where('r.id_empresa_negocio', $sucursal)
            ->orderByDesc('r.res_id')
            ->select('r.*', 'u.apeusu')
            ->paginate(20)->withQueryString();

        $empresa = DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first();

        return view('empresas.sunat.resumenes', compact('fecha', 'pendientes', 'diasPendientes', 'resumenes', 'empresa'));
    }

    public function resumenEnviar(Request $request)
    {
        $this->autorizar();
        $request->validate(['fecha' => 'required|date|before_or_equal:today'], [], ['fecha' => 'Fecha']);

        try {
            $ids = $this->sunat()->enviarResumen($request->fecha);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['resumen' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['resumen' => 'Error al enviar el resumen: '.$e->getMessage()]);
        }

        $resumenes = DB::table('resumenes')->whereIn('res_id', $ids)->get();
        $msg = $resumenes->map(fn ($r) => "{$r->nom_arch}: {$r->est_sunat}".($r->res_est ? " ({$r->res_est})" : ''))->implode(' · ');

        return redirect()->route('sunat.resumenes', ['fecha' => $request->fecha])->with('success', $msg);
    }

    public function resumenConsultar($id)
    {
        $this->autorizar();
        try {
            $r = $this->sunat()->consultarTicket((int) $id);

            return back()->with('success', "{$r['estado']}: {$r['mensaje']}");
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['resumen' => 'No se pudo consultar el ticket: '.$e->getMessage()]);
        }
    }

    public function resumenDescargar($id, $tipo)
    {
        $this->autorizar();
        $res = DB::table('resumenes')->where('res_id', $id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
        abort_unless($res && $res->nom_arch, 404);

        return $this->bajarArchivo($res->nom_arch, $tipo);
    }
}
