<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\EmpresaInicial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EmpresaController extends Controller
{
    public function index()
    {
        // Cada usuario solo ve su propia empresa
        $empresas = Empresa::where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('IdEmpresa', 'asc')->paginate(10);
        return view('empresas.index', compact('empresas'));
    }

    // Registro libre solo en la instalación inicial; después, solo usuarios logueados
    private function puedeRegistrar(): bool
    {
        return Auth::check() || Empresa::count() === 0;
    }

    private function autorizarEmpresa($id): void
    {
        abort_unless((string) $id === (string) Auth::user()->IdEmpresa, 403, 'No tienes acceso a esta empresa.');
    }

    public function crearempresa()
    {
        if (!$this->puedeRegistrar()) {
            return redirect()->route('login');
        }

        return view('empresas.configurar');
    }

    public function store(Request $request)
    {
        abort_unless($this->puedeRegistrar(), 403);

        $request->validate([
            'rucEmpresa'   => 'required|size:11|unique:empresa,IdEmpresa',
            'nomEmpresa'   => 'required|string|max:255',
            'dirEmpresa'   => 'required|string|max:255',
        ], [], [
            'rucEmpresa' => 'RUC',
            'nomEmpresa' => 'Razón Social',
            'dirEmpresa' => 'Dirección',
        ]);

        DB::transaction(fn() => EmpresaInicial::crear([
            'ruc'              => $request->rucEmpresa,
            'razon_social'     => $request->nomEmpresa,
            'nombre_comercial' => $request->NomComercial,
            'direccion'        => $request->dirEmpresa,
            'ubigeo'           => $request->ubigeo,
            // En el registro inicial el usuario y la contraseña son el RUC
            'usuario'          => $request->rucEmpresa,
            'password'         => $request->rucEmpresa,
            'envio'            => $request->envio,
            'produccion'       => $request->produccion,
            'formato'          => $request->formato,
            'icbper'           => $request->icbper,
        ]));

        return redirect()->route('login')
            ->with('success', 'Empresa registrada. Ingresa con tu RUC como usuario y contraseña.');
    }

    public function edit($id)
    {
        $this->autorizarEmpresa($id);
        $empresa = Empresa::findOrFail($id);
        
        $tip_env_fac = [];
        $tipos_sistemas = [];
        
        try {
            if (Schema::hasTable('tipo_envio_facturacion')) {
                $tip_env_fac = DB::table('tipo_envio_facturacion')->get();
            }
        } catch (\Exception $e) {}
        
        try {
            if (Schema::hasTable('tipos_sistemas')) {
                $tipos_sistemas = DB::table('tipos_sistemas')->get();
            }
        } catch (\Exception $e) {}
        
        return view('empresas.edit', compact('empresa', 'tip_env_fac', 'tipos_sistemas'));
    }

    public function update(Request $request, $id)
    {
        $this->autorizarEmpresa($id);

        // 1. Validar campos obligatorios y el logo (solo imágenes rasterizadas, nada de .php ni .svg)
        $request->validate([
            'nomEmpresa'   => 'required|string|max:255',
            'dirEmpresa'   => 'required|string|max:255',
            'logologin'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'sire_client_id'     => 'nullable|string|max:100',
            'sire_client_secret' => 'nullable|string|max:255',
            'sire_usuario'       => 'nullable|string|max:30',
            'sire_clave'         => 'nullable|string|max:100',
        ], [], [
            'nomEmpresa' => 'Razón Social',
            'dirEmpresa' => 'Dirección',
            'logologin'  => 'Logo',
        ]);

        $empresa = Empresa::findOrFail($id);
        
        // 2. Asignar valores normales
        $empresa->NomEmpresa = $request->get('nomEmpresa');
        $empresa->DirEmpresa = $request->get('dirEmpresa');
        $empresa->EstEmpresa = $request->get('estEmpresa', 'Activo');
        $empresa->wsusuario = $request->get('txtWsUsuario');
        $empresa->claveSunat = $request->get('txtWsContrasena');
        $empresa->fec_ini_cer = $request->get('fecini');
        $empresa->fec_fin_cer = $request->get('fecfin');
        $empresa->produccion = $request->get('produccion');
        $empresa->ticket_pantalla = $request->get('ticket_pantalla');
        $empresa->correo_envio = $request->get('correo_envio');
        $empresa->contrasena_envio = $request->get('contrasena_envio');
        $empresa->passcert = $request->get('txtPassCert');
        $empresa->formato = $request->get('formato');
        $empresa->imp_pedido = $request->get('imp_pedido');
        $empresa->imp_venta = $request->get('imp_venta');
        $empresa->icbper = $request->get('icbper');
        $empresa->tip_env_fac_id = $request->get('tip_env_fac');
        $empresa->id_tipo_sistema = $request->get('id_tipo_sistema');
        $empresa->tipo_envio = $request->get('envio');

        // 3. Logo Login
        if ($request->hasFile('logologin')) {
            // Nombre generado por el servidor: nunca se usa el nombre ni la extensión que manda el cliente
            $file = $request->file('logologin');
            $nombreLogo = $empresa->IdEmpresa . '_' . time() . '.' . $file->guessExtension();
            // Dentro de public/imagenes: es la carpeta pública donde el servidor permite escribir (permisos y SELinux)
            $file->move(public_path('imagenes/logos'), $nombreLogo);
            $empresa->LogEmpresa = 'imagenes/logos/' . $nombreLogo;
        }

        // 4. Certificado Digital (.pfx o .p12): se guarda el .pfx y se genera el .pem que firma los XML.
        // Ambos van FUERA de public/ (contienen la clave privada) y el nombre sale del RUC de la BD.
        $dirCertificados = storage_path('app/certificados');
        $rutaPfx = $dirCertificados . '/' . $empresa->IdEmpresa . '.pfx';
        $rutaPem = $dirCertificados . '/' . $empresa->IdEmpresa . '.pem';
        $password = (string) $request->get('txtPassCert');
        $pfxNuevo = null;

        if ($request->hasFile('txtCertificado')) {
            $file = $request->file('txtCertificado');

            if (!in_array(strtolower($file->getClientOriginalExtension()), ['pfx', 'p12'])) {
                return back()->withInput()->with('error', 'El certificado debe ser formato .pfx o .p12');
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                return back()->withInput()->with('error', 'El certificado no debe superar los 10MB');
            }
            if ($password === '') {
                return back()->withInput()->with('error', 'Escribe la contraseña del certificado.');
            }
            $pfxNuevo = file_get_contents($file->getRealPath());
        } elseif (is_file($rutaPfx) && $password !== '' && ($password !== (string) $empresa->getOriginal('passcert') || !is_file($rutaPem))) {
            // Sin archivo nuevo pero cambió la contraseña o falta el .pem: se regenera desde el .pfx guardado
            $pfxNuevo = file_get_contents($rutaPfx);
        }

        if ($pfxNuevo !== null) {
            try {
                // Primero se valida y convierte; solo si todo sale bien se reemplazan los archivos guardados
                $cert = \App\Support\Sunat\Certificado::aPem($pfxNuevo, $password);
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', 'Certificado: ' . $e->getMessage());
            }

            if (!is_dir($dirCertificados)) {
                mkdir($dirCertificados, 0755, true);
            }
            file_put_contents($rutaPfx, $pfxNuevo);
            file_put_contents($rutaPem, $cert['pem']);
            $empresa->certificado = $empresa->IdEmpresa . '.pfx';
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

        $empresa->save();

        // Si cambiaron las credenciales del SIRE, se prueba la conexión con SUNAT al guardar
        $mensajeSire = '';
        if ($cambioSire && \App\Support\Sunat\Sire::configurado($empresa)) {
            cache()->forget('sire_token_' . $empresa->IdEmpresa);
            cache()->forget("sire_periodos_{$empresa->IdEmpresa}_140000");
            cache()->forget("sire_periodos_{$empresa->IdEmpresa}_080000");
            try {
                $periodos = (new \App\Support\Sunat\Sire($empresa))->periodos(\App\Support\Sunat\Sire::VENTAS);
                $mensajeSire = '. SIRE conectado con SUNAT ✔ (' . count($periodos) . ' periodos habilitados).';
            } catch (\Throwable $e) {
                $detalle = $e instanceof \RuntimeException ? $e->getMessage() : 'no se pudo conectar con SUNAT.';
                return redirect()->route('empresas.edit', $empresa->IdEmpresa)
                    ->with('error', 'Se guardó la empresa, pero el SIRE no conectó: ' . $detalle);
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
            $mensaje .= ". Certificado guardado (.pfx y .pem), vigente hasta " . ($cert['hasta'] ?? '—') . '.';
        }

        return redirect()->route('empresas.index')->with('success', $mensaje . $mensajeSire);
    }

    public function consultaRucSunat($ruc)
    {
        abort_unless($this->puedeRegistrar(), 403);
        abort_unless(preg_match('/^\d{11}$/', $ruc), 422);

        $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
            ->get("https://consultas.holape.app/api/v1/ruc/{$ruc}");

        $data = $response->json();

        if (!empty($data['success'])) {
            return response()->json([
                'nom'    => $data['data']['razon_social'],
                'dir'    => $data['data']['direccion'],
                'ubigeo' => $data['data']['ubigeo'],
            ]);
        }

        return response()->json(['error' => 'RUC no encontrado o no válido'], 404);
    }
}