<?php

namespace App\Support;

use App\Models\Turno;
use App\Models\User;
use App\Support\Sunat\SunatService;
use Illuminate\Support\Facades\DB;

/**
 * Corregir una venta que todavía no llegó a SUNAT: productos, cliente, medios de pago y el tipo de comprobante
 * (boleta ↔ factura ↔ nota de venta). Se corrige el mismo registro: el stock, los cobros y los puntos se rehacen.
 * Las ventas de mesa (comandas) conservan sus productos: solo cambian comprobante, cliente y pago.
 */
class EdicionVenta
{
    /** Por qué no se puede editar la venta (null = se puede) */
    public static function bloqueo(object $cab): ?string
    {
        if ($cab->ccabaj) {
            return 'La venta está anulada.';
        }
        if (! in_array($cab->tdocod, ['01', '03', '13'], true)) {
            return 'Solo se editan facturas, boletas y notas de venta.';
        }
        if ($cab->tdocod !== '13' && ! in_array($cab->est_sunat ?? 'PENDIENTE', SunatService::ESTADOS_REENVIABLES, true)) {
            return 'Ya se envió a SUNAT ('.$cab->est_sunat.'): para corregirla emite una nota de crédito.';
        }
        $id = (int) $cab->IdCpe_cabecera;
        if (DB::table('cpe_cabecera')->where('IdCpe_cabecera_ref', $id)->whereNull('ccabaj')->exists()) {
            return 'Tiene notas de crédito o débito.';
        }
        if ((float) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->value('abono') > 0) {
            return 'Es al crédito y ya tiene pagos registrados: anula primero esos pagos.';
        }
        if (DB::table('gre_cabecera')->where('IdCpe_cabecera', $id)->exists()) {
            return 'Tiene una guía de remisión.';
        }
        // Cuotas de socio, membresías y estacionamiento pagados con esta venta: se corrigen anulándola
        foreach (['socio_pagos', 'gym_membresias', 'est_abonados', 'est_tickets'] as $tabla) {
            if (DB::getSchemaBuilder()->hasTable($tabla) && DB::table($tabla)->where('IdCpe_cabecera', $id)->exists()) {
                return 'Es el pago de una cuota, membresía o estacionamiento: para corregirla anúlala y vuelve a cobrar.';
            }
        }
        $turno = DB::table('turnos')->where('id_turno', $cab->id_turno)->value('estado');
        if ($turno !== 'ABIERTO') {
            return 'Su turno de caja ya se cerró.';
        }

        return null;
    }

    /**
     * ¿Los productos se pueden cambiar? Las ventas de mesa no (sus comandas y opciones del menú ya salieron de cocina),
     * ni las que tienen descuento o cortesías (se cobraron con su autorización en la pantalla del restaurante).
     */
    public static function cambiaProductos(object $cab): bool
    {
        return empty($cab->ped_id) && (float) ($cab->descuento ?? 0) == 0
            && ! DB::table('cpe_detalle')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('cortesia', 1)->exists();
    }

    /**
     * La venta lista para abrirla en el Punto Venta.
     *
     * @return array{id: int, numero: string, tdocod: string, soloCabecera: bool, estadopago: int, fecVen: ?string,
     *               cliente: array, observaciones: string, medios: array, items: array}|null
     */
    public static function paraEditar(User $user, int $id): ?array
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $cab || self::bloqueo($cab)) {
            return null;
        }
        $detalle = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->orderBy('IdCpe_detalle')->get();
        $productos = CatalogoVenta::porIds($user, $detalle->pluck('IdProducto')->filter()->unique()->values()->all());
        $presentaciones = DB::table('producto_presentacion')->whereIn('IdProducto', $detalle->pluck('IdProducto')->filter())->get();

        $items = $detalle
            // Los recargos por comisión de tarjeta se vuelven a calcular al guardar
            ->reject(fn ($d) => ! $d->IdProducto && str_starts_with((string) $d->cdedes, 'RECARGO PAGO CON '))
            ->map(function ($d) use ($productos, $presentaciones) {
                $prod = $d->IdProducto ? ($productos[$d->IdProducto] ?? null) : null;
                $factor = (float) ($d->cpe_det_factor ?: 1);
                $pres = $factor != 1.0 ? $presentaciones->first(fn ($p) => (int) $p->IdProducto === (int) $d->IdProducto && (float) $p->factor === $factor) : null;
                $series = Series::lista((string) $d->series);
                if ($prod && $series) {
                    // Sus series vuelven a estar libres al guardar: se pueden volver a elegir
                    $prod['series'] = array_values(array_unique(array_merge($prod['series'] ?? [], $series)));
                }

                return [
                    'producto' => $prod, 'id' => $prod ? (int) $d->IdProducto : null, 'presentacion' => $pres?->id_presentacion,
                    'descripcion' => $d->cdedes, 'cantidad' => (float) $d->cdecan, 'precio' => (float) $d->cdepuni,
                    'series' => $series, 'no_disponible' => $d->IdProducto && ! $prod,
                ];
            })->values();

        $comisiones = DB::table('medios_pagos')->where('id_empresa_negocio', $user->id_empresa_negocio)->pluck('comision', 'id_med_pag');
        $medios = DB::table('venta_medio_pago')->where('IdCpe_cabecera', $id)->get()->map(fn ($m) => [
            'id' => (int) $m->id_med_pag,
            // El monto sin la comisión (al guardar se vuelve a sumar)
            'monto' => round((float) $m->monto / (1 + (float) ($comisiones[$m->id_med_pag] ?? 0) / 100), 2),
            'operacion' => $m->num_operacion,
        ])->values();

        return [
            'id' => (int) $cab->IdCpe_cabecera,
            'numero' => $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT),
            'tdocod' => $cab->tdocod,
            'soloCabecera' => ! self::cambiaProductos($cab),
            'estadopago' => (int) $cab->cre_dia_id,
            'fecVen' => $cab->estadopago === 'CREDITO' ? $cab->ccafve : null,
            'cliente' => ['tdicod' => (string) $cab->tdicod, 'num' => $cab->ccandi, 'nom' => $cab->ccanom, 'dir' => $cab->direccion !== '--' ? (string) $cab->direccion : ''],
            'observaciones' => (string) $cab->ccaobs,
            'medios' => $medios,
            'items' => $items,
        ];
    }

    /**
     * Guarda la corrección. $datos tiene la misma forma que una venta del Punto Venta (VentaDirecta::REGLAS).
     *
     * @return int IdCpe_cabecera
     */
    public static function editar(User $user, int $id, array $datos): int
    {
        return DB::transaction(function () use ($user, $id, $datos) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (! $cab) {
                throw new \RuntimeException('La venta no existe.');
            }
            if ($motivo = self::bloqueo($cab)) {
                throw new \RuntimeException('No se puede editar: '.$motivo);
            }
            $turno = Turno::where('id_turno', $cab->id_turno)->lockForUpdate()->first();

            $lineas = null;
            if (self::cambiaProductos($cab)) {
                $lineas = VentaDirecta::lineas($user, collect($datos['items'] ?? []));
                [$lineas, $datos] = VentaDirecta::aplicarRecargos($user, $lineas, $datos);
            } elseif (abs(round((float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->sum('cdevve'), 2) - (float) $cab->ccaitv) > 0.01) {
                throw new \RuntimeException('El detalle de la venta no cuadra con su total: revísala antes de editar.');
            }

            Comprobante::reemitir($user, $turno, $cab, $datos, $lineas);

            return $id;
        });
    }
}
