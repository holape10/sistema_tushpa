<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->delete(); // limpia los 6 de prueba (el delete sí respeta FKs, a diferencia de truncate)

        DB::table('modulos')->insert([
            ['mod_nom' => 'Dashboard', 'mod_url' => '/dashboard', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'Punto Venta', 'mod_url' => '#', 'mod_gen' => 'Principal'],

            ['mod_nom' => 'Panel Ventas', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Ingresos', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Notas de Crédito', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Guías Remisión', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte de Ventas', 'mod_url' => '#', 'mod_gen' => 'Ventas'],

            ['mod_nom' => 'Cuentas Cobrar', 'mod_url' => '#', 'mod_gen' => 'Cuentas Cobrar'],
            ['mod_nom' => 'Cuentas Pagar', 'mod_url' => '#', 'mod_gen' => 'Cuentas Pagar'],

            ['mod_nom' => 'Compras', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Gastos', 'mod_url' => '#', 'mod_gen' => 'Compras'],

            ['mod_nom' => 'Almacenes', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Inventarios', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Transferencias', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Salidas Productos', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Ingresos Productos', 'mod_url' => '#', 'mod_gen' => 'Almacén'],

            ['mod_nom' => 'Listar Cajas', 'mod_url' => '#', 'mod_gen' => 'Control Caja'],

            ['mod_nom' => 'Envío de Comprobantes', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],
            ['mod_nom' => 'Resumen Diario', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],

            ['mod_nom' => 'Clientes', 'mod_url' => '#', 'mod_gen' => 'Contactos'],
            ['mod_nom' => 'Usuarios', 'mod_url' => '/usuarios', 'mod_gen' => 'Contactos'],
            ['mod_nom' => 'Proveedores', 'mod_url' => '#', 'mod_gen' => 'Contactos'],

            ['mod_nom' => 'Marcar Asistencia', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Motivos', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],

            ['mod_nom' => 'Empresas', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Sucursales', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Tipo Cambio', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Medios de Pago', 'mod_url' => '/mediospagos', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Línea', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Sub Líneas', 'mod_url' => '/categorias', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Productos', 'mod_url' => '/productos', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Insumos', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Combos', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],

            ['mod_nom' => 'Pisos', 'mod_url' => '/pisos', 'mod_gen' => 'Restaurante'],
            ['mod_nom' => 'Mesas', 'mod_url' => '/mesas', 'mod_gen' => 'Restaurante'],

            ['mod_nom' => 'Soporte', 'mod_url' => '#', 'mod_gen' => 'Otros'],
        ]);

        // los usuarios con rol admin recuperan acceso a todo el menú actualizado
        $adminIds = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $modIds   = DB::table('modulos')->pluck('mod_id');

        foreach ($adminIds as $userId) {
            foreach ($modIds as $modId) {
                DB::table('modulos_usuario')->insertOrIgnore([
                    'user_IdUsuario' => $userId,
                    'mod_id'         => $modId,
                ]);
            }
        }
    }
    public function down(): void {}
};