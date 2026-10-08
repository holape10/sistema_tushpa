<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * Guía de remisión electrónica remitente (GRE 2022, UBL 2.1 DespatchAdvice, versión 2.0).
 *
 * SUNAT la recibe por su API REST (no por el servicio SOAP de facturas):
 *  1. Token OAuth2: client_id/client_secret (credenciales API de SUNAT) + RUC y usuario/clave SOL.
 *  2. Envío del ZIP firmado (en base64 con su hash SHA-256) → número de ticket.
 *  3. Consulta del ticket → CDR (aceptada o rechazada) y el enlace del QR de la representación impresa.
 * SUNAT no tiene ambiente de pruebas público para la GRE: se envía solo con la empresa en producción.
 */
class GuiaRemision
{
    private const URL_TOKEN = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/%s/oauth2/token/';

    private const URL_API = 'https://api-cpe.sunat.gob.pe/v1/contribuyente/gem/comprobantes';

    /** Catálogo 20: motivos de traslado */
    public const MOTIVOS = [
        '01' => 'Venta', '02' => 'Compra', '03' => 'Venta con entrega a terceros',
        '04' => 'Traslado entre establecimientos de la misma empresa', '05' => 'Consignación', '06' => 'Devolución',
        '07' => 'Recojo de bienes transformados', '08' => 'Importación', '09' => 'Exportación', '13' => 'Otros',
        '14' => 'Venta sujeta a confirmación del comprador', '17' => 'Traslado de bienes para transformación',
        '18' => 'Traslado emisor itinerante CP',
    ];

    public const MODALIDADES = ['01' => 'Transporte público (empresa de transportes)', '02' => 'Transporte privado (vehículo propio)'];

    /** Comprobantes que se pueden relacionar (catálogo 61) */
    private const DOCS_RELACIONADOS = ['01' => 'Factura', '03' => 'Boleta de Venta', '09' => 'Guía de remisión remitente', '12' => 'Ticket'];

    public function __construct(private Empresa $empresa, private EmpresaNegocio $negocio) {}

    public static function paraUsuario(User $user): self
    {
        $negocio = EmpresaNegocio::findOrFail($user->id_empresa_negocio);

        return new self(Empresa::findOrFail($negocio->IdEmpresa), $negocio);
    }

    // ------------------------------------------------------------------ registro

    /**
     * Guarda la guía con el siguiente número de la serie de la sucursal.
     *
     * @param  array<string, mixed>  $d
     * @param  array<int, array{IdProducto?: ?int, codigo?: ?string, descripcion: string, umecod?: ?string, cantidad: float}>  $items
     */
    public static function registrar(User $user, array $d, array $items): int
    {
        return DB::transaction(function () use ($user, $d, $items) {
            $suc = DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            $serie = strtoupper($suc->SerGuia ?: 'T001');
            $numero = (int) $suc->NumGuia + 1;
            // Por si alguien emitió con otro sistema: nunca repetir un número ya usado
            $max = (int) DB::table('gre_cabecera')->where('id_empresa_negocio', $suc->id_empresa_negocio)->where('serie', $serie)->max('numero');
            $numero = max($numero, $max + 1);

            $id = DB::table('gre_cabecera')->insertGetId([
                'serie' => $serie, 'numero' => $numero, 'fecha_emision' => now()->toDateString(), 'hora_emision' => now()->format('H:i:s'),
                'fecha_traslado' => $d['fecha_traslado'], 'motivo' => $d['motivo'], 'motivo_desc' => $d['motivo_desc'] ?? null,
                'modalidad' => $d['modalidad'], 'peso' => $d['peso'], 'unidad_peso' => $d['unidad_peso'] ?? 'KGM', 'bultos' => $d['bultos'] ?? null,
                'dest_tdicod' => $d['dest_tdicod'], 'dest_num' => $d['dest_num'], 'dest_nom' => mb_strtoupper($d['dest_nom']),
                'partida_ubigeo' => $d['partida_ubigeo'], 'partida_direccion' => mb_strtoupper($d['partida_direccion']), 'partida_codlocal' => $d['partida_codlocal'] ?? null,
                'llegada_ubigeo' => $d['llegada_ubigeo'], 'llegada_direccion' => mb_strtoupper($d['llegada_direccion']), 'llegada_codlocal' => $d['llegada_codlocal'] ?? null,
                'transp_ruc' => $d['transp_ruc'] ?? null, 'transp_nom' => isset($d['transp_nom']) ? mb_strtoupper($d['transp_nom']) : null, 'transp_mtc' => $d['transp_mtc'] ?? null,
                'cond_tdicod' => $d['cond_tdicod'] ?? null, 'cond_num' => $d['cond_num'] ?? null,
                'cond_nombres' => isset($d['cond_nombres']) ? mb_strtoupper($d['cond_nombres']) : null,
                'cond_apellidos' => isset($d['cond_apellidos']) ? mb_strtoupper($d['cond_apellidos']) : null,
                'cond_licencia' => isset($d['cond_licencia']) ? strtoupper($d['cond_licencia']) : null,
                'placa' => isset($d['placa']) ? strtoupper(str_replace([' ', '-'], '', $d['placa'])) : null,
                'vehiculo_m1l' => (int) ($d['vehiculo_m1l'] ?? 0),
                'doc_tdocod' => $d['doc_tdocod'] ?? null, 'doc_numero' => $d['doc_numero'] ?? null, 'IdCpe_cabecera' => $d['IdCpe_cabecera'] ?? null,
                'observacion' => $d['observacion'] ?? null, 'est_sunat' => 'PENDIENTE', 'token' => Str::random(32),
                'IdUsuario' => $user->IdUsuario, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc->id_empresa_negocio, 'creado' => now(),
            ]);
            foreach ($items as $it) {
                DB::table('gre_detalle')->insert([
                    'gre_id' => $id, 'IdProducto' => $it['IdProducto'] ?? null, 'codigo' => $it['codigo'] ?? null,
                    'descripcion' => mb_strtoupper(mb_substr(trim($it['descripcion']), 0, 250)), 'umecod' => $it['umecod'] ?: 'NIU',
                    'cantidad' => round((float) $it['cantidad'], 3),
                ]);
            }
            DB::table('empresa_negocios')->where('id_empresa_negocio', $suc->id_empresa_negocio)->update(['NumGuia' => $numero]);
            // El comprobante muestra su número de guía (casilla "Número Guía" del A4)
            if (! empty($d['IdCpe_cabecera'])) {
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $d['IdCpe_cabecera'])->update(['guia_remision' => $serie.'-'.$numero]);
            }

            return $id;
        });
    }

    // ------------------------------------------------------------------ XML

    public function nombre(object $g): string
    {
        return $this->empresa->IdEmpresa.'-09-'.$g->serie.'-'.$g->numero;
    }

    /** XML sin firmar de la guía (DespatchAdvice 2.1, GRE remitente 2022) */
    public function xml(object $g, Collection $detalle): string
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(false);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('DespatchAdvice');
        $x->writeAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:DespatchAdvice-2');
        $x->writeAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $x->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $x->writeAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        // Aquí va la firma
        $x->startElement('ext:UBLExtensions');
        $x->startElement('ext:UBLExtension');
        $x->startElement('ext:ExtensionContent');
        $x->text('');
        $x->endElement();
        $x->endElement();
        $x->endElement();

        $cbc = function (string $nombre, ?string $valor, array $atributos = []) use ($x): void {
            $x->startElement('cbc:'.$nombre);
            foreach ($atributos as $k => $v) {
                $x->writeAttribute($k, $v);
            }
            $x->text((string) $valor);
            $x->endElement();
        };
        $catalogo = fn (string $nombre, string $n) => ['listAgencyName' => 'PE:SUNAT', 'listName' => $nombre, 'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo'.$n];
        $docIdentidad = fn (string $tipo) => ['schemeID' => $tipo, 'schemeName' => 'Documento de Identidad', 'schemeAgencyName' => 'PE:SUNAT', 'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06'];
        $parte = function (string $tipo, string $num, string $nombre) use ($x, $cbc, $docIdentidad): void {
            $x->startElement('cac:Party');
            $x->startElement('cac:PartyIdentification');
            $cbc('ID', $num, $docIdentidad($tipo));
            $x->endElement();
            $x->startElement('cac:PartyLegalEntity');
            $cbc('RegistrationName', $nombre);
            $x->endElement();
            $x->endElement();
        };
        $direccion = function (string $ubigeo, string $linea, ?string $codLocal, ?string $ruc) use ($x, $cbc): void {
            $cbc('ID', $ubigeo, ['schemeAgencyName' => 'PE:INEI', 'schemeName' => 'Ubigeos']);
            // Traslado entre establecimientos: código del local anexo registrado en SUNAT
            if ($codLocal !== null && $codLocal !== '') {
                $cbc('AddressTypeCode', str_pad($codLocal, 4, '0', STR_PAD_LEFT), ['listID' => (string) $ruc, 'listAgencyName' => 'PE:SUNAT', 'listName' => 'Establecimientos anexos']);
            }
            $x->startElement('cac:AddressLine');
            $cbc('Line', $linea);
            $x->endElement();
        };

        $cbc('UBLVersionID', '2.1');
        $cbc('CustomizationID', '2.0');
        $cbc('ID', $g->serie.'-'.$g->numero);
        $cbc('IssueDate', Carbon::parse($g->fecha_emision)->toDateString());
        $cbc('IssueTime', substr((string) $g->hora_emision, 0, 8));
        $cbc('DespatchAdviceTypeCode', '09', $catalogo('Tipo de Documento', '01'));
        if ($g->observacion) {
            $cbc('Note', $g->observacion);
        }
        if ($g->doc_tdocod && $g->doc_numero && isset(self::DOCS_RELACIONADOS[$g->doc_tdocod])) {
            $x->startElement('cac:AdditionalDocumentReference');
            $cbc('ID', $g->doc_numero);
            $cbc('DocumentTypeCode', $g->doc_tdocod, $catalogo('Documento relacionado al transporte', '61'));
            $cbc('DocumentType', self::DOCS_RELACIONADOS[$g->doc_tdocod]);
            $x->startElement('cac:IssuerParty');
            $x->startElement('cac:PartyIdentification');
            $cbc('ID', $this->empresa->IdEmpresa, $docIdentidad('6'));
            $x->endElement();
            $x->endElement();
            $x->endElement();
        }

        $x->startElement('cac:Signature');
        $cbc('ID', $this->empresa->IdEmpresa);
        $x->startElement('cac:SignatoryParty');
        $x->startElement('cac:PartyIdentification');
        $cbc('ID', $this->empresa->IdEmpresa);
        $x->endElement();
        $x->startElement('cac:PartyName');
        $cbc('Name', $this->empresa->NomEmpresa);
        $x->endElement();
        $x->endElement();
        $x->startElement('cac:DigitalSignatureAttachment');
        $x->startElement('cac:ExternalReference');
        $cbc('URI', '#GreenterSign');
        $x->endElement();
        $x->endElement();
        $x->endElement();

        $x->startElement('cac:DespatchSupplierParty');
        $parte('6', $this->empresa->IdEmpresa, $this->empresa->NomEmpresa);
        $x->endElement();
        $x->startElement('cac:DeliveryCustomerParty');
        $parte((string) $g->dest_tdicod, (string) $g->dest_num, (string) $g->dest_nom);
        $x->endElement();

        // ---- Envío
        $x->startElement('cac:Shipment');
        $cbc('ID', 'SUNAT_Envio');
        $cbc('HandlingCode', $g->motivo, $catalogo('Motivo de traslado', '20'));
        $cbc('HandlingInstructions', $g->motivo === '13' && $g->motivo_desc ? $g->motivo_desc : (self::MOTIVOS[$g->motivo] ?? 'Otros'));
        $cbc('GrossWeightMeasure', number_format((float) $g->peso, 3, '.', ''), ['unitCode' => $g->unidad_peso ?: 'KGM']);
        if ($g->bultos && in_array($g->motivo, ['08', '09'], true)) {
            $cbc('TotalTransportHandlingUnitQuantity', (string) $g->bultos);
        }
        if ($g->vehiculo_m1l) {
            $cbc('SpecialInstructions', 'SUNAT_Envio_IndicadorTrasladoVehiculoM1L');
        }
        $x->startElement('cac:ShipmentStage');
        $cbc('TransportModeCode', $g->modalidad, $catalogo('Modalidad de traslado', '18'));
        $x->startElement('cac:TransitPeriod');
        $cbc('StartDate', Carbon::parse($g->fecha_traslado)->toDateString());
        $x->endElement();
        if ($g->modalidad === '01') {
            $x->startElement('cac:CarrierParty');
            $x->startElement('cac:PartyIdentification');
            $cbc('ID', (string) $g->transp_ruc, $docIdentidad('6'));
            $x->endElement();
            $x->startElement('cac:PartyLegalEntity');
            $cbc('RegistrationName', (string) $g->transp_nom);
            if ($g->transp_mtc) {
                $cbc('CompanyID', $g->transp_mtc);
            }
            $x->endElement();
            $x->endElement();
        } elseif (! $g->vehiculo_m1l) {
            $x->startElement('cac:DriverPerson');
            $cbc('ID', (string) $g->cond_num, $docIdentidad((string) ($g->cond_tdicod ?: '1')));
            $cbc('FirstName', (string) $g->cond_nombres);
            $cbc('FamilyName', (string) $g->cond_apellidos);
            $cbc('JobTitle', 'Principal');
            $x->startElement('cac:IdentityDocumentReference');
            $cbc('ID', (string) $g->cond_licencia);
            $x->endElement();
            $x->endElement();
        }
        $x->endElement(); // ShipmentStage

        $x->startElement('cac:Delivery');
        $x->startElement('cac:DeliveryAddress');
        $direccion($g->llegada_ubigeo, $g->llegada_direccion, $g->motivo === '04' ? $g->llegada_codlocal : null, $this->empresa->IdEmpresa);
        $x->endElement();
        $x->startElement('cac:Despatch');
        $x->startElement('cac:DespatchAddress');
        $direccion($g->partida_ubigeo, $g->partida_direccion, $g->motivo === '04' ? $g->partida_codlocal : null, $this->empresa->IdEmpresa);
        $x->endElement();
        $x->endElement();
        $x->endElement(); // Delivery

        if ($g->modalidad === '02' && ! $g->vehiculo_m1l && $g->placa) {
            $x->startElement('cac:TransportHandlingUnit');
            $x->startElement('cac:TransportEquipment');
            $cbc('ID', $g->placa);
            $x->endElement();
            $x->endElement();
        }
        $x->endElement(); // Shipment

        // ---- Bienes
        foreach ($detalle->values() as $i => $d) {
            $x->startElement('cac:DespatchLine');
            $cbc('ID', (string) ($i + 1));
            $cbc('DeliveredQuantity', rtrim(rtrim(number_format((float) $d->cantidad, 3, '.', ''), '0'), '.'), ['unitCode' => $d->umecod ?: 'NIU']);
            $x->startElement('cac:OrderLineReference');
            $cbc('LineID', (string) ($i + 1));
            $x->endElement();
            $x->startElement('cac:Item');
            $cbc('Description', $d->descripcion);
            if ($d->codigo) {
                $x->startElement('cac:SellersItemIdentification');
                $cbc('ID', $d->codigo);
                $x->endElement();
            }
            $x->endElement();
            $x->endElement();
        }

        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }

    public function firmar(string $xml): string
    {
        $pem = storage_path('app/certificados/'.$this->empresa->IdEmpresa.'.pem');
        if (! is_file($pem)) {
            throw new RuntimeException('Falta el certificado digital. Súbelo en Mantenimiento > Empresas > Editar.');
        }
        $firma = new FirmaSha256;
        $firma->setCertificate(file_get_contents($pem));

        return $firma->signXml($xml);
    }

    // ------------------------------------------------------------------ envío

    private function token(bool $renovar = false): string
    {
        $llave = 'gre_token_'.$this->empresa->IdEmpresa;
        if ($renovar) {
            Cache::forget($llave);
        }
        if ($t = Cache::get($llave)) {
            return $t;
        }
        $e = $this->empresa;
        $r = Http::asForm()->timeout(30)->post(sprintf(self::URL_TOKEN, $e->client_id), [
            'grant_type' => 'password', 'scope' => 'https://api-cpe.sunat.gob.pe',
            'client_id' => $e->client_id, 'client_secret' => $e->client_secret,
            'username' => $e->IdEmpresa.$e->wsusuario, 'password' => $e->claveSunat,
        ]);
        $token = $r->json('access_token');
        if (! $r->successful() || ! $token) {
            throw new RuntimeException('SUNAT no dio acceso: '.($r->json('error_description') ?: $r->json('error') ?: 'HTTP '.$r->status())
                .'. Revisa el client_id, client_secret y el usuario/clave SOL de la empresa.');
        }
        Cache::put($llave, $token, max(60, ((int) $r->json('expires_in', 3600)) - 120));

        return $token;
    }

    private function validarConfiguracion(): void
    {
        $e = $this->empresa;
        if ((string) $e->produccion !== '1') {
            throw new RuntimeException('SUNAT no tiene ambiente de pruebas para la guía electrónica: pon la empresa en PRODUCCIÓN para enviarla. La guía quedó guardada como PENDIENTE.');
        }
        if (! $e->client_id || ! $e->client_secret) {
            throw new RuntimeException('Faltan las credenciales API de SUNAT (client_id y client_secret) en Mantenimiento > Empresas > Editar.');
        }
        if (! $e->wsusuario || ! $e->claveSunat) {
            throw new RuntimeException('Configura el usuario y la clave SOL de la empresa.');
        }
    }

    private function rutaArchivo(string $nombre, string $tipo): string
    {
        $dir = storage_path('app/sunat/'.$this->empresa->IdEmpresa);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.'/'.($tipo === 'cdr' ? 'R-' : '').$nombre.($tipo === 'cdr' ? '.zip' : '.xml');
    }

    public function archivo(object $g, string $tipo): string
    {
        return $this->rutaArchivo($this->nombre($g), $tipo);
    }

    /** @return array{ok: bool, estado: string, mensaje: string} */
    public function enviar(int $greId): array
    {
        $g = DB::table('gre_cabecera')->where('gre_id', $greId)->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)->first();
        if (! $g) {
            throw new RuntimeException('Guía no encontrada.');
        }
        if (in_array($g->est_sunat, ['ACEPTADO', 'ENVIADO'], true)) {
            return $g->est_sunat === 'ENVIADO' ? $this->consultar($greId) : ['ok' => true, 'estado' => 'ACEPTADO', 'mensaje' => 'La guía ya fue aceptada.'];
        }
        $this->validarConfiguracion();

        $nombre = $this->nombre($g);
        $xml = $this->firmar($this->xml($g, DB::table('gre_detalle')->where('gre_id', $greId)->orderBy('det_id')->get()));
        file_put_contents($this->rutaArchivo($nombre, 'xml'), $xml);
        $hash = preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xml, $m) ? $m[1] : null;

        $zipRuta = tempnam(sys_get_temp_dir(), 'gre');
        $zip = new ZipArchive;
        $zip->open($zipRuta, ZipArchive::OVERWRITE);
        $zip->addFromString($nombre.'.xml', $xml);
        $zip->close();
        $contenido = file_get_contents($zipRuta);
        @unlink($zipRuta);

        $cuerpo = ['archivo' => ['nomArchivo' => $nombre.'.zip', 'arcGreZip' => base64_encode($contenido), 'hashZip' => hash('sha256', $contenido)]];
        $r = Http::withToken($this->token())->acceptJson()->timeout(60)->post(self::URL_API.'/'.$nombre, $cuerpo);
        if ($r->status() === 401) {
            $r = Http::withToken($this->token(true))->acceptJson()->timeout(60)->post(self::URL_API.'/'.$nombre, $cuerpo);
        }
        $ticket = $r->json('numTicket');
        if (! $r->successful() || ! $ticket) {
            $mensaje = $this->errorApi($r);
            DB::table('gre_cabecera')->where('gre_id', $greId)->update(['est_sunat' => 'ERROR', 'mensaje' => mb_substr($mensaje, 0, 500), 'hash' => $hash]);

            return ['ok' => false, 'estado' => 'ERROR', 'mensaje' => $mensaje];
        }
        DB::table('gre_cabecera')->where('gre_id', $greId)->update(['est_sunat' => 'ENVIADO', 'ticket' => $ticket, 'hash' => $hash,
            'mensaje' => 'Enviada, esperando respuesta de SUNAT.']);

        // SUNAT suele responder en segundos
        sleep(2);

        return $this->consultar($greId);
    }

    /** @return array{ok: bool, estado: string, mensaje: string} */
    public function consultar(int $greId): array
    {
        $g = DB::table('gre_cabecera')->where('gre_id', $greId)->where('id_empresa_negocio', $this->negocio->id_empresa_negocio)->first();
        if (! $g || ! $g->ticket) {
            throw new RuntimeException('La guía aún no fue enviada.');
        }
        $this->validarConfiguracion();
        $url = self::URL_API.'/envios/'.$g->ticket;
        $r = Http::withToken($this->token())->acceptJson()->timeout(60)->get($url);
        if ($r->status() === 401) {
            $r = Http::withToken($this->token(true))->acceptJson()->timeout(60)->get($url);
        }
        if (! $r->successful()) {
            return ['ok' => false, 'estado' => $g->est_sunat, 'mensaje' => $this->errorApi($r)];
        }

        $cod = (string) $r->json('codRespuesta');
        if ($cod === '98') {
            DB::table('gre_cabecera')->where('gre_id', $greId)->update(['cod_respuesta' => '98', 'mensaje' => 'SUNAT aún está procesando la guía.']);

            return ['ok' => true, 'estado' => 'ENVIADO', 'mensaje' => 'SUNAT aún está procesando la guía. Consulta en unos segundos.'];
        }

        $qr = null;
        $mensaje = '';
        if ($cdr = $r->json('arcCdr')) {
            $zip = base64_decode($cdr);
            file_put_contents($this->rutaArchivo($this->nombre($g), 'cdr'), $zip);
            [$mensaje, $qr] = $this->leerCdr($zip);
        }
        if ($cod === '0') {
            DB::table('gre_cabecera')->where('gre_id', $greId)->update(['est_sunat' => 'ACEPTADO', 'cod_respuesta' => '0',
                'mensaje' => mb_substr($mensaje ?: 'La guía fue aceptada.', 0, 500), 'qr' => $qr]);

            return ['ok' => true, 'estado' => 'ACEPTADO', 'mensaje' => $mensaje ?: 'La guía fue aceptada.'];
        }

        // 99: rechazada (hay que corregir y emitir otra guía con otro número)
        $error = $r->json('error');
        $mensaje = trim(($error['numError'] ?? '') ? '['.$error['numError'].'] '.($error['desError'] ?? '') : ($mensaje ?: 'SUNAT rechazó la guía.'));
        DB::table('gre_cabecera')->where('gre_id', $greId)->update(['est_sunat' => 'RECHAZADO', 'cod_respuesta' => $cod, 'mensaje' => mb_substr($mensaje, 0, 500)]);

        return ['ok' => false, 'estado' => 'RECHAZADO', 'mensaje' => $mensaje];
    }

    /** @return array{0: string, 1: ?string} [descripción del CDR, enlace del QR] */
    private function leerCdr(string $zip): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cdr');
        file_put_contents($ruta, $zip);
        $z = new ZipArchive;
        $xml = '';
        if ($z->open($ruta) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                if (str_ends_with(strtolower($z->getNameIndex($i)), '.xml')) {
                    $xml = (string) $z->getFromIndex($i);
                }
            }
            $z->close();
        }
        @unlink($ruta);
        $desc = preg_match('/<cbc:Description>([^<]+)<\/cbc:Description>/', $xml, $m) ? html_entity_decode($m[1]) : '';
        $qr = preg_match('/(https?:\/\/[^<\s"]+descargaqr[^<\s"]*)/i', $xml, $m) ? html_entity_decode($m[1]) : null;

        return [$desc, $qr];
    }

    private function errorApi($r): string
    {
        $j = $r->json() ?: [];
        $partes = collect($j['errors'] ?? [])->map(fn ($e) => trim(($e['cod'] ?? '').' '.($e['msg'] ?? '')))->filter()->implode(' | ');

        return trim('SUNAT: '.($partes ?: ($j['msg'] ?? $j['message'] ?? $j['error_description'] ?? 'HTTP '.$r->status())));
    }
}
