<?php

namespace App\Http\Controllers;

use App\Support\Bitacora;
use App\Support\Buscar;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bitácora de cambios (solo el administrador): quién cambió qué, cuándo y qué había antes. Solo se puede ver y exportar;
 * no hay forma de editar ni borrar lo registrado.
 */
class BitacoraController extends Controller
{
    /** Tope de filas al exportar (un año entero de una empresa grande cabe de sobra) */
    private const MAX_EXCEL = 20000;

    public function index(Request $request): View
    {
        $this->autorizar();
        $f = $this->filtros($request);
        $disponible = Bitacora::disponible();

        return view('empresas.bitacora.index', [
            'f' => $f,
            'disponible' => $disponible,
            'filas' => $disponible ? $this->consulta($f)->orderByDesc('id')->paginate(50)->withQueryString() : null,
            'alertas' => $disponible ? $this->consulta(['alertas' => true] + $f)->count() : 0,
            'usuarios' => $disponible ? DB::table('bitacora')->whereNotNull('IdUsuario')->select('IdUsuario', DB::raw('MAX(usuario) as nombre'))
                ->groupBy('IdUsuario')->orderBy('nombre')->pluck('nombre', 'IdUsuario') : collect(),
            'sucursales' => DB::table('empresa_negocios')->pluck('nombre_comercial', 'id_empresa_negocio'),
            'modulos' => Bitacora::MODULOS,
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $this->autorizar();
        abort_unless(Bitacora::disponible(), 404);
        $f = $this->filtros($request);
        $sucursales = DB::table('empresa_negocios')->pluck('nombre_comercial', 'id_empresa_negocio');
        $filas = $this->consulta($f)->orderBy('id')->limit(self::MAX_EXCEL)->get()->map(fn ($b) => [
            Carbon::parse($b->fecha)->format('d/m/Y H:i:s'), (string) $b->usuario, (string) $b->rol, $sucursales[$b->id_empresa_negocio] ?? '',
            Bitacora::MODULOS[$b->modulo] ?? $b->modulo, $b->descripcion, Bitacora::resumen(json_decode((string) $b->cambios, true), 50),
            $b->alerta ? 'Sí' : '', (string) $b->ip,
        ])->all();

        $ruta = (new Excel)->hoja('Bitácora', ['Fecha y hora', 'Usuario', 'Rol', 'Sucursal', 'Módulo', 'Qué hizo', 'Cambios (antes → después)', 'Para revisar', 'IP'], $filas)
            ->guardar();
        $nombre = 'bitacora_'.$f['desde'].'_al_'.$f['hasta'].'.xlsx';

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()?->esAdmin(), 403, 'Solo el administrador ve la bitácora.');
    }

    /**
     * Filtros de la URL, ya validados: fechas (por defecto los últimos 30 días; máximo un año), persona, módulo, texto y
     * solo lo que hay que revisar.
     *
     * @return array{desde: string, hasta: string, usuario: ?int, modulo: ?string, q: string, alertas: bool, sucursal: ?int}
     */
    private function filtros(Request $request): array
    {
        $fecha = function (?string $valor, Carbon $defecto): Carbon {
            try {
                return $valor ? Carbon::parse($valor)->startOfDay() : $defecto;
            } catch (\Throwable) {
                return $defecto;
            }
        };
        $hasta = $fecha($request->query('hasta'), today());
        $desde = $fecha($request->query('desde'), $hasta->copy()->subDays(30));
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde->lt($hasta->copy()->subYear())) {
            $desde = $hasta->copy()->subYear();
        }
        $modulo = (string) $request->query('modulo');

        return [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'usuario' => (int) $request->query('usuario') ?: null,
            'modulo' => array_key_exists($modulo, Bitacora::MODULOS) ? $modulo : null,
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100),
            'alertas' => $request->boolean('alertas'),
            'sucursal' => (int) $request->query('sucursal') ?: null,
        ];
    }

    private function consulta(array $f): Builder
    {
        $q = DB::table('bitacora')->whereBetween('fecha', [$f['desde'].' 00:00:00', $f['hasta'].' 23:59:59'])
            ->when($f['usuario'], fn ($q, $u) => $q->where('IdUsuario', $u))
            ->when($f['modulo'], fn ($q, $m) => $q->where('modulo', $m))
            ->when($f['sucursal'], fn ($q, $s) => $q->where('id_empresa_negocio', $s))
            ->when($f['alertas'], fn ($q) => $q->where('alerta', true));
        // Cada palabra puede estar en lo que hizo, en quién o en los cambios (ej. "inca precio")
        Buscar::palabras($q, $f['q'], ['descripcion', 'usuario', 'cambios']);

        return $q;
    }
}
