<?php

namespace App\Http\Controllers;

use App\Support\Courier;
use App\Support\CourierManifiestos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manifiestos de carga del courier: los viajes que salen de la agencia (se cargan escaneando y se despachan) y los que
 * vienen hacia ella (se reciben escaneando bulto por bulto).
 */
class CourierManifiestoController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan los envíos.');

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

    public function index(): View
    {
        $suc = $this->sucursal();
        $user = Auth::user();
        $propias = Courier::agenciasPropias($user);
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->get()->keyBy('age_id');
        // Manifiestos que salen de mis agencias y los que traen envíos para mí
        $llegan = Courier::llegan($user, DB::table('cou_envios as e'))->whereNotNull('e.man_id')->where('e.estado', 'EN_TRANSITO')->distinct()->pluck('e.man_id');
        $manifiestos = DB::table('cou_manifiestos')->where('IdEmpresa', $user->IdEmpresa)->where('estado', '!=', 'ANULADO')
            ->where(fn ($q) => $q->whereIn('origen_id', $propias->pluck('age_id'))->orWhereIn('man_id', $llegan))
            ->orderByDesc('man_id')->limit(100)->get();
        $conteo = DB::table('cou_envios')->whereIn('man_id', $manifiestos->pluck('man_id'))->groupBy('man_id')
            ->selectRaw('man_id, COUNT(*) envios, SUM(piezas) piezas, SUM(peso_cobrado) peso')->get()->keyBy('man_id');

        return view('empresas.courier.manifiestos.index', [
            'manifiestos' => $manifiestos->map(fn ($m) => (object) ((array) $m + ['origen' => $agencias[$m->origen_id] ?? null, 'destino' => $agencias[$m->destino_id] ?? null,
                'envios' => (int) ($conteo[$m->man_id]->envios ?? 0), 'piezas' => (int) ($conteo[$m->man_id]->piezas ?? 0), 'peso' => (float) ($conteo[$m->man_id]->peso ?? 0),
                'sale' => $propias->pluck('age_id')->contains($m->origen_id), 'llega' => $llegan->contains($m->man_id)])),
            'propias' => $propias, 'agencias' => Courier::agencias((string) $user->IdEmpresa), 'medios' => CourierManifiestos::MEDIOS,
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['origen_id' => 'required|integer', 'destino_id' => 'nullable|integer', 'medio' => 'required|in:'.implode(',', array_keys(CourierManifiestos::MEDIOS)),
            'transportista' => 'nullable|string|max:150', 'vehiculo' => 'nullable|string|max:60', 'conductor' => 'nullable|string|max:150',
            'salida' => 'nullable|date', 'observacion' => 'nullable|string|max:255']);

        return $this->json(fn () => ['url' => route('courier.manifiestos.ver', CourierManifiestos::crear(Auth::user(), $d))]);
    }

    private function manifiesto(int $id): object
    {
        $this->sucursal();
        $m = DB::table('cou_manifiestos')->where('man_id', $id)->where('IdEmpresa', Auth::user()->IdEmpresa)->first();
        abort_unless($m, 404);

        return $m;
    }

    public function ver(int $id): View
    {
        $m = $this->manifiesto($id);
        $user = Auth::user();
        $suc = (int) $user->id_empresa_negocio;
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->get()->keyBy('age_id');
        $envios = DB::table('cou_envios')->where('man_id', $id)->orderBy('env_id')->get()
            ->map(fn ($e) => (object) ((array) $e + ['destino' => $agencias[$e->destino_id] ?? null, 'miDestino' => Courier::lado($e, $suc)['destino']]));
        $sale = Courier::agenciasPropias($user)->pluck('age_id')->contains($m->origen_id);
        // Para cargar: lo que espera salir de la agencia del manifiesto hacia su destino (o a cualquiera)
        $porCargar = $m->estado === 'ABIERTO' && $sale
            ? DB::table('cou_envios')->where('origen_id', $m->origen_id)->where('estado', 'REGISTRADO')->whereNull('man_id')
                ->when($m->destino_id, fn ($q) => $q->where('destino_id', $m->destino_id))->orderBy('env_id')->get()
                ->map(fn ($e) => (object) ((array) $e + ['destino' => $agencias[$e->destino_id] ?? null]))
            : collect();

        return view('empresas.courier.manifiestos.ver', [
            'm' => $m, 'origen' => $agencias[$m->origen_id] ?? null, 'destino' => $agencias[$m->destino_id] ?? null,
            'envios' => $envios, 'porCargar' => $porCargar, 'sale' => $sale,
            'recibe' => $m->estado === 'EN_VIAJE' && $envios->where('estado', 'EN_TRANSITO')->where('miDestino', true)->isNotEmpty(),
            'medios' => CourierManifiestos::MEDIOS, 'estados' => CourierManifiestos::ESTADOS,
        ]);
    }

    /** Sube un envío escaneado (o elegido de la lista) */
    public function cargar(Request $request, int $id): JsonResponse
    {
        $this->manifiesto($id);
        $d = $request->validate(['leido' => 'required|string|max:200']);

        return $this->json(fn () => CourierManifiestos::cargar(Auth::user(), $id, $d['leido']));
    }

    public function quitar(int $id, int $env): JsonResponse
    {
        $this->manifiesto($id);

        return $this->json(function () use ($id, $env) {
            CourierManifiestos::quitar(Auth::user(), $id, $env);

            return ['mensaje' => 'Quitado del manifiesto.'];
        });
    }

    public function despachar(int $id): JsonResponse
    {
        $this->manifiesto($id);

        return $this->json(fn () => ['mensaje' => CourierManifiestos::despachar(Auth::user(), $id).' envío(s) en camino.']);
    }

    /** Recepción en destino: con lo escaneado (env_id => bultos) o todo sin escanear */
    public function recibir(Request $request, int $id): JsonResponse
    {
        $this->manifiesto($id);
        $d = $request->validate(['escaneados' => 'nullable|array', 'escaneados.*' => 'integer|min:0|max:999', 'sin_escanear' => 'nullable|boolean']);

        return $this->json(function () use ($id, $d) {
            $r = CourierManifiestos::recibir(Auth::user(), $id, ! empty($d['sin_escanear']) ? null : array_map('intval', (array) ($d['escaneados'] ?? [])));

            return ['mensaje' => count($r['completos']).' completo(s)'.($r['incompletos'] ? ', '.count($r['incompletos']).' incompleto(s)' : '')
                .($r['faltantes'] ? ', '.count($r['faltantes']).' no llegaron' : '').'.', 'resumen' => $r];
        });
    }

    public function anular(int $id): JsonResponse
    {
        $this->manifiesto($id);

        return $this->json(function () use ($id) {
            CourierManifiestos::anular(Auth::user(), $id);

            return ['mensaje' => 'Manifiesto anulado: sus envíos vuelven a estar por despachar.'];
        });
    }

    /** Hoja del manifiesto para el transportista (A4) */
    public function imprimir(int $id): View
    {
        $m = $this->manifiesto($id);
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', Auth::user()->IdEmpresa)->get()->keyBy('age_id');

        return view('empresas.courier.manifiestos.imprimir', [
            'm' => $m, 'origen' => $agencias[$m->origen_id] ?? null, 'destino' => $agencias[$m->destino_id] ?? null, 'agencias' => $agencias,
            'envios' => DB::table('cou_envios')->where('man_id', $id)->orderBy('destino_id')->orderBy('env_id')->get(),
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(['IdEmpresa', 'NomEmpresa']),
            'medios' => CourierManifiestos::MEDIOS,
        ]);
    }
}
