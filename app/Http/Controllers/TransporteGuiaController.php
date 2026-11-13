<?php

namespace App\Http\Controllers;

use App\Support\Sunat\TransporteGuias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Conductores y vehículos de las guías: su lista, alta, edición y baja; y la consulta por DNI (licencia) y por placa */
class TransporteGuiaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja manejan las guías de remisión.');
    }

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $this->autorizar();
        $usoConductor = DB::table('gre_cabecera')->where('id_empresa_negocio', $this->sucursal())->whereNotNull('cond_num')
            ->groupBy('cond_num')->selectRaw('cond_num, COUNT(*) n, MAX(fecha_emision) ultima')->get()->keyBy('cond_num');
        $usoPlaca = DB::table('gre_cabecera')->where('id_empresa_negocio', $this->sucursal())->whereNotNull('placa')
            ->groupBy('placa')->selectRaw('placa, COUNT(*) n, MAX(fecha_emision) ultima')->get()->keyBy('placa');

        return view('empresas.guias.transporte', [
            'conductores' => DB::table('gre_conductores')->where('id_empresa_negocio', $this->sucursal())->orderBy('nombres')->get(),
            'vehiculos' => DB::table('gre_vehiculos')->where('id_empresa_negocio', $this->sucursal())->orderBy('placa')->get(),
            'usoConductor' => $usoConductor, 'usoPlaca' => $usoPlaca,
        ]);
    }

    public function guardarConductor(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'id' => 'nullable|integer',
            'tdicod' => 'required|in:1,4,7',
            'num' => 'required|string|max:15',
            'nombre' => 'required|string|max:200',
            'licencia' => 'nullable|string|max:15',
        ], [], ['num' => 'el N° de documento', 'nombre' => 'el nombre completo']);
        $num = preg_replace('/\s+/', '', $d['num']);
        $repetido = DB::table('gre_conductores')->where('id_empresa_negocio', $this->sucursal())->where('num', $num)
            ->when($d['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->exists();
        if ($repetido) {
            return back()->withInput()->with('error', "Ya hay un conductor con el documento {$num}.");
        }
        // Nombre completo en un campo: los dos últimos nombres son los apellidos (como pide SUNAT)
        $p = preg_split('/\s+/', mb_strtoupper(trim($d['nombre']))) ?: [];
        $corte = count($p) >= 3 ? count($p) - 2 : (count($p) === 2 ? 1 : count($p));
        $datos = ['tdicod' => $d['tdicod'], 'num' => $num, 'nombres' => implode(' ', array_slice($p, 0, $corte)),
            'apellidos' => implode(' ', array_slice($p, $corte)) ?: null, 'licencia' => strtoupper(trim((string) ($d['licencia'] ?? ''))) ?: null, 'updated_at' => now()];
        if (! empty($d['id'])) {
            DB::table('gre_conductores')->where('id', $d['id'])->where('id_empresa_negocio', $this->sucursal())->update($datos);
        } else {
            DB::table('gre_conductores')->insert($datos + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now()]);
        }

        return redirect()->route('guias.transporte')->with('success', 'Conductor guardado.');
    }

    public function guardarVehiculo(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'id' => 'nullable|integer',
            'placa' => 'required|string|max:10',
            'marca' => 'nullable|string|max:60',
            'modelo' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:60',
        ]);
        $placa = TransporteGuias::placa($d['placa']);
        $repetido = DB::table('gre_vehiculos')->where('id_empresa_negocio', $this->sucursal())->where('placa', $placa)
            ->when($d['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->exists();
        if ($repetido) {
            return back()->withInput()->with('error', "Ya está la placa {$placa}.");
        }
        $datos = ['placa' => $placa, 'updated_at' => now()]
            + array_map(fn ($v) => trim((string) $v) !== '' ? mb_strtoupper(trim($v)) : null, array_intersect_key($d, array_flip(['marca', 'modelo', 'color'])));
        if (! empty($d['id'])) {
            DB::table('gre_vehiculos')->where('id', $d['id'])->where('id_empresa_negocio', $this->sucursal())->update($datos);
        } else {
            DB::table('gre_vehiculos')->insert($datos + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now()]);
        }

        return redirect()->route('guias.transporte')->with('success', 'Vehículo guardado.');
    }

    public function eliminar(string $tipo, int $id): RedirectResponse
    {
        $this->autorizar();
        $tabla = $tipo === 'vehiculo' ? 'gre_vehiculos' : 'gre_conductores';
        DB::table($tabla)->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->delete();

        return redirect()->route('guias.transporte')->with('success', ($tipo === 'vehiculo' ? 'Vehículo' : 'Conductor').' quitado de la lista (las guías ya emitidas no cambian).');
    }

    /** Conductor por su DNI (guardado, o json.pe: nombre y licencia) */
    public function conductor(string $dni): JsonResponse
    {
        $this->autorizar();

        return response()->json(TransporteGuias::conductor($this->sucursal(), preg_replace('/\D/', '', $dni)) ?? []);
    }

    /** Vehículo por su placa (guardado, o json.pe: marca, modelo y color) */
    public function placa(string $placa): JsonResponse
    {
        $this->autorizar();

        return response()->json(TransporteGuias::vehiculo($this->sucursal(), $placa));
    }
}
