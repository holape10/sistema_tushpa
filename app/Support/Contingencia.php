<?php

namespace App\Support;

use App\Models\User;
use App\Support\Sunat\SunatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comprobantes de contingencia: cuando no se puede emitir electrónicamente (sin luz ni internet, por ejemplo en la
 * ribera), el negocio entrega boletas o facturas en PAPEL con serie numérica (0001, 0002…). Después se pasan al sistema
 * con esa misma serie, número y fecha y se envían a SUNAT como XML, uno por uno (sendBill), igual que en el sistema
 * anterior: SUNAT los acepta (si pasó el plazo, con observaciones). Nunca van en el resumen diario ni se les cambia la
 * fecha. Los registrados antes de este cambio quedaron en estado CONTINGENCIA y también se pueden enviar.
 */
class Contingencia
{
    public const ESTADO = 'CONTINGENCIA';

    /** Serie de contingencia: solo números (0001); las electrónicas empiezan con letra (B001, F001) */
    public const PATRON_SQL = '^[0-9]+$';

    public static function esSerie(?string $serie): bool
    {
        return $serie !== null && $serie !== '' && ctype_digit($serie);
    }

    /** Estados en que todavía se pueden enviar a SUNAT (los antiguos quedaron en CONTINGENCIA) */
    public static function porEnviar(): array
    {
        return array_merge(SunatService::ESTADOS_REENVIABLES, [self::ESTADO]);
    }

    /** Solo facturas y boletas */
    public const TIPOS = ['01', '03'];

    /** Hasta cuántos días atrás se puede pasar un comprobante en papel */
    public const DIAS_ATRAS = 31;

    /**
     * Serie y número del comprobante en papel, listos para guardar. Falla si la serie no es numérica, la fecha no
     * corresponde o ese número ya se registró.
     *
     * @param  array{serie?: mixed, numero?: mixed}  $d
     * @return array{0: string, 1: int}
     */
    public static function validar(User $user, string $tdocod, array $d, string $fecha): array
    {
        if (! in_array($tdocod, self::TIPOS, true)) {
            throw new \RuntimeException('En contingencia solo se registran facturas y boletas en papel.');
        }
        $serie = trim((string) ($d['serie'] ?? ''));
        $serie = ctype_digit($serie) && strlen($serie) <= 4 ? str_pad($serie, 4, '0', STR_PAD_LEFT) : '';
        $numero = (int) ($d['numero'] ?? 0);
        if ($serie === '' || $serie === '0000') {
            throw new \RuntimeException('La serie de contingencia es la impresa en el papel: 4 números (ej. 0001).');
        }
        if ($numero < 1) {
            throw new \RuntimeException('Escribe el número impreso en el comprobante en papel.');
        }
        try {
            $dia = Carbon::parse($fecha)->startOfDay();
        } catch (\Throwable) {
            throw new \RuntimeException('Fecha de emisión no válida.');
        }
        if ($dia->isFuture() || $dia->lt(now()->startOfDay()->subDays(self::DIAS_ATRAS))) {
            throw new \RuntimeException('La fecha es la del comprobante en papel: de hoy hasta '.self::DIAS_ATRAS.' días atrás.');
        }
        $ruc = (string) $user->IdEmpresa;
        $repetido = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->where('tdocod', $tdocod)->where('serdoc', $serie)->where('numdoc', $numero)->exists();
        if ($repetido) {
            throw new \RuntimeException("El comprobante en papel {$serie}-{$numero} ya está registrado.");
        }

        return [$serie, $numero];
    }

    /** Los de un mes de la sucursal, para revisarlos y declararlos */
    public static function delMes(int $sucursal, string $mes)
    {
        $inicio = Carbon::parse($mes.'-01');

        return DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereIn('tdocod', self::TIPOS)
            ->where('serdoc', 'regexp', self::PATRON_SQL)
            ->whereBetween('ccafem', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()])
            ->orderBy('ccafem')->orderBy('tdocod')->orderBy('serdoc')->orderBy('numdoc')
            ->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'tdicod', 'ccandi', 'ccanom', 'ccatvg', 'ccatexo', 'ccatinaf', 'ccaigv', 'ccaitv', 'ccabaj', 'moncod',
                'est_sunat', 'ccadessun']);
    }
}
