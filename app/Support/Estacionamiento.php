<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Estacionamiento / valet parking.
 *
 *  - Entra un vehículo: se le da un ticket (número correlativo de la sucursal y un código para el QR) y, si hay, un espacio.
 *  - Sale: se calcula lo que debe según su tarifa y se cobra con boleta, factura o nota de venta (como cualquier venta).
 *    Sin nada que cobrar (tolerancia, abonado o cortesía) sale sin comprobante.
 *  - Valet: el ticket guarda el llavero, el vehículo y sus daños; el cliente pide su auto escaneando el QR del ticket.
 *  - Abonados: pensión mensual por placa. Mientras está vigente, la placa no paga.
 */
class Estacionamiento
{
    /** cpe_cabecera.ped_tip de lo cobrado aquí (la columna admite 10 caracteres) */
    public const ORIGEN = 'PARKING';

    public const MODOS = ['FRACCION' => 'Por hora y fracción', 'HORA' => 'Por hora completa', 'FIJO' => 'Precio fijo por día'];

    public const ICONOS = ['auto' => 'fa-car-side', 'moto' => 'fa-motorcycle', 'camioneta' => 'fa-truck-pickup', 'bus' => 'fa-van-shuttle', 'bici' => 'fa-bicycle'];

    private const MINUTOS_DIA = 1440;

    /**
     * Lo que debe un vehículo según su tarifa.
     *  - Dentro de la tolerancia no paga.
     *  - FRACCION: la primera hora completa y luego cada fracción (precio por hora proporcional).
     *  - HORA: cada hora empezada se cobra completa.
     *  - FIJO: un precio por cada día (24 h) empezado.
     *  - Cada 24 horas completas se cobran como máximo el tope del día (si tiene).
     *
     * @return array{minutos: int, importe: float, detalle: string}
     */
    public static function calcular(object $tarifa, Carbon $entrada, Carbon $salida): array
    {
        $minutos = (int) max(0, ceil(($salida->getTimestamp() - $entrada->getTimestamp()) / 60));
        $tolerancia = (int) $tarifa->tolerancia_min;
        if ($minutos <= $tolerancia) {
            return ['minutos' => $minutos, 'importe' => 0.0, 'detalle' => 'Dentro de la tolerancia ('.$tolerancia.' min)'];
        }

        $dias = intdiv($minutos, self::MINUTOS_DIA);
        $resto = $minutos % self::MINUTOS_DIA;
        $tope = (float) $tarifa->tope_dia;
        $porDia = $tope > 0 ? $tope : self::bloque($tarifa, self::MINUTOS_DIA);

        $importe = $dias * $porDia + ($resto > ($dias ? $tolerancia : 0) ? self::bloque($tarifa, $resto) : 0);

        return ['minutos' => $minutos, 'importe' => round($importe, 2), 'detalle' => self::textoTarifa($tarifa)];
    }

    /** Lo que se cobra por un tramo de menos de 24 horas */
    private static function bloque(object $tarifa, int $minutos): float
    {
        $precio = (float) $tarifa->precio;
        $fraccion = max(1, (int) $tarifa->fraccion_min);

        $importe = match ($tarifa->modo) {
            'FIJO' => $precio,
            'HORA' => ceil($minutos / 60) * $precio,
            default => $minutos <= 60 ? $precio : $precio + ceil(($minutos - 60) / $fraccion) * round($precio * $fraccion / 60, 2),
        };
        $tope = (float) $tarifa->tope_dia;

        return $tope > 0 ? min($importe, $tope) : $importe;
    }

    public static function textoTarifa(object $tarifa): string
    {
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $texto = match ($tarifa->modo) {
            'FIJO' => $soles($tarifa->precio).' por día',
            'HORA' => $soles($tarifa->precio).' por hora',
            default => $soles($tarifa->precio).' la 1.ª hora, luego '.$soles(round($tarifa->precio * $tarifa->fraccion_min / 60, 2)).' cada '.$tarifa->fraccion_min.' min',
        };

        return $texto.((float) $tarifa->tope_dia > 0 ? ' · máx. '.$soles($tarifa->tope_dia).' por día' : '');
    }

    /** "2 h 15 min" */
    public static function duracion(int $minutos): string
    {
        $d = intdiv($minutos, self::MINUTOS_DIA);
        $h = intdiv($minutos % self::MINUTOS_DIA, 60);
        $m = $minutos % 60;

        return trim(($d ? $d.' d ' : '').($h ? $h.' h ' : '').($m || (! $d && ! $h) ? $m.' min' : ''));
    }

    public static function normalizarPlaca(?string $placa): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    }

    // ------------------------------------------------------------------ abonados

    /** Pensión vigente hoy para esta placa */
    public static function abonadoVigente(int $suc, string $placa): ?object
    {
        $placa = self::normalizarPlaca($placa);
        if ($placa === '') {
            return null;
        }
        $hoy = now()->toDateString();

        return DB::table('est_abonados')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')
            ->where(fn ($q) => $q->where('placa', $placa)->orWhere('placa2', $placa))
            ->where('inicio', '<=', $hoy)->where('fin', '>=', $hoy)->orderByDesc('fin')->first();
    }

    /** Vende (o renueva) una pensión mensual con su comprobante */
    public static function venderPension(User $user, array $d): array
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $d) {
            $turno = self::turno($user);
            $tarifa = DB::table('est_tarifas')->where('tar_id', $d['tar_id'])->where('id_empresa_negocio', $suc)->first();
            if (! $tarifa) {
                throw new \RuntimeException('Elige el tipo de vehículo.');
            }
            $placa = self::normalizarPlaca($d['placa']);
            $inicio = Carbon::parse($d['inicio']);
            $fin = $inicio->copy()->addMonthsNoOverflow((int) $d['meses'])->subDay();
            $precio = round((float) $d['precio'], 2);
            $doc = trim((string) ($d['clinum'] ?? '')) ?: '00000000';

            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => $d['tdocod'], 'estadopago' => self::contado($suc), 'fecEmi' => now()->toDateString(),
                'tdicod' => $doc === '00000000' ? '1' : (strlen($doc) === 11 ? '6' : '1'), 'clinum' => $doc, 'clinom' => $d['clinom'],
                'clidir' => $d['clidir'] ?? null, 'telefono' => $d['telefono'] ?? null,
                'observaciones' => 'PENSIÓN ESTACIONAMIENTO DEL '.$inicio->format('d/m/Y').' AL '.$fin->format('d/m/Y'),
                'id_med_pag' => $d['id_med_pag'] ?? [], 'mon_med_pag' => $d['mon_med_pag'] ?? [], 'paga' => $d['paga'] ?? 0,
            ], [[
                'IdProducto' => self::productoDe($user, $tarifa), 'cantidad' => 1, 'precio' => $precio,
                'descripcion' => mb_substr('PENSIÓN ESTACIONAMIENTO '.$tarifa->nombre.' · PLACA '.$placa.' ('.$inicio->format('d/m').' - '.$fin->format('d/m/Y').')', 0, 150),
            ]], ['ped_tip' => self::ORIGEN, 'IdUsuario_ven' => $user->IdUsuario, 'placa' => $placa]);

            $aboId = DB::table('est_abonados')->insertGetId([
                'placa' => $placa, 'placa2' => self::normalizarPlaca($d['placa2'] ?? null) ?: null,
                'clinum' => $doc === '00000000' ? null : $doc, 'clinom' => mb_strtoupper(trim($d['clinom'])), 'telefono' => $d['telefono'] ?? null,
                'tar_id' => $tarifa->tar_id, 'esp_id' => $d['esp_id'] ?? null, 'inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString(),
                'precio' => $precio, 'IdCpe_cabecera' => $cabId, 'estado' => 'ACTIVO', 'obs' => $d['obs'] ?? null,
                'IdUsuario' => $user->IdUsuario, 'creado' => now(), 'id_empresa_negocio' => $suc,
            ]);

            return ['cabId' => $cabId, 'aboId' => $aboId];
        });
    }

    // ------------------------------------------------------------------ entrada

    public static function registrarEntrada(User $user, array $d): object
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $d) {
            $placa = self::normalizarPlaca($d['placa']);
            if (strlen($placa) < 3) {
                throw new \RuntimeException('Escribe la placa.');
            }
            $tarifa = DB::table('est_tarifas')->where('tar_id', $d['tar_id'])->where('id_empresa_negocio', $suc)->where('activo', 1)->first();
            if (! $tarifa) {
                throw new \RuntimeException('Elige el tipo de vehículo.');
            }
            $dentro = DB::table('est_tickets')->where('id_empresa_negocio', $suc)->where('placa', $placa)
                ->whereIn('estado', ['DENTRO', 'SOLICITADO'])->lockForUpdate()->value('numero');
            if ($dentro) {
                throw new \RuntimeException('La placa '.$placa.' ya está dentro (ticket N° '.$dentro.').');
            }

            $espacio = null;
            if (! empty($d['esp_id'])) {
                $espacio = DB::table('est_espacios')->where('esp_id', $d['esp_id'])->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
                if (! $espacio || $espacio->estado !== 'ACTIVO') {
                    throw new \RuntimeException('Ese espacio no está disponible.');
                }
                $ocupado = DB::table('est_tickets')->where('esp_id', $espacio->esp_id)->whereIn('estado', ['DENTRO', 'SOLICITADO'])->value('placa');
                if ($ocupado) {
                    throw new \RuntimeException('El espacio '.$espacio->codigo.' está ocupado por '.$ocupado.'.');
                }
            }

            $numero = (int) DB::table('est_tickets')->where('id_empresa_negocio', $suc)->lockForUpdate()->max('numero') + 1;
            $abonado = self::abonadoVigente($suc, $placa);
            $id = DB::table('est_tickets')->insertGetId([
                'numero' => $numero, 'codigo' => self::codigoNuevo(), 'placa' => $placa, 'tar_id' => $tarifa->tar_id, 'tipo' => $tarifa->nombre,
                'esp_id' => $espacio?->esp_id, 'espacio' => $espacio?->codigo, 'abo_id' => $abonado?->abo_id, 'entrada' => now(),
                'estado' => 'DENTRO', 'valet' => (int) ! empty($d['valet']),
                'llavero' => ! empty($d['valet']) ? mb_strtoupper(trim((string) ($d['llavero'] ?? ''))) ?: null : null,
                'marca' => mb_strtoupper(trim((string) ($d['marca'] ?? ''))) ?: null, 'color' => mb_strtoupper(trim((string) ($d['color'] ?? ''))) ?: null,
                'observaciones' => trim((string) ($d['observaciones'] ?? '')) ?: null,
                'cliente' => mb_strtoupper(trim((string) ($d['cliente'] ?? ''))) ?: null, 'telefono' => trim((string) ($d['telefono'] ?? '')) ?: null,
                'IdUsuario_entrada' => $user->IdUsuario, 'IdUsuario_valet' => ! empty($d['valet']) ? ($d['IdUsuario_valet'] ?? null) : null,
                'id_empresa_negocio' => $suc,
            ]);

            return DB::table('est_tickets')->where('tic_id', $id)->first();
        });
    }

    private static function codigoNuevo(): string
    {
        do {
            $codigo = Str::upper(Str::random(10));
        } while (DB::table('est_tickets')->where('codigo', $codigo)->exists());

        return $codigo;
    }

    // ------------------------------------------------------------------ salida

    /**
     * Lo que debe pagar un ticket ahora (o al momento en que salió).
     *
     * @return array{minutos: int, duracion: string, importe: float, penalidad: float, detalle: string, abonado: ?object}
     */
    public static function cotizar(object $ticket, bool $perdido = false): array
    {
        $tarifa = DB::table('est_tarifas')->where('tar_id', $ticket->tar_id)->first();
        $salida = $ticket->salida ? Carbon::parse($ticket->salida) : now();
        $abonado = self::abonadoVigente((int) $ticket->id_empresa_negocio, $ticket->placa);

        if (! $tarifa) {
            $calculo = ['minutos' => 0, 'importe' => 0.0, 'detalle' => 'La tarifa ya no existe'];
        } else {
            $calculo = self::calcular($tarifa, Carbon::parse($ticket->entrada), $salida);
        }
        if ($abonado) {
            $calculo['importe'] = 0.0;
            $calculo['detalle'] = 'Abonado: pensión vigente hasta el '.Carbon::parse($abonado->fin)->format('d/m/Y');
        }

        return $calculo + [
            'duracion' => self::duracion($calculo['minutos']),
            'penalidad' => $perdido && $tarifa ? (float) $tarifa->perdido : 0.0,
            'abonado' => $abonado,
        ];
    }

    /**
     * Da salida al vehículo y, si hay algo que cobrar, emite el comprobante.
     *
     * @return array{cabId: ?int, total: float}
     */
    public static function darSalida(User $user, int $ticketId, array $d): array
    {
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $suc, $ticketId, $d) {
            $ticket = DB::table('est_tickets')->where('tic_id', $ticketId)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $ticket || ! in_array($ticket->estado, ['DENTRO', 'SOLICITADO'])) {
                throw new \RuntimeException('Este vehículo ya salió o el ticket fue anulado.');
            }
            $c = self::cotizar($ticket, ! empty($d['perdido']));
            $descuento = round(min((float) ($d['descuento'] ?? 0), $c['importe'] + $c['penalidad']), 2);
            $total = round($c['importe'] + $c['penalidad'] - $descuento, 2);
            if ($descuento > 0 && trim((string) ($d['motivo'] ?? '')) === '') {
                throw new \RuntimeException('Escribe el motivo del descuento.');
            }

            $cabId = null;
            if ($total > 0) {
                $tarifa = DB::table('est_tarifas')->where('tar_id', $ticket->tar_id)->first();
                $doc = trim((string) ($d['clinum'] ?? '')) ?: '00000000';
                $producto = $tarifa ? self::productoDe($user, $tarifa) : null;
                $tiempo = $ticket->tipo.' · PLACA '.$ticket->placa.' · '.Carbon::parse($ticket->entrada)->format('d/m H:i').' a '.now()->format('d/m H:i').' ('.$c['duracion'].')';
                $lineas = [];
                $cobroTiempo = round($c['importe'] - min($descuento, $c['importe']), 2);
                if ($cobroTiempo > 0) {
                    $lineas[] = ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => $cobroTiempo, 'descripcion' => mb_substr('ESTACIONAMIENTO '.$tiempo, 0, 150)];
                }
                $cobroPenalidad = round($total - $cobroTiempo, 2);
                if ($cobroPenalidad > 0) {
                    $lineas[] = ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => $cobroPenalidad, 'descripcion' => 'PENALIDAD POR TICKET PERDIDO · PLACA '.$ticket->placa];
                }

                $cabId = Comprobante::emitir($user, self::turno($user), [
                    'tdocod' => $d['tdocod'], 'estadopago' => self::contado($suc), 'fecEmi' => now()->toDateString(),
                    'tdicod' => $doc === '00000000' ? '1' : (strlen($doc) === 11 ? '6' : '1'), 'clinum' => $doc,
                    'clinom' => ($d['clinom'] ?? null) ?: ($ticket->cliente ?: 'CLIENTES VARIOS'), 'clidir' => $d['clidir'] ?? null,
                    'telefono' => $ticket->telefono, 'observaciones' => 'TICKET N° '.$ticket->numero.' · PLACA '.$ticket->placa,
                    'id_med_pag' => $d['id_med_pag'] ?? [], 'mon_med_pag' => $d['mon_med_pag'] ?? [], 'paga' => $d['paga'] ?? 0,
                ], $lineas, ['ped_tip' => self::ORIGEN, 'IdUsuario_ven' => $user->IdUsuario, 'placa' => $ticket->placa]);
            }

            DB::table('est_tickets')->where('tic_id', $ticket->tic_id)->update([
                'salida' => now(), 'minutos' => $c['minutos'], 'importe' => $c['importe'], 'penalidad' => $c['penalidad'],
                'descuento' => $descuento, 'total' => $total, 'estado' => 'SALIO', 'IdCpe_cabecera' => $cabId,
                'motivo' => $descuento > 0 ? mb_substr(trim($d['motivo']), 0, 200) : $ticket->motivo,
                'IdUsuario_salida' => $user->IdUsuario,
            ]);

            return ['cabId' => $cabId, 'total' => $total];
        });
    }

    /** Si el comprobante se anula (o tiene nota de crédito total), la pensión que pagó queda anulada y el cobro del ticket se marca */
    public static function revertirComprobante(int $cabId): void
    {
        DB::table('est_abonados')->where('IdCpe_cabecera', $cabId)->where('estado', 'ACTIVO')->update(['estado' => 'ANULADO']);
        DB::table('est_tickets')->where('IdCpe_cabecera', $cabId)->where('estado', 'SALIO')
            ->update(['estado' => 'ANULADO', 'motivo' => 'Se anuló el comprobante del cobro']);
    }

    // ------------------------------------------------------------------ pantalla

    /** Tickets dentro con lo que deben ahora mismo */
    public static function dentro(int $suc): Collection
    {
        $tarifas = DB::table('est_tarifas')->where('id_empresa_negocio', $suc)->get()->keyBy('tar_id');
        $abonados = DB::table('est_abonados')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')
            ->where('inicio', '<=', now()->toDateString())->where('fin', '>=', now()->toDateString())->get(['placa', 'placa2']);
        $placasAbonadas = $abonados->pluck('placa')->merge($abonados->pluck('placa2'))->filter()->flip();

        return DB::table('est_tickets')->where('id_empresa_negocio', $suc)->whereIn('estado', ['DENTRO', 'SOLICITADO'])
            ->orderByRaw("estado = 'SOLICITADO' desc")->orderBy('entrada')->get()
            ->map(function ($t) use ($tarifas, $placasAbonadas) {
                $tarifa = $tarifas[$t->tar_id] ?? null;
                $c = $tarifa ? self::calcular($tarifa, Carbon::parse($t->entrada), now()) : ['minutos' => 0, 'importe' => 0];
                $abonado = isset($placasAbonadas[$t->placa]);

                return [
                    'tic_id' => $t->tic_id, 'numero' => $t->numero, 'placa' => $t->placa, 'tipo' => $t->tipo, 'tar_id' => $t->tar_id,
                    'icono' => self::ICONOS[$tarifa->icono ?? 'auto'] ?? 'fa-car-side', 'esp_id' => $t->esp_id, 'espacio' => $t->espacio,
                    'entrada' => $t->entrada, 'minutos' => $c['minutos'], 'importe' => $abonado ? 0 : $c['importe'], 'abonado' => $abonado,
                    'estado' => $t->estado, 'valet' => (bool) $t->valet, 'llavero' => $t->llavero, 'marca' => $t->marca, 'color' => $t->color,
                    'observaciones' => $t->observaciones, 'cliente' => $t->cliente, 'telefono' => $t->telefono, 'solicitado' => $t->solicitado,
                ];
            })->values();
    }

    /** @return array{ingresos: int, salidas: int, recaudado: float} */
    public static function resumenHoy(int $suc): array
    {
        $hoy = now()->toDateString();
        $salidas = DB::table('est_tickets')->where('id_empresa_negocio', $suc)->where('estado', 'SALIO')->whereDate('salida', $hoy);

        return [
            'ingresos' => DB::table('est_tickets')->where('id_empresa_negocio', $suc)->where('estado', '!=', 'ANULADO')->whereDate('entrada', $hoy)->count(),
            'salidas' => (clone $salidas)->count(),
            'recaudado' => (float) (clone $salidas)->sum('total'),
        ];
    }

    // ------------------------------------------------------------------ apoyo

    private static function turno(User $user): Turno
    {
        $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->where('estado', 'ABIERTO')->lockForUpdate()->first();
        if (! $turno) {
            throw new \RuntimeException('Debes aperturar tu turno de caja antes de cobrar.');
        }

        return $turno;
    }

    private static function contado(int $suc): ?int
    {
        return DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
    }

    /** Producto (concepto del comprobante) de una tarifa: en la categoría ESTACIONAMIENTO, sin stock */
    public static function productoDe(User $user, object $tarifa): int
    {
        $datos = ['pronom' => 'ESTACIONAMIENTO '.mb_strtoupper($tarifa->nombre), 'propun' => $tarifa->precio];
        if ($tarifa->IdProducto && DB::table('productos')->where('IdProducto', $tarifa->IdProducto)->exists()) {
            DB::table('productos')->where('IdProducto', $tarifa->IdProducto)->update($datos);

            return (int) $tarifa->IdProducto;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cat_nom', 'ESTACIONAMIENTO')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'ESTACIONAMIENTO', 'IdEmpresa' => $user->IdEmpresa,
                'id_empresa_negocio' => $user->id_empresa_negocio, 'tip_pro_id' => DB::table('tipo_producto')
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);

        $id = (int) Producto::create($datos + ['procod' => 'E'.$tarifa->tar_id.'-'.time(), 'umecod' => 'ZZ', 'costo' => 0, 'promocion' => 2,
            'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio])->IdProducto;
        DB::table('est_tarifas')->where('tar_id', $tarifa->tar_id)->update(['IdProducto' => $id]);

        return $id;
    }
}
