<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CartaWebController;
use App\Http\Controllers\Controller;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Posibles clientes de la web (tushpa.app): negocios que crearon su carta digital gratis, los que escribieron por el
 * formulario de contacto y las empresas en prueba gratis. Para escribirles por WhatsApp o correo con tus ofertas.
 */
class ProspectoController extends Controller
{
    public const TABS = ['cartas' => 'Crearon su carta', 'pruebas' => 'En prueba gratis', 'mensajes' => 'Escribieron por la web'];

    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema ve los prospectos.');
    }

    /** Filas comunes de cada lista: nombre, contacto, fecha y datos propios */
    private function filas(string $tab, string $q, string $estado)
    {
        $central = DB::connection('central');
        $buscar = fn ($w, array $cols) => $q === '' ? $w : $w->where(fn ($x) => collect($cols)->each(fn ($c) => $x->orWhere($c, 'like', "%{$q}%")));

        return match ($tab) {
            'cartas' => $buscar($central->table('cartas_web'), ['nombre', 'email', 'whatsapp', 'direccion'])
                ->when($estado === 'sin', fn ($w) => $w->whereNull('contactado_at'))->when($estado === 'con', fn ($w) => $w->whereNotNull('contactado_at'))
                ->orderByDesc('id')->get()->map(fn ($c) => (object) ['id' => $c->id, 'nombre' => $c->nombre, 'email' => $c->email, 'telefono' => $c->whatsapp,
                    'detalle' => trim(($c->direccion ?: '').' · '.$c->visitas.' visita(s) a su carta', ' ·'), 'fecha' => $c->created_at,
                    'enlace' => CartaWebController::url($c), 'contactado' => $c->contactado_at, 'nota' => $c->nota, 'estado' => $c->estado]),
            'pruebas' => $buscar($central->table('clientes')->where('origen', 'web'), ['razon_social', 'nombre_comercial', 'ruc', 'contacto_correo', 'contacto_telefono'])
                ->orderByDesc('id')->get()->map(fn ($c) => (object) ['id' => $c->id, 'nombre' => $c->nombre_comercial ?: $c->razon_social, 'email' => $c->contacto_correo,
                    'telefono' => $c->contacto_telefono, 'detalle' => "RUC {$c->ruc} · {$c->estado}".($c->prueba_hasta ? ' · prueba hasta '.Carbon::parse($c->prueba_hasta)->format('d/m/Y') : ''),
                    'fecha' => $c->created_at, 'enlace' => null, 'contactado' => null, 'nota' => $c->contacto_nombre, 'estado' => $c->estado]),
            default => $buscar($central->table('web_contactos'), ['nombre', 'correo', 'telefono', 'empresa', 'mensaje'])
                ->orderByDesc('id')->get()->map(fn ($c) => (object) ['id' => $c->id, 'nombre' => ($c->empresa ?? null) ?: $c->nombre, 'email' => $c->correo,
                    'telefono' => $c->telefono ?? null, 'detalle' => mb_strimwidth((string) ($c->mensaje ?? ''), 0, 120, '…'), 'fecha' => $c->created_at,
                    'enlace' => null, 'contactado' => $c->atendido ? $c->created_at : null, 'nota' => $c->nombre, 'estado' => null]),
        };
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'cartas';
        $q = trim((string) $request->get('q'));
        $estado = in_array($request->get('estado'), ['sin', 'con'], true) ? $request->get('estado') : '';
        $filas = $this->filas($tab, $q, $estado);
        $central = DB::connection('central');

        return view('admin.prospectos', [
            'tab' => $tab, 'q' => $q, 'estado' => $estado, 'filas' => $filas,
            'totales' => ['cartas' => $central->table('cartas_web')->count(), 'pruebas' => $central->table('clientes')->where('origen', 'web')->count(),
                'mensajes' => $central->table('web_contactos')->count()],
            'correos' => $filas->pluck('email')->filter()->unique()->values(),
        ]);
    }

    public function excel(Request $request)
    {
        $this->autorizar();
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'cartas';
        $filas = $this->filas($tab, trim((string) $request->get('q')), (string) $request->get('estado'))
            ->map(fn ($f) => [$f->nombre, (string) $f->email, (string) $f->telefono, (string) $f->detalle,
                $f->fecha ? Carbon::parse($f->fecha)->format('d/m/Y') : '', $f->contactado ? 'SÍ' : '', (string) $f->nota, (string) $f->enlace])->all();
        $x = (new Excel)->hoja(self::TABS[$tab], ['Negocio', 'Correo', 'WhatsApp / teléfono', 'Detalle', 'Fecha', 'Contactado', 'Nota', 'Enlace'], $filas);

        return response()->download($x->guardar(), "prospectos_{$tab}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Carta: marcar que ya se le escribió (o quitar la marca) y guardar una nota */
    public function contactado(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['nota' => 'nullable|string|max:200', 'quitar' => 'nullable|boolean']);
        DB::connection('central')->table('cartas_web')->where('id', $id)->update([
            'contactado_at' => $request->boolean('quitar') ? null : now(), 'nota' => $d['nota'] ?? null,
        ]);

        return back()->with('ok', $request->boolean('quitar') ? 'Marca quitada.' : 'Marcado como contactado.');
    }
}
