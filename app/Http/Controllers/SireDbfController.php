<?php

namespace App\Http\Controllers;

use App\Support\Contabilidad\SireDbf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'libro' => $libro, 'periodo' => $periodo, 'totales' => SireDbf::totales($libro, $datos['comprobantes']),
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
            'excluido' => 'sometimes|boolean', 'ctacargo' => $cuenta, 'ctaabono' => $cuenta,
            'montos' => 'sometimes|nullable|array', 'montos.destino' => ['sometimes', Rule::in(array_keys(SireDbf::DESTINOS))],
            'montos.*' => 'nullable',
        ], ['regex' => 'La cuenta va solo con números (sin puntos ni espacios).']);
        $c = $this->comprobante($ruc, $d['libro'], $d['periodo'], $d['clave']);

        $cambios = array_intersect_key($d, array_flip(['excluido', 'ctacargo', 'ctaabono']));
        if ($request->has('montos')) {
            $cambios['montos'] = $d['montos'] ? $this->montos($d['libro'], $d['montos']) : null;
        }
        SireDbf::guardar($ruc, $d['libro'], $d['periodo'], $d['clave'], $c['ruc'], $cambios);
        $c = $this->comprobante($ruc, $d['libro'], $d['periodo'], $d['clave']);

        return response()->json(['ok' => true, 'diferencia' => $c['diferencia'], 'montos' => $c['montos'], 'editado' => $c['editado'],
            'totales' => SireDbf::totales($d['libro'], SireDbf::delPeriodo($ruc, $d['libro'], $d['periodo'])['comprobantes'])]);
    }

    /**
     * Los montos que escribió el contador. En compras la base y el IGV van al destino elegido (gravada, mixta o no
     * gravada) y el ISC queda dentro de "otros".
     *
     * @return array<string, float>
     */
    private function montos(string $libro, array $m): array
    {
        $num = fn (string $k) => round((float) str_replace(',', '', (string) ($m[$k] ?? 0)), 2);
        if ($libro === 'ventas') {
            return collect(['exporta', 'gravada', 'igv', 'exonerada', 'inafecta', 'isc', 'icbper', 'otros', 'total'])->mapWithKeys(fn ($k) => [$k => $num($k)])->all();
        }
        $destino = $m['destino'] ?? 'dg';
        $res = array_fill_keys(SireDbf::MONTOS_COMPRA, 0.0);
        $res["base_{$destino}"] = $num('base');
        $res["igv_{$destino}"] = $num('igv');
        $res['no_gravado'] = $num('no_gravado');
        $res['otros'] = $num('otros');
        $res['total'] = $num('total');

        return $res;
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
            return back()->with('error', "Hay {$totales['descuadrados']} comprobante(s) que no cuadran: revisa sus montos (están marcados en rojo) o no los lleves.");
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
