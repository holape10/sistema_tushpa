<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Carta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Carta digital con QR: la página pública que ven los clientes y su configuración e impresión de QR.
 */
class CartaController extends Controller
{
    /** Carta pública: /carta/{sucursal}?mesa={id} */
    public function ver(Request $request, ?int $sucursal = null): View
    {
        $negocio = Carta::sucursal($sucursal);
        if (! $negocio) {
            abort(response()->view('carta.no_disponible', [], 404));
        }
        $mesa = $request->integer('mesa')
            ? DB::table('mesas')->where('mes_id', $request->integer('mesa'))->where('id_empresa_negocio', $negocio->id_empresa_negocio)->value('mes_nom')
            : null;

        return view('carta.ver', $this->datos($negocio) + [
            'categorias' => Carta::categorias($negocio->id_empresa_negocio),
            'mesa' => $mesa,
        ]);
    }

    public function configuracion(): View
    {
        $negocio = $this->negocioDelAdmin();

        return view('empresas.carta.configuracion', $this->datos($negocio) + [
            'url' => Carta::url($negocio->id_empresa_negocio),
            'qr' => Carta::qr(Carta::url($negocio->id_empresa_negocio), 220),
            'mesas' => $this->mesas($negocio->id_empresa_negocio),
            'categorias' => Carta::categorias($negocio->id_empresa_negocio),
            'sinCategoria' => DB::table('productos')->where('id_empresa_negocio', $negocio->id_empresa_negocio)
                ->where('proest', 'Activo')->where('promocion', '!=', 4)->whereNull('cat_id')->count(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $negocio = $this->negocioDelAdmin();
        $datos = $request->validate([
            'carta_mensaje' => 'nullable|string|max:300',
            'carta_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['carta_mensaje' => 'mensaje', 'carta_color' => 'color']);
        $negocio->update($datos + ['carta_activa' => $request->boolean('carta_activa')]);

        return back()->with('success', $request->boolean('carta_activa') ? 'Carta digital publicada.' : 'Carta digital desactivada.');
    }

    /** Hojas para imprimir: el afiche con el QR general o una tarjeta con QR por cada mesa */
    public function imprimir(Request $request): View
    {
        $negocio = $this->negocioDelAdmin();
        $suc = $negocio->id_empresa_negocio;
        $elegidas = array_map('intval', (array) $request->input('mesas', []));
        $tarjetas = $request->input('tipo') === 'mesas'
            ? $this->mesas($suc)->when($elegidas, fn ($m) => $m->whereIn('mes_id', $elegidas))
                ->map(fn ($m) => ['titulo' => $m->mes_nom, 'sub' => $m->pis_nom, 'qr' => Carta::qr(Carta::url($suc, $m->mes_id), 300)])->values()
            : collect([['titulo' => null, 'sub' => null, 'qr' => Carta::qr(Carta::url($suc), 420)]]);

        return view('empresas.carta.imprimir', $this->datos($negocio) + [
            'tarjetas' => $tarjetas,
            'tipo' => $request->input('tipo') === 'mesas' ? 'mesas' : 'general',
        ]);
    }

    private function negocioDelAdmin(): EmpresaNegocio
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador configura la carta digital.');

        return EmpresaNegocio::findOrFail(Auth::user()->id_empresa_negocio);
    }

    private function mesas(int $sucursal): Collection
    {
        return DB::table('mesas as m')->leftJoin('pisos as p', 'p.pis_id', '=', 'm.pis_id')
            ->where('m.id_empresa_negocio', $sucursal)->orderBy('m.pis_id')->orderBy('m.mes_id')
            ->get(['m.mes_id', 'm.mes_nom', 'p.pis_nom']);
    }

    /**
     * @return array{negocio: EmpresaNegocio, empresa: ?Empresa, nombre: string, logo: ?string, color: string}
     */
    private function datos(EmpresaNegocio $negocio): array
    {
        $empresa = Empresa::find($negocio->IdEmpresa);

        return [
            'negocio' => $negocio,
            'empresa' => $empresa,
            'nombre' => $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Nuestra carta'),
            'logo' => collect([$negocio->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'color' => Carta::color($negocio),
        ];
    }
}
