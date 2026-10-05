<?php
namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Almacenes de la sucursal. El predeterminado es con el que trabaja el sistema:
 * ventas, compras y reportes descuentan / ingresan stock en él.
 */
class AlmacenController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador gestiona los almacenes.');
    }

    private function buscar(int $id): Almacen
    {
        return Almacen::where('id_almacen', $id)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->firstOrFail();
    }

    public function index()
    {
        $almacenes = Almacen::where('almacenes.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->orderByDesc('predeterminado')->orderBy('descripcion')
            ->select('almacenes.*',
                DB::raw('(SELECT COUNT(*) FROM producto_stock ps WHERE ps.id_almacen = almacenes.id_almacen AND ps.stock <> 0) as productos'),
                DB::raw('(SELECT COALESCE(SUM(ps.stock * p.costo), 0) FROM producto_stock ps JOIN productos p ON p.IdProducto = ps.IdProducto
                          WHERE ps.id_almacen = almacenes.id_almacen AND ps.stock > 0) as valorizado'),
                DB::raw('(SELECT COUNT(*) FROM movimientos_productos mp WHERE mp.id_almacen = almacenes.id_almacen) as movimientos'))
            ->get();

        return view('empresas.almacenes.index', compact('almacenes'));
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $datos = $request->validate([
            'descripcion' => 'required|string|max:150',
            'codigo'      => 'nullable|string|max:4',
            'direccion'   => 'nullable|string|max:150',
            'ubigeo'      => 'nullable|string|max:100',
        ], [], ['descripcion' => 'Nombre del almacén', 'codigo' => 'Código']);

        $datos['descripcion'] = mb_strtoupper(trim($datos['descripcion']));
        $repetido = Almacen::where('id_empresa_negocio', $sucursal)->where('descripcion', $datos['descripcion'])
            ->when($id, fn($q) => $q->where('id_almacen', '!=', $id))->exists();
        if ($repetido) {
            throw \Illuminate\Validation\ValidationException::withMessages(['descripcion' => 'Ya existe un almacén con ese nombre.']);
        }

        return array_map(fn($v) => $v ?? '', $datos);
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $datos = $this->validar($request);
        $sucursal = Auth::user()->id_empresa_negocio;

        Almacen::create($datos + [
            'id_empresa_negocio' => $sucursal,
            // El primer almacén de la sucursal queda como predeterminado
            'predeterminado' => Almacen::where('id_empresa_negocio', $sucursal)->exists() ? 0 : 1,
        ]);

        return back()->with('success', 'Almacén registrado.');
    }

    public function update(Request $request, int $id)
    {
        $this->autorizar();
        $this->buscar($id)->update($this->validar($request, $id));
        return back()->with('success', 'Almacén actualizado.');
    }

    /** Cambia el almacén con el que trabaja el sistema */
    public function predeterminado(int $id)
    {
        $this->autorizar();
        $almacen = $this->buscar($id);

        DB::transaction(function () use ($almacen) {
            Almacen::where('id_empresa_negocio', $almacen->id_empresa_negocio)->update(['predeterminado' => 0]);
            $almacen->update(['predeterminado' => 1]);
        });

        return back()->with('success', "Ahora el sistema trabaja con el almacén {$almacen->descripcion}.");
    }

    public function destroy(int $id)
    {
        $this->autorizar();
        $almacen = $this->buscar($id);

        if ($almacen->predeterminado) {
            return back()->withErrors(['almacen' => 'No se puede eliminar el almacén predeterminado. Marca otro como predeterminado primero.']);
        }
        $usado = DB::table('movimientos_productos')->where('id_almacen', $id)->exists()
            || DB::table('producto_stock')->where('id_almacen', $id)->where('stock', '<>', 0)->exists();
        if ($usado) {
            return back()->withErrors(['almacen' => 'El almacén tiene stock o movimientos en el kardex; no se puede eliminar.']);
        }

        DB::transaction(function () use ($almacen) {
            DB::table('producto_stock')->where('id_almacen', $almacen->id_almacen)->delete();
            $almacen->delete();
        });

        return back()->with('success', 'Almacén eliminado.');
    }
}
