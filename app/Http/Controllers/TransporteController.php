<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Support\Bitacora;
use App\Support\Impresion;
use App\Support\Transportes;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Transportes: venta de pasajes sobre el plano de asientos, salidas, unidades (con su plano) y rutas, y el manifiesto
 * de pasajeros. Vende quien cobra (con su turno de caja); lo demás lo configura el administrador.
 */
class TransporteController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function admin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura rutas, unidades y salidas.');
    }

    private function vende(): void
    {
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja venden pasajes.');
    }

    private function salida(int $id): object
    {
        $s = DB::table('tra_salidas')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($s, 404);

        return $s;
    }

    /** Salidas con su ruta y unidad, y cuántos asientos van vendidos */
    private function salidas()
    {
        return DB::table('tra_salidas as s')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')->join('tra_unidades as u', 'u.id', '=', 's.unidad_id')
            ->where('s.id_empresa_negocio', $this->sucursal())
            ->select('s.*', 'r.origen', 'r.destino', 'u.nombre as unidad', 'u.placa', 'u.asientos', 'u.tipo')
            ->selectSub(DB::table('tra_pasajes as p')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
                ->whereColumn('p.salida_id', 's.id')->where('p.estado', 'VENDIDO')->whereNull('c.ccabaj')->selectRaw('COUNT(*)'), 'vendidos');
    }

    // ------------------------------------------------------------------ venta

    public function index(Request $request): View
    {
        $this->vende();
        $proximas = $this->salidas()->where('s.estado', 'PROGRAMADA')->where('s.salida', '>=', now()->subHours(2))
            ->where('s.salida', '<=', now()->addDays(14))->orderBy('s.salida')->get();
        $elegida = $request->filled('salida') ? $proximas->firstWhere('id', (int) $request->salida) : null;
        $datos = null;
        if ($elegida) {
            $unidad = DB::table('tra_unidades')->where('id', $elegida->unidad_id)->first();
            $plano = Transportes::plano($unidad);
            $precios = [];
            foreach (Transportes::asientosDe($plano) as $a) {
                $precios[$a['cat']] ??= Transportes::precio($elegida, $a['cat']);
            }
            $datos = ['plano' => $plano, 'ocupados' => collect(Transportes::ocupados($elegida->id))->map(fn ($p) => $p->nombre)->all(), 'precios' => $precios];
        }
        $medios = MedioPago::where('id_empresa_negocio', $this->sucursal())->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']);

        return view('empresas.transportes.venta', compact('proximas', 'elegida', 'datos', 'medios'));
    }

    public function vender(Request $request, int $id): JsonResponse
    {
        $this->vende();
        $this->salida($id);
        $d = $request->validate([
            'pasajeros' => 'required|array|min:1|max:60', 'pasajeros.*.asiento' => 'required|string|max:8',
            'pasajeros.*.tdicod' => 'required|string|size:1', 'pasajeros.*.doc' => 'required|string|max:15', 'pasajeros.*.nombre' => 'required|string|max:150',
            'pasajeros.*.telefono' => 'nullable|string|max:20',
            'tdocod' => 'required|in:01,03,13', 'clinum' => 'nullable|string|max:15', 'clinom' => 'nullable|string|max:150', 'clidir' => 'nullable|string|max:200',
            'id_med_pag' => 'nullable|integer', 'paga' => 'nullable|numeric|min:0', 'imprimir' => 'nullable|boolean',
        ], [], ['pasajeros.*.doc' => 'documento del pasajero', 'pasajeros.*.nombre' => 'nombre del pasajero']);
        try {
            $cabId = Transportes::vender(Auth::user(), $id, $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        $impreso = false;
        if ($d['imprimir'] ?? false) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'comprobante' => VentaDirecta::respuesta($cabId) + ['impreso' => $impreso]]);
    }

    public function manifiesto(int $id): View
    {
        $this->vende();
        $salida = $this->salidas()->where('s.id', $id)->first();
        abort_unless($salida, 404);

        return view('empresas.transportes.manifiesto', ['salida' => $salida, 'pasajeros' => Transportes::manifiesto($id),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->first()]);
    }

    // ------------------------------------------------------------------ salidas

    public function salidasIndex(Request $request): View
    {
        $this->admin();
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get('desde')) ? $request->get('desde') : now()->toDateString();

        return view('empresas.transportes.salidas', [
            'salidas' => $this->salidas()->where('s.salida', '>=', $desde.' 00:00:00')->orderBy('s.salida')->limit(200)->get(), 'desde' => $desde,
            'rutas' => DB::table('tra_rutas')->where('id_empresa_negocio', $this->sucursal())->where('activo', true)->orderBy('origen')->get(),
            'unidades' => DB::table('tra_unidades')->where('id_empresa_negocio', $this->sucursal())->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function salidaGuardar(Request $request): RedirectResponse
    {
        $this->admin();
        $suc = $this->sucursal();
        $d = $request->validate([
            'id' => 'nullable|integer', 'ruta_id' => ['required', Rule::exists('tra_rutas', 'id')->where('id_empresa_negocio', $suc)],
            'unidad_id' => ['required', Rule::exists('tra_unidades', 'id')->where('id_empresa_negocio', $suc)],
            'salida' => 'required|date', 'conductor' => 'nullable|string|max:120', 'precio' => 'required|numeric|min:0|max:99999',
            'precios' => 'nullable|array', 'precios.*' => 'nullable|numeric|min:0|max:99999', 'observacion' => 'nullable|string|max:200',
            'repetir' => 'nullable|integer|min:0|max:30',
        ], [], ['ruta_id' => 'ruta', 'unidad_id' => 'unidad']);
        $precios = array_filter(array_map(fn ($v) => $v === null || $v === '' ? null : round((float) $v, 2), $d['precios'] ?? []), fn ($v) => $v !== null);
        $datos = ['ruta_id' => $d['ruta_id'], 'unidad_id' => $d['unidad_id'], 'conductor' => $d['conductor'] ?? null, 'precio' => $d['precio'],
            'precios' => $precios ? json_encode($precios) : null, 'observacion' => $d['observacion'] ?? null, 'updated_at' => now()];
        if (! empty($d['id'])) {
            $salida = $this->salida((int) $d['id']);
            // Con pasajes vendidos no se cambia de unidad (los asientos son de su plano)
            if ((int) $salida->unidad_id !== (int) $d['unidad_id'] && Transportes::ocupados($salida->id)) {
                return back()->withInput()->withErrors(['unidad_id' => 'Esta salida ya tiene pasajes vendidos: no se puede cambiar de unidad.']);
            }
            DB::table('tra_salidas')->where('id', $salida->id)->update($datos + ['salida' => $d['salida']]);

            return back()->with('success', 'Salida actualizada.');
        }
        // Repetir: la misma salida los días siguientes, a la misma hora
        $veces = 1 + (int) ($d['repetir'] ?? 0);
        for ($i = 0; $i < $veces; $i++) {
            DB::table('tra_salidas')->insert($datos + ['id_empresa_negocio' => $suc, 'salida' => now()->parse($d['salida'])->addDays($i),
                'estado' => 'PROGRAMADA', 'IdUsuario' => Auth::user()->IdUsuario, 'created_at' => now()]);
        }

        return back()->with('success', $veces > 1 ? "{$veces} salidas programadas." : 'Salida programada.');
    }

    /** PROGRAMADA (vende), PARTIO (ya salió: no vende más) o CANCELADA (sin pasajes vendidos) */
    public function salidaEstado(Request $request, int $id): RedirectResponse
    {
        $this->admin();
        $salida = $this->salida($id);
        $estado = $request->validate(['estado' => 'required|in:PROGRAMADA,PARTIO,CANCELADA'])['estado'];
        if ($estado === 'CANCELADA' && Transportes::ocupados($id)) {
            return back()->withErrors(['estado' => 'Tiene pasajes vendidos: anúlalos (o cámbialos de salida) antes de cancelarla.']);
        }
        DB::table('tra_salidas')->where('id', $id)->update(['estado' => $estado, 'updated_at' => now()]);
        Bitacora::registrar('venta.salida_transporte', 'Salida del '.now()->parse($salida->salida)->format('d/m/Y H:i').' → '.$estado, null, ['referencia' => ['salida', $id]]);

        return back()->with('success', 'Salida '.mb_strtolower($estado === 'PARTIO' ? 'marcada como partida' : $estado).'.');
    }

    // ------------------------------------------------------------------ unidades

    public function unidadesIndex(): View
    {
        $this->admin();

        return view('empresas.transportes.unidades', [
            'unidades' => DB::table('tra_unidades')->where('id_empresa_negocio', $this->sucursal())->orderByDesc('activo')->orderBy('nombre')->get(),
        ]);
    }

    public function plantilla(string $clave): JsonResponse
    {
        $this->admin();
        abort_unless(isset(Transportes::PLANTILLAS[$clave]), 404);

        return response()->json(Transportes::plantilla($clave));
    }

    public function unidadGuardar(Request $request): JsonResponse
    {
        $this->admin();
        $d = $request->validate(['id' => 'nullable|integer', 'tipo' => ['required', Rule::in(array_keys(Transportes::TIPOS))], 'nombre' => 'required|string|max:80',
            'placa' => 'nullable|string|max:20', 'activo' => 'nullable|boolean', 'plano' => 'required|array', 'plano.pisos' => 'required|array|min:1|max:4']);
        $plano = Transportes::numerar($d['plano']);
        $asientos = count(Transportes::asientosDe($plano));
        if ($asientos === 0) {
            return response()->json(['ok' => false, 'mensaje' => 'El plano no tiene asientos.']);
        }
        $datos = ['tipo' => $d['tipo'], 'nombre' => mb_strtoupper(trim($d['nombre'])), 'placa' => ($d['placa'] ?? null) ? mb_strtoupper(trim($d['placa'])) : null,
            'plano' => json_encode($plano, JSON_UNESCAPED_UNICODE), 'asientos' => $asientos, 'activo' => $d['activo'] ?? true, 'updated_at' => now()];
        if (! empty($d['id'])) {
            $unidad = DB::table('tra_unidades')->where('id', $d['id'])->where('id_empresa_negocio', $this->sucursal())->first();
            abort_unless($unidad, 404);
            // Un asiento ya vendido en una salida próxima no puede desaparecer del plano
            $vendidos = DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
                ->where('s.unidad_id', $unidad->id)->where('s.estado', 'PROGRAMADA')->where('p.estado', 'VENDIDO')->whereNull('c.ccabaj')->pluck('p.asiento')->unique();
            $faltan = $vendidos->diff(array_keys(Transportes::asientosDe($plano)));
            if ($faltan->isNotEmpty()) {
                return response()->json(['ok' => false, 'mensaje' => 'Los asientos '.$faltan->implode(', ').' ya están vendidos en salidas próximas: no se pueden quitar del plano.']);
            }
            DB::table('tra_unidades')->where('id', $unidad->id)->update($datos);
            $id = $unidad->id;
        } else {
            $id = DB::table('tra_unidades')->insertGetId($datos + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now()]);
        }

        return response()->json(['ok' => true, 'id' => $id, 'plano' => $plano, 'asientos' => $asientos]);
    }

    // ------------------------------------------------------------------ rutas

    public function rutasIndex(): View
    {
        $this->admin();

        return view('empresas.transportes.rutas', [
            'rutas' => DB::table('tra_rutas')->where('id_empresa_negocio', $this->sucursal())->orderByDesc('activo')->orderBy('origen')->orderBy('destino')->get(),
        ]);
    }

    public function rutaGuardar(Request $request): RedirectResponse
    {
        $this->admin();
        $d = $request->validate(['id' => 'nullable|integer', 'origen' => 'required|string|max:80', 'destino' => 'required|string|max:80|different:origen',
            'paradas' => 'nullable|string|max:255', 'duracion_min' => 'nullable|integer|min:1|max:10000', 'precio' => 'required|numeric|min:0|max:99999',
            'activo' => 'nullable|boolean']);
        $datos = ['origen' => mb_strtoupper(trim($d['origen'])), 'destino' => mb_strtoupper(trim($d['destino'])),
            'paradas' => ($d['paradas'] ?? null) ? mb_strtoupper(trim($d['paradas'])) : null, 'duracion_min' => $d['duracion_min'] ?? null,
            'precio' => $d['precio'], 'activo' => $request->boolean('activo', true), 'updated_at' => now()];
        if (! empty($d['id'])) {
            abort_unless(DB::table('tra_rutas')->where('id', $d['id'])->where('id_empresa_negocio', $this->sucursal())->update($datos) !== false, 404);
        } else {
            DB::table('tra_rutas')->insert($datos + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now()]);
        }

        return back()->with('success', 'Ruta guardada.');
    }
}
