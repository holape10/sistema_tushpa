<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja una empresa "en cero" para empezar a trabajar de verdad después de las pruebas: borra los movimientos (ventas,
 * pedidos, cajas, compras, kardex, gastos…), pone el stock en 0 y reinicia los correlativos. NO toca la configuración:
 * usuarios, productos, categorías, recetas, mesas, pisos, impresoras, clientes, proveedores, medios de pago, etc.
 * Antes saca un respaldo completo de la base.
 *
 * Correlativos electrónicos: si la empresa ya envió comprobantes a SUNAT en producción, sus series (facturas, boletas,
 * notas, guías) NO se reinician: SUNAT rechazaría los números repetidos. Las notas de venta y proformas sí.
 */
class Reiniciador
{
    /** Grupo => tablas que se vacían (las que no existen en la base se saltan) */
    public const TABLAS = [
        'Ventas y comprobantes' => ['cpe_detalle', 'venta_medio_pago', 'cpe_cabecera', 'resumenes', 'ventas_cuotas', 'cuentas_cobrar_medios', 'cuentas_cobrar_detalle',
            'cuentas_cobrar', 'sire_solicitudes', 'conta_asiento_detalle', 'conta_asientos'],
        'Pedidos, comandas y reservas' => ['pedidos_detalle', 'pedidos', 'cocina_items', 'cocina_tickets', 'cola_impresion', 'reserva_detalle', 'reservas',
            'delivery_liquidaciones', 'motorizado_posiciones'],
        'Cajas y turnos' => ['movimientoscaja', 'turno_medio_pago', 'turnos'],
        'Compras y gastos' => ['compras_detalle', 'compras_cabecera', 'cuentas_pagar_medios', 'cuentas_pagar_detalle', 'cuentas_pagar', 'gastos', 'costo_historial'],
        'Kardex e inventario' => ['movimientos_productos', 'movimientos_cabecera', 'inventario_detalle', 'inventario_cabecera', 'mermas', 'porciones_movimientos',
            'producto_lote', 'producto_series'],
        'Otros documentos' => ['proforma_detalle', 'proformas', 'gre_detalle', 'gre_cabecera', 'fid_movimientos', 'est_tickets', 'hospedajes', 'hotel_eventos', 'hotel_reservas'],
    ];

    /** Series de la sucursal: [campo del número, ¿es electrónica (va a SUNAT)?] */
    private const NUMEROS = [
        'FnuEmpresa' => true, 'BnuEmpresa' => true, 'NumNCF' => true, 'NumNCB' => true, 'NumNDF' => true, 'NumNDB' => true, 'NumGuia' => true,
        'NumNota' => false, 'NumProforma' => false,
    ];

    /**
     * Lo que se va a borrar, para mostrarlo antes de confirmar.
     *
     * @return array{grupos: array<string, int>, stock: int, sunat: int, produccion: bool, reinicia_electronicos: bool}
     */
    public static function previa(Cliente $cliente): array
    {
        return Tenancy::en($cliente->base_datos, function () {
            $grupos = [];
            foreach (self::TABLAS as $grupo => $tablas) {
                $grupos[$grupo] = collect($tablas)->filter(fn ($t) => Schema::hasTable($t))->sum(fn ($t) => DB::table($t)->count());
            }
            $sunat = self::enviadosASunat();
            $produccion = (string) DB::table('empresa')->value('produccion') === '1';

            return [
                'grupos' => $grupos,
                'stock' => Schema::hasTable('producto_stock') ? DB::table('producto_stock')->where('stock', '!=', 0)->count() : 0,
                'sunat' => $sunat, 'produccion' => $produccion,
                'reinicia_electronicos' => ! ($produccion && $sunat > 0),
            ];
        });
    }

    /**
     * Respaldo y luego todo en cero. Devuelve la ruta del respaldo y el resumen.
     *
     * @return array{respaldo: string, borrados: int, reinicia_electronicos: bool}
     */
    public static function reiniciar(Cliente $cliente): array
    {
        if (Tenancy::nombreBase($cliente->ruc) !== $cliente->base_datos) {
            throw new \RuntimeException('La base registrada no coincide con el RUC; revísala a mano.');
        }
        $respaldo = Respaldos::crear($cliente->base_datos, 'antes-de-reiniciar');

        return Tenancy::en($cliente->base_datos, function () use ($respaldo) {
            $reiniciaElectronicos = ! ((string) DB::table('empresa')->value('produccion') === '1' && self::enviadosASunat() > 0);
            $borrados = 0;

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (self::TABLAS as $tablas) {
                    foreach ($tablas as $tabla) {
                        if (Schema::hasTable($tabla)) {
                            $borrados += DB::table($tabla)->count();
                            DB::table($tabla)->truncate();   // el autoincremento también vuelve a 1
                        }
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            DB::transaction(function () use ($reiniciaElectronicos) {
                if (Schema::hasTable('producto_stock')) {
                    DB::table('producto_stock')->update(['stock' => 0]);
                }
                DB::table('mesas')->update(['mes_est' => 'Libre'] + (Schema::hasColumn('mesas', 'unida_ped_id') ? ['unida_ped_id' => null] : []));
                if (Schema::hasTable('habitaciones')) {
                    DB::table('habitaciones')->update(['hab_est' => 'Libre']);
                }
                if (Schema::hasColumn('cliente', 'puntos')) {
                    DB::table('cliente')->update(['puntos' => 0]);
                }
                $numeros = collect(self::NUMEROS)->filter(fn ($electronico, $campo) => Schema::hasColumn('empresa_negocios', $campo) && ($reiniciaElectronicos || ! $electronico))
                    ->map(fn () => 0)->all();
                if ($numeros) {
                    DB::table('empresa_negocios')->update($numeros);
                }
            });

            return ['respaldo' => $respaldo, 'borrados' => $borrados, 'reinicia_electronicos' => $reiniciaElectronicos];
        });
    }

    /** Comprobantes electrónicos que ya llegaron a SUNAT (aceptados, en resumen, anulados por baja…) */
    private static function enviadosASunat(): int
    {
        return DB::table('cpe_cabecera')->whereIn('tdocod', ['01', '03', '07', '08'])
            ->whereIn('est_sunat', ['ACEPTADO', 'ENVIADO', 'EN RESUMEN', 'EN PROCESO', 'ANULADO', 'OBSERVADO'])->count();
    }
}
