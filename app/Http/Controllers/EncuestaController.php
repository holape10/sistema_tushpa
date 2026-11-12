<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Carta;
use App\Support\Encuestas;
use App\Support\TelegramCitas;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Encuesta de satisfacción: la página pública que abre el cliente con el QR de su ticket (sin sesión)
 * y el reporte del administrador con los promedios, por mozo y los comentarios.
 */
class EncuestaController extends Controller
{
    /** Página pública: /encuesta/{codigo} */
    public function ver(Request $request, string $codigo): View
    {
        [$objetivo, $motivo] = Encuestas::objetivo($codigo);

        return view('encuesta.ver', $this->marca($objetivo?->sucursal) + ['codigo' => $codigo, 'motivo' => $motivo, 'gracias' => session('gracias'),
            'origen' => $request->query('desde') === 'carta' ? 'CARTA' : null]);
    }

    public function responder(Request $request, string $codigo): RedirectResponse
    {
        [$objetivo, $motivo] = Encuestas::objetivo($codigo);
        if (! $objetivo || $motivo) {
            return redirect()->route('encuesta.ver', $codigo);
        }
        $d = $request->validate([
            'comida' => 'required|integer|between:1,5',
            'atencion' => 'required|integer|between:1,5',
            'rapidez' => 'required|integer|between:1,5',
            'comentario' => 'nullable|string|max:300',
            'telefono' => ['nullable', 'regex:/^\+?[\d\s]{9,15}$/'],
        ], ['required' => 'Toca una carita en cada pregunta.', 'telefono.regex' => 'Escribe tu celular (9 dígitos).']);

        Encuestas::guardar($objetivo, $d, $request->input('origen') === 'CARTA' ? 'CARTA' : $objetivo->origen);

        return redirect()->route('encuesta.ver', $codigo)->with('gracias', Encuestas::promedio((object) $d));
    }

    /** Reporte del administrador */
    public function index(Request $request): View
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdmin(), 403, 'Solo el administrador ve las encuestas.');
        $suc = (int) $usuario->id_empresa_negocio;
        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->toDateString();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->toDateString();

        $encuestas = DB::table('encuestas as e')
            ->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', DB::raw('COALESCE(e.IdCpe_cabecera, (SELECT MIN(x.IdCpe_cabecera) FROM cpe_cabecera x WHERE x.ped_id = e.ped_id AND x.ccabaj IS NULL))'))
            ->leftJoin('pedidos as p', 'p.ped_id', '=', DB::raw('COALESCE(e.ped_id, c.ped_id)'))
            ->leftJoin('users as u', 'u.IdUsuario', '=', DB::raw('COALESCE(p.mozo, c.mozo, c.IdUsuario)'))
            ->leftJoin('mesas as m', 'm.mes_id', '=', DB::raw('COALESCE(p.mes_id, c.mes_id)'))
            ->where('e.id_empresa_negocio', $suc)->whereBetween('e.created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderByDesc('e.created_at')
            ->get(['e.*', 'c.serdoc', 'c.numdoc', DB::raw('COALESCE(c.ccaitv, p.ped_tot) as ccaitv'), 'c.ccanom', 'm.mes_nom',
                DB::raw('COALESCE(p.mozo, c.mozo, c.IdUsuario) as mozo_id'), DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as mozo")])
            ->each(fn ($e) => $e->promedio = Encuestas::promedio($e));
        $tickets = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereNull('ccabaj')->whereIn('tdocod', ['01', '03', '13'])
            ->whereBetween('fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])->count();

        $promedio = fn ($lista, $campo) => $lista->isNotEmpty() ? round($lista->avg($campo), 1) : null;
        $porMozo = $encuestas->groupBy('mozo_id')->map(fn ($g) => (object) [
            'mozo' => $g->first()->mozo ?: 'SIN MOZO', 'encuestas' => $g->count(), 'atencion' => $promedio($g, 'atencion'), 'general' => $promedio($g, 'promedio'),
        ])->sortByDesc('general')->values();

        return view('empresas.encuestas.index', [
            'desde' => $desde, 'hasta' => $hasta, 'encuestas' => $encuestas, 'porMozo' => $porMozo, 'tickets' => $tickets,
            'activa' => Encuestas::activa($suc),
            'qrTicket' => (bool) (DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('encuesta_qr_ticket') ?? true),
            // Calificaciones malas por Telegram (el mismo bot de los pedidos): los administradores de la empresa
            'telegram' => TelegramCitas::configurado() ? $this->administradores() : null,
            'promedios' => collect(['general' => 'promedio'] + array_combine(array_keys(Encuestas::PREGUNTAS), array_keys(Encuestas::PREGUNTAS)))
                ->map(fn ($campo) => $promedio($encuestas, $campo)),
            'contentos' => $encuestas->isNotEmpty() ? round($encuestas->where('promedio', '>=', 4)->count() / $encuestas->count() * 100) : null,
        ]);
    }

    public function activar(): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $negocio = EmpresaNegocio::findOrFail(Auth::user()->id_empresa_negocio);
        // Al activarla, el QR en el ticket viene marcado (se puede apagar para ahorrar papel)
        $negocio->update(['encuesta_activa' => ! $negocio->encuesta_activa] + (! $negocio->encuesta_activa ? ['encuesta_qr_ticket' => true] : []));

        return back()->with('success', $negocio->encuesta_activa
            ? 'Encuesta activada: el mozo la muestra con el botón ⭐ de la mesa, sale en la carta QR y en el ticket.' : 'Encuesta desactivada: ya no se muestra en ninguna parte.');
    }

    /** Imprimir o no el QR de la encuesta en el ticket (la encuesta sigue en la mesa y en la carta QR) */
    public function qrTicket(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $si = $request->boolean('qr_ticket');
        EmpresaNegocio::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->update(['encuesta_qr_ticket' => $si]);

        return back()->with('success', $si ? 'El QR de la encuesta sale impreso en el ticket.' : 'El ticket ya no lleva el QR (ahorra papel). La encuesta sigue en la mesa y en la carta QR.');
    }

    /** @return Collection<int, object> administradores activos de la empresa con su Telegram */
    private function administradores()
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 2)->where('u.IdEmpresa', Auth::user()->IdEmpresa)->where('u.estusu', 1)
            ->orderBy('u.IdUsuario')->distinct()->get(['u.IdUsuario', 'u.apeusu', 'u.name', 'u.telegram_chat_id', 'u.telegram_encuestas']);
    }

    private function administrador(int $usuario): object
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura los avisos por Telegram.');
        $u = $this->administradores()->firstWhere('IdUsuario', $usuario);
        abort_unless($u, 404);

        return $u;
    }

    /** Recibir (o no) las calificaciones malas en su Telegram */
    public function telegramRecibir(Request $request, int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        DB::table('users')->where('IdUsuario', $usuario)->update(['telegram_encuestas' => $request->boolean('recibir')]);

        return response()->json(['ok' => true]);
    }

    public function telegramProbar(int $usuario): JsonResponse
    {
        $u = $this->administrador($usuario);
        if (! $u->telegram_chat_id) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese administrador aún no vincula su Telegram.']);
        }
        [$ok, $detalle] = TelegramCitas::enviar((string) $u->telegram_chat_id, '⭐ Prueba: así te llegarán las calificaciones malas de los clientes de '
            .DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial').'.');

        return response()->json(['ok' => $ok, 'mensaje' => $ok ? '✔ Mensaje de prueba enviado a su Telegram.' : $detalle]);
    }

    /** El mozo, antes de cobrar: el QR de la encuesta de esa mesa para que el cliente lo escanee de su celular */
    public function qrPedido(int $pedido): JsonResponse
    {
        $p = DB::table('pedidos')->where('ped_id', $pedido)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first(['ped_id']);
        abort_unless($p, 404);
        $url = Encuestas::paraPedido($pedido);
        if (! $url) {
            return response()->json(['ok' => false, 'mensaje' => Encuestas::activa((int) Auth::user()->id_empresa_negocio)
                ? 'Esta mesa ya respondió la encuesta. ¡Gracias!' : 'La encuesta está desactivada.']);
        }
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 1),
            new SvgImageBackEnd));

        return response()->json(['ok' => true, 'url' => $url, 'svg' => $writer->writeString($url)]);
    }

    /** Nombre, logo y color del negocio para la página pública */
    private function marca(?int $sucursal): array
    {
        $negocio = $sucursal ? EmpresaNegocio::find($sucursal) : null;
        $empresa = $negocio ? Empresa::find($negocio->IdEmpresa) : null;

        return [
            'nombre' => $negocio?->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Nuestro restaurante'),
            'logo' => collect([$negocio?->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'color' => $negocio ? Carta::color($negocio) : Carta::COLOR,
        ];
    }
}
