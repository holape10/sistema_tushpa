<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Piso;
use App\Support\MesasUnidas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class MesaController extends Controller
{
    /** Máximo de mesas por creación en lote */
    private const MAX_LOTE = 200;

    public function index(Request $request)
    {
        $query = Mesa::with('piso')
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio);

        // Búsqueda
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('mes_nom', 'LIKE', "%{$search}%")
                    ->orWhereHas('piso', function ($pq) use ($search) {
                        $pq->where('pis_nom', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Ordenamiento por piso y luego por nombre de mesa
        $mesas = $query->orderByRaw('
            CASE
                WHEN pis_id IS NULL THEN 1
                ELSE 0
            END,
            pis_id ASC,
            mes_nom ASC
        ')->get();

        // Para "Crear varias mesas": los pisos y los nombres que ya tiene cada piso
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderBy('pis_nom')->get(['pis_id', 'pis_nom']);
        $nombres = $this->nombresPorPiso();

        return view('empresas.mesas.index', compact('mesas', 'pisos', 'nombres'));
    }

    public function create()
    {
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();

        return view('empresas.mesas.create', compact('pisos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'mes_nom' => 'required|string|max:255',
            'pis_id' => ['required', $this->pisoDeLaSucursal()],
        ], [], ['mes_nom' => 'Nombre de la mesa', 'pis_id' => 'Piso']);
        $this->noRepetidaEnElPiso($request->mes_nom, (int) $request->pis_id);

        Mesa::create([
            'mes_nom' => $request->mes_nom,
            'pis_id' => $request->pis_id,
            'mes_est' => 'Libre',
            'IdEmpresa' => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('mesas.index')->with('success', 'Mesa creada.');
    }

    /**
     * Crea varias mesas de una vez en un piso: "MESA 01" … "MESA 20".
     * Las que ya existen en ese mismo piso se saltan. En otro piso sí se pueden repetir los números
     * (PISO 01 - MESA 03 y PISO 02 - MESA 03), pero el usuario debe confirmarlo.
     */
    public function lote(Request $request)
    {
        $d = $request->validate([
            'pis_id' => ['required', $this->pisoDeLaSucursal()],
            'prefijo' => 'required|string|max:30',
            'desde' => 'required|integer|min:0|max:9999',
            'hasta' => 'required|integer|gte:desde|max:9999',
        ], ['hasta.gte' => 'El número final debe ser mayor o igual al inicial.'],
            ['pis_id' => 'Piso', 'prefijo' => 'Nombre', 'desde' => 'Desde el número', 'hasta' => 'Hasta el número']);

        $cantidad = $d['hasta'] - $d['desde'] + 1;
        if ($cantidad > self::MAX_LOTE) {
            return back()->withInput()->withErrors(['hasta' => 'Puedes crear hasta '.self::MAX_LOTE.' mesas a la vez.']);
        }

        $sucursal = Auth::user()->id_empresa_negocio;
        $porPiso = $this->nombresPorPiso();
        $enEstePiso = array_flip($porPiso[$d['pis_id']] ?? []);
        $enOtrosPisos = collect($porPiso)->except($d['pis_id'])->flatten()->flip();
        $prefijo = mb_strtoupper(trim($d['prefijo']));
        $digitos = max(2, strlen((string) $d['hasta']));

        $nuevas = [];
        $omitidas = 0;
        $repetidas = 0;
        for ($n = $d['desde']; $n <= $d['hasta']; $n++) {
            $nombre = $prefijo.' '.str_pad((string) $n, $digitos, '0', STR_PAD_LEFT);
            if (isset($enEstePiso[$nombre])) {
                $omitidas++;

                continue;
            }
            $repetidas += isset($enOtrosPisos[$nombre]) ? 1 : 0;
            $nuevas[] = ['mes_nom' => $nombre, 'pis_id' => $d['pis_id'], 'mes_est' => 'Libre',
                'IdEmpresa' => Auth::user()->IdEmpresa, 'id_empresa_negocio' => $sucursal];
        }
        if ($repetidas && ! $request->boolean('repetir_en_otro_piso')) {
            return back()->withInput()->withErrors(['repetir_en_otro_piso' => "{$repetidas} nombres ya existen en otro piso. Confirma que quieres repetirlos (en la comanda saldrá el piso delante)."]);
        }
        Mesa::insert($nuevas);

        $piso = Piso::where('pis_id', $d['pis_id'])->value('pis_nom');
        $mensaje = count($nuevas)
            ? 'Se crearon '.count($nuevas).' mesas en '.$piso.'.'.($omitidas ? " Se saltaron {$omitidas} que ya existían." : '')
            : 'No se creó ninguna mesa: todas ya existían.';

        return redirect()->route('mesas.index')->with('success', $mensaje);
    }

    public function edit(Mesa $mesa)
    {
        $this->deLaSucursal($mesa);
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();

        return view('empresas.mesas.edit', compact('mesa', 'pisos'));
    }

    public function update(Request $request, Mesa $mesa)
    {
        $this->deLaSucursal($mesa);
        $request->validate([
            'mes_nom' => 'required|string|max:255',
            'pis_id' => ['required', $this->pisoDeLaSucursal()],
        ], [], ['mes_nom' => 'Nombre de la mesa', 'pis_id' => 'Piso']);
        $this->noRepetidaEnElPiso($request->mes_nom, (int) $request->pis_id, $mesa->mes_id);

        $mesa->update($request->only(['mes_nom', 'pis_id', 'mes_est']));

        return redirect()->route('mesas.index')->with('success', 'Mesa actualizada.');
    }

    public function destroy(Mesa $mesa)
    {
        $this->deLaSucursal($mesa);
        if (MesasUnidas::pedidoDe($mesa->mes_id)) {
            return back()->withErrors(['mesa' => "La {$mesa->mes_nom} está ocupada: cóbrala o libérala antes de eliminarla."]);
        }
        $mesa->delete();

        return back()->with('success', 'Mesa eliminada.');
    }

    /**
     * Nombres de mesa de cada piso de la sucursal, en mayúsculas.
     *
     * @return array<int, array<int, string>> pis_id => nombres
     */
    private function nombresPorPiso(): array
    {
        return Mesa::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get(['pis_id', 'mes_nom'])
            ->groupBy('pis_id')->map(fn ($m) => $m->map(fn ($x) => mb_strtoupper(trim((string) $x->mes_nom)))->values()->all())->all();
    }

    /** En un mismo piso no puede haber dos mesas con el mismo nombre (en pisos distintos sí) */
    private function noRepetidaEnElPiso(string $nombre, int $piso, ?int $exceptoMesa = null): void
    {
        $existe = Mesa::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->where('pis_id', $piso)
            ->whereRaw('UPPER(TRIM(mes_nom)) = ?', [mb_strtoupper(trim($nombre))])
            ->when($exceptoMesa, fn ($q) => $q->where('mes_id', '!=', $exceptoMesa))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['mes_nom' => 'Ya hay una mesa con ese nombre en este piso.']);
        }
    }

    /** Solo se tocan las mesas de la sucursal del usuario */
    private function deLaSucursal(Mesa $mesa): void
    {
        abort_unless((int) $mesa->id_empresa_negocio === (int) Auth::user()->id_empresa_negocio, 404);
    }

    private function pisoDeLaSucursal(): Exists
    {
        return Rule::exists('pisos', 'pis_id')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio);
    }
}
