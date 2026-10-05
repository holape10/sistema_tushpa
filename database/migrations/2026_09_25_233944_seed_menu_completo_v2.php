<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modulos')->delete();

        DB::table('modulos')->insert([
            // PRINCIPAL
            ['mod_nom' => 'Inicio', 'mod_url' => '/dashboard', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'Dashboard', 'mod_url' => '/dashboard', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'Punto Venta', 'mod_url' => '#', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'PV Móvil', 'mod_url' => '#', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'Comandas', 'mod_url' => '/comandas', 'mod_gen' => 'Principal'],
            ['mod_nom' => 'Caja', 'mod_url' => '#', 'mod_gen' => 'Principal'],

            // VENTAS
            ['mod_nom' => 'Panel Ventas', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Ingresos', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Notas de Créditos', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Guías Remisión', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Ventas', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Ventas por Vendedor', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Ventas Delivery', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Ventas por Cliente', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Ventas por Producto', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Productos (+/-) Vendidos', 'mod_url' => '#', 'mod_gen' => 'Ventas'],
            ['mod_nom' => 'Reporte: Rentabilidad', 'mod_url' => '#', 'mod_gen' => 'Ventas'],

            // CUENTAS COBRAR
            ['mod_nom' => 'Cuentas Cobrar', 'mod_url' => '#', 'mod_gen' => 'Cuentas Cobrar'],
            ['mod_nom' => 'Reporte: Cuentas por Cobrar', 'mod_url' => '#', 'mod_gen' => 'Cuentas Cobrar'],

            // CUENTAS PAGAR
            ['mod_nom' => 'Cuentas Pagar', 'mod_url' => '#', 'mod_gen' => 'Cuentas Pagar'],

            // COMPRAS
            ['mod_nom' => 'Compras', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Gastos', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Reporte: Compras', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Reporte: Compras por Proveedor', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Reporte: Compras por Productos', 'mod_url' => '#', 'mod_gen' => 'Compras'],
            ['mod_nom' => 'Reporte: Gastos', 'mod_url' => '#', 'mod_gen' => 'Compras'],

            // ALMACÉN
            ['mod_nom' => 'Almacenes', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Inventarios', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Transferencias', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Salidas Productos', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Ingresos Productos', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Reporte: Ajustes', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Reporte: Inventario', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Kardex', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Stock Productos', 'mod_url' => '#', 'mod_gen' => 'Almacén'],
            ['mod_nom' => 'Stock General', 'mod_url' => '#', 'mod_gen' => 'Almacén'],

            // CONTROL CAJA
            ['mod_nom' => 'Listar Cajas', 'mod_url' => '#', 'mod_gen' => 'Control Caja'],

            // SUNAT
            ['mod_nom' => 'Envío de Comprobantes', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],
            ['mod_nom' => 'Resumen Diario', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],
            ['mod_nom' => 'Consulta CPE Individual', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],
            ['mod_nom' => 'Consulta CPE Masivo', 'mod_url' => '#', 'mod_gen' => 'SUNAT'],

            // CONTABILIDAD
            ['mod_nom' => 'Plan Contable', 'mod_url' => '#', 'mod_gen' => 'Contabilidad'],
            ['mod_nom' => 'Centralizar Ventas', 'mod_url' => '#', 'mod_gen' => 'Contabilidad'],
            ['mod_nom' => 'Centralizar Compras', 'mod_url' => '#', 'mod_gen' => 'Contabilidad'],
            ['mod_nom' => 'Libro Diario', 'mod_url' => '#', 'mod_gen' => 'Contabilidad'],

            // SIRE
            ['mod_nom' => 'Consultar SIRE', 'mod_url' => '#', 'mod_gen' => 'SIRE'],
            ['mod_nom' => 'Generar TXT', 'mod_url' => '#', 'mod_gen' => 'SIRE'],

            // CONTACTOS
            ['mod_nom' => 'Clientes', 'mod_url' => '#', 'mod_gen' => 'Contactos'],
            ['mod_nom' => 'Usuarios', 'mod_url' => '/usuarios', 'mod_gen' => 'Contactos'],
            ['mod_nom' => 'Proveedores', 'mod_url' => '#', 'mod_gen' => 'Contactos'],

            // ASISTENCIA
            ['mod_nom' => 'Marcar Asistencia', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Motivos', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Reporte Tareo', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Reporte de Jornadas (8h)', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Matriz de Turnos', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Gestionar Turnos', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],
            ['mod_nom' => 'Configurar IP Local', 'mod_url' => '#', 'mod_gen' => 'Asistencia'],

            // MANTENIMIENTO
            ['mod_nom' => 'Empresas', 'mod_url' => '/empresas', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Sucursales', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Tipo Cambio', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Medios de Pago', 'mod_url' => '/mediospagos', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Gestión Preparados', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Mermas', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Línea', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Categorías', 'mod_url' => '/categorias', 'mod_gen' => 'Mantenimiento'], // antes "Sub Líneas"
            ['mod_nom' => 'Productos', 'mod_url' => '/productos', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Insumos', 'mod_url' => '/productos?tipo=4', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Combos', 'mod_url' => '/productos?tipo=6', 'mod_gen' => 'Mantenimiento'],
            ['mod_nom' => 'Generar Backup', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento'],

            // FIDELIZACIÓN
            ['mod_nom' => 'Configurar Reglas', 'mod_url' => '#', 'mod_gen' => 'Fidelización'],

            // RESTAURANTE
            ['mod_nom' => 'Pisos', 'mod_url' => '/pisos', 'mod_gen' => 'Restaurante'],
            ['mod_nom' => 'Mesas', 'mod_url' => '/mesas', 'mod_gen' => 'Restaurante'],

            // FARMACIAS
            ['mod_nom' => 'Laboratorio', 'mod_url' => '#', 'mod_gen' => 'Farmacias'],
            ['mod_nom' => 'Tipos Medicamentos', 'mod_url' => '#', 'mod_gen' => 'Farmacias'],
            ['mod_nom' => 'Principios Activos', 'mod_url' => '#', 'mod_gen' => 'Farmacias'],

            // OTROS
            ['mod_nom' => 'CONCAR', 'mod_url' => '#', 'mod_gen' => 'Otros'],
            ['mod_nom' => 'Soporte', 'mod_url' => '#', 'mod_gen' => 'Otros'],
            ['mod_nom' => 'Monitor de Impresiones NUBE', 'mod_url' => '#', 'mod_gen' => 'Otros'],
        ]);

        // los admins recuperan acceso a todo el menú actualizado
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