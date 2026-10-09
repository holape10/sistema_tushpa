<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\AnulacionVenta;
use App\Support\Comprobante;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Greenter\See;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Ws\Services\WsdlProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Envío de comprobantes a SUNAT con Greenter.
 *  - Individual (sendBill): facturas, boletas, notas de crédito y débito.
 *  - Resumen diario (sendSummary + ticket): boletas y sus notas. Se pueden enviar varios resúmenes del mismo día;
 *    cada uno toma solo lo que aún no fue aceptado y lleva su propio correlativo (RC-YYYYMMDD-001, -002, ...).
 *
 * Estados en cpe_cabecera.est_sunat: PENDIENTE | ACEPTADO | OBSERVADO | RECHAZADO | ERROR | EN RESUMEN
 */
class SunatService
{
    public const TIPOS_ELECTRONICOS = ['01', '03', '07', '08'];

    public const ESTADOS_REENVIABLES = ['PENDIENTE', 'ERROR', 'RECHAZADO'];

    private const MAX_POR_RESUMEN = 500;

    /** Días que SUNAT da para comunicar la baja de una factura o boleta */
    public const PLAZO_BAJA_DIAS = 7;

    private ?See $see = null;

    public function __construct(private Empresa $empresa, private EmpresaNegocio $negocio) {}

    public static function paraUsuario($user): self
    {
        $negocio = EmpresaNegocio::findOrFail($user->id_empresa_negocio);

        return new self(Empresa::findOrFail($negocio->IdEmpresa), $negocio);
    }

    public function esProduccion(): bool
    {
        return (string) $this->empresa->produccion === '1';
    }

    // ------------------------------------------------------------------ configuración

    private function see(): See
    {
        if ($this->see) {
            return $this->see;
        }

        if ((string) $this->empresa->tip_env_fac_id === '02') {
            throw new RuntimeException('La empresa está configurada para envío por OSE; este módulo solo envía directo a SUNAT.');
        }

        $pem = storage_path('app/certificados/'.$this->empresa->IdEmpresa.'.pem');
        if (! is_file($pem)) {
            throw new RuntimeException('Falta el certificado digital. Súbelo en Mantenimiento > Empresas > Editar.');
        }

        $see = new See;
        // Firma SHA-256: el servidor (OpenSSL 3 en AlmaLinux 10) ya no firma con SHA-1
        $firma = new FirmaSha256;
        $firma->setCertificate(file_get_contents($pem));
        $see->getFactory()->setSigner($firma);
        if ($this->esProduccion()) {
            if (! $this->empresa->wsusuario || ! $this->empresa->claveSunat) {
                throw new RuntimeException('Configura el usuario y la clave SOL de la empresa.');
            }
            $see->setService(SunatEndpoints::FE_PRODUCCION);
            $see->setClaveSOL($this->empresa->IdEmpresa, $this->empresa->wsusuario, $this->empresa->claveSunat);
        } else {
            // Beta de SUNAT: credenciales públicas de prueba
            $see->setService(SunatEndpoints::FE_BETA);
            $see->setClaveSOL($this->empresa->IdEmpresa, 'MODDATOS', 'moddatos');
        }

        return $this->see = $see;
    }

    private const DEPARTAMENTOS = ['AMAZONAS', 'ANCASH', 'APURIMAC', 'AREQUIPA', 'AYACUCHO', 'CAJAMARCA', 'CALLAO', 'CUSCO',
        'HUANCAVELICA', 'HUANUCO', 'ICA', 'JUNIN', 'LA LIBERTAD', 'LAMBAYEQUE', 'LIMA', 'LORETO', 'MADRE DE DIOS', 'MOQUEGUA',
        'PASCO', 'PIURA', 'PUNO', 'SAN MARTIN', 'TACNA', 'TUMBES', 'UCAYALI'];

    /**
     * Si la sucursal no tiene departamento/provincia/distrito se sacan de la dirección del padrón SUNAT
     * ("JR. ... LORETO - MAYNAS - IQUITOS") y se guardan para la próxima vez. Sin esto SUNAT observa (4097/4098).
     */
    private function completarUbicacion(): void
    {
        $n = $this->negocio;
        if ($n->departamento && $n->provincia && $n->distrito) {
            return;
        }
        $partes = array_map('trim', explode(' - ', strtoupper((string) ($n->direccion ?: $this->empresa->DirEmpresa))));
        if (count($partes) < 3) {
            return;
        }
        $distrito = array_pop($partes);
        $provincia = array_pop($partes);
        $resto = ' '.implode(' - ', $partes);
        $departamento = collect(self::DEPARTAMENTOS)->first(fn ($d) => str_ends_with($resto, ' '.$d));
        if (! $departamento) {
            return;
        }

        $n->departamento = $n->departamento ?: $departamento;
        $n->provincia = $n->provincia ?: $provincia;
        $n->distrito = $n->distrito ?: $distrito;
        $n->save();
    }

    private function company(): Company
    {
        $this->completarUbicacion();
        $n = $this->negocio;
        $address = (new Address)
            ->setUbigueo($n->ubigeo ?: '150101')
            ->setDepartamento($n->departamento ?: '-')
            ->setProvincia($n->provincia ?: '-')
            ->setDistrito($n->distrito ?: '-')
            ->setUrbanizacion('-')
            ->setDireccion($n->direccion ?: $this->empresa->DirEmpresa)
            ->setCodLocal(str_pad(preg_replace('/\D/', '', (string) $n->codigofiscal) ?: '0', 4, '0', STR_PAD_LEFT));

        return (new Company)
            ->setRuc($this->empresa->IdEmpresa)
            ->setRazonSocial($this->empresa->NomEmpresa)
            ->setNombreComercial($n->nombre_comercial ?: $this->empresa->NomEmpresa)
            ->setAddress($address);
    }

    private function carpeta(): string
    {
        $dir = storage_path('app/sunat/'.$this->empresa->IdEmpresa);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function rutaArchivo(string $nombre, string $tipo): string
    {
        return $this->carpeta().'/'.($tipo === 'cdr' ? 'R-'.$nombre.'.zip' : $nombre.'.xml');
    }

    // ------------------------------------------------------------------ armado del comprobante

    private function construirComprobante(object $cab): Invoice|Note
    {
        $detalles = DB::table('cpe_detalle')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->get();
        if ($detalles->isEmpty()) {
            throw new RuntimeException('El comprobante no tiene detalle.');
        }

        $esNota = in_array($cab->tdocod, ['07', '08'], true);
        $doc = $esNota ? new Note : new Invoice;

        $fecha = new DateTime(($cab->ccafem).' '.date('H:i:s', strtotime((string) $cab->fecha_hora)), new DateTimeZone('America/Lima'));
        $client = (new Client)
            ->setTipoDoc((string) $cab->tdicod)
            ->setNumDoc((string) $cab->ccandi)
            ->setRznSocial(mb_substr((string) $cab->ccanom, 0, 100));

        $valorVenta = round($cab->ccatvg + $cab->ccatexo + $cab->ccatinaf, 2);

        $doc->setUblVersion('2.1')
            ->setTipoDoc($cab->tdocod)
            ->setSerie($cab->serdoc)
            ->setCorrelativo((string) $cab->numdoc)
            ->setFechaEmision($fecha)
            ->setTipoMoneda($cab->moncod ?: 'PEN')
            ->setCompany($this->company())
            ->setClient($client)
            ->setMtoOperGravadas((float) $cab->ccatvg)
            ->setMtoOperExoneradas((float) $cab->ccatexo)
            ->setMtoOperInafectas((float) $cab->ccatinaf)
            ->setMtoIGV((float) $cab->ccaigv)
            ->setTotalImpuestos((float) $cab->ccaigv)
            ->setValorVenta($valorVenta)
            ->setSubTotal((float) $cab->ccaitv)
            ->setMtoImpVenta((float) $cab->ccaitv)
            ->setLegends([(new Legend)->setCode('1000')->setValue(NumeroLetras::convertir((float) $cab->ccaitv))]);

        if ($esNota) {
            if (! $cab->tdocod_ref || ! $cab->serie_ref || ! $cab->num_ref || ! $cab->tipnot) {
                throw new RuntimeException('La nota no tiene el documento que modifica o el motivo.');
            }
            $motivo = $cab->tdocod === '07'
                ? DB::table('tipo_nota_credito')->where('nccod', $cab->tipnot)->value('ncdes')
                : DB::table('tipo_nota_debito')->where('ndcod', $cab->tipnot)->value('nddes');
            $doc->setTipDocAfectado($cab->tdocod_ref)
                ->setNumDocfectado($cab->serie_ref.'-'.(int) $cab->num_ref)
                ->setCodMotivo($cab->tipnot)
                ->setDesMotivo(mb_substr(trim(($motivo ?? '').' '.($cab->ccaobs ?? '')), 0, 250) ?: 'NOTA');
        } else {
            $doc->setTipoOperacion($cab->topcod ?: '0101');
            if ($cab->estadopago === 'CREDITO' && $cab->ccafve) {
                $doc->setFormaPago(new FormaPagoCredito((float) $cab->ccaitv))
                    ->setCuotas([(new Cuota)->setMonto((float) $cab->ccaitv)
                        ->setFechaPago(new DateTime($cab->ccafve, new DateTimeZone('America/Lima')))])
                    ->setFecVencimiento(new DateTime($cab->ccafve, new DateTimeZone('America/Lima')));
            } else {
                $doc->setFormaPago(new FormaPagoContado);
            }
        }

        $porcentajeIgv = round((Comprobante::factorDe($cab) - 1) * 100, 2);
        $items = [];
        foreach ($detalles as $d) {
            $cant = (float) $d->cdecan ?: 1;
            $valor = (float) $d->cdepve;            // valor de venta de la línea (sin IGV)
            $igv = (float) $d->cdeigv;
            $afecto = (string) $d->tigcod ?: '20';
            $unidad = strtoupper((string) $d->umecod);

            $items[] = (new SaleDetail)
                ->setCodProducto($d->procod ?: 'P'.$d->IdProducto)
                ->setUnidad(in_array($unidad, ['', 'UNI', 'UND'], true) ? 'NIU' : $unidad)
                ->setCantidad($cant)
                ->setDescripcion(mb_substr((string) $d->cdedes, 0, 250))
                ->setMtoBaseIgv($valor)
                ->setPorcentajeIgv($afecto === '10' ? $porcentajeIgv : 0)
                ->setIgv($igv)
                ->setTipAfeIgv($afecto)
                ->setTotalImpuestos($igv)
                ->setMtoValorVenta($valor)
                ->setMtoValorUnitario(round($valor / $cant, 10))
                ->setMtoPrecioUnitario((float) $d->cdepuni);
        }
        $doc->setDetails($items);

        return $doc;
    }

    // ------------------------------------------------------------------ envío individual

    /** @return array{ok: bool, estado: string, codigo: ?string, mensaje: string} */
    public function enviarComprobante(int $id): array
    {
        $cab = DB::table('cpe_cabecera')
            ->where('IdCpe_cabecera', $id)
            ->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)
            ->first();

        if (! $cab) {
            throw new RuntimeException('Comprobante no encontrado.');
        }
        if (! in_array($cab->tdocod, self::TIPOS_ELECTRONICOS, true)) {
            throw new RuntimeException('La nota de venta es un documento interno y no se envía a SUNAT.');
        }
        if (! in_array($cab->est_sunat ?? 'PENDIENTE', self::ESTADOS_REENVIABLES, true)) {
            throw new RuntimeException("El comprobante {$cab->serdoc}-{$cab->numdoc} ya está {$cab->est_sunat}.");
        }

        $doc = $this->construirComprobante($cab);
        $see = $this->see();
        $resultado = $see->send($doc);

        $xml = $see->getFactory()->getLastXml();
        if ($xml) {
            file_put_contents($this->rutaArchivo($doc->getName(), 'xml'), $xml);
        }
        $hash = $xml && preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xml, $m) ? $m[1] : null;

        $r = $this->interpretar($resultado);
        if ($resultado instanceof BillResult && $resultado->getCdrZip()) {
            file_put_contents($this->rutaArchivo($doc->getName(), 'cdr'), $resultado->getCdrZip());
        }

        // 1033 = "ya fue registrado anteriormente": el primer envío sí llegó, pero la respuesta se perdió (internet).
        // Se pide a SUNAT su constancia (CDR) original; si no se puede, se toma como aceptado.
        $r = $this->resolver1033($r, $cab, $doc->getName());

        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update([
            'est_sunat' => $r['estado'],
            'ccasunrescod' => $r['codigo'],
            'ccadessun' => mb_substr($r['mensaje'], 0, 500),
            'ccaqr' => $hash ?? $cab->ccaqr,
            'enviado' => in_array($r['estado'], ['ACEPTADO', 'OBSERVADO'], true) ? 1 : 0,
        ]);

        return $r;
    }

    /** Si SUNAT respondió 1033 (ya registrado), se recupera su constancia o se toma como aceptado */
    public function resolver1033(array $r, object $cab, ?string $nombre = null): array
    {
        if ($r['ok'] || (int) preg_replace('/\D/', '', (string) $r['codigo']) !== 1033) {
            return $r;
        }

        return $this->consultarCdr($cab, $nombre) ?? ['ok' => true, 'estado' => 'ACEPTADO', 'codigo' => '1033',
            'mensaje' => 'SUNAT indica que ya fue registrado anteriormente (1033): el primer envío sí llegó. Se toma como ACEPTADO.'];
    }

    /**
     * Pide a SUNAT la constancia (CDR) de un comprobante ya enviado (servicio de consulta, solo en producción).
     *
     * @return array{ok: bool, estado: string, codigo: ?string, mensaje: string}|null null si no se pudo obtener
     */
    public function consultarCdr(object $cab, ?string $nombre = null): ?array
    {
        if (! $this->esProduccion() || ! $this->empresa->wsusuario || ! $this->empresa->claveSunat) {
            return null;
        }
        try {
            $ws = new SoapClient(WsdlProvider::getConsultPath());
            $ws->setService(SunatEndpoints::FE_CONSULTA_CDR);
            $ws->setCredentials($this->empresa->IdEmpresa.$this->empresa->wsusuario, $this->empresa->claveSunat);
            $servicio = new ConsultCdrService;
            $servicio->setClient($ws);
            $res = $servicio->getStatusCdr($this->empresa->IdEmpresa, $cab->tdocod, $cab->serdoc, (int) $cab->numdoc);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if (! $res->isSuccess() || ! $res->getCdrResponse()) {
            return null;
        }
        if ($res->getCdrZip()) {
            $nombre ??= $this->empresa->IdEmpresa.'-'.$cab->tdocod.'-'.$cab->serdoc.'-'.$cab->numdoc;
            file_put_contents($this->rutaArchivo($nombre, 'cdr'), $res->getCdrZip());
        }
        $r = $this->interpretarCdr($res->getCdrResponse());
        $r['mensaje'] = 'Constancia recuperada de SUNAT: '.$r['mensaje'];

        return $r;
    }

    /** Traduce la respuesta de SUNAT a un estado del sistema */
    private function interpretar($resultado): array
    {
        if (! $resultado->isSuccess()) {
            $error = $resultado->getError();
            $codigo = (string) ($error?->getCode() ?? '');
            $num = (int) preg_replace('/\D/', '', $codigo);
            // 2000-3999 = rechazo (hay que corregir y reenviar); lo demás = excepción o problema de conexión (reintentar)
            $estado = ($num >= 2000 && $num < 4000) ? 'RECHAZADO' : 'ERROR';

            return ['ok' => false, 'estado' => $estado, 'codigo' => $codigo ?: null,
                'mensaje' => trim(($codigo ? "[$codigo] " : '').($error?->getMessage() ?? 'Error desconocido'))];
        }

        return $this->interpretarCdr($resultado->getCdrResponse());
    }

    private function interpretarCdr(?CdrResponse $cdr): array
    {
        if (! $cdr) {
            return ['ok' => false, 'estado' => 'ERROR', 'codigo' => null, 'mensaje' => 'SUNAT no devolvió constancia (CDR).'];
        }

        $codigo = (int) $cdr->getCode();
        $notas = $cdr->getNotes() ?: [];
        $mensaje = trim($cdr->getDescription().($notas ? ' | Observaciones: '.implode(' | ', $notas) : ''));

        $estado = match (true) {
            $codigo === 0 => $notas ? 'OBSERVADO' : 'ACEPTADO',
            $codigo >= 4000 => 'OBSERVADO',
            $codigo >= 2000 => 'RECHAZADO',
            default => 'ERROR',
        };

        return ['ok' => in_array($estado, ['ACEPTADO', 'OBSERVADO'], true), 'estado' => $estado,
            'codigo' => (string) $cdr->getCode(), 'mensaje' => $mensaje];
    }

    // ------------------------------------------------------------------ resumen diario

    /** Boletas (y notas de boletas) de una fecha que todavía deben ir en un resumen */
    public function pendientesResumen(string $fecha)
    {
        return DB::table('cpe_cabecera')
            ->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)
            ->where('ccafem', $fecha)
            ->where(function ($q) {
                $q->where('tdocod', '03')
                    ->orWhere(fn ($q2) => $q2->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'B%'));
            })
            ->whereIn('est_sunat', self::ESTADOS_REENVIABLES)
            ->whereNull('ccabaj')
            ->orderBy('tdocod')->orderBy('serdoc')->orderBy('numdoc')
            ->get();
    }

    /** Envía uno o más resúmenes (de 500 en 500) con lo pendiente de la fecha. Devuelve los res_id creados. */
    public function enviarResumen(string $fecha): array
    {
        $docs = $this->pendientesResumen($fecha);
        if ($docs->isEmpty()) {
            throw new RuntimeException('No hay boletas pendientes de resumen para esa fecha.');
        }

        $see = $this->see();
        $creados = [];

        foreach ($docs->chunk(self::MAX_POR_RESUMEN) as $lote) {
            $hoy = now()->toDateString();

            // Correlativo del día por RUC (bloqueado para que dos usuarios no usen el mismo número)
            $resId = DB::transaction(function () use ($hoy, $fecha, $lote) {
                $ultimo = DB::table('resumenes')->where('IdEmpresa', $this->empresa->IdEmpresa)
                    ->where('res_fec_gen', $hoy)->lockForUpdate()->max('res_cor');
                $cor = (int) $ultimo + 1;

                return DB::table('resumenes')->insertGetId([
                    'res_fec_com' => $fecha, 'res_fec_gen' => $hoy, 'res_tip' => 'RC', 'tip_res_com' => '03',
                    'res_cor' => $cor, 'IdEmpresa' => $this->empresa->IdEmpresa,
                    'id_empresa_negocio' => $this->negocio->id_empresa_negocio,
                    'nom_arch' => $this->empresa->IdEmpresa.'-RC-'.str_replace('-', '', $hoy).'-'.str_pad((string) $cor, 3, '0', STR_PAD_LEFT),
                    'res_cant' => $lote->count(), 'res_total' => round($lote->sum('ccaitv'), 2),
                    'est_sunat' => 'GENERADO', 'IdUsuario' => Auth::id(), 'fecha_hora' => now(),
                ]);
            });

            $res = DB::table('resumenes')->where('res_id', $resId)->first();

            $detalles = $lote->map(function ($c) {
                $d = (new SummaryDetail)
                    ->setTipoDoc($c->tdocod)
                    ->setSerieNro($c->serdoc.'-'.$c->numdoc)
                    ->setEstado('1') // 1 = adicionar
                    ->setClienteTipo((string) $c->tdicod)
                    ->setClienteNro((string) $c->ccandi)
                    ->setTotal((float) $c->ccaitv)
                    ->setMtoOperGravadas((float) $c->ccatvg)
                    ->setMtoOperExoneradas((float) $c->ccatexo)
                    ->setMtoOperInafectas((float) $c->ccatinaf)
                    ->setMtoIGV((float) $c->ccaigv);
                if (in_array($c->tdocod, ['07', '08'], true)) {
                    $d->setDocReferencia((new Document)->setTipoDoc($c->tdocod_ref)->setNroDoc($c->serie_ref.'-'.(int) $c->num_ref));
                }

                return $d;
            })->values()->all();

            $summary = (new Summary)
                // ReferenceDate (fecGeneracion) = día de las boletas; IssueDate (fecResumen) = día en que se genera el resumen
                ->setFecGeneracion(new DateTime($fecha, new DateTimeZone('America/Lima')))
                ->setFecResumen(new DateTime($res->res_fec_gen, new DateTimeZone('America/Lima')))
                ->setCorrelativo(str_pad((string) $res->res_cor, 3, '0', STR_PAD_LEFT))
                ->setCompany($this->company())
                ->setDetails($detalles);

            $resultado = $see->send($summary);
            if ($xml = $see->getFactory()->getLastXml()) {
                file_put_contents($this->rutaArchivo($summary->getName(), 'xml'), $xml);
            }

            if (! $resultado->isSuccess()) {
                $e = $resultado->getError();
                DB::table('resumenes')->where('res_id', $resId)->update([
                    'est_sunat' => 'ERROR', 'error_code' => $e?->getCode(), 'error' => $e?->getMessage(),
                    'res_est' => trim('['.$e?->getCode().'] '.$e?->getMessage()),
                ]);
                $creados[] = $resId;

                continue;
            }

            DB::table('resumenes')->where('res_id', $resId)->update([
                'res_ticket' => $resultado->getTicket(), 'est_sunat' => 'ENVIADO', 'res_est' => 'Enviado, esperando respuesta del ticket.',
            ]);
            DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', $lote->pluck('IdCpe_cabecera'))->update([
                'res_id' => $resId, 'est_sunat' => 'EN RESUMEN', 'ccasunrescod' => null,
                'ccadessun' => 'Enviado en resumen '.$res->nom_arch,
            ]);

            $creados[] = $resId;
        }

        // SUNAT suele responder el ticket en segundos: se espera un momento y se consulta una vez
        if ($creados) {
            sleep(2);
        }
        foreach ($creados as $id) {
            $this->consultarTicket($id);
        }

        return $creados;
    }

    // ------------------------------------------------------------------ comunicación de baja

    /**
     * Da de baja ante SUNAT una factura o boleta ya aceptada (hasta 7 días después de emitida):
     *  - Factura: Comunicación de Baja (RA-YYYYMMDD-NNN).
     *  - Boleta: Resumen Diario con estado 3 = anular (RC-YYYYMMDD-NNN).
     * La venta se anula en el sistema (stock, cobros) recién cuando SUNAT acepta el ticket.
     *
     * @return array{ok: bool, estado: string, mensaje: string}
     */
    public function comunicarBaja(int $id, string $motivo): array
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)
            ->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)->first();
        if (! $cab) {
            throw new RuntimeException('Comprobante no encontrado.');
        }
        if (! in_array($cab->tdocod, ['01', '03'], true)) {
            throw new RuntimeException('La comunicación de baja es para facturas y boletas.');
        }
        if ($cab->ccabaj) {
            throw new RuntimeException('El comprobante ya está anulado.');
        }
        if (! in_array($cab->est_sunat, ['ACEPTADO', 'OBSERVADO'], true)) {
            throw new RuntimeException("El comprobante está {$cab->est_sunat} en SUNAT: primero envíalo y, cuando esté ACEPTADO, dale de baja.");
        }
        if ($cab->res_id_baja) {
            $previo = DB::table('resumenes')->where('res_id', $cab->res_id_baja)->first();
            if ($previo && ! in_array($previo->est_sunat, ['RECHAZADO', 'ERROR'], true)) {
                throw new RuntimeException("La baja ya se envió ({$previo->nom_arch}, {$previo->est_sunat}). Consulta su ticket en Resumen diario.");
            }
        }
        $limite = now()->subDays(self::PLAZO_BAJA_DIAS)->toDateString();
        if ($cab->ccafem < $limite) {
            throw new RuntimeException('SUNAT solo acepta la baja hasta '.self::PLAZO_BAJA_DIAS.' días después de emitido el comprobante. Emite una Nota de Crédito.');
        }

        $esFactura = $cab->tdocod === '01';
        $tipo = $esFactura ? 'RA' : 'RC';
        $hoy = now()->toDateString();
        $motivo = mb_strtoupper(mb_substr(trim($motivo), 0, 70));

        $resId = DB::transaction(function () use ($hoy, $cab, $tipo) {
            $cor = (int) DB::table('resumenes')->where('IdEmpresa', $this->empresa->IdEmpresa)
                ->where('res_fec_gen', $hoy)->lockForUpdate()->max('res_cor') + 1;

            return DB::table('resumenes')->insertGetId([
                'res_fec_com' => $cab->ccafem, 'res_fec_gen' => $hoy, 'res_tip' => $tipo, 'tip_res_com' => $cab->tdocod,
                'res_cor' => $cor, 'IdEmpresa' => $this->empresa->IdEmpresa, 'es_baja' => 1,
                'id_empresa_negocio' => $this->negocio->id_empresa_negocio,
                'nom_arch' => $this->empresa->IdEmpresa.'-'.$tipo.'-'.str_replace('-', '', $hoy).'-'.str_pad((string) $cor, 3, '0', STR_PAD_LEFT),
                'res_cant' => 1, 'res_total' => round((float) $cab->ccaitv, 2),
                'est_sunat' => 'GENERADO', 'IdUsuario' => Auth::id(), 'fecha_hora' => now(),
            ]);
        });
        $res = DB::table('resumenes')->where('res_id', $resId)->first();
        $correlativo = str_pad((string) $res->res_cor, 3, '0', STR_PAD_LEFT);
        $zona = new DateTimeZone('America/Lima');

        if ($esFactura) {
            $documento = (new Voided)
                ->setCorrelativo($correlativo)
                ->setFecGeneracion(new DateTime($cab->ccafem, $zona))
                ->setFecComunicacion(new DateTime($hoy, $zona))
                ->setCompany($this->company())
                ->setDetails([(new VoidedDetail)
                    ->setTipoDoc('01')->setSerie($cab->serdoc)->setCorrelativo((string) (int) $cab->numdoc)
                    ->setDesMotivoBaja($motivo)]);
        } else {
            $documento = (new Summary)
                ->setFecGeneracion(new DateTime($cab->ccafem, $zona))
                ->setFecResumen(new DateTime($hoy, $zona))
                ->setCorrelativo($correlativo)
                ->setCompany($this->company())
                ->setDetails([(new SummaryDetail)
                    ->setTipoDoc('03')
                    ->setSerieNro($cab->serdoc.'-'.$cab->numdoc)
                    ->setEstado('3') // 3 = anular
                    ->setClienteTipo((string) $cab->tdicod)
                    ->setClienteNro((string) $cab->ccandi)
                    ->setTotal((float) $cab->ccaitv)
                    ->setMtoOperGravadas((float) $cab->ccatvg)
                    ->setMtoOperExoneradas((float) $cab->ccatexo)
                    ->setMtoOperInafectas((float) $cab->ccatinaf)
                    ->setMtoIGV((float) $cab->ccaigv)]);
        }

        $see = $this->see();
        $resultado = $see->send($documento);
        if ($xml = $see->getFactory()->getLastXml()) {
            file_put_contents($this->rutaArchivo($documento->getName(), 'xml'), $xml);
        }

        if (! $resultado->isSuccess()) {
            $e = $resultado->getError();
            $mensaje = trim('['.$e?->getCode().'] '.$e?->getMessage());
            DB::table('resumenes')->where('res_id', $resId)->update([
                'est_sunat' => 'ERROR', 'error_code' => $e?->getCode(), 'error' => $e?->getMessage(), 'res_est' => $mensaje,
            ]);

            return ['ok' => false, 'estado' => 'ERROR', 'mensaje' => 'SUNAT no recibió la baja: '.$mensaje];
        }

        DB::table('resumenes')->where('res_id', $resId)->update([
            'res_ticket' => $resultado->getTicket(), 'est_sunat' => 'ENVIADO', 'res_est' => 'Enviado, esperando respuesta del ticket.',
        ]);
        // El motivo y el usuario quedan guardados: se usan al anular la venta cuando SUNAT acepte
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update([
            'res_id_baja' => $resId, 'motivo_baja' => $motivo, 'IdUsuario_baja' => Auth::id(),
        ]);

        sleep(2);
        $r = $this->consultarTicket($resId);

        return match ($r['estado']) {
            'ACEPTADO' => ['ok' => true, 'estado' => 'ACEPTADO', 'mensaje' => "Baja aceptada por SUNAT ({$res->nom_arch}). La venta quedó anulada y el stock volvió al almacén."],
            'RECHAZADO' => ['ok' => false, 'estado' => 'RECHAZADO', 'mensaje' => 'SUNAT rechazó la baja: '.$r['mensaje']],
            default => ['ok' => true, 'estado' => 'EN PROCESO', 'mensaje' => "Baja enviada ({$res->nom_arch}). SUNAT aún la procesa: consulta el ticket en Resumen diario en unos minutos; al aceptarse se anula la venta."],
        };
    }

    /** SUNAT aceptó la baja: el comprobante queda ANULADO y la venta se anula en el sistema */
    private function aplicarBaja(object $res, array $r): void
    {
        $cabs = DB::table('cpe_cabecera')->where('res_id_baja', $res->res_id)->get();
        foreach ($cabs as $cab) {
            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update([
                'est_sunat' => 'ANULADO',
                'ccadessun' => mb_substr('Baja aceptada en '.$res->nom_arch.'. '.$r['mensaje'], 0, 500),
            ]);
            AnulacionVenta::anular((int) $cab->IdCpe_cabecera, (string) ($cab->motivo_baja ?: 'COMUNICACIÓN DE BAJA'),
                $cab->IdUsuario_baja ? (int) $cab->IdUsuario_baja : null, 'BAJA SUNAT');
        }
    }

    /** Consulta el ticket del resumen y actualiza el estado de las boletas que incluye */
    public function consultarTicket(int $resId): array
    {
        $res = DB::table('resumenes')->where('res_id', $resId)
            ->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)->first();
        if (! $res) {
            throw new RuntimeException('Resumen no encontrado.');
        }
        // Sin ticket o con respuesta final: no se vuelve a consultar (SUNAT da error al reconsultar un ticket cerrado)
        if (! $res->res_ticket || in_array($res->est_sunat, ['ACEPTADO', 'RECHAZADO'], true)) {
            return ['estado' => $res->est_sunat, 'mensaje' => $res->res_est ?? 'Sin ticket'];
        }

        $status = $this->see()->getStatus($res->res_ticket);
        $codigoTicket = (string) $status->getCode();

        if ($codigoTicket === '98') {
            DB::table('resumenes')->where('res_id', $resId)->update([
                'res_cod_est' => '98', 'est_sunat' => 'EN PROCESO', 'res_est' => 'SUNAT aún está procesando el resumen.',
            ]);

            return ['estado' => 'EN PROCESO', 'mensaje' => 'SUNAT aún está procesando el resumen. Consulta en unos minutos.'];
        }

        if (! $status->isSuccess() && ! $status->getCdrResponse()) {
            $e = $status->getError();
            DB::table('resumenes')->where('res_id', $resId)->update([
                'res_cod_est' => $codigoTicket, 'error_code_ticket' => $e?->getCode(), 'error_ticket' => $e?->getMessage(),
                'res_est' => trim('['.$e?->getCode().'] '.$e?->getMessage()),
            ]);

            return ['estado' => $res->est_sunat, 'mensaje' => trim('['.$e?->getCode().'] '.$e?->getMessage())];
        }

        if ($status->getCdrZip()) {
            file_put_contents($this->rutaArchivo($res->nom_arch, 'cdr'), $status->getCdrZip());
        }
        $r = $this->interpretarCdr($status->getCdrResponse());

        // Comunicación de baja: el comprobante sigue ACEPTADO hasta que SUNAT acepta la baja
        if ($res->es_baja ?? 0) {
            DB::table('resumenes')->where('res_id', $resId)->update($r['ok']
                ? ['res_cod_est' => $codigoTicket, 'est_sunat' => 'ACEPTADO', 'res_est' => $r['mensaje']]
                : ['res_cod_est' => $codigoTicket, 'est_sunat' => 'RECHAZADO', 'res_est' => $r['mensaje'],
                    'error_code_ticket' => $r['codigo'], 'error_ticket' => $r['mensaje']]);
            if ($r['ok']) {
                $this->aplicarBaja($res, $r);
            } else {
                DB::table('cpe_cabecera')->where('res_id_baja', $resId)->update([
                    'res_id_baja' => null,
                    'ccadessun' => mb_substr('Baja '.$res->nom_arch.' rechazada: '.$r['mensaje'], 0, 500),
                ]);
            }

            return ['estado' => $r['ok'] ? 'ACEPTADO' : 'RECHAZADO', 'mensaje' => $r['mensaje']];
        }

        if ($r['ok']) {
            DB::table('resumenes')->where('res_id', $resId)->update([
                'res_cod_est' => $codigoTicket, 'est_sunat' => 'ACEPTADO', 'res_est' => $r['mensaje'],
            ]);
            DB::table('cpe_cabecera')->where('res_id', $resId)->update([
                'est_sunat' => $r['estado'], 'ccasunrescod' => $r['codigo'], 'enviado' => 1,
                'ccadessun' => mb_substr('Aceptado en resumen '.$res->nom_arch.'. '.$r['mensaje'], 0, 500),
            ]);
        } else {
            // Rechazado: las boletas se liberan para poder ir en un nuevo resumen
            DB::table('resumenes')->where('res_id', $resId)->update([
                'res_cod_est' => $codigoTicket, 'est_sunat' => 'RECHAZADO', 'res_est' => $r['mensaje'],
                'error_code_ticket' => $r['codigo'], 'error_ticket' => $r['mensaje'],
            ]);
            DB::table('cpe_cabecera')->where('res_id', $resId)->update([
                'est_sunat' => 'RECHAZADO', 'res_id' => null, 'ccasunrescod' => $r['codigo'],
                'ccadessun' => mb_substr('Resumen '.$res->nom_arch.' rechazado: '.$r['mensaje'], 0, 500),
            ]);
        }

        return ['estado' => $r['ok'] ? 'ACEPTADO' : 'RECHAZADO', 'mensaje' => $r['mensaje']];
    }
}
