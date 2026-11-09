<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Bitacora;
use App\Support\ConsultaPeru;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Estudio;
use App\Support\EstudioDescargas;
use App\Support\EstudioImportar;
use App\Support\ExcelLector;
use App\Support\Sunat\Sire;
use App\Support\Vencimientos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Estudio contable: registrar las empresas de los clientes, decidir qué usuario trabaja cada una y cambiar de empresa.
 * Solo el administrador del estudio administra; cualquiera de sus usuarios cambia entre las empresas que tiene.
 */
class EstudioController extends Controller
{
    private function administrar(): string
    {
        abort_unless(Estudio::activo(), 404);
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador del estudio registra empresas y da accesos.');

        return Estudio::ruc();
    }

    public function index(Request $request): View
    {
        $estudio = $this->administrar();
        $q = trim((string) $request->get('q'));
        $empresas = DB::table('estudio_empresas as ee')->join('empresa as e', 'e.IdEmpresa', '=', 'ee.IdEmpresa')
            ->where('ee.propia', false)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('e.NomEmpresa', 'like', "%{$q}%")->orWhere('ee.IdEmpresa', 'like', "{$q}%")))
            ->orderByDesc('ee.activa')->orderBy('e.NomEmpresa')
            ->get(['ee.IdEmpresa', 'e.NomEmpresa', 'e.DirEmpresa', 'ee.activa', 'e.client_id', 'e.sire_usuario', 'e.sol_usuario']);
        $accesos = DB::table('estudio_accesos')->get()->groupBy('IdEmpresa')->map(fn ($a) => $a->pluck('IdUsuario')->map(fn ($id) => (int) $id)->all());
        // Los usuarios del estudio que no son administradores (el administrador ya ve todas)
        $usuarios = User::query()->whereNotIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->select('user_IdUsuario'))
            ->where('estusu', 1)->orderBy('apeusu')->get(['IdUsuario', 'apeusu', 'email']);

        return view('empresas.estudio.index', [
            'estudio' => DB::table('empresa')->where('IdEmpresa', $estudio)->first(),
            'empresas' => $empresas, 'accesos' => $accesos, 'usuarios' => $usuarios, 'q' => $q,
            'activas' => Estudio::activas(), 'limite' => Estudio::limite(),
            'cuentas' => DB::table('concar_config')->whereIn('IdEmpresa', $empresas->pluck('IdEmpresa'))->get()->keyBy('IdEmpresa'),
            // Igual que al conectarse: con el usuario SOL del SIRE o, si no se puso, con la Clave SOL principal
            'sireListo' => Empresa::whereIn('IdEmpresa', $empresas->pluck('IdEmpresa'))->get()
                ->mapWithKeys(fn (Empresa $empresa) => [$empresa->IdEmpresa => Sire::configurado($empresa)])->all(),
        ]);
    }

    private function sinCupo(): RedirectResponse
    {
        return back()->withInput()->with('error', 'Tu plan permite hasta '.Estudio::limite().' empresas activas. Desactiva una que ya no lleves o pide a TUSHPA ampliar tu plan.');
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->administrar();
        if (! Estudio::hayCupo()) {
            return $this->sinCupo();
        }
        $d = $request->validate([
            'ruc' => ['required', 'digits:11', 'unique:empresa,IdEmpresa', fn ($campo, $valor, $falla) => ConsultaPeru::rucValido((string) $valor) || $falla('Ese RUC no es válido. Revisa los 11 dígitos.')],
            'razon_social' => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:250',
        ] + self::reglasConfiguracion(), ['ruc.unique' => 'Esa empresa ya está registrada en el estudio.', 'regex' => 'Solo letras y números, sin espacios ni puntos.'],
            ['ruc' => 'RUC', 'razon_social' => 'razón social'] + self::NOMBRES);
        Estudio::crearEmpresa(['ruc' => $d['ruc'], 'razon_social' => mb_strtoupper(trim($d['razon_social'])),
            'nombre_comercial' => ! empty($d['nombre_comercial']) ? mb_strtoupper(trim($d['nombre_comercial'])) : null, 'direccion' => $d['direccion'] ?? null]);
        // Credenciales y cuentas de una vez (lo que no se escriba se pone luego con "Configurar")
        $this->aplicarConfiguracion($d['ruc'], $d);

        return redirect()->route('estudio.index')->with('success', "Empresa {$d['ruc']} registrada.".(empty($d['client_id']) ? ' Ponle sus credenciales SIRE con "Configurar" cuando las tengas.' : ''));
    }

    /** @return array<string, mixed> reglas de las credenciales y cuentas de una empresa (todas opcionales) */
    public static function reglasConfiguracion(): array
    {
        $cuenta = ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Za-z]+$/'];

        return [
            'client_id' => 'nullable|string|max:100', 'client_secret' => 'nullable|string|max:255',
            'sire_usuario' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'], 'sire_clave' => 'nullable|string|max:100',
            'sol_usuario' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'], 'sol_clave' => 'nullable|string|max:60',
            'cta_compras' => $cuenta, 'cta_por_pagar' => $cuenta, 'cta_por_cobrar' => $cuenta, 'cta_ventas' => $cuenta, 'cta_ventas_exo' => $cuenta,
        ];
    }

    public const NOMBRES = ['client_id' => 'ID del API SIRE', 'sire_usuario' => 'usuario SOL del SIRE', 'sol_usuario' => 'usuario SOL principal'];

    /** Guarda las credenciales (las claves solo si se escriben) y las cuentas por defecto de una empresa */
    private function aplicarConfiguracion(string $ruc, array $d): void
    {
        Estudio::configurar($ruc, $d);
    }

    /**
     * Lo que necesita cada empresa para trabajar: sus credenciales del API SIRE, su Clave SOL principal (para traer los
     * XML de compras y, si no se pone otro, para entrar al SIRE) y las cuentas por defecto de sus asientos y del .dbf.
     */
    public function configurar(Request $request, string $ruc): RedirectResponse
    {
        $this->administrar();
        abort_unless(DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->exists(), 404);
        $d = $request->validate(self::reglasConfiguracion(), ['regex' => 'Solo letras y números, sin espacios ni puntos.'], self::NOMBRES);
        $antes = (Bitacora::foto('empresa', ['IdEmpresa' => $ruc]) ?? []) + (Bitacora::foto('concar_config', ['IdEmpresa' => $ruc]) ?? []);
        $this->aplicarConfiguracion($ruc, $d);
        $etiquetas = ['client_id' => 'ID API SUNAT (SIRE)', 'client_secret' => 'Clave API SUNAT (SIRE)', 'sire_usuario' => 'Usuario SOL del SIRE',
            'sire_clave' => 'Clave SOL del SIRE', 'sol_usuario' => 'Usuario SOL principal', 'sol_clave' => 'Clave SOL principal'] + ['cta_compras' => 'Cuenta compras', 'cta_por_pagar' => 'Cuenta por pagar', 'cta_por_cobrar' => 'Cuenta por cobrar',
                'cta_ventas' => 'Cuenta ventas', 'cta_ventas_exo' => 'Cuenta ventas exoneradas'];
        Bitacora::siCambio('config.estudio_empresa', "Cambió las credenciales o cuentas de la empresa {$ruc}", $antes,
            (Bitacora::foto('empresa', ['IdEmpresa' => $ruc]) ?? []) + (Bitacora::foto('concar_config', ['IdEmpresa' => $ruc]) ?? []), $etiquetas,
            ['referencia' => ['empresa', $ruc], 'alerta' => true]);

        return back()->with('success', "Datos de la empresa {$ruc} guardados.");
    }

    /** El formato de Excel para importar las empresas (con lo que pide el SIRE) */
    public function formato()
    {
        $this->administrar();

        return response()->download(EstudioImportar::formato(), 'formato_empresas_estudio.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Importa las empresas del Excel: las nuevas se crean con sus claves y cuentas; un RUC que ya existe se omite */
    public function importar(Request $request): RedirectResponse
    {
        $this->administrar();
        $request->validate(['archivo' => 'required|file|max:10240|extensions:'.ExcelLector::EXTENSIONES],
            ['archivo.extensions' => 'Sube el formato en Excel (.xlsx, .xls, .ods) o .csv.', 'archivo.required' => 'Elige el archivo del formato.']);
        try {
            $filas = ExcelLector::filas($request->file('archivo')->getRealPath(), $request->file('archivo')->getClientOriginalExtension(), EstudioImportar::HOJA);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        set_time_limit(600);
        $r = EstudioImportar::importar($filas);
        if ($r['nuevas']) {
            Bitacora::registrar('config.estudio_importar', 'Importó '.count($r['nuevas']).' empresa(s) al estudio desde Excel', null,
                ['datos' => ['nuevas' => array_values($r['nuevas']), 'omitidas' => array_values($r['omitidas'])]]);
        }

        $partes = [count($r['nuevas']).' empresa(s) creada(s)'];
        if ($r['omitidas']) {
            $partes[] = count($r['omitidas']).' ya existían y se omitieron';
        }
        if ($r['sinCupo']) {
            $partes[] = "{$r['sinCupo']} no se crearon porque llegaste al límite de tu plan";
        }

        return redirect()->route('estudio.index')->with($r['nuevas'] ? 'success' : 'error', implode(' · ', $partes).'.')
            ->with('importacion', $r);
    }

    /** Una empresa que el estudio ya no lleva: deja de verse en el selector (sus datos se conservan) */
    public function estado(string $ruc): RedirectResponse
    {
        $this->administrar();
        $fila = DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->first();
        abort_unless($fila, 404);
        if (! $fila->activa && ! Estudio::hayCupo()) {
            return $this->sinCupo();
        }
        DB::table('estudio_empresas')->where('id', $fila->id)->update(['activa' => ! $fila->activa, 'updated_at' => now()]);
        Estudio::anotarEnPanel();

        return back()->with('success', $fila->activa ? "Empresa {$ruc} desactivada: ya nadie la ve en el selector. Sus datos se conservan." : "Empresa {$ruc} activada de nuevo.");
    }

    public function accesos(Request $request, string $ruc): RedirectResponse
    {
        $this->administrar();
        abort_unless(DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->exists(), 404);
        $d = $request->validate(['usuarios' => 'nullable|array', 'usuarios.*' => 'integer|exists:users,IdUsuario']);
        Estudio::guardarAccesos($ruc, $d['usuarios'] ?? []);

        return back()->with('success', 'Accesos de la empresa '.$ruc.' guardados.');
    }

    /** Después de ingresar (y cuando se quiera): la lista de las empresas que le tocan al usuario para elegir una */
    public function elegir(): View
    {
        abort_unless(Estudio::activo(), 404);
        $empresas = Estudio::empresas(Auth::user());

        return view('empresas.estudio.elegir', [
            'empresas' => $empresas, 'actual' => (string) Auth::user()->IdEmpresa,
            'digitos' => $empresas->where('propia', false)->countBy(fn ($e) => substr((string) $e->IdEmpresa, -1)),
            'periodo' => $periodo = Vencimientos::periodoPendiente(),
            'vencimientos' => Estudio::vencimientos(Auth::user(), $periodo)->keyBy('IdEmpresa'),
            // Cómo va la última descarga automática (SIRE y XML)
            'descarga' => ($ultimo = DB::table('estudio_descargas')->max('periodo')) ? ['periodo' => $ultimo] + EstudioDescargas::resumen($ultimo) : null,
        ]);
    }

    /** Descarga automática: cómo va lo traído de cada empresa (todos lo ven) y la programación (solo el administrador) */
    public function descargas(Request $request): View
    {
        abort_unless(Estudio::activo(), 404);
        $ultimo = DB::table('estudio_descargas')->max('periodo') ?? EstudioDescargas::periodo();
        $periodo = preg_match('/^20\d{2}(0[1-9]|1[0-2])$/', (string) $request->query('periodo')) ? (string) $request->query('periodo') : $ultimo;

        return view('empresas.estudio.descargas', [
            'periodo' => $periodo, 'config' => EstudioDescargas::config(), 'empresas' => EstudioDescargas::delPeriodo($periodo),
            'resumen' => EstudioDescargas::resumen($periodo), 'esAdmin' => Auth::user()->esAdmin(),
            'periodos' => DB::table('estudio_descargas')->distinct()->orderByDesc('periodo')->limit(12)->pluck('periodo'),
            'siguiente' => EstudioDescargas::periodo(),
            // A qué empresas puede entrar este usuario (para los accesos directos a su DBF)
            'puede' => Estudio::empresas(Auth::user())->pluck('IdEmpresa')->map(fn ($r) => (string) $r)->all(),
        ]);
    }

    public function programacion(Request $request): RedirectResponse
    {
        $this->administrar();
        $d = $request->validate(['activo' => 'required|boolean', 'dia' => 'required|integer|min:1|max:28', 'hora' => 'required|integer|min:0|max:23',
            'sire_compras' => 'nullable|boolean', 'sire_ventas' => 'nullable|boolean', 'xml' => 'nullable|boolean']);
        EstudioDescargas::guardarConfig(['activo' => (bool) $d['activo'], 'dia' => (int) $d['dia'], 'hora' => (int) $d['hora'],
            'sire_compras' => $request->boolean('sire_compras'), 'sire_ventas' => $request->boolean('sire_ventas'), 'xml' => $request->boolean('xml')]);
        Bitacora::registrar('config.estudio_descargas', $d['activo'] ? "Programó la descarga automática el día {$d['dia']} de cada mes a las {$d['hora']}:00" : 'Apagó la descarga automática');

        return back()->with('success', $d['activo'] ? "Listo: cada día {$d['dia']} a las {$d['hora']}:00 se trae solo lo del mes anterior." : 'Descarga automática apagada.');
    }

    /** Traer ya un periodo (o reintentar lo que falló): se arma la lista y el trabajador de colas la avanza en los próximos minutos */
    public function descargarAhora(Request $request): RedirectResponse
    {
        $this->administrar();
        $d = $request->validate(['periodo' => ['required', 'regex:/^20\d{2}(0[1-9]|1[0-2])$/']]);
        $n = EstudioDescargas::programar($d['periodo']);
        Bitacora::registrar('config.estudio_descargas', 'Pidió descargar '.Contabilidad::nombrePeriodo($d['periodo'])." ({$n} tarea(s))");

        return redirect()->route('estudio.descargas', ['periodo' => $d['periodo']])->with('success', $n
            ? "{$n} tarea(s) en cola: se van trayendo en los próximos minutos (puedes cerrar esta pantalla)."
            : 'No había nada que traer o volver a intentar en ese periodo.');
    }

    /** Vencimientos SUNAT del periodo: qué empresa vence cuándo y cuáles ya se declararon */
    public function vencimientos(Request $request): View
    {
        abort_unless(Estudio::activo(), 404);
        $periodo = (string) $request->query('periodo', Vencimientos::periodoPendiente());
        $mes = preg_match('/^(20\d{2})(0[1-9]|1[0-2])$/', $periodo) ? Carbon::createFromFormat('!Ym', $periodo) : null;
        abort_unless($mes && $mes->year >= 2023 && $mes->lte(now()->addYear()), 404);

        return view('empresas.estudio.vencimientos', [
            'periodo' => $periodo, 'mes' => $mes, 'lista' => Estudio::vencimientos(Auth::user(), $periodo),
            'cronograma' => Vencimientos::cronograma($mes->year), 'pendiente' => Vencimientos::periodoPendiente(),
        ]);
    }

    /** Marca que una empresa ya declaró el periodo (o lo desmarca si se marcó por error) */
    public function declarar(Request $request): RedirectResponse
    {
        abort_unless(Estudio::activo(), 404);
        $d = $request->validate(['ruc' => 'required|digits:11', 'periodo' => ['required', 'regex:/^20\d{2}(0[1-9]|1[0-2])$/'], 'declarado' => 'required|boolean']);
        Estudio::declarar(Auth::user(), $d['ruc'], $d['periodo'], (bool) $d['declarado']);
        $nombre = Contabilidad::nombrePeriodo($d['periodo']);
        Bitacora::registrar('estudio.declaracion', ($d['declarado'] ? 'Marcó como declarado' : 'Quitó la marca de declarado de').
            " el periodo {$nombre} de la empresa {$d['ruc']}", null, ['referencia' => ['empresa', $d['ruc']]]);

        return back()->with('success', $d['declarado'] ? "Empresa {$d['ruc']}: {$nombre} declarado." : "Empresa {$d['ruc']}: se quitó la marca de {$nombre}.");
    }

    /** Los buenos contribuyentes vencen en su propia fecha (la última del cronograma) */
    public function buenContribuyente(string $ruc): RedirectResponse
    {
        $this->administrar();
        $fila = DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->first();
        abort_unless($fila, 404);
        DB::table('estudio_empresas')->where('id', $fila->id)->update(['buen_contribuyente' => ! $fila->buen_contribuyente, 'updated_at' => now()]);

        return back()->with('success', "Empresa {$ruc}: ".($fila->buen_contribuyente ? 'ya no es' : 'ahora es').' buen contribuyente.');
    }

    public function cambiar(Request $request): RedirectResponse
    {
        abort_unless(Estudio::activo(), 404);
        // ir: a qué pantalla del sistema entrar después (solo del SIRE o de contabilidad, nunca una dirección de afuera)
        $d = $request->validate(['ruc' => 'required|digits:11', 'ir' => ['nullable', 'string', 'max:200', 'regex:#^/(sire|contabilidad)(/[A-Za-z0-9/_-]*)?(\?[A-Za-z0-9=&_-]*)?$#']]);
        $ruc = (string) $d['ruc'];
        Estudio::cambiar(Auth::user(), $ruc);
        $nombre = DB::table('empresa')->where('IdEmpresa', $ruc)->value('NomEmpresa');

        return redirect()->to(! empty($d['ir']) ? url($d['ir']) : route('inicio'))->with('success', "Ahora trabajas en {$nombre} ({$ruc}).");
    }
}
