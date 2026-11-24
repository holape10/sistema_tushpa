<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guía de primeros pasos, solo para lo nuevo:
 *  - Administrador de una empresa que todavía no hizo su primera venta: los pasos para dejarla lista (datos y logo,
 *    SUNAT, series, impresora, productos y la primera venta). Cada paso se marca solo cuando ya está hecho; se oculta
 *    al hacer la primera venta o si el administrador la cierra.
 *  - Usuario nuevo que no es administrador: una bienvenida con lo que le toca hacer según su rol, hasta que diga "entendido".
 * Las empresas y usuarios que ya usaban el sistema no la ven.
 */
class PrimerosPasos
{
    /** @return list<array{titulo: string, detalle: string, hecho: bool, url: string}>|null */
    public static function guia(User $user): ?array
    {
        if (! $user->esAdmin() || ! Schema::hasColumn('empresa', 'guia_oculta')) {
            return null;
        }
        $empresa = DB::table('empresa')->where('IdEmpresa', $user->IdEmpresa)->first();
        if (! $empresa || $empresa->guia_oculta) {
            return null;
        }
        // Ya vendió: la empresa está en marcha, la guía ya no hace falta
        if (DB::table('cpe_cabecera')->where('IdEmpresa', $user->IdEmpresa)->exists()) {
            return null;
        }
        $sucursal = DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->first();

        return [
            ['titulo' => 'Datos y logo de tu empresa', 'detalle' => 'Razón social, dirección y el logo que sale en los comprobantes.',
                'hecho' => (bool) ($empresa->LogEmpresa || ($sucursal->logo_suc ?? null)), 'url' => route('empresas.edit', $user->IdEmpresa)],
            ['titulo' => 'Conexión con SUNAT', 'detalle' => 'Usuario y clave SOL, y tu certificado digital (.pfx) para enviar boletas y facturas.',
                'hecho' => $empresa->wsusuario && ($empresa->fec_fin_cer ?? null), 'url' => route('empresas.edit', $user->IdEmpresa).'#sunat'],
            ['titulo' => 'Series y correlativos', 'detalle' => 'Revisa con qué serie y número empiezan tus boletas y facturas (ej. B001, F001).',
                'hecho' => (bool) ($sucursal && $sucursal->BseEmpresa && $sucursal->FseEmpresa && $sucursal->updated_at && $sucursal->updated_at !== $sucursal->created_at),
                'url' => $sucursal ? route('sucursales.edit', $sucursal->id_empresa_negocio) : route('empresas.edit', $user->IdEmpresa)],
            ['titulo' => 'Tu impresora', 'detalle' => 'La ticketera o impresora donde salen los comprobantes (y el agente de impresión en la PC).',
                'hecho' => DB::table('configuracion_impresoras')->where('id_empresa_negocio', $user->id_empresa_negocio)->exists(), 'url' => route('impresion.index')],
            ['titulo' => 'Tus productos', 'detalle' => 'Créalos uno por uno, con voz (IA) o súbelos todos desde un Excel.',
                'hecho' => DB::table('productos')->where('id_empresa_negocio', $user->id_empresa_negocio)->exists(), 'url' => route('productos.index')],
            ['titulo' => 'Tu primera venta', 'detalle' => 'Abre la caja y haz una venta de prueba en el Punto de Venta.',
                'hecho' => false, 'url' => route('pv.index')],
        ];
    }

    /**
     * Bienvenida del usuario nuevo (no administrador): lo que le toca según su rol.
     *
     * @return list<array{texto: string, url: ?string}>|null
     */
    public static function bienvenida(User $user): ?array
    {
        if ($user->esAdmin() || ! Schema::hasColumn('users', 'bienvenida_vista') || $user->bienvenida_vista) {
            return null;
        }
        if ($user->esMozo()) {
            return [
                ['texto' => 'Elige la mesa o el tipo de pedido y agrega los platos.', 'url' => route('comandas.seleccion')],
                ['texto' => 'Envía el pedido: sale solo en la cocina o en la barra.', 'url' => null],
                ['texto' => 'Pide la precuenta cuando el cliente quiera pagar.', 'url' => null],
            ];
        }
        if ($user->puedeCobrar()) {
            return [
                ['texto' => 'Abre tu caja (turno) al empezar el día, con el efectivo que tienes.', 'url' => route('turnos.index')],
                ['texto' => 'Vende en el Punto de Venta: busca el producto, elige el comprobante y cobra.', 'url' => route('pv.index')],
                ['texto' => 'Al terminar, cierra tu caja: el sistema te dice cuánto debe haber.', 'url' => route('turnos.index')],
            ];
        }

        return [['texto' => 'Usa el menú de la izquierda: ahí está todo lo que puedes hacer con tu usuario.', 'url' => null]];
    }
}
