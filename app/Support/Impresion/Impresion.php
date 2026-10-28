<?php

namespace App\Support\Impresion;

use App\Models\Mesa;
use App\Support\ConsultaCpe;
use App\Support\Delivery;
use App\Support\Descuentos;
use App\Support\Encuestas;
use App\Support\Fidelizacion;
use App\Support\NombreCorto;
use App\Support\Propinas;
use App\Support\Sunat\CodigoQr;
use App\Support\Sunat\NumeroLetras;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impresión directa: arma el ticket ESC/POS y lo deja en cola_impresion.
 * El agente de la PC de las impresoras (resources/stubs/agente) lo recoge cada 2 s y lo imprime sin vista previa.
 */
class Impresion
{
    /** Ventas directas (Punto Venta, PV Farmacia, PV móvil, táctil y grifo): su comprobante no muestra vendedor ni cajero */
    private const VENTA_DIRECTA = ['PV', 'FARMACIA', 'POS', 'TACTIL', 'GRIFO'];

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

    /** Cookie con la PC de caja en la que está este navegador (CAJA01, CAJA02…) */
    public const COOKIE_EQUIPO = 'equipo_caja';

    /** Equipos (PCs con agente) de la sucursal que tienen impresoras asignadas */
    public static function equipos(int $sucursal)
    {
        // Base que aún no corrió su migración (por ejemplo, justo al actualizar): sin equipos, en vez de romper la pantalla
        static $listas = [];
        $base = DB::connection()->getDatabaseName();
        if (! ($listas[$base] ??= Schema::hasColumn('impresion_agentes', 'impresora_caja'))) {
            return collect();
        }

        return DB::table('impresion_agentes')->where('id_empresa_negocio', $sucursal)
            ->where(fn ($q) => $q->whereNotNull('impresora_caja')->orWhereNotNull('impresora_barra'))->orderBy('pc')->get();
    }

    /** La PC de caja de este navegador (elegida una vez en esa PC), si la sucursal la tiene configurada */
    public static function equipo(int $sucursal): ?object
    {
        $pc = request()->cookie(self::COOKIE_EQUIPO);
        if (! is_string($pc) || $pc === '') {
            return null;
        }

        return self::equipos($sucursal)->firstWhere('pc', mb_strtoupper($pc));
    }

    /** Impresora de caja: la de la PC donde se cobra, la del usuario (terminal) o la predeterminada de la sucursal */
    public static function impresoraCaja(int $sucursal, $user = null): ?object
    {
        $user ??= Auth::user();

        return self::impresora((int) (self::equipo($sucursal)?->impresora_caja) ?: null, $sucursal)
            ?? self::impresora($user?->terminal ? (int) $user->terminal : null, $sucursal)
            ?? DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->where('activo', 1)
                ->orderByDesc('predeterminado')->orderBy('Id')->first();
    }

    /**
     * Varias barras: la comanda que sale al cobrar va SOLO a la barra de la PC donde se cobra (o la barra del cajero);
     * la ticketera de caja queda para los comprobantes. null = cada producto a la impresora de su categoría.
     *
     * @return array<int, int>|null
     */
    public static function destinosComandaCaja(int $sucursal, $cajero = null): ?array
    {
        return self::barraDeEnvio($sucursal, $cajero);
    }

    /**
     * Varias barras con la comanda al enviar: lo que manda un mozo (o una PC de caja) sale en SU barra, no en la
     * impresora de cada categoría. null = cada producto a la impresora de su categoría.
     *
     * @return array<int, int>|null
     */
    public static function barraDeEnvio(int $sucursal, $usuario = null): ?array
    {
        $usuario ??= Auth::user();
        $barra = (int) (self::equipo($sucursal)?->impresora_barra ?: ($usuario->impresora_barra ?? 0));

        return $barra && self::impresora($barra, $sucursal) ? [$barra] : null;
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

    /** Encabezado del ticket ya armado (logo, nombre, RUC, dirección) para la caja sin internet: ella agrega el resto */
    public static function encabezadoBytes(int $sucursal, int $columnas): string
    {
        $p = new Escpos($columnas);
        self::encabezado($p, $sucursal);

        return $p->bytes();
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
    /** Copias configuradas en la sucursal (copias_comanda o copias_comprobante), de 1 a 5 */
    public static function copias(int $sucursal, string $campo): int
    {
        return max(1, min(5, (int) (DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value($campo) ?: 1)));
    }

    /** Los bytes del ticket repetidos tantas copias como se pidan; el cajón se abre una sola vez al final */
    private static function conCopias(object $imp, string $bytes, int $copias, bool $cajon): string
    {
        return str_repeat($bytes, max(1, $copias)).($cajon && $imp->abrir_cajon ? (new Escpos((int) $imp->columnas))->abrirCajon()->bytes() : '');
    }

    /**
     * Quién atendió la venta, para el comprobante: el mozo (o doctor) que tomó el pedido, el promotor y el cajero que cobró.
     * Si una misma persona hizo varias cosas sale una sola vez. Las ventas directas de los puntos de venta no llevan nombres.
     *
     * @return array<int, array{0: string, 1: string}> [etiqueta, nombre]
     */
    public static function atencion(object $cab): array
    {
        if (in_array($cab->ped_tip ?? null, self::VENTA_DIRECTA, true)) {
            return [];
        }
        $cajero = (int) ($cab->IdUsuario ?? 0);
        $atendio = (int) (($cab->mozo ?? null) ?: ($cab->IdUsuario_ven ?? 0));
        $promotor = (int) ($cab->promotor_id ?? 0);
        $ids = array_filter([$atendio, $promotor, $cajero]);
        if (! $ids) {
            return [];
        }
        $nombres = DB::table('users')->whereIn('IdUsuario', $ids)
            ->select('IdUsuario', DB::raw("COALESCE(NULLIF(TRIM(apeusu), ''), name) as nombre"))->pluck('nombre', 'IdUsuario')
            ->map(fn ($n) => NombreCorto::para((int) ($cab->id_empresa_negocio ?? 0), (string) $n));
        $etiqueta = match ($cab->ped_tip ?? null) {
            'Salon', 'Llevar', 'Delivery', 'Hotel', 'PVCOMANDA' => 'Mozo',
            'Clinica' => 'Atendió',
            default => 'Vendedor',
        };
        $lista = [];
        if ($atendio && $atendio !== $cajero && isset($nombres[$atendio])) {
            $lista[] = [$etiqueta, (string) $nombres[$atendio]];
        }
        if ($promotor && isset($nombres[$promotor])) {
            $lista[] = ['Promotor', (string) $nombres[$promotor]];
        }
        if ($cajero && isset($nombres[$cajero])) {
            $lista[] = ['Cajero', (string) $nombres[$cajero]];
        }

        return $lista;
    }

    /** $copias: null = las configuradas en la sucursal (una reimpresión manda 1) */
    public static function comprobante(int $idCpe, bool $exigirAgente = false, ?int $copias = null): bool
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $idCpe)->first();
        if (! $cab || ($exigirAgente && ! self::agenteConectado((int) $cab->id_empresa_negocio))) {
            return false;
        }
        $imp = self::impresoraCaja((int) $cab->id_empresa_negocio);
        if (! $imp) {
            return false;
        }
        $copias ??= self::copias((int) $cab->id_empresa_negocio, 'copias_comprobante');

        // Impresora A4 (instalada en Windows): va el PDF y el agente lo imprime con SumatraPDF
        if ($imp->tip_conex_imp === 'WINDOWS') {
            [, $pdf] = ComprobantePdf::generar($idCpe);
            for ($i = 0; $i < $copias; $i++) {
                self::encolar($imp, $pdf, 'COMPROBANTE A4', $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT));
            }

            return true;
        }

        $detalle = DB::table('cpe_detalle')->where('IdCpe_cabecera', $idCpe)->get();
        $medios = DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->where('v.IdCpe_cabecera', $idCpe)->get(['m.nom_med_pag', 'v.monto']);
        $tdodes = DB::table('tipo_documento')->where('tdocod', $cab->tdocod)->value('tdodes');
        $pedido = $cab->ped_id ? DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'm.pis_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.mozo')->where('p.ped_id', $cab->ped_id)
            ->first(['m.mes_nom', 'pi.pis_nom', 'u.apeusu as mozo']) : null;
        $numero = $cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT);

        if (($imp->diseno ?? 'CLASICO') !== 'MODERNO') {
            $p = ($imp->diseno ?? '') === 'COMPACTO'
                ? self::ticketCompacto($cab, $imp, $detalle, $medios, (string) $tdodes, $pedido)
                : self::ticketClasico($cab, $imp, $detalle, $medios, (string) $tdodes, $pedido, ($imp->diseno ?? '') === 'EN_LINEA');
            self::encolar($imp, self::conCopias($imp, $p->bytes(), $copias, true), 'COMPROBANTE', $numero);

            return true;
        }

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
            $p->texto('Mesa  : '.Mesa::etiqueta($pedido->pis_nom, $pedido->mes_nom));
        }
        foreach (self::atencion($cab) as [$etiqueta, $nombre]) {
            $p->parrafo(mb_str_pad(mb_substr($etiqueta, 0, 6), 6).': '.$nombre);
        }
        $p->texto('Pago  : '.$cab->estadopago.($cab->estadopago === 'CREDITO' && $cab->ccafve ? ' (vence '.Carbon::parse($cab->ccafve)->format('d/m/Y').')' : ''));
        if (trim((string) $cab->ccaobs) !== '') {
            $p->parrafo('Obs.  : '.trim($cab->ccaobs));
        }

        $p->linea();
        if ($cab->consumo) {
            $p->item('POR CONSUMO', 1, (float) $cab->ccaitv, (float) $cab->ccaitv);
        } else {
            foreach ($detalle as $d) {
                $l = Descuentos::linea($d);
                $p->item($d->cdedes.($l->cortesia ? ' (CORTESIA)' : ''), (float) $d->cdecan, $l->precio, $l->total);
                if (! empty($d->series)) {
                    $p->parrafo('  S/N: '.$d->series);
                }
            }
        }
        $p->linea();

        self::lineasDescuento($p, $cab, '');
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
        self::extrasDelCobro($p, $cab);
        $p->parrafo(NumeroLetras::convertir((float) $cab->ccaitv));
        // Fidelización: puntos ganados con esta compra y saldo
        if ($fid = Fidelizacion::delComprobante((int) $cab->IdCpe_cabecera)) {
            $p->linea()->alinear('centro')->negrita()->texto('*** TUS PUNTOS ***')->negrita(false)->alinear('izq')
                ->dosColumnas('Tenias', (string) $fid['antes']);
            if ($fid['ganados']) {
                $p->dosColumnas('Ganaste con esta compra', '+'.$fid['ganados']);
            }
            if ($fid['premio']) {
                $p->dosColumnas('Canjeaste', '-'.$fid['canjeados']);
            }
            $p->negrita()->dosColumnas('Te quedan', (string) $fid['saldo'])->negrita(false);
            if ($fid['premio']) {
                $p->alinear('centro')->negrita()->texto('PREMIO ENTREGADO:')->texto(($fid['cantidad'] ? rtrim(rtrim(number_format($fid['cantidad'], 3), '0'), '.').' x ' : '').$fid['premio'])
                    ->negrita(false)->alinear('izq');
            }
            $p->linea();
        }

        // QR de SUNAT para comprobantes electrónicos
        if (in_array($cab->tdocod, ['01', '03', '07', '08'], true)) {
            $p->avanzar()->alinear('centro')->qr(CodigoQr::texto($cab))->avanzar();
            $p->texto('Representación impresa de la '.mb_strtolower((string) $tdodes))
                ->texto('Consulta tu comprobante en:')->texto(ConsultaCpe::direccion());
        }
        $p->alinear('centro')->parrafo('BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA')
            ->texto('¡Gracias por su preferencia!');
        self::firma($p);
        $p->avanzar(3)->cortar();
        self::encolar($imp, self::conCopias($imp, $p->bytes(), $copias, true), 'COMPROBANTE', $numero);

        return true;
    }

    /**
     * Diseño clásico (el del sistema anterior): razón social en letra grande, datos del cliente en columnas,
     * cada producto con su nombre completo y debajo Cant. / UDM / P.U. / Total, y SUBTOTAL / TOTAL.
     */
    /**
     * Diseño compacto (ahorra papel): letra normal en el encabezado, fecha y condición en una línea, cliente en una línea,
     * TOTAL grande y "paga con / vuelto" juntos. Como los tickets hechos a medida del sistema anterior.
     */
    private static function ticketCompacto(object $cab, object $imp, $detalle, $medios, string $tdodes, ?object $pedido): Escpos
    {
        $p = new Escpos((int) $imp->columnas);
        $ancho = $p->columnas();
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $cab->id_empresa_negocio)->first();
        $empresa = DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa)->first();
        $soles = fn ($n) => number_format((float) $n, 2);
        // Dos datos en una línea (si no entran, uno debajo del otro: papel de 58 mm)
        $juntos = fn (string $izq, string $der) => mb_strlen($izq) + 2 + mb_strlen($der) > $ancho
            ? $izq."\n".$der : $izq.str_repeat(' ', $ancho - mb_strlen($izq) - mb_strlen($der)).$der;

        // Encabezado: logo (o nombre comercial en negrita), "DE: razón social", dirección, ubigeo, teléfono y RUC
        $logo = $negocio->logo_suc ?: $empresa->LogEmpresa;
        $conLogo = $logo && is_file(public_path($logo));
        $p->logo($conLogo ? public_path($logo) : null)->alinear('centro');
        $comercial = trim((string) ($negocio->nombre_comercial ?: $empresa->NomEmpresa));
        $mismoNombre = mb_strtoupper(preg_replace('/\s+/', ' ', $comercial)) === mb_strtoupper(preg_replace('/\s+/', ' ', (string) $empresa->NomEmpresa));
        if (! $conLogo) {
            $p->negrita()->tamano(1, 2)->parrafo($comercial)->tamano()->negrita(false);
        }
        if (! $mismoNombre) {
            $p->parrafo('DE: '.$empresa->NomEmpresa);
        } elseif ($conLogo) {
            $p->negrita()->parrafo($comercial)->negrita(false);
        }
        $p->parrafo((string) $negocio->direccion);
        if ($negocio->distrito && ! str_contains(mb_strtoupper((string) $negocio->direccion), mb_strtoupper($negocio->distrito))) {
            $p->texto(trim($negocio->departamento.'-'.$negocio->provincia.'-'.$negocio->distrito, '-'));
        }
        if ($negocio->telefono) {
            $p->texto('Telf: '.$negocio->telefono);
        }
        $p->texto()->texto('RUC:'.$empresa->IdEmpresa)->texto(mb_strtoupper($tdodes))
            ->texto($cab->serdoc.'-'.(ltrim((string) $cab->numdoc, '0') ?: '0'))->texto();

        // Fecha y condición en una línea; documento y nombre del cliente en otra
        $p->alinear('izq')
            ->texto($juntos('Fecha: '.Carbon::parse($cab->fecha_hora)->format('d/m/y H:i'), 'Condición: '.$cab->estadopago))
            ->parrafo(trim($cab->ccandi.' '.$cab->ccanom));
        if ($cab->direccion && $cab->direccion !== '--') {
            $p->parrafo('Dirección: '.$cab->direccion);
        }
        if ($pedido?->mes_nom) {
            $p->texto('Mesa: '.Mesa::etiqueta($pedido->pis_nom, $pedido->mes_nom));
        }
        foreach (self::atencion($cab) as [$etiqueta, $nombre]) {
            $p->parrafo($etiqueta.': '.$nombre);
        }
        if ($cab->estadopago === 'CREDITO' && $cab->ccafve) {
            $p->texto('Vence: '.Carbon::parse($cab->ccafve)->format('d/m/Y'));
        }
        if (trim((string) $cab->ccaobs) !== '') {
            $p->parrafo('Obs.: '.trim($cab->ccaobs));
        }

        // Productos: nombre y debajo Cant. / UDM / P.U / Total
        $w = $ancho >= 42 ? [7, 5, 9, 9] : [6, 4, 8, 8];
        $columnas = fn (string $cant, string $udm, string $pu, string $total) => str_pad($cant, $w[0], ' ', STR_PAD_LEFT).str_pad($udm, $w[1], ' ', STR_PAD_LEFT)
            .str_pad($pu, $w[2], ' ', STR_PAD_LEFT).str_pad($total, $w[3], ' ', STR_PAD_LEFT);
        $resto = max(0, $ancho - array_sum($w));
        $p->linea()->texto(($resto >= 11 ? mb_str_pad('Descripción', $resto) : 'Desc.'.str_repeat(' ', max(0, $resto - 5))).$columnas('Cant.', 'UDM.', 'P.U', 'Total'))->linea();
        $filas = $cab->consumo ? [(object) ['cdedes' => 'POR CONSUMO', 'cdecan' => 1, 'umecod' => 'NIU', 'cdepuni' => $cab->ccaitv, 'cdevve' => $cab->ccaitv, 'series' => null]] : $detalle;
        foreach ($filas as $d) {
            $l = Descuentos::linea($d);
            $p->parrafo((string) $d->cdedes.($l->cortesia ? ' (CORTESIA)' : ''))
                ->texto(str_repeat(' ', $resto).$columnas(number_format((float) $d->cdecan, 2), (string) ($d->umecod ?: 'NIU'), $soles($l->precio), $soles($l->total)));
            if (! empty($d->series)) {
                $p->parrafo('  S/N: '.$d->series);
            }
        }
        $p->linea();

        // Medios, TOTAL grande y paga con / vuelto en una línea
        self::lineasDescuento($p, $cab, ' S/');
        foreach ($medios as $m) {
            $p->dosColumnas(($m->nom_med_pag ?? 'PAGO').' S/', $soles($m->monto));
        }
        if ($cab->ccaigv > 0) {
            $p->dosColumnas('IGV S/', $soles($cab->ccaigv));
        }
        $total = $soles($cab->ccaitv);
        $p->negrita()->tamano(2, 2)->texto('TOTAL: S/'.str_repeat(' ', max(1, intdiv($ancho, 2) - 9 - mb_strlen($total))).$total)->tamano()->negrita(false);
        if ($cab->paga > 0 || $cab->vuelto > 0) {
            $p->texto($juntos('PAGA CON: S/ '.$soles($cab->paga), 'VUELTO: S/ '.$soles($cab->vuelto)));
        }
        self::extrasDelCobro($p, $cab);
        $p->parrafo('Son: '.preg_replace('/^SON\s+/i', '', NumeroLetras::convertir((float) $cab->ccaitv)));

        if ($fid = Fidelizacion::delComprobante((int) $cab->IdCpe_cabecera)) {
            $p->texto($juntos('Puntos ganados: +'.$fid['ganados'], 'Te quedan: '.$fid['saldo']));
        }

        // Pie en letra pequeña
        $chico = $p->columnasFuenteB();
        $p->alinear('centro')->fuente('b');
        if (in_array($cab->tdocod, ['01', '03', '07', '08'], true)) {
            $p->texto()->parrafo('REPRESENTACION IMPRESA DE LA '.mb_strtoupper($tdodes), $chico)->fuente()->qr(CodigoQr::texto($cab), 4)->avanzar()->fuente('b')
                ->parrafo('Consulta tu comprobante en: '.ConsultaCpe::direccion(), $chico);
        }

        $p->parrafo('BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA', $chico)->fuente();
        self::firma($p);

        return $p->avanzar(3)->cortar();
    }

    /**
     * $enLinea: diseño "En una línea" (como los tickets del sistema anterior): Cant. U.M. Descrip. P.U. Total en la misma fila
     * y la descripción larga sigue debajo, en su columna. El resto es igual al clásico.
     */
    private static function ticketClasico(object $cab, object $imp, $detalle, $medios, string $tdodes, ?object $pedido, bool $enLinea = false): Escpos
    {
        $p = new Escpos((int) $imp->columnas);
        $ancho = $p->columnas();
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $cab->id_empresa_negocio)->first();
        $empresa = DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa)->first();
        $soles = fn ($n) => number_format((float) $n, 2);

        // Encabezado: logo, razón social grande, dirección, ubigeo, teléfono y RUC
        $logo = $negocio->logo_suc ?: $empresa->LogEmpresa;
        $p->logo($logo ? public_path($logo) : null);
        $p->alinear('centro')->negrita()->tamano(2, 2)->parrafo(trim((string) $empresa->NomEmpresa), intdiv($ancho, 2))->tamano()->negrita(false);
        if ($negocio->nombre_comercial && mb_strtoupper(preg_replace('/\s+/', ' ', $negocio->nombre_comercial)) !== mb_strtoupper(preg_replace('/\s+/', ' ', $empresa->NomEmpresa))) {
            $p->negrita()->parrafo($negocio->nombre_comercial)->negrita(false);
        }
        $p->parrafo((string) $negocio->direccion);
        if ($negocio->distrito && ! str_contains(mb_strtoupper((string) $negocio->direccion), mb_strtoupper($negocio->distrito))) {
            $p->texto(trim($negocio->departamento.'-'.$negocio->provincia.'-'.$negocio->distrito, '-'));
        }
        $p->texto();
        if ($negocio->telefono) {
            $p->texto('Telf: '.$negocio->telefono);
        }
        $p->texto('RUC:'.$empresa->IdEmpresa)
            ->texto(mb_strtoupper($tdodes))
            ->negrita()->texto($cab->serdoc.'-'.(ltrim((string) $cab->numdoc, '0') ?: '0'))->negrita(false)->texto();

        // Cliente
        $dato = fn (string $etiqueta, string $valor) => $p->parrafo(mb_str_pad($etiqueta, 13).$valor);
        $p->alinear('izq');
        $dato('Fecha:', Carbon::parse($cab->fecha_hora)->format('Y-m-d H:i:s'));
        $dato('RUC/DNI:', (string) $cab->ccandi);
        $dato('Cliente:', (string) $cab->ccanom);
        $dato('Dirección:', (string) ($cab->direccion ?: '--'));
        if ($pedido?->mes_nom) {
            $dato('Mesa:', Mesa::etiqueta($pedido->pis_nom, $pedido->mes_nom));
        }
        foreach (self::atencion($cab) as [$etiqueta, $nombre]) {
            $dato($etiqueta.':', $nombre);
        }
        $p->texto('Condición de Pago:   '.$cab->estadopago.($cab->estadopago === 'CREDITO' && $cab->ccafve ? ' (vence '.Carbon::parse($cab->ccafve)->format('d/m/Y').')' : ''));
        if (trim((string) $cab->ccaobs) !== '') {
            $dato('Observación:', trim($cab->ccaobs));
        }
        $p->texto();

        if ($enLinea) {
            self::productosEnLinea($p, $cab, $detalle);
        } else {
            self::productosClasico($p, $cab, $detalle);
        }

        // Totales
        self::lineasDescuento($p, $cab, ': S/');
        $p->dosColumnas('SUBTOTAL: S/', $soles((float) $cab->ccatvg + (float) $cab->ccatexo + (float) $cab->ccatinaf));
        if ($cab->ccaigv > 0) {
            $p->dosColumnas('IGV: S/', $soles($cab->ccaigv));
        }
        $p->dosColumnas('ICBPER: S/', $soles($cab->icbper ?? 0));
        $p->negrita()->dosColumnas('TOTAL: S/', $soles($cab->ccaitv))->negrita(false);
        foreach ($medios as $m) {
            $p->dosColumnas(($m->nom_med_pag ?? 'PAGO').': S/', $soles($m->monto));
        }
        if ($cab->paga > 0) {
            $p->dosColumnas('PAGA CON: S/', $soles($cab->paga));
        }
        if ($cab->vuelto > 0) {
            $p->dosColumnas('VUELTO: S/', $soles($cab->vuelto));
        }
        self::extrasDelCobro($p, $cab);
        $p->texto()->parrafo('Son: '.preg_replace('/^SON\s+/i', '', NumeroLetras::convertir((float) $cab->ccaitv)));

        if ($fid = Fidelizacion::delComprobante((int) $cab->IdCpe_cabecera)) {
            $p->linea()->alinear('centro')->negrita()->texto('*** TUS PUNTOS ***')->negrita(false)->alinear('izq')
                ->dosColumnas('Tenias', (string) $fid['antes']);
            if ($fid['ganados']) {
                $p->dosColumnas('Ganaste con esta compra', '+'.$fid['ganados']);
            }
            if ($fid['premio']) {
                $p->dosColumnas('Canjeaste', '-'.$fid['canjeados'])
                    ->negrita()->texto('PREMIO: '.($fid['cantidad'] ? rtrim(rtrim(number_format($fid['cantidad'], 3), '0'), '.').' x ' : '').$fid['premio'])->negrita(false);
            }
            $p->negrita()->dosColumnas('Te quedan', (string) $fid['saldo'])->negrita(false)->linea();
        }

        // Pie en letra pequeña: representación impresa, QR, consulta y Amazonía
        $chico = $p->columnasFuenteB();
        $p->alinear('centro')->fuente('b');
        if (in_array($cab->tdocod, ['01', '03', '07', '08'], true)) {
            $p->parrafo('REPRESENTACION IMPRESA DE LA '.mb_strtoupper($tdodes), $chico)->fuente()->qr(CodigoQr::texto($cab))->avanzar()->fuente('b')
                ->parrafo('Consulta tu comprobante en: '.ConsultaCpe::direccion(), $chico);
        }
        $p->parrafo('BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA', $chico)->fuente();
        self::firma($p);
        $p->avanzar(3)->cortar();

        return $p;
    }

    /** Línea discreta al pie del comprobante (letra pequeña): quién desarrolló el sistema */
    private static function firma(Escpos $p): void
    {
        $p->alinear('centro')->fuente('b')->parrafo((string) config('soporte.firma_comprobante'), $p->columnasFuenteB())->fuente();
    }

    /** Lo que se recibió en dólares y la propina (no es parte del comprobante: va aparte, después del vuelto) */
    private static function extrasDelCobro(Escpos $p, object $cab): void
    {
        $dolares = DB::table('venta_medio_pago')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('moneda', 'USD')
            ->get(['monto_usd', 'tipo_cambio']);
        if ($dolares->isNotEmpty()) {
            $p->dosColumnas('RECIBIDO EN US$', number_format((float) $dolares->sum('monto_usd'), 2))
                ->texto('T.C. '.rtrim(rtrim(number_format((float) $dolares->first()->tipo_cambio, 3), '0'), '.'));
        }
        if (($propina = Propinas::delComprobante((int) $cab->IdCpe_cabecera)) > 0) {
            $p->dosColumnas('PROPINA S/', number_format($propina, 2))->texto('(Propina voluntaria: no forma parte del comprobante)');
        }
        // Encuesta de satisfacción: QR para calificar la atención
        if ($url = Encuestas::paraTicket($cab)) {
            $p->linea()->alinear('centro')->negrita()->texto('¿QUE TAL TE ATENDIMOS?')->negrita(false)
                ->qr($url, 4)->texto('Escanea y califícanos')->alinear('izq')->linea();
        }
    }

    /** Consumo y descuento a la cuenta, y el valor de las cortesías (operaciones gratuitas) */
    private static function lineasDescuento(Escpos $p, object $cab, string $sufijo): void
    {
        if ((float) ($cab->descuento ?? 0) > 0) {
            $p->dosColumnas('CONSUMO'.$sufijo, number_format((float) $cab->ccaitv + (float) $cab->descuento, 2))
                ->dosColumnas('DESCUENTO'.$sufijo, '-'.number_format((float) $cab->descuento, 2));
        }
        if ((float) ($cab->ccagra ?? 0) > 0) {
            $p->dosColumnas('OP. GRATUITAS'.$sufijo, number_format((float) $cab->ccagra, 2));
        }
    }

    /** Productos del clásico: nombre completo y debajo Cant. / UDM / P.U. / Total alineados a la derecha */
    private static function productosClasico(Escpos $p, object $cab, $detalle): void
    {
        $ancho = $p->columnas();
        $soles = fn ($n) => number_format((float) $n, 2);
        $w = $ancho >= 42 ? [7, 5, 9, 9] : [6, 4, 8, 8];
        $columnas = fn (string $cant, string $udm, string $pu, string $total) => str_pad($cant, $w[0], ' ', STR_PAD_LEFT).str_pad($udm, $w[1], ' ', STR_PAD_LEFT)
            .str_pad($pu, $w[2], ' ', STR_PAD_LEFT).str_pad($total, $w[3], ' ', STR_PAD_LEFT);
        $resto = max(0, $ancho - array_sum($w));
        $fila = fn (string $cant, string $udm, string $pu, string $total) => str_repeat(' ', $resto).$columnas($cant, $udm, $pu, $total);
        $p->texto(($resto >= 11 ? mb_str_pad('Descripción', $resto) : 'Desc.'.str_repeat(' ', max(0, $resto - 5))).$columnas('Cant.', 'UDM.', 'P.U.', 'Total'))->linea('_');
        if ($cab->consumo) {
            $p->texto('POR CONSUMO')->texto($fila('1.00', 'NIU', $soles($cab->ccaitv), $soles($cab->ccaitv)));
        } else {
            foreach ($detalle as $d) {
                $l = Descuentos::linea($d);
                $p->parrafo((string) $d->cdedes.($l->cortesia ? ' (CORTESIA)' : ''));
                $p->texto($fila(number_format((float) $d->cdecan, 2), (string) ($d->umecod ?: 'NIU'), $soles($l->precio), $soles($l->total)));
                if (! empty($d->series)) {
                    $p->parrafo('  S/N: '.$d->series);
                }
            }
        }
        $p->linea('_');
    }

    /** Productos en una línea: Cant. U.M. Descrip. P.U. Total; la descripción que no entra sigue debajo en su columna */
    private static function productosEnLinea(Escpos $p, object $cab, $detalle): void
    {
        $ancho = $p->columnas();
        // Anchos de Cant., U.M., P.U. y Total (con un espacio después de Cant. y de U.M.); la descripción usa el resto
        [$wc, $wu, $wp, $wt] = $ancho >= 42 ? [6, 3, 7, 9] : [5, 3, 6, 7];
        $wd = max(6, $ancho - $wc - $wu - $wp - $wt - 2);
        $fila = fn (string $c, string $u, string $d, string $pu, string $t) => str_pad($c, $wc, ' ', STR_PAD_LEFT).' '.mb_str_pad(mb_substr($u, 0, $wu), $wu).' '
            .mb_str_pad($d, $wd).str_pad($pu, $wp, ' ', STR_PAD_LEFT).str_pad($t, $wt, ' ', STR_PAD_LEFT);
        $soles = fn ($n) => number_format((float) $n, 2, '.', '');

        $filas = $cab->consumo ? [(object) ['cdedes' => 'POR CONSUMO', 'cdecan' => 1, 'umecod' => 'NIU', 'cdepuni' => $cab->ccaitv, 'cdevve' => $cab->ccaitv, 'series' => null]] : $detalle;

        // Papel de 58 mm: no entran 5 columnas; Cant. U.M. y descripción en una línea, y debajo P.U. y Total a la derecha
        if ($ancho < 42) {
            $p->linea('_')->texto(mb_str_pad('Cant U.M Descrip', $ancho - 16).str_pad('P.U.', 7, ' ', STR_PAD_LEFT).str_pad('Total', 9, ' ', STR_PAD_LEFT))->linea('_');
            foreach ($filas as $d) {
                $l = Descuentos::linea($d);
                $p->parrafo(number_format((float) $d->cdecan, 2).' '.($d->umecod ?: 'NIU').' '.trim((string) $d->cdedes).($l->cortesia ? ' (CORTESIA)' : ''))
                    ->texto(str_pad($soles($l->precio), $ancho - 9, ' ', STR_PAD_LEFT).str_pad($soles($l->total), 9, ' ', STR_PAD_LEFT));
                if (! empty($d->series)) {
                    $p->parrafo('  S/N: '.$d->series);
                }
            }
            $p->linea('_');

            return;
        }

        $p->linea('_')->texto($fila('Cant.', 'U.M', 'Descrip.', 'P.U.', 'Total'))->linea('_');
        foreach ($filas as $d) {
            $l = Descuentos::linea($d);
            $partes = explode("\n", wordwrap(trim((string) $d->cdedes).($l->cortesia ? ' (CORTESIA)' : ''), $wd - 1, "\n", true));
            $p->texto($fila(number_format((float) $d->cdecan, 2), (string) ($d->umecod ?: 'NIU'), array_shift($partes), $soles($l->precio), $soles($l->total)));
            foreach ($partes as $resto) {
                $p->texto(str_repeat(' ', $wc + $wu + 2).$resto);
            }
            if (! empty($d->series)) {
                $p->parrafo('  S/N: '.$d->series);
            }
        }
        $p->linea('_');
    }

    // ------------------------------------------------------------------ cocina / bar

    /**
     * Comanda para cocina/bar: cada línea va a la impresora de la categoría del producto.
     * $lineas: [['IdProducto', 'nombre', 'cantidad', 'observacion']], $anulacion: true = ticket de ANULACIÓN.
     */
    /** $soloEn: ids de impresoras donde sale TODA la comanda (en vez de la impresora de cada categoría) */
    public static function comanda(int $pedId, array $lineas, bool $anulacion = false, string $motivo = '', ?array $soloEn = null): int
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

        // Delivery: los datos de entrega van en la comanda (cliente, dirección, referencia, WhatsApp y cómo paga)
        $entrega = $pedido->ped_tip === 'Delivery' && ! $anulacion ? Delivery::lineasComanda($pedido) : [];
        $atiende = $pedido->entrega_origen === 'CARTA' ? 'PEDIDO WEB (CARTA QR)' : null;
        $promotor = $pedido->promotor_id ? DB::table('users')->where('IdUsuario', $pedido->promotor_id)->value(DB::raw("COALESCE(NULLIF(apeusu, ''), name)")) : null;
        // "Atiende" es siempre el mozo de la mesa (aunque la comanda la envíe o la cobre el cajero)
        if (! $atiende && $pedido->mozo) {
            $atiende = DB::table('users')->where('IdUsuario', $pedido->mozo)->value(DB::raw("COALESCE(NULLIF(apeusu, ''), name)"));
        }

        return self::comandaPara((int) $pedido->id_empresa_negocio, $destino, 'Pedido N° '.$pedido->ped_id, $lineas, $anulacion, $motivo, $entrega, $atiende, $promotor, $soloEn);
    }

    /** ¿La sucursal imprime las comandas recién al cobrar? (empresa_negocios.comanda_al_cobrar) */
    public static function comandaAlCobrar(int $sucursal): bool
    {
        return (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('comanda_al_cobrar');
    }

    /**
     * Guarda lo enviado a cocina para imprimirlo al cobrar. Se acumula por producto e indicación; lo que se anula
     * antes de cobrar se descuenta (no sale nunca en papel).
     *
     * @param  array<int, array{IdProducto: ?int, nombre: string, cantidad: float, observacion?: ?string}>  $nuevos
     * @param  array<int, array{IdProducto: ?int, nombre: string, cantidad: float, observacion?: ?string}>  $anulaciones
     */
    public static function guardarComandaPendiente(int $pedId, array $nuevos, array $anulaciones): void
    {
        $pendiente = json_decode((string) DB::table('pedidos')->where('ped_id', $pedId)->value('comanda_pendiente'), true) ?: [];
        foreach ($nuevos as $l) {
            // Aún no salió nada a cocina: un "(adicional)" se suma a la misma línea
            $obs = trim(str_replace('(adicional)', '', (string) ($l['observacion'] ?? '')));
            $clave = ($l['IdProducto'] ?? '').'|'.$l['nombre'].'|'.$obs;
            $pendiente[$clave] ??= ['IdProducto' => $l['IdProducto'] ?? null, 'nombre' => $l['nombre'], 'cantidad' => 0, 'observacion' => $obs];
            $pendiente[$clave]['cantidad'] = round($pendiente[$clave]['cantidad'] + (float) $l['cantidad'], 3);
        }
        foreach ($anulaciones as $a) {
            $resta = (float) $a['cantidad'];
            foreach ($pendiente as $clave => $l) {
                if ($resta <= 0 || (int) $l['IdProducto'] !== (int) ($a['IdProducto'] ?? 0)) {
                    continue;
                }
                $quita = min($resta, (float) $l['cantidad']);
                $pendiente[$clave]['cantidad'] = round($l['cantidad'] - $quita, 3);
                $resta -= $quita;
                if ($pendiente[$clave]['cantidad'] <= 0) {
                    unset($pendiente[$clave]);
                }
            }
        }
        DB::table('pedidos')->where('ped_id', $pedId)->update(['comanda_pendiente' => $pendiente ? json_encode($pendiente, JSON_UNESCAPED_UNICODE) : null]);
    }

    /**
     * Al cobrar: imprime lo que quedó guardado de la comanda y lo limpia. Devuelve cuántos tickets salieron.
     * Con barra (la de la PC donde se cobra o la del cajero) la comanda sale solo en esa barra (varias barras con sus
     * cajas); si no, en la impresora de cada categoría.
     */
    public static function imprimirComandaPendiente(int $pedId, $cajero = null): int
    {
        $pendiente = json_decode((string) DB::table('pedidos')->where('ped_id', $pedId)->value('comanda_pendiente'), true);
        if (! $pendiente) {
            return 0;
        }
        DB::table('pedidos')->where('ped_id', $pedId)->update(['comanda_pendiente' => null]);
        $sucursal = (int) DB::table('pedidos')->where('ped_id', $pedId)->value('id_empresa_negocio');

        return self::comanda($pedId, array_values($pendiente), false, '', self::destinosComandaCaja($sucursal, $cajero));
    }

    /**
     * Imprime la comanda en la impresora de la categoría de cada producto.
     * Sirve para pedidos de mesa/llevar y para ventas directas (sin pedido).
     * $soloEn: los productos que se imprimen (su categoría tiene impresora) salen todos juntos en esas impresoras.
     */
    public static function comandaPara(int $sucursal, string $destino, string $referencia, array $lineas, bool $anulacion = false, string $motivo = '',
        array $entrega = [], ?string $atiende = null, ?string $promotor = null, ?array $soloEn = null): int
    {
        if (! $lineas) {
            return 0;
        }

        // Impresora(s) de cada producto según su categoría: la principal y, si tiene, la segunda (copia en otra ticketera)
        $impresorasDe = DB::table('productos as pr')->leftJoin('categorias as c', 'c.cat_id', '=', 'pr.cat_id')
            ->whereIn('pr.IdProducto', array_filter(array_column($lineas, 'IdProducto')))
            ->get(['pr.IdProducto', 'c.impresora', 'c.impresora2'])->keyBy('IdProducto');

        $grupos = [];
        foreach ($lineas as $l) {
            $cat = $impresorasDe[$l['IdProducto']] ?? null;
            $destinos = array_unique(array_filter([(int) ($cat->impresora ?? 0), (int) ($cat->impresora2 ?? 0)]));
            foreach ($destinos && $soloEn ? $soloEn : $destinos as $idImp) {
                $grupos[$idImp][] = $l;
            }
        }

        $usuario = NombreCorto::para($sucursal, $atiende ?? Auth::user()?->apeusu);
        $promotor = NombreCorto::para($sucursal, $promotor);
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
                ->texto('Atiende: '.($usuario ?? ''));
            if ($promotor) {
                $p->negrita()->texto('Promotor: '.$promotor)->negrita(false);
            }
            $p->alinear('izq')->linea('=');
            if ($entrega) {
                foreach ($entrega as $dato) {
                    $p->negrita()->parrafo($dato)->negrita(false);
                }
                $p->linea('=');
            }

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

            self::encolar($imp, self::conCopias($imp, $p->bytes(), self::copias($sucursal, 'copias_comanda'), false), $anulacion ? 'ANULACION' : 'COMANDA', $referencia.' '.$destino);
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
            ->texto('Fecha : '.now()->format('d/m/Y H:i'))->texto('Mozo  : '.NombreCorto::para((int) $pedido->id_empresa_negocio, $pedido->mozo_nom))->texto('Pedido: '.$pedido->ped_id)->linea();
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
