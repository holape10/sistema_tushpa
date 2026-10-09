<?php

namespace App\Support;

use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Venta directa sin mesa ni pedido (Punto Venta y PV Móvil): arma las líneas del carrito y emite el comprobante.
 * Nombre y código de los productos salen de la BD; el precio y la descripción pueden ajustarse en caja.
 */
class VentaDirecta
{
    public const REGLAS = [
        'tdocod' => 'required|in:01,03,13',
        'estadopago' => 'required|integer',
        'fecEmi' => 'required|date',
        'tdicod' => 'required|string|size:1',
        'clinum' => 'required|string|max:15',
        'clinom' => 'required|string|max:120',
        'clidir' => 'nullable|string|max:150',
        'observaciones' => 'nullable|string|max:100',
        'items' => 'required|array|min:1|max:200',
        'items.*.id' => 'nullable|integer',
        'items.*.descripcion' => 'nullable|string|max:150',
        'items.*.cantidad' => 'required|numeric|min:0.001|max:99999',
        'items.*.importe' => 'nullable|numeric|min:0.01|max:999999',
        'items.*.precio' => 'required|numeric|min:0.01|max:999999',
        'items.*.lote' => 'nullable|string|max:50',
        'items.*.presentacion' => 'nullable|integer',
        'proforma_id' => 'nullable|integer',
        'placa' => 'nullable|string|max:10',
        'guia_remision' => 'nullable|string|max:20',
        'id_med_pag' => 'nullable|array|max:10',
        'mon_med_pag' => 'nullable|array|max:10',
        'paga' => 'nullable|numeric|min:0',
    ];

    public const MENSAJES = ['items.required' => 'El carrito está vacío.'];

    public const NOMBRES = ['clinum' => 'DNI / RUC', 'clinom' => 'Nombre o razón social', 'fecEmi' => 'Fecha de emisión'];

    /**
     * @param  string  $origen  se guarda en cpe_cabecera.ped_tip (POS, PV)
     * @param  bool  $recargos  suma la comisión de cada medio de pago como una línea del comprobante
     * @return int IdCpe_cabecera
     */
    public static function registrar(User $user, array $datos, string $origen, bool $recargos = false): int
    {
        return DB::transaction(function () use ($user, $datos, $origen, $recargos) {
            // Sin turno abierto no se vende: toda venta queda amarrada a un turno de caja
            $turno = Turno::where('IdUsuario', $user->IdUsuario)
                ->where('id_empresa_negocio', $user->id_empresa_negocio)
                ->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (! $turno) {
                throw new \RuntimeException('Tu turno ya no está abierto. Apertura un turno para seguir vendiendo.');
            }

            // Cobrar una proforma: se bloquea para que no se cobre dos veces
            $proformaId = (int) ($datos['proforma_id'] ?? 0);
            if ($proformaId) {
                Proformas::bloquearPendiente($user, $proformaId);
            }

            $lineas = self::lineas($user, collect($datos['items']));
            if ($recargos) {
                [$lineas, $datos] = self::aplicarRecargos($user, $lineas, $datos);
            }

            $cabId = Comprobante::emitir($user, $turno, $datos, $lineas, [
                'ped_tip' => $origen, 'IdUsuario_ven' => $user->IdUsuario,
                'placa' => mb_strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($datos['placa'] ?? ''))) ?: null,
                'guia_remision' => mb_strtoupper(trim((string) ($datos['guia_remision'] ?? ''))) ?: null,
            ]);

            if ($proformaId) {
                Proformas::marcarFacturada($proformaId, $cabId);
            }

            return $cabId;
        });
    }

    /**
     * Mismo producto, presentación, descripción y precio = una sola línea en el comprobante.
     * Con presentación (SACO x 50) la línea lleva su factor: del stock sale cantidad x factor. También lo usan las proformas.
     */
    public static function lineas(User $user, $items): array
    {
        $productos = Producto::whereIn('IdProducto', $items->pluck('id')->filter())
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->where('proest', 'Activo')->where('promocion', '!=', 4)
            ->get()->keyBy('IdProducto');
        $presentaciones = ProductoPresentacion::whereIn('id_presentacion', $items->pluck('presentacion')->filter())
            ->where('estado', 1)->get()->keyBy('id_presentacion');

        $lineas = [];
        foreach ($items as $i) {
            $descripcion = mb_strtoupper(trim((string) ($i['descripcion'] ?? '')));

            if (! empty($i['id'])) {
                $prod = $productos[$i['id']] ?? null;
                if (! $prod) {
                    throw new \RuntimeException('Un producto del detalle ya no está disponible. Quítalo y vuelve a intentar.');
                }
                $idProducto = $prod->IdProducto;
                $descripcion = $descripcion ?: $prod->pronom;

                if (! empty($i['presentacion'])) {
                    $pres = $presentaciones[$i['presentacion']] ?? null;
                    if (! $pres || (int) $pres->IdProducto !== (int) $prod->IdProducto) {
                        throw new \RuntimeException("La presentación elegida de {$prod->pronom} ya no está disponible. Elígela de nuevo.");
                    }
                    if (! str_contains($descripcion, $pres->nombre)) {
                        $descripcion = mb_substr($descripcion.' ('.$pres->nombre.')', 0, 150);
                    }
                }
            } else {
                // Línea libre (botón +): se vende con la descripción escrita y no mueve stock
                if ($descripcion === '') {
                    throw new \RuntimeException('Escribe la descripción de las líneas agregadas a mano.');
                }
                $idProducto = null;
            }
            $pres = $idProducto && ! empty($i['presentacion']) ? $presentaciones[$i['presentacion']] : null;

            $precio = round((float) $i['precio'], 2);
            // Lote elegido en el PV Farmacia (vacío = el sistema elige el que vence antes)
            $lote = $idProducto ? (trim((string) ($i['lote'] ?? '')) ?: null) : null;
            // Venta por importe (PV Grifo: S/ 20 de combustible): el total de la línea es el importe y la cantidad lleva 3 decimales
            $importe = isset($i['importe']) && $i['importe'] !== null && $i['importe'] !== '' ? round((float) $i['importe'], 2) : null;
            // Tolerancia: lo que mueve redondear la cantidad a 3 decimales (precios altos) o 5 céntimos
            if ($importe !== null && abs($importe - round((float) $i['cantidad'], 3) * $precio) > max(0.05, $precio * 0.0005 + 0.001)) {
                throw new \RuntimeException("El importe de {$descripcion} no corresponde a la cantidad por el precio.");
            }

            $clave = ($idProducto ?? 'libre').'|'.($pres->id_presentacion ?? '').'|'.$descripcion.'|'.number_format($precio, 2, '.', '').'|'.$lote
                .($importe !== null ? '|importe'.count($lineas) : '');
            $lineas[$clave] ??= ['IdProducto' => $idProducto, 'descripcion' => $descripcion, 'cantidad' => 0, 'precio' => $precio, 'lote' => $lote,
                'presentacion' => $pres?->id_presentacion, 'factor' => $pres ? (float) $pres->factor : 1, 'umecod' => $pres?->umecod];
            $lineas[$clave]['cantidad'] = round($lineas[$clave]['cantidad'] + (float) $i['cantidad'], 3);
            if ($importe !== null) {
                $lineas[$clave]['importe'] = $importe;
            }
        }

        return array_values($lineas);
    }

    /**
     * Comisión por medio de pago (medios_pagos.comision, en %), calculada aquí y no en el navegador.
     * Los montos llegan sin comisión; cada recargo se suma a su medio y entra al comprobante como una línea.
     */
    private static function aplicarRecargos(User $user, array $lineas, array $datos): array
    {
        $ids = array_values((array) ($datos['id_med_pag'] ?? []));
        $montos = array_values((array) ($datos['mon_med_pag'] ?? []));
        $contado = DB::table('credito_dias')->where('cre_dia_id', $datos['estadopago'] ?? 0)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->value('cre_dia_tip') === 'CONTADO';
        if (! $ids || ! $contado) {
            return [$lineas, $datos];
        }

        $medios = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)
            ->whereIn('id_med_pag', $ids)->get()->keyBy('id_med_pag');

        foreach ($ids as $k => $id) {
            $medio = $medios[$id] ?? null;
            $porcentaje = (float) ($medio->comision ?? 0);
            $recargo = round((float) ($montos[$k] ?? 0) * $porcentaje / 100, 2);
            if ($recargo > 0) {
                $montos[$k] = round((float) $montos[$k] + $recargo, 2);
                $lineas[] = [
                    'IdProducto' => null, 'cantidad' => 1, 'precio' => $recargo,
                    'descripcion' => 'RECARGO PAGO CON '.mb_strtoupper($medio->nom_med_pag).' ('.rtrim(rtrim(number_format($porcentaje, 2), '0'), '.').'%)',
                ];
            }
        }

        $datos['mon_med_pag'] = $montos;

        return [$lineas, $datos];
    }

    /** Respuesta común para la pantalla de venta */
    public static function respuesta(int $cabId): array
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['serdoc', 'numdoc', 'ccaitv', 'vuelto']);

        return [
            'estado' => 'success',
            'id' => $cabId,
            'numero' => $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT),
            'total' => (float) $cab->ccaitv,
            'vuelto' => (float) $cab->vuelto,
            'ticket' => route('cobros.voucher', $cabId).'?origen=pos&embed=1',
            // Puntos del cliente (todas las pantallas de venta lo muestran en un aviso grande)
            'fidelizacion' => Fidelizacion::resumen($cabId),
        ];
    }
}
