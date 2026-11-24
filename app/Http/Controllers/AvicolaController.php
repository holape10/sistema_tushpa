<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Avicola;
use App\Support\AvicolaGranja;
use App\Support\AvicolaReportes;
use App\Support\Excel;
use App\Support\Impresion\Impresion;
use App\Support\Proformas;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Avícola: venta por peso (con precio del día), clientes con su cuenta corriente y sus jabas, ingreso de pollo,
 * beneficio y partes, granja (lotes), mermas, reportes y configuración.
 */
class AvicolaController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan el módulo avícola.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function admin(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede hacer esto.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function json(callable $accion): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function volver(callable $accion, string $exito): RedirectResponse
    {
        try {
            $r = $accion();

            return back()->with('success', is_string($r) ? $r : $exito);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rango(Request $request, int $dias = 0): array
    {
        $d = $request->validate(['desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde']);

        return [Carbon::parse($d['desde'] ?? today()->subDays($dias)), Carbon::parse($d['hasta'] ?? today())];
    }

    // ------------------------------------------------------------------ venta por peso

    public function index(): View
    {
        $suc = $this->sucursal();
        $user = Auth::user();

        return view('empresas.avicola.venta', [
            'cfg' => Avicola::config($suc), 'productos' => Avicola::productos($suc),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'turno' => Turno::abiertoDe($user), 'puedePrecio' => $user->esAdmin() || $user->puede('cambiar_precio'),
            'hoy' => DB::table('avi_ventas as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
                ->join('productos as p', 'p.IdProducto', '=', 'v.IdProducto')
                ->where('v.id_empresa_negocio', $suc)->whereDate('v.fecha', today())->orderByDesc('v.id')
                ->get(['v.*', 'p.pronom', 'c.serdoc', 'c.numdoc', 'c.ccabaj']),
        ]);
    }

    public function vender(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate([
            'IdProducto' => 'required|integer', 'tdocod' => 'required|in:01,03,13', 'tdicod' => 'required|string|size:1',
            'clinum' => 'required|string|max:15', 'clinom' => 'required|string|max:120', 'clidir' => 'nullable|string|max:150',
            'pesadas' => 'required|array|min:1|max:200', 'pesadas.*.jabas' => 'nullable|integer|min:0|max:999',
            'pesadas.*.aves' => 'nullable|integer|min:0|max:99999', 'pesadas.*.peso' => 'nullable|numeric|min:0|max:99999',
            'aves_muertas' => 'nullable|integer|min:0|max:99999', 'peso_muertas' => 'nullable|numeric|min:0|max:99999',
            'precio' => 'required|numeric|min:0.01|max:9999', 'credito' => 'nullable|boolean', 'fecVen' => 'nullable|date',
            'id_med_pag' => 'nullable|array|max:5', 'id_med_pag.*' => 'integer', 'mon_med_pag' => 'nullable|array|max:5', 'mon_med_pag.*' => 'numeric|min:0',
            'op_med_pag' => 'nullable|array|max:5', 'op_med_pag.*' => 'nullable|string|max:30',
            'paga' => 'nullable|numeric|min:0', 'jabas_devueltas' => 'nullable|integer|min:0|max:9999', 'ruta' => 'nullable|string|max:60',
        ]);

        return $this->json(function () use ($d) {
            $r = Avicola::vender(Auth::user(), $d);

            return VentaDirecta::respuesta($r['comprobante']) + ['venta' => $r['venta']];
        });
    }

    /** Proforma de la venta por peso: se guarda y sale en la impresora de caja (como en el Punto Venta) */
    public function proforma(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate([
            'IdProducto' => 'required|integer', 'tdicod' => 'required|string|size:1', 'clinum' => 'required|string|max:15', 'clinom' => 'required|string|max:120',
            'clidir' => 'nullable|string|max:150', 'pesadas' => 'required|array|min:1|max:200', 'pesadas.*.jabas' => 'nullable|integer|min:0|max:999',
            'pesadas.*.aves' => 'nullable|integer|min:0|max:99999', 'pesadas.*.peso' => 'nullable|numeric|min:0|max:99999',
            'peso_muertas' => 'nullable|numeric|min:0|max:99999', 'precio' => 'required|numeric|min:0.01|max:9999',
        ]);

        return $this->json(function () use ($d) {
            $id = Avicola::proforma(Auth::user(), $d);
            $p = DB::table('proformas')->where('id_proforma', $id)->first();

            return ['id' => $id, 'numero' => Proformas::numero($p), 'total' => (float) $p->total, 'imprimir' => route('proformas.imprimir', $id),
                'impresora' => Impresion::proforma($id, (int) Auth::user()->id_empresa_negocio)];
        });
    }

    public function precios(Request $request): RedirectResponse
    {
        $this->sucursal();
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->puede('cambiar_precio'), 403, 'No tienes permiso para cambiar precios.');
        $d = $request->validate(['precios' => 'required|array', 'precios.*' => 'nullable|numeric|min:0|max:9999']);

        return $this->volver(fn () => ($n = Avicola::fijarPrecios($user, $d['precios'])) ? "Precio del día guardado ({$n})." : 'No cambió ningún precio.', '');
    }

    // ------------------------------------------------------------------ clientes, cuenta corriente y jabas

    public function clientes(Request $request): View
    {
        $suc = $this->sucursal();
        $saldos = Avicola::cuentaCorriente($suc);
        $jabas = Avicola::saldoJabas($suc);
        $conVentas = DB::table('avi_ventas')->where('id_empresa_negocio', $suc)->groupBy('clicod')
            ->selectRaw('clicod, MAX(fecha) as ultima, SUM(peso_neto) as kg')->get()->keyBy('clicod');
        $ids = $saldos->keys()->merge(array_keys($jabas))->merge($conVentas->keys())->unique()->filter();
        $q = trim((string) $request->query('q'));
        $clientes = DB::table('cliente')->whereIn('clicod', $ids)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('clinom', 'like', "%{$q}%")->orWhere('clinum', 'like', "{$q}%")))
            ->get(['clicod', 'clinum', 'clinom', 'telefono'])
            ->map(fn ($c) => (object) ((array) $c + ['saldo' => (float) ($saldos[$c->clicod]->saldo ?? 0), 'vencido' => (float) ($saldos[$c->clicod]->vencido ?? 0),
                'jabas' => (int) ($jabas[$c->clicod] ?? 0), 'ultima' => $conVentas[$c->clicod]->ultima ?? null]))
            ->sortByDesc(fn ($c) => [$c->saldo, $c->jabas])->values();

        return view('empresas.avicola.clientes', ['clientes' => $clientes, 'q' => $q,
            'total' => ['saldo' => $clientes->sum('saldo'), 'vencido' => $clientes->sum('vencido'), 'jabas' => $clientes->sum('jabas')]]);
    }

    public function cliente(int $clicod): View
    {
        $suc = $this->sucursal();
        $cliente = DB::table('cliente')->where('clicod', $clicod)->first();
        abort_unless($cliente, 404);

        return view('empresas.avicola.cliente', [
            'cliente' => $cliente, 'saldo' => Avicola::cuentaCorriente($suc, $clicod)->first(), 'jabas' => Avicola::saldoJabas($suc, $clicod)[$clicod] ?? 0,
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'pendientes' => DB::table('cuentas_cobrar as cc')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->where('cc.id_empresa_negocio', $suc)->where('cc.clicod', $clicod)->whereNull('c.ccabaj')->where('cc.saldo', '>', 0)
                ->whereNotIn('cc.estado_cob', ['PAGADO', 'ANULADO'])->orderBy('c.ccafem')
                ->get(['c.serdoc', 'c.numdoc', 'c.ccafem', 'cc.fec_ven', 'cc.total', 'cc.saldo']),
            'ventas' => DB::table('avi_ventas as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
                ->join('productos as p', 'p.IdProducto', '=', 'v.IdProducto')->where('v.id_empresa_negocio', $suc)->where('v.clicod', $clicod)
                ->orderByDesc('v.id')->limit(40)->get(['v.*', 'p.pronom', 'c.serdoc', 'c.numdoc', 'c.ccabaj']),
            'movJabas' => DB::table('avi_jabas')->where('id_empresa_negocio', $suc)->where('clicod', $clicod)->orderByDesc('id')->limit(40)->get(),
        ]);
    }

    public function abonar(Request $request, int $clicod): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate(['monto' => 'required|numeric|min:0.01|max:9999999', 'medio' => 'required|integer', 'operacion' => 'nullable|string|max:30']);

        return $this->volver(function () use ($clicod, $d) {
            $r = Avicola::abonar(Auth::user(), $clicod, (int) $d['medio'], (float) $d['monto'], $d['operacion'] ?? null);

            return 'Abono de S/ '.number_format($r['pagado'], 2).' registrado ('.$r['documentos'].' documento(s)).';
        }, '');
    }

    public function jabas(Request $request, int $clicod): RedirectResponse
    {
        $suc = $this->sucursal();
        abort_unless(DB::table('cliente')->where('clicod', $clicod)->exists(), 404);
        $d = $request->validate(['tipo' => 'required|in:DEVOLUCION,SALIDA,AJUSTE', 'cantidad' => 'required|integer|min:-9999|max:9999|not_in:0',
            'observacion' => 'nullable|string|max:150']);
        if ($d['tipo'] === 'AJUSTE') {
            abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ajusta el saldo de jabas.');
        } elseif ($d['cantidad'] < 0) {
            return back()->with('error', 'Escribe la cantidad de jabas sin signo.');
        }

        return $this->volver(function () use ($clicod, $d, $suc) {
            if ($d['tipo'] === 'DEVOLUCION' && $d['cantidad'] > (Avicola::saldoJabas($suc, $clicod)[$clicod] ?? 0)) {
                throw new \RuntimeException('El cliente no tiene tantas jabas por devolver.');
            }
            Avicola::movimientoJabas(Auth::user(), $clicod, $d['tipo'], (int) $d['cantidad'], null, $d['observacion'] ?? null);
        }, 'Jabas actualizadas.');
    }

    // ------------------------------------------------------------------ ingreso de pollo

    public function ingresos(Request $request): View
    {
        $suc = $this->sucursal();
        [$desde, $hasta] = $this->rango($request, 7);

        return view('empresas.avicola.ingresos', [
            'cfg' => Avicola::config($suc), 'productos' => Avicola::productos($suc), 'desde' => $desde, 'hasta' => $hasta,
            'lotes' => DB::table('avi_lotes')->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->where('tipo', 'ENGORDE')->orderBy('codigo')->get(),
            'ingresos' => DB::table('avi_ingresos as i')->join('productos as p', 'p.IdProducto', '=', 'i.IdProducto')->where('i.id_empresa_negocio', $suc)
                ->whereBetween('i.fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])->orderByDesc('i.id')->get(['i.*', 'p.pronom']),
        ]);
    }

    public function ingresar(Request $request): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate([
            'IdProducto' => 'required|integer', 'lote_id' => 'nullable|integer',
            'prov_tdicod' => 'nullable|in:1,6', 'prov_num' => 'required_without:lote_id|nullable|string|max:15', 'prov_nom' => 'required_without:lote_id|nullable|string|max:150',
            'tdocod' => 'nullable|in:01,03,12,13,00', 'serie' => 'nullable|string|max:4', 'numero' => 'nullable|string|max:8',
            'pesadas' => 'required|array|min:1|max:200', 'pesadas.*.jabas' => 'nullable|integer|min:0|max:999',
            'pesadas.*.aves' => 'nullable|integer|min:0|max:99999', 'pesadas.*.peso' => 'nullable|numeric|min:0|max:99999',
            'aves_muertas' => 'nullable|integer|min:0|max:99999', 'peso_muertas' => 'nullable|numeric|min:0|max:99999',
            'costo_kg' => 'nullable|numeric|min:0|max:9999', 'tip_igv' => 'nullable|in:10,20,30', 'credito' => 'nullable|boolean',
        ], [], ['prov_num' => 'RUC o DNI del proveedor', 'prov_nom' => 'proveedor']);

        return $this->volver(function () use ($d) {
            $id = Avicola::ingresar(Auth::user(), $d);
            $i = DB::table('avi_ingresos')->where('id', $id)->first();

            return 'Ingresaron '.Avicola::kg((float) $i->peso_neto)." ({$i->aves} aves) por S/ ".number_format((float) $i->total, 2).'.';
        }, '');
    }

    // ------------------------------------------------------------------ beneficio

    public function beneficio(Request $request): View
    {
        $suc = $this->sucursal();
        [$desde, $hasta] = $this->rango($request, 7);

        return view('empresas.avicola.beneficio', [
            'productos' => Avicola::productos($suc), 'desde' => $desde, 'hasta' => $hasta,
            'beneficios' => DB::table('avi_beneficios as b')->join('productos as p', 'p.IdProducto', '=', 'b.IdProducto')->where('b.id_empresa_negocio', $suc)
                ->whereBetween('b.fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])->orderByDesc('b.id')->get(['b.*', 'p.pronom']),
            'partes' => DB::table('avi_beneficio_partes as x')->join('productos as p', 'p.IdProducto', '=', 'x.IdProducto')
                ->join('avi_beneficios as b', 'b.id', '=', 'x.beneficio_id')->where('b.id_empresa_negocio', $suc)
                ->whereBetween('b.fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])->get(['x.beneficio_id', 'p.pronom', 'x.kg'])->groupBy('beneficio_id'),
        ]);
    }

    public function beneficiar(Request $request): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate(['IdProducto' => 'required|integer', 'aves' => 'nullable|integer|min:0|max:99999', 'kg_vivo' => 'required|numeric|min:0.001|max:99999',
            'partes' => 'required|array|min:1|max:30', 'partes.*.IdProducto' => 'nullable|integer', 'partes.*.kg' => 'nullable|numeric|min:0|max:99999',
            'observacion' => 'nullable|string|max:150']);

        return $this->volver(function () use ($d) {
            $b = DB::table('avi_beneficios')->where('id', Avicola::beneficiar(Auth::user(), $d))->first();

            return 'Beneficio registrado: salió '.Avicola::kg((float) $b->kg_salida).' de '.Avicola::kg((float) $b->kg_vivo)." (rendimiento {$b->rendimiento}%).";
        }, '');
    }

    // ------------------------------------------------------------------ granja

    public function granja(): View
    {
        $suc = $this->sucursal();
        $lotes = DB::table('avi_lotes')->where('id_empresa_negocio', $suc)->orderByRaw("estado = 'ABIERTO' DESC")->orderByDesc('fecha_ingreso')->limit(60)->get();

        return view('empresas.avicola.granja', ['lotes' => $lotes->map(fn ($l) => (object) ((array) $l + ['r' => AvicolaGranja::resumen($l)]))]);
    }

    public function crearLote(Request $request): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate(['codigo' => 'nullable|string|max:30', 'galpon' => 'nullable|string|max:60', 'tipo' => 'required|in:ENGORDE,POSTURA',
            'linea' => 'nullable|string|max:60', 'fecha_ingreso' => 'required|date', 'aves_iniciales' => 'required|integer|min:1|max:9999999',
            'costo_ave' => 'nullable|numeric|min:0|max:9999', 'observacion' => 'nullable|string|max:200']);
        try {
            $id = AvicolaGranja::crear(Auth::user(), $d);

            return redirect()->route('avicola.lote', $id)->with('success', 'Lote creado.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function lote(int $id): View
    {
        $this->sucursal();
        $lote = rescue(fn () => AvicolaGranja::lote(Auth::user(), $id), null, false);
        abort_unless($lote, 404);

        return view('empresas.avicola.lote', ['lote' => $lote, 'r' => AvicolaGranja::resumen($lote),
            'registros' => DB::table('avi_lote_registros')->where('lote_id', $id)->orderByDesc('fecha')->orderByDesc('id')->get(),
            'salidas' => DB::table('avi_ingresos')->where('lote_id', $id)->where('estado', 'ACTIVO')->orderByDesc('id')->get()]);
    }

    public function registrarLote(Request $request, int $id): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate(['fecha' => 'required|date|before_or_equal:today', 'mortalidad' => 'nullable|integer|min:0|max:9999999',
            'alimento_kg' => 'nullable|numeric|min:0|max:999999', 'costo_alimento' => 'nullable|numeric|min:0|max:9999999',
            'otros_gastos' => 'nullable|numeric|min:0|max:9999999', 'peso_promedio' => 'nullable|numeric|min:0|max:20',
            'huevos' => 'nullable|integer|min:0|max:9999999', 'observacion' => 'nullable|string|max:150']);

        return $this->volver(fn () => AvicolaGranja::registrar(Auth::user(), $id, $d), 'Registro del día guardado.');
    }

    public function cerrarLote(int $id): RedirectResponse
    {
        $this->sucursal();

        return $this->volver(fn () => AvicolaGranja::cerrar(Auth::user(), $id), 'Lote cerrado.');
    }

    // ------------------------------------------------------------------ mermas

    public function mermas(Request $request): View
    {
        $suc = $this->sucursal();
        [$desde, $hasta] = $this->rango($request, 30);

        return view('empresas.avicola.mermas', [
            'productos' => Avicola::productos($suc), 'desde' => $desde, 'hasta' => $hasta,
            'mermas' => DB::table('avi_mermas as m')->leftJoin('productos as p', 'p.IdProducto', '=', 'm.IdProducto')->where('m.id_empresa_negocio', $suc)
                ->whereBetween('m.fecha', [$desde->toDateString(), $hasta->toDateString()])->orderByDesc('m.fecha')->orderByDesc('m.id')->get(['m.*', 'p.pronom']),
        ]);
    }

    public function registrarMerma(Request $request): RedirectResponse
    {
        $this->sucursal();
        $d = $request->validate(['tipo' => 'required|in:'.implode(',', array_keys(Avicola::TIPOS_MERMA)), 'IdProducto' => 'required|integer',
            'aves' => 'nullable|integer|min:0|max:99999', 'kg' => 'nullable|numeric|min:0|max:99999', 'observacion' => 'nullable|string|max:150']);

        return $this->volver(fn () => Avicola::merma(Auth::user(), $d + ['origen' => 'MANUAL', 'sale_stock' => true]), 'Merma registrada: salió del stock.');
    }

    public function anularMerma(int $id): RedirectResponse
    {
        $this->admin();

        return $this->volver(fn () => Avicola::anularMerma(Auth::user(), $id), 'Merma anulada: volvió al stock.');
    }

    // ------------------------------------------------------------------ reportes

    public function reportes(Request $request): View
    {
        $suc = $this->sucursal();
        [$desde, $hasta] = $this->rango($request, 6);

        return view('empresas.avicola.reportes', ['desde' => $desde, 'hasta' => $hasta, 'r' => AvicolaReportes::generar($suc, $desde, $hasta)]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $suc = $this->sucursal();
        [$desde, $hasta] = $this->rango($request, 6);
        $r = AvicolaReportes::generar($suc, $desde, $hasta);
        $n = fn ($x) => round((float) $x, 3);
        $precio = fn ($f) => (float) $f->kg > 0 ? round((float) $f->total / (float) $f->kg, 2) : 0;
        $ruta = (new Excel)
            ->hoja('Ventas por día', ['Día', 'Ventas', 'Jabas', 'Aves', 'Kilos', 'Total S/', 'Al crédito S/', 'Precio prom. kg'],
                $r['porDia']->map(fn ($f) => [Carbon::parse($f->dia)->format('d/m/Y'), (int) $f->ventas, (int) $f->jabas, (int) $f->aves, $n($f->kg), (float) $f->total, (float) $f->credito, $precio($f)])->all())
            ->hoja('Por cliente', ['Cliente', 'Ventas', 'Aves', 'Kilos', 'Total S/', 'Al crédito S/', 'Precio prom. kg'],
                $r['porCliente']->map(fn ($f) => [$f->clinom, (int) $f->ventas, (int) $f->aves, $n($f->kg), (float) $f->total, (float) $f->credito, $precio($f)])->all())
            ->hoja('Por producto', ['Producto', 'Ventas', 'Aves', 'Kilos', 'Total S/', 'Precio prom. kg'],
                $r['porProducto']->map(fn ($f) => [$f->pronom, (int) $f->ventas, (int) $f->aves, $n($f->kg), (float) $f->total, $precio($f)])->all())
            ->hoja('Ingresos', ['Proveedor o lote', 'Ingresos', 'Aves', 'Kilos', 'Total S/', 'Aves muertas'],
                $r['porProveedor']->map(fn ($f) => [$f->proveedor, (int) $f->ingresos, (int) $f->aves, $n($f->kg), (float) $f->total, (int) $f->muertas])->all())
            ->hoja('Mermas', ['Tipo', 'Veces', 'Aves', 'Kilos', 'Costo S/'],
                $r['mermas']->map(fn ($f, $t) => [Avicola::TIPOS_MERMA[$t][1] ?? $t, (int) $f->veces, (int) $f->aves, $n($f->kg), (float) $f->costo])->values()->all())
            ->hoja('Cuenta corriente', ['Cliente', 'Documentos', 'Debe S/', 'Vencido S/', 'Desde'],
                $r['saldos']->map(fn ($f) => [$f->clinom, (int) $f->documentos, (float) $f->saldo, (float) $f->vencido, Carbon::parse($f->desde)->format('d/m/Y')])->all())
            ->hoja('Jabas por devolver', ['Cliente', 'Jabas'], $r['jabas']->map(fn ($f) => [$f->clinom, $f->jabas])->all())
            ->guardar();

        return response()->download($ruta, 'avicola_'.$desde->toDateString().'_al_'.$hasta->toDateString().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    // ------------------------------------------------------------------ configuración

    public function configuracion(): View
    {
        $suc = $this->admin();

        return view('empresas.avicola.configuracion', ['cfg' => Avicola::config($suc), 'productos' => Avicola::productos($suc)]);
    }

    public function guardarConfiguracion(Request $request): RedirectResponse
    {
        $suc = $this->admin();
        $d = $request->validate(['tara_jaba' => 'required|numeric|min:0|max:50', 'aves_jaba' => 'required|integer|min:1|max:100']);
        Avicola::config($suc);
        DB::table('avi_config')->where('id_empresa_negocio', $suc)->update($d + ['updated_at' => now()]);

        return back()->with('success', 'Configuración guardada.');
    }

    public function crearTipicos(): RedirectResponse
    {
        $this->admin();
        $n = Avicola::crearTipicos(Auth::user());

        return back()->with('success', $n ? "Se crearon {$n} productos. Ahora pon su precio del día en Venta por peso." : 'Ya tenías todos los productos típicos.');
    }
}
