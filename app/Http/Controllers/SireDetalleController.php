<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\ComprasDetalle;
use App\Support\Concar;
use App\Support\Contabilidad\CuentasCompras;
use App\Support\Contabilidad\SireDbf;
use App\Support\Excel;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use App\Support\Sunat\XmlJsonPe;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Compras del SIRE con su detalle: se suben los XML de las facturas recibidas, se ve lo que se compró en cada una, se
 * le pone la cuenta contable a cada línea (el sistema la aprende) y sale el Excel y el CONCAR con el detalle.
 */
class SireDetalleController extends Controller
{
    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->contadorCon('/sire/compras'), 403, 'Solo el Administrador o el contador pueden usar el SIRE.');

        return Auth::user()->IdEmpresa;
    }

    private function periodo(Request $request): string
    {
        return preg_match('/^\d{6}$/', (string) $request->get('periodo')) ? $request->get('periodo') : now()->subMonth()->format('Ym');
    }

    /** La propuesta de compras del periodo que ya se trajo de SUNAT (null si aún no se trajo) */
    private function datos(string $ruc, string $periodo, ?array $dbf = null): array
    {
        $dbf ??= SireDbf::delPeriodo($ruc, 'compras', $periodo)['comprobantes'];

        // Las líneas sin cuenta propia van con la Cta. cargo de su comprobante
        return ComprasDetalle::heredarDelComprobante(ComprasDetalle::conPropuesta($ruc, $periodo), $dbf, Concar::config($ruc)['cta_compras']);
    }

    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);

        // Lo mismo que "Contabilidad (DBF)": montos, cuentas y cuáles se llevan, editables aquí mismo
        $dbf = SireDbf::delPeriodo($ruc, 'compras', $periodo)['comprobantes'];

        return view('empresas.sire.detalle', ['periodo' => $periodo, 'cfg' => Concar::config($ruc), 'faltaJsonPe' => XmlJsonPe::falta(),
            'libro' => 'compras', 'dbf' => $dbf, 'totales' => SireDbf::totales('compras', $dbf)] + $this->datos($ruc, $periodo, $dbf));
    }

    /**
     * El comprobante de compra en PDF (A4, con el diseño de los comprobantes de venta), armado con lo que dice su XML:
     * para tenerlo a la mano o archivarlo sin entrar a SUNAT.
     */
    public function pdf(int $id)
    {
        $ruc = $this->autorizar();
        $xml = DB::table('compras_xml')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        abort_unless($xml, 404);
        $lineas = DB::table('compras_xml_lineas')->where('compra_xml_id', $xml->id)->orderBy('orden')->get();
        $numero = $xml->serie.'-'.str_pad($xml->numero, 8, '0', STR_PAD_LEFT);

        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->setChroot(public_path());
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.sire.compra_pdf', ['x' => $xml, 'lineas' => $lineas, 'numero' => $numero,
            'comprador' => Empresa::find($ruc)])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $nombre = mb_substr(trim($xml->ruc_emisor.' '.$numero.' '.preg_replace('/[^A-Za-z0-9 &.-]/', '', (string) $xml->razon_emisor)), 0, 90).'.pdf';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    public function subir(Request $request): RedirectResponse
    {
        $ruc = $this->autorizar();
        $request->validate(['archivos' => 'required|array|max:500', 'archivos.*' => 'file|max:51200|extensions:xml,zip'],
            ['archivos.*.extensions' => 'Sube archivos .xml o .zip.', 'archivos.*.max' => 'Cada archivo puede pesar hasta 50 MB.'], ['archivos' => 'archivos']);
        set_time_limit(300);

        $leidos = CompraXml::desdeArchivos(collect($request->file('archivos'))
            ->map(fn ($f) => ['nombre' => $f->getClientOriginalName(), 'ruta' => $f->getRealPath()])->all());
        if (! $leidos['comprobantes']) {
            return back()->with('error', 'No encontré facturas en esos archivos. Sube los .xml de tus compras (o un .zip con ellos).');
        }
        $r = ComprasDetalle::guardar($ruc, $leidos['comprobantes']);

        $partes = ["✔ {$r['nuevos']} comprobante(s) con su detalle"];
        if ($r['repetidos']) {
            $partes[] = "{$r['repetidos']} ya estaban";
        }
        if ($r['ajenos']) {
            $partes[] = "{$r['ajenos']} no son compras de esta empresa (otro comprador) y no se guardaron";
        }

        return back()->with('success', implode(' · ', $partes).'.');
    }

    /**
     * Trae de SUNAT (json.pe) los XML que faltan, unos pocos por llamada: la pantalla vuelve a llamar hasta que no
     * quede ninguno. Los que SUNAT no tiene no se vuelven a pedir en el día (cada consulta usa créditos).
     */
    public function traer(Request $request): JsonResponse
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        if ($falta = XmlJsonPe::falta()) {
            return response()->json(['ok' => false, 'mensaje' => $falta]);
        }
        set_time_limit(240);
        $claveFallidos = "compras-xml-fallidos:{$ruc}:{$periodo}";
        // "Reintentar": se vuelven a pedir los que fallaron hoy (gasta créditos otra vez)
        if ($request->boolean('reintentar')) {
            Cache::forget($claveFallidos);
        }
        $fallidos = Cache::get($claveFallidos, []);
        $faltantes = collect($this->datos($ruc, $periodo)['comprobantes'])->filter(fn ($c) => ! $c['xml']);
        $sinXml = $faltantes->filter(fn ($c) => in_array($c['tipo'], XmlJsonPe::TIPOS, true));
        // Tickets, recibos de servicios y otros: SUNAT no tiene su XML (solo facturas, boletas y notas)
        if ($sinXml->isEmpty() && $faltantes->isNotEmpty()) {
            $tipos = $faltantes->countBy('tipo')->map(fn ($n, $t) => "tipo {$t}: {$n}")->implode(', ');

            return response()->json(['ok' => false, 'traidos' => 0, 'pendientes' => 0, 'quedan' => 0,
                'mensaje' => "Los {$faltantes->count()} que faltan no son facturas, boletas ni notas ({$tipos}): SUNAT no tiene su XML. Si tienes el XML, súbelo con \"Subir XML\"."]);
        }
        $pendientes = $sinXml->filter(fn ($c) => ! isset($fallidos["{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"]))->values();
        // Los que ya fallaron hoy no se vuelven a pedir solos: se avisa cuántos y por qué (antes no se veía nada)
        if ($pendientes->isEmpty() && $sinXml->isNotEmpty()) {
            $motivos = collect($fallidos)->map(fn ($m) => is_string($m) ? $m : 'SUNAT no lo entregó')->countBy()->sortDesc();

            return response()->json(['ok' => true, 'traidos' => 0, 'errores' => [], 'pendientes' => 0, 'quedan' => 0,
                'saltados' => $sinXml->count(), 'motivos' => $motivos->take(3)->map(fn ($n, $m) => "{$m} ({$n})")->values()]);
        }

        $traidos = 0;
        $errores = [];
        // De a dos: cada XML demora unos segundos en SUNAT y así la pantalla muestra el avance seguido
        foreach ($pendientes->take(2) as $c) {
            $r = XmlJsonPe::traer($ruc, $c['ruc'], $c['tipo'], $c['serie'], $c['numero']);
            if ($r['xml'] && ($leido = CompraXml::leer($r['xml'])) && ComprasDetalle::guardar($ruc, [$leido])['nuevos']) {
                $traidos++;

                continue;
            }
            $r['error'] ??= 'El XML que llegó no es de una compra de esta empresa.';
            if ($r['detener']) {
                return response()->json(['ok' => false, 'traidos' => $traidos, 'mensaje' => $r['error']]);
            }
            // Se guarda el motivo: si fallan todos por lo mismo, la pantalla lo muestra
            $fallidos["{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"] = mb_substr((string) $r['error'], 0, 200);
            $errores[] = "{$c['serie']}-{$c['numero']}: {$r['error']}";
        }
        Cache::put($claveFallidos, $fallidos, now()->addHours(12));

        return response()->json(['ok' => true, 'traidos' => $traidos, 'errores' => $errores, 'pendientes' => $pendientes->count(),
            'quedan' => max(0, $pendientes->count() - 2)]);
    }

    /** Pone el centro de costo a una o varias líneas (las de cuentas de gasto 62–68 lo llevan obligatorio) */
    public function centro(Request $request): JsonResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['lineas' => 'required|array|max:500', 'lineas.*' => 'integer', 'centro' => 'nullable|string|max:6|regex:/^[0-9A-Za-z]*$/']);
        $n = DB::table('compras_xml_lineas as l')->join('compras_xml as c', 'c.id', '=', 'l.compra_xml_id')
            ->where('c.IdEmpresa', $ruc)->whereIn('l.id', $d['lineas'])->update(['l.centro_costo' => $d['centro'] ?: null]);

        return response()->json(['ok' => true, 'cambiadas' => $n]);
    }

    /** Pone la cuenta a una o varias líneas (y la aprende para las próximas) */
    public function cuenta(Request $request): JsonResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['lineas' => 'required|array|max:500', 'lineas.*' => 'integer', 'cuenta' => 'required|string|max:12|regex:/^[0-9A-Za-z]+$/'],
            ['cuenta.regex' => 'La cuenta va solo con números (sin puntos ni espacios).']);
        $n = ComprasDetalle::asignar($ruc, $d['lineas'], $d['cuenta']);

        return response()->json(['ok' => true, 'cambiadas' => $n]);
    }

    /** Excel con una fila por línea comprada: proveedor, comprobante, producto, montos y cuenta */
    public function excel(Request $request)
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        $d = $this->datos($ruc, $periodo);
        $filas = [];
        foreach (array_merge($d['comprobantes'], $d['sinPropuesta']) as $c) {
            $comun = [$c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '', $c['tipo'], $c['serie'], $c['numero'], $c['ruc'], $c['nombre'], $c['moneda']];
            if (! $c['lineas']) {
                $filas[] = array_merge($comun, ['(falta el XML)', null, null, null, round($c['total'] - $c['igv'], 2), $c['igv'], '']);
            }
            foreach ($c['lineas'] as $l) {
                $filas[] = array_merge($comun, [$l->descripcion, (float) $l->cantidad, $l->unidad, (float) $l->precio, (float) $l->base, (float) $l->igv, (string) $l->cuenta]);
            }
        }
        $ruta = (new Excel)->hoja("Compras {$periodo}", ['Fecha', 'Tipo', 'Serie', 'Número', 'RUC proveedor', 'Proveedor', 'Moneda',
            'Descripción', 'Cantidad', 'Unidad', 'Valor unitario', 'Base', 'IGV', 'Cuenta'], $filas, [8, 10, 11, 12])->guardar();

        return response()->download($ruta, "COMPRAS_DETALLE_{$ruc}_{$periodo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** CONCAR de compras con una línea por cuenta contable (según el detalle de cada factura) */
    public function concar(Request $request)
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        $v = $request->validate(['desde' => 'nullable|integer|min:1|max:9999', 'subdiario' => 'nullable|string|max:4|regex:/^[0-9A-Za-z]+$/']);
        $desde = (int) ($v['desde'] ?? 1) ?: 1;
        // El subdiario de compras se cambia aquí mismo y queda guardado en la configuración CONCAR de la empresa
        if (! empty($v['subdiario']) && $v['subdiario'] !== Concar::config($ruc)['subdiario_compras']) {
            DB::table('concar_config')->updateOrInsert(['IdEmpresa' => $ruc], ['subdiario_compras' => $v['subdiario'], 'updated_at' => now()]);
        }
        $d = $this->datos($ruc, $periodo);
        // Cuentas de gasto 62–68 sin centro de costo: CONCAR no las acepta
        $cfg = Concar::config($ruc);
        $faltaCentro = collect($d['comprobantes'])->flatMap(fn ($c) => collect($c['lineas'])
            ->filter(fn ($l) => CuentasCompras::requiereCentro($l->cuenta ?: $cfg['cta_compras']) && ! ($l->centro_costo ?? null))
            ->map(fn () => $c['serie'].'-'.$c['numero']))->unique()->values();
        if ($faltaCentro->isNotEmpty()) {
            return back()->with('error', 'Falta el centro de costo en líneas con cuenta de gasto (62, 63, 64, 65, 67 o 68) de '.$faltaCentro->count()
                .' comprobante(s): '.$faltaCentro->take(8)->implode(', ').($faltaCentro->count() > 8 ? '…' : '').'. Elígelo en «Ver detalle».');
        }
        $datos = Concar::comprasConDetalle($ruc, $periodo, $d['comprobantes'], $desde);
        if (! $datos['filas']) {
            return back()->with('error', 'No hay compras para exportar en ese periodo.');
        }
        if ($datos['asientos'] + $desde - 1 > 9999) {
            return back()->with('error', 'El correlativo pasa de 9999 asientos en el mes; CONCAR solo admite 4 dígitos.');
        }
        $ruta = (new Excel)->hoja('Asientos', Concar::titulos(Concar::config($ruc)), $datos['filas'])->guardar();

        return response()->download($ruta, "CONCAR_COMPRAS_DETALLE_{$ruc}_{$periodo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
