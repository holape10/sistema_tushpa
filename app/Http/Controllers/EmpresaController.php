<?php
namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Almacen;
use App\Models\MedioPago;
use App\Models\TipoProducto;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmpresaController extends Controller
{
    public function crearempresa()
    {
        return view('empresas.configurar');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rucEmpresa'   => 'required|size:11|unique:empresa,IdEmpresa',
            'nomEmpresa'   => 'required|string|max:255',
            'dirEmpresa'   => 'required|string|max:255',
        ], [], [
            'rucEmpresa' => 'RUC',
            'nomEmpresa' => 'Razón Social',
            'dirEmpresa' => 'Dirección',
        ]);

        DB::transaction(function () use ($request) {
            $empresa = Empresa::create([
                'IdEmpresa'   => $request->rucEmpresa,
                'NomEmpresa'  => $request->nomEmpresa,
                'DirEmpresa'  => $request->dirEmpresa,
                'tipo_envio'  => $request->envio ?? 1,
                'produccion'  => $request->produccion,
                'formato'     => $request->formato ?? 'ticket',
                'icbper'      => $request->icbper,
                'EstEmpresa'  => 'Activo',
            ]);

            $sucursal = EmpresaNegocio::create([
                'IdEmpresa'        => $empresa->IdEmpresa,
                'tipo_negocio'     => 'Oficina Principal - '.$empresa->IdEmpresa,
                'nombre_comercial' => $request->NomComercial ?? $request->nomEmpresa,
                'direccion'        => $request->dirEmpresa,
                'ubigeo'           => $request->ubigeo,
                'estado'           => 'Activo',
            ]);

            // ALMACÉN PRINCIPAL (esto era lo que faltaba)
            $almacen = Almacen::create([
                'descripcion'        => 'ALMACEN PRINCIPAL',
                'predeterminado'     => 1,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'direccion'          => $request->dirEmpresa,
                'ubigeo'             => $request->ubigeo,
            ]);

            $empleado = Empleado::create([
                'emp_nom'            => $empresa->IdEmpresa,
                'emp_ape_pat'        => $empresa->NomEmpresa,
                'emp_ape_mat'        => '.',
                'emp_num_doc'        => $empresa->IdEmpresa,
                'tdicod'             => '6',
                'rol_id'             => 2, // admin
                'est_cod'            => '1',
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
            ]);

            $usuario = User::create([
                'name'               => $empresa->IdEmpresa,
                'apeusu'             => $empresa->NomEmpresa,
                'email'              => $empresa->IdEmpresa, // login = RUC
                'password'           => bcrypt($empresa->IdEmpresa),
                'estusu'             => 1,
                'IdEmpresa'          => $empresa->IdEmpresa,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'emp_id'             => $empleado->emp_id,
            ]);

            DB::table('role_user')->insert([
                'role_id'            => 2,
                'user_IdUsuario'     => $usuario->IdUsuario,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
            ]);

            $modIds = DB::table('modulos')->pluck('mod_id');
            $usuario->modulos()->sync($modIds);

            // MEDIO DE PAGO PREDETERMINADO
            MedioPago::create([
                'IdEmpresa'          => $empresa->IdEmpresa,
                'nom_med_pag'        => 'EFECTIVO',
                'predeterminado'     => '1',
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
            ]);

            // CRÉDITO: CONTADO Y CRÉDITO
            DB::table('credito_dias')->insert([
                'IdEmpresa'          => $empresa->IdEmpresa,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'cre_dia_nom'        => 'CONTADO',
                'cre_dia_fac'        => 0,
                'cre_dia_tip'        => 'CONTADO',
            ]);
            DB::table('credito_dias')->insert([
                'IdEmpresa'          => $empresa->IdEmpresa,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'cre_dia_nom'        => 'CREDITO',
                'cre_dia_fac'        => 0,
                'cre_dia_tip'        => 'PERSONALIZADO',
            ]);

            // TIPO PRODUCTO / CATEGORÍA / SUBCATEGORÍA GENERAL
            $tipoProducto = TipoProducto::create([
                'tip_pro_nom'        => 'GENERAL',
                'IdEmpresa'          => $empresa->IdEmpresa,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
            ]);

            $categoria = Categoria::create([
                'IdEmpresa'          => $empresa->IdEmpresa,
                'color'              => '#3f4aee',
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'predeterminado'     => 1,
                'cat_nom'            => 'GENERAL',
                'tip_pro_id'         => $tipoProducto->tip_pro_id,
            ]);

            Subcategoria::create([
                'color'              => '#3f4aee',
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'subcat_nom'         => 'GENERAL',
                'cat_id'             => $categoria->cat_id,
                'IdEmpresa'          => $empresa->IdEmpresa,
            ]);

            // NOTA: el bucle de "productos" de tu sistema viejo (asignar producto_empresa /
            // producto_stock a la nueva sucursal) lo dejamos pendiente porque aún no
            // creamos las tablas `productos`, `producto_empresa` ni `producto_stock`.
            // Lo agregamos cuando armemos el módulo de Productos.
        });

        return redirect()->route('login')
            ->with('success', 'Empresa registrada. Ingresa con tu RUC como usuario y contraseña.');
    }

    public function consultaRucSunat($ruc)
    {
        $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
            ->get("https://consultas.holape.app/api/v1/ruc/{$ruc}");

        $data = $response->json();

        if (!empty($data['success'])) {
            return response()->json([
                'nom'    => $data['data']['razon_social'],
                'dir'    => $data['data']['direccion'],
                'ubigeo' => $data['data']['ubigeo'],
            ]);
        }

        return response()->json(['error' => 'RUC no encontrado o no válido'], 404);
    }
}