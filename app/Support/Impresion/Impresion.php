<?php

namespace App\Support\Impresion;

use App\Support\Sunat\CodigoQr;
use App\Support\Sunat\NumeroLetras;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Impresión directa: arma el ticket ESC/POS y lo deja en cola_impresion.
 * El agente de la PC de las impresoras (resources/stubs/agente) lo recoge cada 2 s y lo imprime sin vista previa.
 */
class Impresion
{
    /** Segundos sin contacto para considerar que el agente está apagado */
    public const AGENTE_TIMEOUT = 90;

    // ------------------------------------------------------------------ configuración

    public static function impresora(?int $id, int $sucursal): ?object
    {
        if (! $id) {
            return null;
        }

        return DB::table('configuracion_impresoras')->where('Id', $id)
            ->where('id_empresa_negocio', $sucursal)->where('activo', 1)->first();
    }

    /** Impresora de caja: la del usuario (terminal) o la predeterminada de la sucursal */
    public static function impresoraCaja(int $sucursal, $user = null): ?object
    {
        $user ??= Auth::user();

        return self::impresora($user?->terminal ? (int) $user->terminal : null, $sucursal)
            ?? DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->where('activo', 1)
                ->orderByDesc('predeterminado')->orderBy('Id')->first();
    }

    public static function agenteConectado(int $sucursal): bool
    {
        $contacto = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('impresion_contacto');

        return $contacto && now()->diffInSeconds(Carbon::parse($contacto), true) <= self::AGENTE_TIMEOUT;
    }

    /** ¿Se puede imprimir directo en esta sucursal ahora? (hay impresora y el agente está conectado) */
    public static function directaDisponible(int $sucursal): bool
    {
        return self::impresoraCaja($sucursal) !== null && self::agenteConectado($sucursal);
    }

    public static function encolar(object $impresora, string $bytes, string $tipo, string $referencia): int
    {
        return DB::table('cola_impresion')->insertGetId([
            'contenido' => base64_encode($bytes),
            'impresora' => $impresora->descripcion,
            'id_impresora' => $impresora->Id,
            'id_empresa_negocio' => $impresora->id_empresa_negocio,
            'tipo' => $tipo,
            'referencia' => mb_substr($referencia, 0, 60),
            'estado' => 0,
            'creado' => now(),
        ]);
    }

    private static function encabezado(Escpos $p, int $sucursal): void
    {
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();
        $empresa = DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa)->first();

        $logo = $negocio->logo_suc ?: $empresa->LogEmpresa;
        $p->logo($logo ? public_path($logo) : null);
        $p->alinear('centro')->negrita()->texto($negocio->nombre_comercial ?: $empresa->NomEmpresa)->negrita(false);
        if ($negocio->nombre_comercial && $negocio->nombre_comercial !== $empresa->NomEmpresa) {
            $p->texto($empresa->NomEmpresa);
        }
        $p->texto('RUC: '.$empresa->IdEmpresa);
        $p->parrafo((string) $negocio->direccion);
        // La dirección del padrón SUNAT ya suele terminar en "DEPARTAMENTO - PROVINCIA - DISTRITO"
        if ($negocio->distrito && ! str_contains(mb_strtoupper((string) $negocio->direccion), mb_strtoupper($negocio->distrito))) {
            $p->texto(trim($negocio->distrito.' - '.$negocio->provincia.' - '.$negocio->departamento, ' -'));
        }
        if ($negocio->telefono) {
            $p->texto('Tel: '.$negocio->telefono);
        }
    }

    // ------------------------------------------------------------------ comprobante

    /**
     * Encola el comprobante en la impresora de caja. Con impresora configurada siempre va al agente (si está apagado,
     * sale apenas se conecte) y la pantalla no muestra la impresión del navegador. false = no hay impresora configurada.
     */
    public static function comprobante(int $idCpe, bool $exigirAgente = false): bool
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $idCpe)->first();
        if (! $cab || ($exigirAgente && ! self::agenteConectado((int) $cab->id_empresa_negocio))) {
            return false;
        }
        $imp = self::impresoraCaja((int) $cab->id_empresa_negocio);
        if (! $imp) {
            return false;
        }

        // Impresora A4 (instalada en Windows): va el PDF y el agente lo imprime con SumatraPDF
        if ($imp->tip_conex_imp === 'WINDOWS') {
            [, $pdf] = ComprobantePdf::generar($idCpe);
            self::encolar($imp, $pdf, 'COMPROBANTE A4', $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT));

            return true;
        }

        $detalle = DB::table('cpe_detalle')->where('IdCpe_cabecera', $idCpe)->get();
        $medios = DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->where('v.IdCpe_cabecera', $idCpe)->get(['m.nom_med_pag', 'v.monto']);
        $tdodes = DB::table('tipo_documento')->where('tdocod', $cab->tdocod)->value('tdodes');
        $pedido = $cab->ped_id ? DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.mozo')->where('p.ped_id', $cab->ped_id)
            ->first(['m.mes_nom', 'u.apeusu as mozo']) : null;
        $numero = $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT);

        $p = new Escpos((int) $imp->columnas);
        self::encabezado($p, (int) $cab->id_empresa_negocio);
        $p->linea('=')->negrita()->texto(mb_strtoupper((string) $tdodes))->tamano(1, 2)->texto($numero)->tamano()->negrita(false)->linea('=');

        $p->alinear('izq')
            ->texto('Fecha : '.Carbon::parse($cab->fecha_hora)->format('d/m/Y H:i'))
            ->parrafo('Cliente: '.$cab->ccanom)
            ->texto(($cab->tdicod === '6' ? 'RUC' : 'DOC').'   : '.$cab->ccandi);
        if ($cab->direccion && $cab->direccion !== '--') {
            $p->parrafo('Dir.  : '.$cab->direccion);
        }
        if ($pedido?->mes_nom) {
            $p->texto('Mesa  : '.$pedido->mes_nom);
        }
        if ($pedido?->mozo) {
            $p->texto('Mozo  : '.$pedido->mozo);
        }
        $p->texto('Pago  : '.$cab->estadopago.($cab->estadopago === 'CREDITO' && $cab->ccafve ? ' (vence '.Carbon::parse($cab->ccafve)->format('d/m/Y').')' : ''));

        $p->linea();
        if ($cab->consumo) {
            $p->item('POR CONSUMO', 1, (float) $cab->ccaitv, (float) $cab->ccaitv);
        } else {
            foreach ($detalle as $d) {
                $p->item($d->cdedes, (float) $d->cdecan, (float) $d->cdepuni, (float) $d->cdevve);
            }
        }
        $p->linea();

        if ($cab->ccatvg > 0) {
            $p->dosColumnas('OP. GRAVADA', number_format($cab->ccatvg, 2));
        }
        if ($cab->ccatexo > 0) {
            $p->dosColumnas('OP. EXONERADA', number_format($cab->ccatexo, 2));
        }
        if ($cab->ccaigv > 0) {
            $p->dosColumnas('IGV', number_format($cab->ccaigv, 2));
        }
        $p->negrita()->tamano(1, 2)->dosColumnas('TOTAL S/', number_format($cab->ccaitv, 2))->tamano()->negrita(false);
        foreach ($medios as $m) {
            $p->dosColumnas($m->nom_med_pag ?? 'PAGO', number_format($m->monto, 2));
        }
        if ($cab->paga > 0) {
            $p->dosColumnas('PAGA CON', number_format($cab->paga, 2));
        }
        if ($cab->vuelto > 0) {
            $p->dosColumnas('VUELTO', number_format($cab->vuelto, 2));
        }
        $p->parrafo(NumeroLetras::convertir((float) $cab->ccaitv));
        // Fidelización: puntos ganados con esta compra y saldo
        if ($fid = DB::table('fid_movimientos')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('tipo', 'VENTA')->first(['puntos', 'saldo'])) {
            $p->alinear('centro')->negrita()->texto('*** GANASTE '.$fid->puntos.' PUNTOS ***')->negrita(false)
                ->texto('Tus puntos acumulados: '.$fid->saldo)->alinear('izq');
        }

        // QR de SUNAT para comprobantes electrónicos
        if (in_array($cab->tdocod, ['01', '03', '07', '08'], true)) {
            $p->avanzar()->alinear('centro')->qr(CodigoQr::texto($cab));
            $p->texto('Representación impresa de la '.mb_strtolower((string) $tdodes));
        }
        $p->alinear('centro')->parrafo('BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA')
            ->texto('¡Gracias por su preferencia!')->avanzar(3)->cortar();
        if ($imp->abrir_cajon) {
            $p->abrirCajon();
        }

        self::encolar($imp, $p->bytes(), 'COMPROBANTE', $numero);

        return true;
    }

    // ------------------------------------------------------------------ cocina / bar

    /**
     * Comanda para cocina/bar: cada línea va a la impresora de la categoría del producto.
     * $lineas: [['IdProducto', 'nombre', 'cantidad', 'observacion']], $anulacion: true = ticket de ANULACIÓN.
     */
    public static function comanda(int $pedId, array $lineas, bool $anulacion = false, string $motivo = ''): int
    {
        if (! $lineas) {
            return 0;
        }
        $pedido = DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'p.pis_id')
            ->where('p.ped_id', $pedId)->first(['p.*', 'm.mes_nom', 'pi.pis_nom']);
        if (! $pedido) {
            return 0;
        }

        $destino = $pedido->ped_tip === 'Hotel' ? (string) $pedido->ped_obs
            : ($pedido->mes_nom ? ($pedido->pis_nom ? $pedido->pis_nom.' / ' : '').$pedido->mes_nom : mb_strtoupper((string) $pedido->ped_tip));

        return self::comandaPara((int) $pedido->id_empresa_negocio, $destino, 'Pedido N° '.$pedido->ped_id, $lineas, $anulacion, $motivo);
    }

    /**
     * Imprime la comanda en la impresora de la categoría de cada producto.
     * Sirve para pedidos de mesa/llevar y para ventas directas (sin pedido).
     */
    public static function comandaPara(int $sucursal, string $destino, string $referencia, array $lineas, bool $anulacion = false, string $motivo = ''): int
    {
        if (! $lineas) {
            return 0;
        }

        // Impresora de cada producto según su categoría
        $impresoraDe = DB::table('productos as pr')->leftJoin('categorias as c', 'c.cat_id', '=', 'pr.cat_id')
            ->whereIn('pr.IdProducto', array_filter(array_column($lineas, 'IdProducto')))->pluck('c.impresora', 'pr.IdProducto');

        $grupos = [];
        foreach ($lineas as $l) {
            $idImp = (int) ($impresoraDe[$l['IdProducto']] ?? 0);
            if ($idImp) {
                $grupos[$idImp][] = $l;
            }
        }

        $usuario = Auth::user()?->apeusu;
        $enviados = 0;

        foreach ($grupos as $idImp => $items) {
            $imp = self::impresora($idImp, $sucursal);
            if (! $imp) {
                continue;
            }
            $p = new Escpos((int) $imp->columnas);
            $p->alinear('centro');
            if ($anulacion) {
                $p->negrita()->tamano(2, 2)->texto('ANULACIÓN')->tamano()->negrita(false);
            } else {
                $p->negrita()->texto('COMANDA - '.mb_strtoupper($imp->descripcion))->negrita(false);
            }
            $p->tamano(2, 2)->negrita()->texto($destino)->negrita(false)->tamano()
                ->texto($referencia.' · '.now()->format('d/m/Y H:i'))
                ->texto('Atiende: '.($usuario ?? ''))
                ->alinear('izq')->linea('=');

            foreach ($items as $it) {
                $cant = rtrim(rtrim(number_format($it['cantidad'], 2, '.', ''), '0'), '.');
                $p->tamano(1, 2)->negrita()->parrafo($cant.'  '.$it['nombre'])->negrita(false)->tamano();
                if (! empty($it['observacion'])) {
                    $p->parrafo('   >> '.$it['observacion']);
                }
            }
            if ($anulacion && $motivo) {
                $p->linea()->parrafo('Motivo: '.$motivo);
            }
            $p->linea('=')->avanzar(3)->cortar();

            self::encolar($imp, $p->bytes(), $anulacion ? 'ANULACION' : 'COMANDA', $referencia.' '.$destino);
            $enviados++;
        }

        return $enviados;
    }

    // ------------------------------------------------------------------ precuenta

    public static function precuenta(int $pedId): bool
    {
        $pedido = DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'p.pis_id')->leftJoin('users as u', 'u.IdUsuario', '=', 'p.mozo')
            ->where('p.ped_id', $pedId)->first(['p.*', 'm.mes_nom', 'pi.pis_nom', 'u.apeusu as mozo_nom']);
        if (! $pedido) {
            return false;
        }
        $imp = self::impresoraCaja((int) $pedido->id_empresa_negocio);
        if (! $imp) {
            return false;
        }

        $items = DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()
            ->groupBy(fn ($d) => $d->IdProducto.'|'.$d->ped_det_pre)
            ->map(fn ($g) => (object) ['descripcion' => $g->first()->descripcion, 'precio' => (float) $g->first()->ped_det_pre,
                'cantidad' => (float) $g->sum('ped_det_can'), 'pagado' => (float) $g->sum('item_facturado')]);
        $total = round($items->sum(fn ($i) => $i->cantidad * $i->precio), 2);
        $pagado = round($items->sum(fn ($i) => $i->pagado * $i->precio), 2);

        $p = new Escpos((int) $imp->columnas);
        self::encabezado($p, (int) $pedido->id_empresa_negocio);
        $p->linea('=')->tamano(2, 2)->negrita()->texto('PRECUENTA')->negrita(false)->tamano()
            ->tamano(1, 2)->texto($pedido->mes_nom ? trim(($pedido->pis_nom ?? '').' / '.$pedido->mes_nom, ' /') : mb_strtoupper((string) $pedido->ped_tip))->tamano()
            ->alinear('izq')->linea('=')
            ->texto('Fecha : '.now()->format('d/m/Y H:i'))->texto('Mozo  : '.$pedido->mozo_nom)->texto('Pedido: '.$pedido->ped_id)->linea();
        foreach ($items as $i) {
            $p->item($i->descripcion, $i->cantidad, $i->precio, $i->cantidad * $i->precio);
        }
        $p->linea()->negrita()->tamano(1, 2)->dosColumnas('TOTAL S/', number_format($total, 2))->tamano()->negrita(false);
        if ($pagado > 0) {
            $p->dosColumnas('YA PAGADO', '-'.number_format($pagado, 2))->negrita()->dosColumnas('SALDO S/', number_format($total - $pagado, 2))->negrita(false);
        }
        $p->linea()->alinear('centro')->texto('*** NO VÁLIDO COMO COMPROBANTE ***')->texto('Solicite su boleta o factura')->avanzar(3)->cortar();

        self::encolar($imp, $p->bytes(), 'PRECUENTA', 'Pedido '.$pedido->ped_id);

        return true;
    }

    // ------------------------------------------------------------------ prueba

    public static function prueba(object $imp): void
    {
        if ($imp->tip_conex_imp === 'WINDOWS') {
            $pdf = new Dompdf;
            $pdf->loadHtml('<div style="font-family:DejaVu Sans; text-align:center; margin-top:80px;"><h1>PRUEBA OK</h1><p>Impresora A4: '
                .e($imp->descripcion).'</p><p>'.now()->format('d/m/Y H:i:s').'</p><p>Tildes: áéíóú ÁÉÍÓÚ ñÑ ¿? ¡!</p></div>', 'UTF-8');
            $pdf->setPaper('A4');
            $pdf->render();
            self::encolar($imp, $pdf->output(), 'PRUEBA A4', 'Prueba '.$imp->descripcion);

            return;
        }

        $p = new Escpos((int) $imp->columnas);
        self::encabezado($p, (int) $imp->id_empresa_negocio);
        $p->linea('=')->tamano(2, 2)->negrita()->texto('PRUEBA OK')->negrita(false)->tamano()
            ->texto('Impresora: '.$imp->descripcion)->texto(now()->format('d/m/Y H:i:s'))
            ->alinear('izq')->linea()->texto('Tildes: áéíóú ÁÉÍÓÚ ñÑ ¿? ¡!')
            ->texto(mb_substr(str_repeat('1234567890', 5), 0, $p->columnas()))->dosColumnas('Izquierda', 'Derecha')->linea()
            ->alinear('centro')->qr('https://tushpa.app')->avanzar(3)->cortar();
        if ($imp->abrir_cajon) {
            $p->abrirCajon();
        }
        self::encolar($imp, $p->bytes(), 'PRUEBA', 'Prueba '.$imp->descripcion);
    }
}
