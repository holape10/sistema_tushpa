<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Support\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedioPagoController extends Controller
{
    /** Quien tiene la opción en su menú, y solo los medios de pago de su sucursal (nunca los de otra cambiando el número en la dirección) */
    private function autorizar(?MedioPago $medioPago = null): void
    {
        abort_unless(Auth::user()->tieneModulo('/mediospagos'), 403, 'No tienes permiso para configurar los medios de pago.');
        abort_unless($medioPago === null || (int) $medioPago->id_empresa_negocio === (int) Auth::user()->id_empresa_negocio, 404);
    }

    /** Carpeta pública de los QR de cobro (se muestran al cliente en la caja) */
    private const CARPETA_QR = 'imagenes/qr';

    private const REGLA_QR = 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096';

    /** Guarda la imagen del QR (con un nombre propio, nunca el del navegador) y borra la anterior */
    private function guardarQr(Request $request, MedioPago $medio): void
    {
        $anterior = $medio->qr;
        if ($request->hasFile('qr')) {
            $dir = public_path(self::CARPETA_QR);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $nombre = preg_replace('/\D/', '', (string) $medio->IdEmpresa).'_'.$medio->id_med_pag.'_'.time().'.'.($request->file('qr')->guessExtension() ?: 'png');
            $request->file('qr')->move($dir, $nombre);
            $medio->update(['qr' => self::CARPETA_QR.'/'.$nombre]);
        } elseif ($request->boolean('quitar_qr')) {
            $medio->update(['qr' => null]);
        }
        if ($anterior && $anterior !== $medio->qr && str_starts_with($anterior, self::CARPETA_QR.'/') && is_file(public_path($anterior))) {
            @unlink(public_path($anterior));
        }
    }

    public function index()
    {
        $this->autorizar();
        $mediosPagos = MedioPago::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();

        return view('empresas.mediospagos.index', compact('mediosPagos'));
    }

    public function create()
    {
        $this->autorizar();

        return view('empresas.mediospagos.create');
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $request->validate(['nom_med_pag' => 'required|string|max:255', 'qr' => self::REGLA_QR], [], ['nom_med_pag' => 'Nombre', 'qr' => 'QR']);

        $nuevo = MedioPago::create([
            'nom_med_pag' => $request->nom_med_pag,
            'tipo_medio' => $request->tipo_medio,
            'predeterminado' => $request->predeterminado ? '1' : '0',
            'IdEmpresa' => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        $this->guardarQr($request, $nuevo);
        Bitacora::registrar('config.medio_pago', 'Creó el medio de pago '.$nuevo->nom_med_pag, null, ['referencia' => ['medio_pago', $nuevo->id_med_pag]]);

        return redirect()->route('mediospagos.index')->with('success', 'Medio de pago creado.');
    }

    public function edit(MedioPago $medioPago)
    {
        $this->autorizar($medioPago);

        return view('empresas.mediospagos.edit', compact('medioPago'));
    }

    public function update(Request $request, MedioPago $medioPago)
    {
        $this->autorizar($medioPago);
        $request->validate(['nom_med_pag' => 'required|string|max:255', 'qr' => self::REGLA_QR], [], ['nom_med_pag' => 'Nombre', 'qr' => 'QR']);
        $antes = $medioPago->only(['nom_med_pag', 'tipo_medio', 'qr']);
        $medioPago->update($request->only(['nom_med_pag', 'tipo_medio']));
        $this->guardarQr($request, $medioPago);
        Bitacora::siCambio('config.medio_pago', 'Editó el medio de pago '.$medioPago->nom_med_pag, $antes, $medioPago->only(['nom_med_pag', 'tipo_medio', 'qr']),
            ['nom_med_pag' => 'Nombre', 'tipo_medio' => 'Tipo', 'qr' => 'QR de cobro'], ['referencia' => ['medio_pago', $medioPago->id_med_pag]]);

        return redirect()->route('mediospagos.index')->with('success', 'Medio de pago actualizado.');
    }

    public function destroy(MedioPago $medioPago)
    {
        $this->autorizar($medioPago);
        Bitacora::registrar('config.medio_pago', 'Eliminó el medio de pago '.$medioPago->nom_med_pag, null,
            ['referencia' => ['medio_pago', $medioPago->id_med_pag], 'alerta' => true]);
        $medioPago->delete();

        return back()->with('success', 'Medio de pago eliminado.');
    }
}
