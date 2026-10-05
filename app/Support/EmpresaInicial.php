<?php
namespace App\Support;

use App\Models\{Almacen, Categoria, Empleado, Empresa, EmpresaNegocio, MedioPago, Subcategoria, TipoProducto, User};
use Illuminate\Support\Facades\DB;

/**
 * Datos con los que arranca una empresa: sucursal principal, almacén, usuario administrador con todo el menú,
 * medio de pago EFECTIVO, contado/crédito y categoría GENERAL.
 * Lo usan el registro inicial (/config) y el panel multi-empresa. Debe llamarse dentro de una transacción.
 */
class EmpresaInicial
{
    /**
     * @param array $d ruc, razon_social, nombre_comercial, direccion, ubigeo, usuario, password,
     *                 y opcionales de configuración: envio, produccion, formato, icbper
     */
    public static function crear(array $d): User
    {
        $empresa = Empresa::create([
            'IdEmpresa'   => $d['ruc'],
            'NomEmpresa'  => $d['razon_social'],
            'DirEmpresa'  => $d['direccion'],
            'tipo_envio'  => $d['envio'] ?? 1,
            'produccion'  => $d['produccion'] ?? null,
            'formato'     => $d['formato'] ?? 'ticket',
            'icbper'      => $d['icbper'] ?? null,
            'EstEmpresa'  => 'Activo',
        ]);

        $sucursal = EmpresaNegocio::create([
            'IdEmpresa'        => $empresa->IdEmpresa,
            'tipo_negocio'     => 'Oficina Principal - ' . $empresa->IdEmpresa,
            'nombre_comercial' => ($d['nombre_comercial'] ?? null) ?: $d['razon_social'],
            'direccion'        => $d['direccion'],
            'ubigeo'           => $d['ubigeo'] ?? null,
            'estado'           => 'Activo',
        ]);

        Almacen::create([
            'descripcion'        => 'ALMACEN PRINCIPAL',
            'predeterminado'     => 1,
            'id_empresa_negocio' => $sucursal->id_empresa_negocio,
            'direccion'          => $d['direccion'],
            'ubigeo'             => $d['ubigeo'] ?? null,
        ]);

        $empleado = Empleado::create([
            'emp_nom'            => $empresa->IdEmpresa,
            'emp_ape_pat'        => $empresa->NomEmpresa,
            'emp_ape_mat'        => '.',
            'emp_num_doc'        => $empresa->IdEmpresa,
            'tdicod'             => '6',
            'rol_id'             => 2,
            'est_cod'            => '1',
            'id_empresa_negocio' => $sucursal->id_empresa_negocio,
        ]);

        $usuario = User::create([
            'name'               => $empresa->IdEmpresa,
            'apeusu'             => $empresa->NomEmpresa,
            'email'              => $d['usuario'],
            'password'           => bcrypt($d['password']),
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

        $usuario->modulos()->sync(DB::table('modulos')->pluck('mod_id'));

        MedioPago::create([
            'IdEmpresa'          => $empresa->IdEmpresa,
            'nom_med_pag'        => 'EFECTIVO',
            'predeterminado'     => '1',
            'id_empresa_negocio' => $sucursal->id_empresa_negocio,
        ]);

        foreach ([['CONTADO', 'CONTADO'], ['CREDITO', 'PERSONALIZADO']] as [$nombre, $tipo]) {
            DB::table('credito_dias')->insert([
                'IdEmpresa'          => $empresa->IdEmpresa,
                'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'cre_dia_nom'        => $nombre,
                'cre_dia_fac'        => 0,
                'cre_dia_tip'        => $tipo,
            ]);
        }

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

        return $usuario;
    }
}
