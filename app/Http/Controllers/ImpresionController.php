<?php
namespace App\Http\Controllers;

use App\Support\Impresion\Impresion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;

class ImpresionController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador configura las impresoras.');
    }

    // ------------------------------------------------------------------ impresión desde las pantallas

    /** Comprobante: imprime directo si el agente está conectado; si no, la pantalla usa la impresión del navegador */
    public function comprobante($id)
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($cab, 404);

        $ok = Impresion::comprobante((int) $id);
        return response()->json(['directa' => $ok, 'mensaje' => $ok ? $this->enviado() : $this->motivoNoDirecta()]);
    }

    public function precuenta($pedId)
    {
        abort_unless(DB::table('pedidos')->where('ped_id', $pedId)->where('id_empresa_negocio', $this->sucursal())->exists(), 404);
        $ok = Impresion::precuenta((int) $pedId);
        return response()->json(['directa' => $ok, 'mensaje' => $ok ? 'Precuenta: ' . mb_strtolower($this->enviado()) : $this->motivoNoDirecta()]);
    }

    /** Con impresora configurada todo va al agente; si está apagado queda en cola y sale apenas se conecte */
    private function enviado(): string
    {
        return Impresion::agenteConectado($this->sucursal()) ? 'Enviado a la impresora.'
            : 'En cola: el agente de impresión no está conectado, se imprimirá apenas se conecte.';
    }

    private function motivoNoDirecta(): string
    {
        return 'No hay impresoras configuradas: se usará la impresión del navegador.';
    }

    // ------------------------------------------------------------------ configuración (admin)

    public function index()
    {
        $this->soloAdmin();
        $sucursal = $this->sucursal();

        return view('empresas.impresion.index', [
            'impresoras' => DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('Id')->get(),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(),
            'conectado' => Impresion::agenteConectado($sucursal),
            'cola' => DB::table('cola_impresion')->where('id_empresa_negocio', $sucursal)->orderByDesc('id')->limit(25)
                ->get(['id', 'impresora', 'tipo', 'referencia', 'estado', 'intentos', 'error', 'creado', 'impreso']),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $sucursal)->orderBy('cat_nom')->get(['cat_id', 'cat_nom', 'impresora']),
            'usuarios' => DB::table('users')->where('id_empresa_negocio', $sucursal)->where('estusu', 1)->orderBy('apeusu')->get(['IdUsuario', 'apeusu', 'terminal']),
        ]);
    }

    private function datosImpresora(Request $request): array
    {
        $d = $request->validate([
            'descripcion'   => 'required|string|max:30',
            'tip_conex_imp' => 'required|in:COMPARTIDO,RED,WINDOWS',
            'ruta'          => ['required', 'string', 'max:100', $request->tip_conex_imp === 'RED'
                ? 'regex:/^[\w.\-]+(:\d{2,5})?$/' : 'regex:/^[^"<>|*?]+$/'],
            'columnas'      => 'required|integer|in:32,42,48',
            'predeterminado'=> 'nullable|boolean',
            'abrir_cajon'   => 'nullable|boolean',
            'activo'        => 'nullable|boolean',
        ], ['ruta.regex' => 'Para RED escribe la IP (ej. 192.168.1.50 o 192.168.1.50:9100); para COMPARTIDO el nombre con el que compartiste la impresora; para A4 el nombre de la impresora en Windows.'],
            ['descripcion' => 'Nombre', 'ruta' => 'Ruta / IP', 'tip_conex_imp' => 'Conexión']);

        return [
            'descripcion' => mb_strtoupper(trim($d['descripcion'])), 'tip_conex_imp' => $d['tip_conex_imp'], 'ruta' => trim($d['ruta']),
            'columnas' => (int) $d['columnas'], 'predeterminado' => (int) $request->boolean('predeterminado'),
            'abrir_cajon' => (int) $request->boolean('abrir_cajon'), 'activo' => (int) $request->boolean('activo', true),
        ];
    }

    public function guardarImpresora(Request $request, $id = null)
    {
        $this->soloAdmin();
        $sucursal = $this->sucursal();
        $datos = $this->datosImpresora($request);

        DB::transaction(function () use ($datos, $id, $sucursal) {
            // Solo una predeterminada por sucursal
            if ($datos['predeterminado']) {
                DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->update(['predeterminado' => 0]);
            }
            if ($id) {
                DB::table('configuracion_impresoras')->where('Id', $id)->where('id_empresa_negocio', $sucursal)->update($datos);
            } else {
                DB::table('configuracion_impresoras')->insert($datos + ['id_empresa_negocio' => $sucursal, 'IdEmpresa' => Auth::user()->IdEmpresa]);
            }
        });

        return redirect()->route('impresion.index')->with('success', 'Impresora ' . $datos['descripcion'] . ' guardada.');
    }

    public function eliminarImpresora($id)
    {
        $this->soloAdmin();
        $sucursal = $this->sucursal();
        DB::table('configuracion_impresoras')->where('Id', $id)->where('id_empresa_negocio', $sucursal)->delete();
        DB::table('categorias')->where('id_empresa_negocio', $sucursal)->where('impresora', $id)->update(['impresora' => null]);
        DB::table('users')->where('id_empresa_negocio', $sucursal)->where('terminal', $id)->update(['terminal' => null]);
        return back()->with('success', 'Impresora eliminada.');
    }

    public function prueba($id)
    {
        $this->soloAdmin();
        $imp = Impresion::impresora((int) $id, $this->sucursal());
        abort_unless($imp, 404);
        Impresion::prueba($imp);
        $aviso = Impresion::agenteConectado($this->sucursal()) ? '' : ' (el agente no está conectado: se imprimirá cuando se conecte)';
        return back()->with('success', 'Prueba enviada a ' . $imp->descripcion . $aviso . '.');
    }

    /** Qué impresora usa cada categoría (cocina/bar) y cada usuario (caja) */
    public function asignaciones(Request $request)
    {
        $this->soloAdmin();
        $sucursal = $this->sucursal();
        $validas = DB::table('configuracion_impresoras')->where('id_empresa_negocio', $sucursal)->pluck('Id')->map(fn($v) => (int) $v)->all();
        $limpiar = fn($v) => in_array((int) $v, $validas, true) ? (int) $v : null;

        foreach ((array) $request->input('categoria', []) as $cat => $imp) {
            DB::table('categorias')->where('cat_id', $cat)->where('id_empresa_negocio', $sucursal)->update(['impresora' => $limpiar($imp)]);
        }
        foreach ((array) $request->input('usuario', []) as $usu => $imp) {
            DB::table('users')->where('IdUsuario', $usu)->where('id_empresa_negocio', $sucursal)->update(['terminal' => $limpiar($imp)]);
        }
        return back()->with('success', 'Asignaciones guardadas.');
    }

    public function reintentar($id)
    {
        $this->soloAdmin();
        // Solo trabajos con error o sin confirmar (los impresos ya no guardan su contenido)
        DB::table('cola_impresion')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())
            ->whereIn('estado', [1, 9])->whereNotNull('contenido')
            ->update(['estado' => 0, 'intentos' => 0, 'error' => null, 'entregado' => null]);
        return back()->with('success', 'Trabajo enviado otra vez a la cola.');
    }

    /**
     * Agente con una clave NUEVA (el agente anterior de esta sucursal deja de funcionar).
     *  - completo: ZIP con worker.php, config.ini de esta sucursal, iniciar.bat, invisible.vbs y LEEME.txt
     *  - clave:    solo el bloque para agregar al config.ini de una PC que ya tiene el agente (varias empresas en una PC)
     */
    public function descargarAgente(Request $request)
    {
        $this->soloAdmin();
        $sucursal = $this->sucursal();
        $token = Str::random(48);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->update(['token_impresion' => hash('sha256', $token)]);

        $negocio = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $sucursal)->first(['n.nombre_comercial', 'e.NomEmpresa', 'e.IdEmpresa']);
        $nombre = trim(preg_replace('/[^A-Za-z0-9 ._-]/', '', (string) ($negocio->nombre_comercial ?: $negocio->NomEmpresa))) ?: 'SUCURSAL';
        $seccion = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $negocio->IdEmpresa . '_' . $sucursal));
        $bloque = "[{$seccion}]\r\n; {$nombre}\r\ntipo = nuevo\r\nservidor = \"" . rtrim(url('/'), '/') . "\"\r\ntoken = \"{$token}\"\r\n";

        if ($request->input('modo') === 'clave') {
            return response($bloque, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="agregar-al-config-' . $seccion . '.txt"',
            ]);
        }

        $carpeta = resource_path('stubs/agente');
        $zipRuta = tempnam(sys_get_temp_dir(), 'agente');
        $zip = new \ZipArchive();
        $zip->open($zipRuta, \ZipArchive::OVERWRITE);
        $zip->addFile($carpeta . '/worker.php', 'worker.php');
        $zip->addFromString('config.ini', str_replace('__SISTEMAS__', $bloque, file_get_contents($carpeta . '/config.ini')));
        foreach (['iniciar.bat', 'invisible.vbs', 'LEEME.txt'] as $archivo) {
            $zip->addFile($carpeta . '/' . $archivo, $archivo);
        }
        $zip->close();

        return response()->download($zipRuta, 'agente-impresion.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    // ------------------------------------------------------------------ API del agente (sin sesión, con token)

    private function sucursalDelToken(Request $request): int
    {
        $token = (string) $request->header('X-Token', '');
        abort_if(strlen($token) < 20, 401);
        $id = DB::table('empresa_negocios')->where('token_impresion', hash('sha256', $token))->value('id_empresa_negocio');
        abort_unless($id, 401);
        return (int) $id;
    }

    /**
     * Trabajos pendientes de la sucursal. El agente pregunta cada 2 s con espera=0 (responde al instante y no deja
     * procesos PHP ocupados); con espera=N el servidor la mantiene abierta hasta N s (agentes antiguos).
     */
    public function trabajos(Request $request)
    {
        $sucursal = $this->sucursalDelToken($request);
        $espera = max(0, min(25, (int) $request->query('espera', 20)));
        $limite = microtime(true) + $espera;
        @set_time_limit($espera + 15);

        // "Agente conectado": basta marcarlo una vez por consulta (llega cada <= 25 s; se considera caído a los 90 s)
        DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->update(['impresion_contacto' => now()]);

        // Lo que se le puede entregar: pendiente, o entregado hace más de 90 s sin confirmación (el agente se cayó a medio camino)
        $porEntregar = fn($q) => $q->where('id_empresa_negocio', $sucursal)
            ->where(fn($q) => $q->where('estado', 0)
                ->orWhere(fn($q2) => $q2->where('estado', 1)->where('entregado', '<', now()->subSeconds(90))->where('intentos', '<', 3)));

        do {
            // Mientras no hay nada solo se hace una lectura liviana (sin bloqueo ni escritura):
            // con muchas sucursales conectadas la base no carga trabajo cuando nadie imprime
            if (!DB::table('cola_impresion')->where($porEntregar)->exists()) {
                if (microtime(true) + 0.7 >= $limite) {
                    break;   // espera=0 (el agente pregunta cada 2 s): responde al instante
                }
                usleep(700000);
                continue;
            }

            $ids = DB::transaction(function () use ($porEntregar) {
                $ids = DB::table('cola_impresion')->where($porEntregar)->orderBy('id')->limit(10)->lockForUpdate()->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('cola_impresion')->whereIn('id', $ids)->update(['estado' => 1, 'entregado' => now(), 'intentos' => DB::raw('intentos + 1')]);
                }
                return $ids;
            });

            if ($ids->isNotEmpty()) {
                $trabajos = DB::table('cola_impresion as c')
                    ->leftJoin('configuracion_impresoras as i', 'i.Id', '=', 'c.id_impresora')
                    ->whereIn('c.id', $ids)->orderBy('c.id')
                    ->get(['c.id', 'c.tipo', 'c.referencia', 'c.contenido', 'i.descripcion', 'i.ruta', 'i.tip_conex_imp']);
                return response()->json(['trabajos' => $trabajos]);
            }
        } while (microtime(true) < $limite);

        return response()->json(['trabajos' => []]);
    }

    public function resultado(Request $request)
    {
        $sucursal = $this->sucursalDelToken($request);
        $request->validate(['id' => 'required|integer', 'ok' => 'required|boolean', 'error' => 'nullable|string|max:250']);

        DB::table('cola_impresion')->where('id', $request->id)->where('id_empresa_negocio', $sucursal)->update(
            $request->boolean('ok')
                ? ['estado' => 2, 'impreso' => now(), 'error' => null, 'contenido' => null] // ya impreso: no se guarda el contenido
                : ['estado' => 9, 'error' => mb_substr((string) $request->error, 0, 250)]
        );

        return response()->json(['ok' => true]);
    }
}
