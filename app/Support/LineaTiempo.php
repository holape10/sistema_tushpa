<?php

namespace App\Support;

use App\Models\Mesa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Línea de tiempo del día: qué hizo cada usuario, en orden, en palabras simples. Se arma con lo que el sistema ya guarda
 * (caja, comprobantes, comandas, anulaciones, mermas, compras, gastos y proformas); no hace falta registrar nada aparte.
 *
 * Cada evento: hora, usuario, tipo (caja | venta | comanda | alerta | almacen | compra), icono, título, detalle, monto
 * y si es una alerta (anulaciones, descuentos, cortesías, platos quitados y notas de crédito).
 */
class LineaTiempo
{
    /** Tipo => [nombre para el filtro, color] */
    public const TIPOS = [
        'caja' => ['Caja', 'emerald'],
        'venta' => ['Cobros', 'indigo'],
        'comanda' => ['Comandas', 'amber'],
        'alerta' => ['Anulaciones y descuentos', 'rose'],
        'almacen' => ['Mermas', 'orange'],
        'compra' => ['Compras y gastos', 'sky'],
    ];

    private const COMPROBANTES = ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito', '13' => 'Nota de venta'];

    /** @return Collection<int, array{hora: string, usuario: ?int, tipo: string, icono: string, titulo: string, detalle: ?string, monto: ?float, alerta: bool}> */
    public static function eventos(int $sucursal, string $fecha): Collection
    {
        $desde = $fecha.' 00:00:00';
        $hasta = $fecha.' 23:59:59';
        $ev = collect();
        $agregar = function (?string $hora, ?int $usuario, string $tipo, string $icono, string $titulo, ?string $detalle = null, ?float $monto = null, bool $alerta = false) use ($ev) {
            if ($hora) {
                $ev->push(compact('hora', 'usuario', 'tipo', 'icono', 'titulo', 'detalle', 'monto', 'alerta'));
            }
        };

        // ---- Caja: apertura y cierre
        foreach (DB::table('turnos')->where('id_empresa_negocio', $sucursal)
            ->where(fn ($q) => $q->whereBetween('apertura', [$desde, $hasta])->orWhereBetween('cierre', [$desde, $hasta]))->get() as $t) {
            if ($t->apertura >= $desde && $t->apertura <= $hasta) {
                $agregar($t->apertura, $t->IdUsuario, 'caja', '🔓', 'Abrió la caja (turno N° '.($t->turno ?? $t->id_turno).')', 'Fondo inicial', (float) $t->monto);
            }
            if ($t->cierre && $t->cierre >= $desde && $t->cierre <= $hasta) {
                $agregar($t->cierre, $t->IdUsuario, 'caja', '🔒', 'Cerró la caja (turno N° '.($t->turno ?? $t->id_turno).')', $t->montocierre !== null ? 'Efectivo contado' : 'Sin contar el efectivo', $t->montocierre !== null ? (float) $t->montocierre : null);
            }
        }

        // ---- Comprobantes (cobros) y lo que se le hizo: descuento, cortesía, anulación
        $mesas = fn ($pedidos) => DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')->leftJoin('pisos as pi', 'pi.pis_id', '=', 'p.pis_id')
            ->whereIn('p.ped_id', $pedidos)->get(['p.ped_id', 'p.ped_tip', 'm.mes_nom', 'pi.pis_nom'])->keyBy('ped_id');
        $cpes = DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->where('ccafem', $fecha)->get();
        $pedidos = $mesas($cpes->pluck('ped_id')->filter()->unique()->values());
        $cortesias = DB::table('cpe_detalle')->whereIn('IdCpe_cabecera', $cpes->pluck('IdCpe_cabecera'))->where('cortesia', 1)
            ->get(['IdCpe_cabecera', 'cdedes', 'cdecan', 'cortesia_motivo'])->groupBy('IdCpe_cabecera');
        foreach ($cpes as $c) {
            $numero = $c->serdoc.'-'.(ltrim((string) $c->numdoc, '0') ?: '0');
            $doc = self::COMPROBANTES[$c->tdocod] ?? 'Comprobante';
            $origen = self::origen($c->ped_tip, $pedidos[$c->ped_id] ?? null);
            $cliente = $c->ccanom && ! in_array(mb_strtoupper($c->ccanom), ['VENTA AL PORTADOR', 'CLIENTES VARIOS'], true) ? ' · '.$c->ccanom : '';
            $esNota = in_array($c->tdocod, ['07', '08'], true);
            $agregar((string) $c->fecha_hora, $c->IdUsuario, $esNota ? 'alerta' : 'venta', $esNota ? '↩️' : '🧾',
                ($esNota ? 'Emitió ' : 'Cobró ').$doc.' '.$numero, trim($origen.$cliente, ' ·') ?: null, (float) $c->ccaitv, $esNota);
            if ((float) ($c->descuento ?? 0) > 0) {
                $agregar((string) $c->fecha_hora, $c->IdUsuario_descuento ?? $c->IdUsuario, 'alerta', '🏷️', 'Hizo un descuento en '.$numero,
                    $c->descuento_motivo ? 'Motivo: '.$c->descuento_motivo : null, (float) $c->descuento, true);
            }
            foreach ($cortesias[$c->IdCpe_cabecera] ?? [] as $d) {
                $agregar((string) $c->fecha_hora, $c->IdUsuario, 'alerta', '🎁', 'Regaló (cortesía) '.self::cantidad($d->cdecan).' '.$d->cdedes,
                    'En '.$numero.($d->cortesia_motivo ? ' · Motivo: '.$d->cortesia_motivo : ''), null, true);
            }
        }
        foreach (DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereBetween('ccabaj', [$desde, $hasta])->get() as $c) {
            $agregar((string) $c->ccabaj, $c->IdUsuario_baja, 'alerta', '🚫', 'Anuló '.(self::COMPROBANTES[$c->tdocod] ?? 'el comprobante').' '.$c->serdoc.'-'.(ltrim((string) $c->numdoc, '0') ?: '0'),
                $c->motivo_baja ? 'Motivo: '.$c->motivo_baja : null, (float) $c->ccaitv, true);
        }

        // ---- Comandas: lo que se envió a cocina/bar y lo que se quitó; y cuando el mozo llevó el pedido a la mesa
        $tickets = DB::table('cocina_tickets')->where('id_empresa_negocio', $sucursal)
            ->where(fn ($q) => $q->whereBetween('creado', [$desde, $hasta])->orWhereBetween('entregado', [$desde, $hasta]))->get();
        $items = DB::table('cocina_items')->whereIn('ticket_id', $tickets->pluck('id'))->get()->groupBy('ticket_id');
        $mozos = DB::table('pedidos')->whereIn('ped_id', $tickets->pluck('ped_id')->filter())->pluck('mozo', 'ped_id');
        $conUsuario = Schema::hasColumn('cocina_tickets', 'IdUsuario');
        foreach ($tickets as $t) {
            $quien = ($conUsuario ? $t->IdUsuario : null) ?: ($mozos[$t->ped_id] ?? null);
            $lista = ($items[$t->id] ?? collect())->map(fn ($i) => self::cantidad($i->cantidad).' '.$i->descripcion)->implode(', ');
            if ($t->creado >= $desde && $t->creado <= $hasta) {
                if ($t->tipo === 'ANULACION') {
                    $motivo = ($items[$t->id] ?? collect())->pluck('observacion')->filter()->first();
                    $agregar($t->creado, $quien, 'alerta', '❌', 'Quitó de '.$t->destino.': '.$lista, $motivo, null, true);
                } else {
                    $agregar($t->creado, $quien, 'comanda', '🍽️', ($t->tipo === 'ADICIONAL' ? 'Agregó a ' : 'Envió la comanda de ').$t->destino, $lista ?: null);
                }
            }
            if ($t->entregado && $t->entregado >= $desde && $t->entregado <= $hasta && $t->tipo !== 'ANULACION') {
                $agregar($t->entregado, $mozos[$t->ped_id] ?? $quien, 'comanda', '🛎️', 'Llevó el pedido a '.$t->destino, null);
            }
        }

        // ---- Mermas
        foreach (DB::table('mermas as m')->join('productos as p', 'p.IdProducto', '=', 'm.IdProducto')->where('m.id_empresa_negocio', $sucursal)
            ->whereBetween('m.created_at', [$desde, $hasta])->get(['m.*', 'p.pronom']) as $m) {
            $agregar((string) $m->created_at, $m->IdUsuario, 'almacen', '🗑️', 'Registró merma: '.self::cantidad($m->cantidad).' '.$m->umecod.' de '.$m->pronom,
                (Mermas::MOTIVOS[$m->motivo][1] ?? $m->motivo).($m->observacion ? ' · '.$m->observacion : '').($m->estado === 'ANULADA' ? ' · (anulada)' : ''), (float) $m->costo);
        }

        // ---- Compras, gastos e ingresos de caja, proformas
        foreach (DB::table('compras_cabecera as c')->leftJoin('proveedor as pv', 'pv.prov_id', '=', 'c.prov_id')->where('c.id_empresa_negocio', $sucursal)
            ->whereBetween('c.created_at', [$desde, $hasta])->get(['c.*', 'pv.prov_raz']) as $c) {
            $agregar((string) $c->created_at, $c->IdUsuario, 'compra', '📦', 'Registró la compra '.$c->com_doc_ser.'-'.$c->com_doc_num, $c->prov_raz, (float) $c->total_com);
        }
        foreach (DB::table('movimientoscaja as m')->leftJoin('tiposcaja as t', 't.tip_caj_id', '=', 'm.tip_caj_id')->where('m.id_empresa_negocio', $sucursal)
            ->whereNotIn('m.tip_caj_id', ['001', '002'])->whereBetween('m.registro', [$desde, $hasta])->get(['m.*', 't.tip_caj_nom', 't.tipo']) as $m) {
            $sale = $m->tipo === 'SALIDA';
            $agregar((string) $m->registro, $m->IdUsuario, 'compra', $sale ? '💸' : '💵', ($sale ? 'Sacó dinero de caja: ' : 'Ingresó dinero a caja: ').mb_strtolower((string) $m->tip_caj_nom),
                $m->mov_com ?: null, (float) $m->importe, $sale);
        }
        if (Schema::hasTable('proformas')) {
            foreach (DB::table('proformas')->where('id_empresa_negocio', $sucursal)->whereBetween('created_at', [$desde, $hasta])->get() as $p) {
                $agregar((string) $p->created_at, $p->IdUsuario, 'venta', '📝', 'Hizo la proforma '.$p->serie.'-'.$p->numero, $p->clinom, (float) $p->total);
            }
        }

        return $ev->sortBy('hora')->values();
    }

    private static function origen(?string $tipo, ?object $pedido): string
    {
        if ($pedido?->mes_nom) {
            return 'Mesa '.Mesa::etiqueta($pedido->pis_nom, $pedido->mes_nom);
        }

        return match ($tipo) {
            'Llevar' => 'Para llevar', 'Delivery' => 'Delivery', 'PV' => 'Punto de venta', 'PVCOMANDA' => 'PV Restaurante',
            'FARMACIA' => 'PV Farmacia', 'POS' => 'PV móvil', 'TACTIL' => 'PV táctil', 'GRIFO' => 'PV Grifo', 'Hotel' => 'Hotel', 'Clinica' => 'Clínica',
            default => '',
        };
    }

    private static function cantidad($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}
