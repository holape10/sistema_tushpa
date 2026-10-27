<?php

namespace App\Http\Controllers;

use App\Support\Impresion\ComprobantePdf;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Mis comprobantes": lo que TUSHPA (el sistema principal, a.tushpa.app) le facturó a esta empresa, leído de la base
 * del sistema principal por el RUC de la empresa (y su DNI si es RUC 10, por las boletas a la persona).
 * El cliente ve el historial y descarga o imprime el PDF; de los electrónicos, también el XML y el CDR.
 */
class MisComprobantesController extends Controller
{
    public const TIPOS = ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito', '13' => 'Nota de venta'];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ve los comprobantes de su servicio TUSHPA.');
    }

    /**
     * Documentos con los que esta empresa figura como cliente en el sistema principal (vacío si no es una empresa cliente)
     *
     * @return array<int, string>
     */
    private function documentos(): array
    {
        $ruc = (string) (Tenancy::cliente()?->ruc ?? '');
        if (! preg_match('/^\d{11}$/', $ruc) || $ruc === '99999999999') {
            return [];
        }

        return str_starts_with($ruc, '10') ? [$ruc, substr($ruc, 2, 8)] : [$ruc];
    }

    /** Consulta en la base del sistema principal */
    private function enPrincipal(callable $fn): mixed
    {
        return Tenancy::en(config('tenancy.base_sistema'), $fn);
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $docs = $this->documentos();
        $tipo = array_key_exists($request->get('tipo'), self::TIPOS) ? $request->get('tipo') : '';

        [$comprobantes, $conteo, $emisor] = $docs ? $this->enPrincipal(function () use ($docs, $tipo) {
            $base = fn () => DB::table('cpe_cabecera')->whereIn('ccandi', $docs)->whereIn('tdocod', array_keys(self::TIPOS));
            $lista = $base()->when($tipo, fn ($q) => $q->where('tdocod', $tipo))
                ->orderByDesc('ccafem')->orderByDesc('IdCpe_cabecera')
                ->paginate(30, ['IdCpe_cabecera', 'IdEmpresa', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccaitv', 'moncod', 'est_sunat',
                    'ccabaj', 'anulado_nc', 'serie_ref', 'num_ref', 'estadopago'])
                ->withQueryString();
            // Primera línea de cada uno (para saber qué se cobró)
            $detalles = DB::table('cpe_detalle')->whereIn('IdCpe_cabecera', $lista->pluck('IdCpe_cabecera'))->orderBy('IdCpe_detalle')
                ->get(['IdCpe_cabecera', 'cdedes'])->unique('IdCpe_cabecera')->pluck('cdedes', 'IdCpe_cabecera');
            $lista->getCollection()->transform(fn ($c) => tap($c, function ($c) use ($detalles) {
                $c->detalle = $detalles[$c->IdCpe_cabecera] ?? '';
                $nombre = $c->IdEmpresa.'-'.$c->tdocod.'-'.$c->serdoc.'-'.$c->numdoc;
                $c->tieneXml = is_file(storage_path('app/sunat/'.$c->IdEmpresa.'/'.$nombre.'.xml'));
                $c->tieneCdr = is_file(storage_path('app/sunat/'.$c->IdEmpresa.'/R-'.$nombre.'.zip'));
            }));

            return [$lista, $base()->groupBy('tdocod')->selectRaw('tdocod, COUNT(*) n')->pluck('n', 'tdocod'),
                DB::table('empresa')->orderBy('IdEmpresa')->value('NomEmpresa')];
        }) : [null, collect(), null];

        return view('empresas.mis_comprobantes', compact('comprobantes', 'conteo', 'emisor', 'tipo', 'docs'));
    }

    /** Un comprobante que el sistema principal le emitió a esta empresa (404 si no es suyo) */
    private function comprobante(int $id): object
    {
        $docs = $this->documentos();
        abort_unless($docs, 404);
        $cab = $this->enPrincipal(fn () => DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->whereIn('ccandi', $docs)
            ->whereIn('tdocod', array_keys(self::TIPOS))->first(['IdCpe_cabecera', 'IdEmpresa', 'tdocod', 'serdoc', 'numdoc']));
        abort_unless($cab, 404);

        return $cab;
    }

    /** PDF A4: se ve en el navegador (para imprimir) o se descarga con ?descargar=1 */
    public function pdf(Request $request, int $id)
    {
        $this->autorizar();
        $cab = $this->comprobante($id);
        [$nombre, $contenido] = $this->enPrincipal(fn () => ComprobantePdf::generar((int) $cab->IdCpe_cabecera));

        return response($contenido, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="'.$nombre.'"']);
    }

    /** XML firmado o CDR (constancia de SUNAT) del comprobante electrónico */
    public function archivo(int $id, string $tipo)
    {
        $this->autorizar();
        $cab = $this->comprobante($id);
        $nombre = $cab->IdEmpresa.'-'.$cab->tdocod.'-'.$cab->serdoc.'-'.$cab->numdoc;
        $ruta = storage_path('app/sunat/'.$cab->IdEmpresa.'/'.($tipo === 'cdr' ? 'R-'.$nombre.'.zip' : $nombre.'.xml'));
        abort_unless(is_file($ruta), 404, 'Este comprobante no tiene '.strtoupper($tipo).' (las boletas van a SUNAT en el resumen diario).');

        return response()->download($ruta);
    }
}
