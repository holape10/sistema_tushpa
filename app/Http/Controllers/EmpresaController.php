<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Bitacora;
use App\Support\ConsultaPeru;
use App\Support\EmpresaInicial;
use App\Support\LogoNegocio;
use App\Support\Sunat\Certificado;
use App\Support\Sunat\Sire;
use App\Support\Tenancy\Contratos;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmpresaController extends Controller
{
    public function index()
    {
        // Cada usuario solo ve su propia empresa
        $empresas = Empresa::where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('IdEmpresa', 'asc')->paginate(10);

        return view('empresas.index', compact('empresas'));
    }

    /**
     * /config solo sirve para instalar el sistema la primera vez (base sin empresas). Después no existe: las empresas
     * las crea el panel TUSHPA y las de un estudio, "Empresas del estudio". Antes cualquier usuario logueado podía
     * crear ahí una empresa con un administrador nuevo.
     */
    private function puedeRegistrar(): bool
    {
        return Empresa::count() === 0;
    }

    /** Solo el administrador y solo su empresa: aquí están la clave SOL, el certificado digital y el paso a producción */
    private function autorizarEmpresa($id): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador cambia los datos de la empresa.');
        abort_unless((string) $id === (string) Auth::user()->IdEmpresa, 403, 'No tienes acceso a esta empresa.');
    }

    public function crearempresa()
    {
        abort_unless($this->puedeRegistrar(), 404);

        return view('empresas.configurar');
    }

    public function store(Request $request)
    {
        abort_unless($this->puedeRegistrar(), 404);

        $request->validate([
            'rucEmpresa' => 'required|size:11|unique:empresa,IdEmpresa',
            'nomEmpresa' => 'required|string|max:255',
            'dirEmpresa' => 'required|string|max:255',
        ], [], [
            'rucEmpresa' => 'RUC',
            'nomEmpresa' => 'Razón Social',
            'dirEmpresa' => 'Dirección',
        ]);

        DB::transaction(fn () => EmpresaInicial::crear([
            'ruc' => $request->rucEmpresa,
            'razon_social' => $request->nomEmpresa,
            'nombre_comercial' => $request->NomComercial,
            'direccion' => $request->dirEmpresa,
            'ubigeo' => $request->ubigeo,
            // En el registro inicial el usuario y la contraseña son el RUC
            'usuario' => $request->rucEmpresa,
            'password' => $request->rucEmpresa,
            'envio' => $request->envio,
            'produccion' => $request->produccion,
            'formato' => $request->formato,
            'icbper' => $request->icbper,
        ]));

        return redirect()->route('login')
            ->with('success', 'Empresa registrada. Ingresa con tu RUC como usuario y contraseña.');
    }

    public function edit($id)
    {
        $this->autorizarEmpresa($id);
        $empresa = Empresa::findOrFail($id);

        $tip_env_fac = [];

        try {
            if (Schema::hasTable('tipo_envio_facturacion')) {
                $tip_env_fac = DB::table('tipo_envio_facturacion')->get();
            }
        } catch (\Exception $e) {
        }

        // Su contrato de servicio con TUSHPA (multi-empresa), para verlo con sus documentos
        $cliente = Tenancy::cliente();
        $contrato = $cliente ? Contratos::deCliente($cliente->id) : null;

        return view('empresas.edit', compact('empresa', 'tip_env_fac', 'contrato'));
    }

    /** Lo que se compara en la bitácora */
    public const ETIQUETAS_BITACORA = ['NomEmpresa' => 'Razón social', 'DirEmpresa' => 'Dirección', 'EstEmpresa' => 'Estado', 'LogEmpresa' => 'Logo',
        'wsusuario' => 'Usuario SOL (envío a SUNAT)', 'claveSunat' => 'Clave SOL (envío a SUNAT)', 'produccion' => 'Envío a SUNAT en producción',
        'passcert' => 'Contraseña del certificado', 'certificado_archivo' => 'Certificado digital', 'fec_ini_cer' => 'Certificado vigente desde',
        'fec_fin_cer' => 'Certificado vigente hasta', 'icbper' => 'ICBPER', 'tip_env_fac_id' => 'Envío de facturas', 'tipo_envio' => 'Tipo de envío',
        'client_id' => 'ID API SUNAT (SIRE)', 'client_secret' => 'Clave API SUNAT (SIRE)', 'sire_usuario' => 'Usuario SOL del SIRE', 'sire_clave' => 'Clave SOL del SIRE',
        'cuentas_bancarias' => 'Cuentas bancarias del A4'];

    public function update(Request $request, $id)
    {
        $this->autorizarEmpresa($id);

        // 1. Validar campos obligatorios y el logo (solo imágenes rasterizadas, nada de .php ni .svg)
        $request->validate([
            'nomEmpresa' => 'required|string|max:255',
            'dirEmpresa' => 'required|string|max:255',
            'logologin' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'sire_client_id' => 'nullable|string|max:100',
            'sire_client_secret' => 'nullable|string|max:255',
            'sire_usuario' => 'nullable|string|max:30',
            'sire_clave' => 'nullable|string|max:100',
            'cuentas' => 'nullable|array|max:6',
            'cuentas.*.banco' => 'nullable|string|max:40',
            'cuentas.*.moneda' => 'nullable|in:PEN,USD',
            'cuentas.*.numero' => 'nullable|string|max:30',
            'cuentas.*.cci' => 'nullable|string|max:30',
        ], [], [
            'cuentas.*.banco' => 'banco', 'cuentas.*.numero' => 'número de cuenta', 'cuentas.*.cci' => 'CCI',
            'nomEmpresa' => 'Razón Social',
            'dirEmpresa' => 'Dirección',
            'logologin' => 'Logo',
        ]);

        $empresa = Empresa::findOrFail($id);

        // 2. Asignar valores normales (formato, copias de impresión, ticket en pantalla y correo de envío ya no se usan:
        // el formato va por sucursal; se dejan como están)
        $empresa->NomEmpresa = $request->get('nomEmpresa');
        $empresa->DirEmpresa = $request->get('dirEmpresa');
        $empresa->EstEmpresa = $request->get('estEmpresa', 'Activo');
        $empresa->wsusuario = $request->get('txtWsUsuario');
        // Las claves no se muestran en la pantalla: vacías = se conserva la guardada
        if ($request->filled('txtWsContrasena')) {
            $empresa->claveSunat = $request->get('txtWsContrasena');
        }
        $empresa->produccion = $request->get('produccion');
        if ($request->filled('txtPassCert')) {
            $empresa->passcert = $request->get('txtPassCert');
        }
        $empresa->icbper = $request->get('icbper');
        $empresa->tip_env_fac_id = $request->get('tip_env_fac');
        $empresa->tipo_envio = $request->get('envio');
        // Cuentas bancarias del A4: solo las que tienen banco y número o CCI
        if ($request->has('cuentas')) {
            $cuentas = collect($request->input('cuentas', []))->map(fn ($c) => [
                'banco' => mb_strtoupper(trim((string) ($c['banco'] ?? ''))), 'moneda' => ($c['moneda'] ?? 'PEN') === 'USD' ? 'USD' : 'PEN',
                'numero' => trim((string) ($c['numero'] ?? '')), 'cci' => trim((string) ($c['cci'] ?? '')),
            ])->filter(fn ($c) => $c['banco'] !== '' && ($c['numero'] !== '' || $c['cci'] !== ''))->values()->all();
            $empresa->cuentas_bancarias = $cuentas ?: null;
        }

        // 3. Logo de la empresa (lo usan las sucursales que no tienen logo propio)
        if ($request->hasFile('logologin')) {
            $empresa->LogEmpresa = LogoNegocio::guardar($request->file('logologin'), $empresa->IdEmpresa);
        } elseif ($request->boolean('quitar_logo') && $empresa->LogEmpresa) {
            // Sin logo: tickets y comprobantes salen solo con el nombre del negocio
            LogoNegocio::borrar($empresa->LogEmpresa);
            $empresa->LogEmpresa = null;
        }

        // 4. Certificado Digital (.pfx o .p12): se guarda el .pfx y se genera el .pem que firma los XML.
        // Ambos van FUERA de public/ (contienen la clave privada) y el nombre sale del RUC de la BD.
        $dirCertificados = storage_path('app/certificados');
        $rutaPfx = $dirCertificados.'/'.$empresa->IdEmpresa.'.pfx';
        $rutaPem = $dirCertificados.'/'.$empresa->IdEmpresa.'.pem';
        $password = (string) $request->get('txtPassCert');
        $pfxNuevo = null;

        if ($request->hasFile('txtCertificado')) {
            $file = $request->file('txtCertificado');

            if (! in_array(strtolower($file->getClientOriginalExtension()), ['pfx', 'p12'])) {
                return back()->withInput()->with('error', 'El certificado debe ser formato .pfx o .p12');
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                return back()->withInput()->with('error', 'El certificado no debe superar los 10MB');
            }
            if ($password === '') {
                return back()->withInput()->with('error', 'Escribe la contraseña del certificado.');
            }
            $pfxNuevo = file_get_contents($file->getRealPath());
        } elseif (is_file($rutaPfx) && $password !== '' && ($password !== (string) $empresa->getOriginal('passcert') || ! is_file($rutaPem))) {
            // Sin archivo nuevo pero cambió la contraseña o falta el .pem: se regenera desde el .pfx guardado
            $pfxNuevo = file_get_contents($rutaPfx);
        }

        if ($pfxNuevo !== null) {
            try {
                // Primero se valida y convierte; solo si todo sale bien se reemplazan los archivos guardados
                $cert = Certificado::aPem($pfxNuevo, $password);
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', 'Certificado: '.$e->getMessage());
            }

            if (! is_dir($dirCertificados)) {
                mkdir($dirCertificados, 0755, true);
            }
            file_put_contents($rutaPfx, $pfxNuevo);
            file_put_contents($rutaPem, $cert['pem']);
            $empresa->certificado = $empresa->IdEmpresa.'.pfx';
            // Las fechas de vigencia se toman del propio certificado
            $empresa->fec_ini_cer = $cert['desde'] ?? $empresa->fec_ini_cer;
            $empresa->fec_fin_cer = $cert['hasta'] ?? $empresa->fec_fin_cer;
        }

        // 4b. Credenciales del API SIRE: la CLAVE y la clave SOL solo cambian si se escriben (nunca se muestran)
        $cambioSire = false;
        if ($request->has('sire_client_id')) {
            $nuevoId = trim((string) $request->get('sire_client_id')) ?: null;
            $nuevoUsuario = strtoupper(trim((string) $request->get('sire_usuario'))) ?: null;
            $cambioSire = $nuevoId !== $empresa->client_id || $nuevoUsuario !== $empresa->sire_usuario
                || $request->filled('sire_client_secret') || $request->filled('sire_clave');

            $empresa->client_id = $nuevoId;
            $empresa->sire_usuario = $nuevoUsuario;
            if ($request->filled('sire_client_secret')) {
                $empresa->client_secret = trim($request->get('sire_client_secret'));
            }
            if ($request->filled('sire_clave')) {
                $empresa->sire_clave = $request->get('sire_clave');
            }
        }

        // Bitácora: solo lo que cambió (las claves y el certificado se anotan sin su valor)
        $sucio = array_keys($empresa->getDirty());
        $antesEmpresa = Arr::only($empresa->getRawOriginal(), $sucio) + ($pfxNuevo !== null ? ['certificado_archivo' => 'anterior'] : []);
        $despuesEmpresa = Arr::only($empresa->getAttributes(), $sucio) + ($pfxNuevo !== null ? ['certificado_archivo' => 'nuevo'] : []);
        $empresa->save();
        $cambiosEmpresa = Bitacora::diferencias($antesEmpresa, $despuesEmpresa, self::ETIQUETAS_BITACORA);
        if ($cambiosEmpresa) {
            Bitacora::registrar('config.empresa', 'Cambió la configuración de la empresa '.$empresa->NomEmpresa, $cambiosEmpresa, [
                'referencia' => ['empresa', $empresa->IdEmpresa],
                'alerta' => (bool) array_intersect(array_keys($cambiosEmpresa), ['claveSunat', 'passcert', 'certificado_archivo', 'produccion', 'wsusuario',
                    'client_id', 'client_secret', 'sire_usuario', 'sire_clave', 'EstEmpresa']),
            ]);
        }
        // El panel ve al instante la nueva vigencia del certificado
        if ($pfxNuevo !== null && ($cliente = Tenancy::cliente())) {
            $cliente->update(['cert_vence' => $empresa->fec_fin_cer ? Carbon::parse($empresa->fec_fin_cer)->toDateString() : null]);
        }

        // Si cambiaron las credenciales del SIRE, se prueba la conexión con SUNAT al guardar
        $mensajeSire = '';
        if ($cambioSire && Sire::configurado($empresa)) {
            cache()->forget('sire_token_'.$empresa->IdEmpresa);
            cache()->forget("sire_periodos_{$empresa->IdEmpresa}_140000");
            cache()->forget("sire_periodos_{$empresa->IdEmpresa}_080000");
            try {
                $periodos = (new Sire($empresa))->periodos(Sire::VENTAS);
                $mensajeSire = '. SIRE conectado con SUNAT ✔ ('.count($periodos).' periodos habilitados).';
            } catch (\Throwable $e) {
                $detalle = $e instanceof \RuntimeException ? $e->getMessage() : 'no se pudo conectar con SUNAT.';

                return redirect()->route('empresas.edit', $empresa->IdEmpresa)
                    ->with('error', 'Se guardó la empresa, pero el SIRE no conectó: '.$detalle);
            }
        }

        // 5. Actualizar ICBPER en productos (solo si la tabla ya tiene esas columnas)
        if (Schema::hasColumn('productos', 'icbper') && Schema::hasColumn('productos', 'mon_icbper')) {
            DB::table('productos')
                ->where('icbper', '1')
                ->where('IdEmpresa', Auth::user()->IdEmpresa)
                ->update(['mon_icbper' => $empresa->icbper]);
        }

        $mensaje = 'Empresa actualizada correctamente';
        if ($pfxNuevo !== null) {
            $mensaje .= '. Certificado guardado (.pfx y .pem), vigente hasta '.($cert['hasta'] ?? '—').'.';
        }

        return redirect()->route('empresas.index')->with('success', $mensaje.$mensajeSire);
    }

    /** Datos de un RUC en SUNAT: para la instalación inicial y para los usuarios logueados (Nueva empresa del estudio, clientes) */
    public function consultaRucSunat($ruc)
    {
        abort_unless(Auth::check() || $this->puedeRegistrar(), 403);
        abort_unless(preg_match('/^\d{11}$/', $ruc), 422);

        // Padrón de SUNAT propio y, de respaldo, apiperu.dev
        if ($d = ConsultaPeru::ruc($ruc)) {
            return response()->json(['nom' => $d['nombre'], 'dir' => $d['direccion'], 'ubigeo' => $d['ubigeo']]);
        }

        return response()->json(['error' => 'RUC no encontrado o no válido'], 404);
    }
}
