<?php

namespace App\Http\Controllers;

use App\Support\ComprasDetalle;
use App\Support\Concar;
use App\Support\Excel;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    private function propuesta(string $ruc, string $periodo): ?array
    {
        $s = DB::table('sire_solicitudes')->where('IdEmpresa', $ruc)->where('libro', Sire::COMPRAS)->where('periodo', $periodo)
            ->whereNotNull('archivo')->orderByDesc('id')->first();
        if (! $s || ! Storage::exists($s->archivo)) {
            return null;
        }
        try {
            return SireCuadre::comprasPropuesta(Sire::leerZip(Storage::path($s->archivo)));
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function datos(string $ruc, string $periodo): array
    {
        $propuesta = $this->propuesta($ruc, $periodo);

        return ['propuesta' => $propuesta !== null] + ComprasDetalle::delPeriodo($ruc, $periodo, $propuesta);
    }

    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);

        return view('empresas.sire.detalle', ['periodo' => $periodo, 'cfg' => Concar::config($ruc)] + $this->datos($ruc, $periodo));
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
        $desde = (int) ($request->validate(['desde' => 'nullable|integer|min:1|max:9999'])['desde'] ?? 1) ?: 1;
        $d = $this->datos($ruc, $periodo);
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
