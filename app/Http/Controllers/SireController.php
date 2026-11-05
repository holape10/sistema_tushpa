<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Excel;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use App\Support\Sunat\SireSolicitudes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SIRE: credenciales del API SUNAT, descarga de la propuesta RVIE (ventas) / RCE (compras)
 * y cuadre con lo registrado en el sistema.
 */
class SireController extends Controller
{
    private const LIBROS = ['ventas' => Sire::VENTAS, 'compras' => Sire::COMPRAS];

    private function autorizar(): Empresa
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->contadorCon('/sire/ventas', '/sire/compras', '/sire/credenciales'), 403, 'Solo el Administrador o el contador pueden usar el SIRE.');

        return Empresa::findOrFail(Auth::user()->IdEmpresa);
    }

    /** Ya configuradas (ID y CLAVE del API), solo el administrador las cambia: el contador las usa, no las toca */
    public static function credencialesBloqueadas(Empresa $empresa): bool
    {
        return ! Auth::user()->esAdmin() && $empresa->client_id && $empresa->client_secret;
    }

    public function credenciales()
    {
        $empresa = $this->autorizar();

        return view('empresas.sire.credenciales', ['empresa' => $empresa, 'bloqueadas' => self::credencialesBloqueadas($empresa)]);
    }

    public function guardarCredenciales(Request $request)
    {
        $empresa = $this->autorizar();
        abort_if(self::credencialesBloqueadas($empresa), 403, 'Las credenciales del SIRE ya están configuradas: solo el administrador puede cambiarlas.');
        $d = $request->validate([
            'client_id' => 'required|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'sire_usuario' => 'nullable|string|max:30',
            'sire_clave' => 'nullable|string|max:100',
        ], [], ['client_id' => 'ID', 'client_secret' => 'CLAVE', 'sire_usuario' => 'usuario SOL', 'sire_clave' => 'clave SOL']);

        $empresa->client_id = trim($d['client_id']);
        $empresa->sire_usuario = trim((string) ($d['sire_usuario'] ?? '')) ?: null;
        // Los secretos solo se cambian si se escriben (el formulario nunca los muestra)
        if (! empty($d['client_secret'])) {
            $empresa->client_secret = trim($d['client_secret']);
        }
        if (! empty($d['sire_clave'])) {
            $empresa->sire_clave = $d['sire_clave'];
        }
        if (! $empresa->client_secret) {
            return back()->withInput()->with('error', 'Ingresa la CLAVE (client_secret) del API SUNAT.');
        }
        $empresa->save();
        cache()->forget('sire_token_'.$empresa->IdEmpresa);

        // Se prueba la conexión al guardar
        try {
            $periodos = (new Sire($empresa))->periodos(Sire::VENTAS);

            return back()->with('success', '✔ Conexión con el SIRE correcta. '.count($periodos).' periodos habilitados en el Registro de Ventas.');
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Se guardaron las credenciales, pero la prueba falló: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Se guardaron las credenciales, pero no se pudo conectar con SUNAT. Intenta más tarde.');
        }
    }

    public function index(Request $request, string $libro)
    {
        $empresa = $this->autorizar();
        $codLibro = self::LIBROS[$libro] ?? abort(404);
        $periodo = preg_match('/^\d{6}$/', (string) $request->get('periodo')) ? $request->get('periodo') : now()->subMonth()->format('Ym');

        $periodos = [];
        $errorSunat = null;
        if (Sire::configurado($empresa)) {
            try {
                $periodos = cache()->remember("sire_periodos_{$empresa->IdEmpresa}_{$codLibro}", 3600,
                    fn () => (new Sire($empresa))->periodos($codLibro));
            } catch (\Throwable $e) {
                $errorSunat = $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo conectar con SUNAT.';
            }
        }

        $solicitud = DB::table('sire_solicitudes')->where('IdEmpresa', $empresa->IdEmpresa)
            ->where('libro', $codLibro)->where('periodo', $periodo)->orderByDesc('id')->first();

        $cuadre = null;
        if ($solicitud && $solicitud->archivo && Storage::exists($solicitud->archivo)) {
            try {
                $propuesta = Sire::leerZip(Storage::path($solicitud->archivo));
                $cuadre = SireCuadre::diagnosticar(SireCuadre::comparar($codLibro, $periodo, $propuesta, $empresa->IdEmpresa), $codLibro, $empresa->IdEmpresa);
            } catch (\RuntimeException $e) {
                $errorSunat = $e->getMessage();
            }
        }

        // Estado del periodo según SUNAT (ej. "Presentado"), para no confundir diferencias con deudas tributarias
        $estadoPeriodo = collect($periodos)->firstWhere('periodo', $periodo)['estado'] ?? null;

        return view('empresas.sire.index', [
            'libro' => $libro, 'codLibro' => $codLibro, 'periodo' => $periodo, 'periodos' => $periodos, 'estadoPeriodo' => $estadoPeriodo,
            'solicitud' => $solicitud, 'cuadre' => $cuadre, 'errorSunat' => $errorSunat,
            'configurado' => Sire::configurado($empresa), 'estados' => Sire::ESTADOS,
        ]);
    }

    /** Pide la propuesta del periodo a SUNAT (genera un ticket) */
    public function solicitar(Request $request, string $libro)
    {
        $empresa = $this->autorizar();
        $codLibro = self::LIBROS[$libro] ?? abort(404);
        $periodo = $request->validate(['periodo' => 'required|digits:6'])['periodo'];

        $r = SireSolicitudes::pedir($empresa, $codLibro, $periodo);

        return response()->json(array_intersect_key($r, array_flip(['estado', 'mensaje', 'ticket'])));
    }

    /** Revisa el ticket; si SUNAT terminó, descarga y guarda el .zip */
    public function estado(string $libro, int $id)
    {
        $empresa = $this->autorizar();
        $codLibro = self::LIBROS[$libro] ?? abort(404);
        $s = DB::table('sire_solicitudes')->where('id', $id)->where('IdEmpresa', $empresa->IdEmpresa)->where('libro', $codLibro)->first();
        abort_unless($s, 404);

        return response()->json(SireSolicitudes::revisar($empresa, $s));
    }

    /** Excel de la propuesta: hoja 1 = el .txt de SUNAT con cada campo en su columna; hoja 2 = cuadre con el sistema */
    public function excel(string $libro, int $id)
    {
        $empresa = $this->autorizar();
        $codLibro = self::LIBROS[$libro] ?? abort(404);
        $s = DB::table('sire_solicitudes')->where('id', $id)->where('IdEmpresa', $empresa->IdEmpresa)->where('libro', $codLibro)->first();
        abort_unless($s && $s->archivo && Storage::exists($s->archivo), 404);

        $propuesta = Sire::leerZip(Storage::path($s->archivo));
        $cuadre = SireCuadre::diagnosticar(SireCuadre::comparar($codLibro, $s->periodo, $propuesta, $empresa->IdEmpresa), $codLibro, $empresa->IdEmpresa);

        $filasCuadre = [];
        $agregar = function (string $estado, array $c, $totSunat, $totSistema, array $diagnostico = []) use (&$filasCuadre) {
            $filasCuadre[] = [$estado, $c['tipo'], $c['serie'], $c['numero'], $c['fecha'], $c['doc'], $c['nombre'],
                $totSunat, $totSistema, $totSunat !== null && $totSistema !== null ? round($totSunat - $totSistema, 2) : null,
                $diagnostico['causa'] ?? '', $diagnostico['solucion'] ?? ''];
        };
        foreach ($cuadre['diferencias'] as $d) {
            $agregar('Total distinto', $d['sunat'], $d['sunat']['total'], $d['sistema']['total'], $d);
        }
        foreach ($cuadre['soloSunat'] as $c) {
            $agregar('Solo en SUNAT', $c, $c['total'], null, $c);
        }
        foreach ($cuadre['soloSistema'] as $c) {
            $agregar('Solo en el sistema', $c, null, $c['total'], $c);
        }
        foreach ($cuadre['coinciden'] as $c) {
            $agregar('Coincide', $c, $c['total'], $c['total']);
        }

        $nombreLibro = $codLibro === Sire::VENTAS ? 'RVIE' : 'RCE';
        $ruta = (new Excel)
            ->hoja("Propuesta SUNAT {$s->periodo}", $propuesta['cabecera'], $propuesta['filas'], SireCuadre::columnasMonto($propuesta['cabecera']))
            ->hoja('Cuadre con el sistema', ['Estado', 'Tipo', 'Serie', 'Número', 'Fecha', $libro === 'ventas' ? 'Doc. cliente' : 'Doc. proveedor',
                $libro === 'ventas' ? 'Cliente' : 'Proveedor', 'Total SUNAT', 'Total sistema', 'Diferencia', 'Causa', 'Qué hacer'], $filasCuadre)
            ->guardar();

        return response()->download($ruta, "SIRE_{$nombreLibro}_{$empresa->IdEmpresa}_{$s->periodo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Descarga el .zip original de SUNAT */
    public function archivo(string $libro, int $id)
    {
        $empresa = $this->autorizar();
        $s = DB::table('sire_solicitudes')->where('id', $id)->where('IdEmpresa', $empresa->IdEmpresa)->first();
        abort_unless($s && $s->archivo && Storage::exists($s->archivo), 404);

        return Storage::download($s->archivo, basename($s->archivo));
    }
}
