<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Estudio;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireSolicitudes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bandeja SIRE del estudio contable: trae las propuestas de ventas y compras de todas sus empresas de una vez. La
 * pantalla pide una a una (sin saturar a SUNAT) y luego revisa los tickets por turnos: el que sigue en proceso se deja
 * y se pasa al siguiente. No cambia la empresa en la que trabaja el usuario.
 */
class SireBandejaController extends Controller
{
    private const LIBROS = ['ventas' => Sire::VENTAS, 'compras' => Sire::COMPRAS];

    private function autorizar(): void
    {
        abort_unless(Estudio::activo(), 404);
        $u = Auth::user();
        abort_unless($u->esAdmin() || $u->contadorCon('/sire/ventas', '/sire/compras') || $u->tieneModulo('/sire/ventas') || $u->tieneModulo('/sire/compras'),
            403, 'No tienes acceso al SIRE.');
    }

    /** Una empresa que el usuario puede trabajar (nunca otra, aunque se cambie el RUC en la dirección) */
    private function empresa(string $ruc): Empresa
    {
        abort_unless(Estudio::puede(Auth::user(), $ruc), 403, 'No tienes acceso a esa empresa.');

        return Empresa::findOrFail($ruc);
    }

    private function periodo(Request $request): string
    {
        return preg_match('/^\d{6}$/', (string) $request->input('periodo')) ? $request->input('periodo') : now()->subMonth()->format('Ym');
    }

    /** Cómo va el pedido de una propuesta: nada | proceso | listo | error */
    public static function estado(?object $s): array
    {
        if (! $s) {
            return ['estado' => 'nada'];
        }
        if ($s->archivo) {
            return ['estado' => 'listo', 'filas' => (int) $s->filas, 'cuando' => (string) $s->updated_at];
        }
        if ($s->cod_estado === '03' || $s->mensaje) {
            return ['estado' => 'error', 'mensaje' => $s->mensaje ?: 'SUNAT procesó el pedido con errores: '.$s->des_estado];
        }
        // Un pedido de hace horas que nunca terminó se vuelve a pedir (no se revisa para siempre)
        if (now()->diffInMinutes($s->created_at, true) > 120) {
            return ['estado' => 'nada', 'descripcion' => 'El pedido anterior no terminó: se pedirá de nuevo'];
        }

        return ['estado' => 'proceso', 'descripcion' => $s->des_estado ?: 'Pedido enviado', 'desde' => (string) $s->created_at];
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $periodo = $this->periodo($request);
        $lista = Estudio::empresas(Auth::user());
        $empresas = Empresa::whereIn('IdEmpresa', $lista->pluck('IdEmpresa'))->get()->keyBy('IdEmpresa');
        $filas = [];
        foreach ($lista as $e) {
            $empresa = $empresas[$e->IdEmpresa] ?? null;
            foreach (self::LIBROS as $libro => $cod) {
                $filas[] = ['ruc' => $e->IdEmpresa, 'nombre' => $e->NomEmpresa, 'propia' => (bool) $e->propia, 'libro' => $libro,
                    'configurado' => $empresa && Sire::configurado($empresa)] + self::estado(SireSolicitudes::ultima($e->IdEmpresa, $cod, $periodo));
            }
        }

        return view('empresas.sire.bandeja', ['periodo' => $periodo, 'filas' => $filas]);
    }

    /** Pide a SUNAT la propuesta de una empresa (genera su ticket) */
    public function pedir(Request $request, string $ruc, string $libro): JsonResponse
    {
        $this->autorizar();
        $empresa = $this->empresa($ruc);
        $periodo = $this->periodo($request);
        if (! Sire::configurado($empresa)) {
            return response()->json(['estado' => 'error', 'mensaje' => 'Faltan sus credenciales del SIRE (Empresas del estudio → Configurar).']);
        }

        return response()->json(SireSolicitudes::pedir($empresa, self::LIBROS[$libro], $periodo));
    }

    /** Revisa una vez el último ticket de esa empresa; si SUNAT terminó, se descarga */
    public function revisar(Request $request, string $ruc, string $libro): JsonResponse
    {
        $this->autorizar();
        $empresa = $this->empresa($ruc);
        $s = SireSolicitudes::ultima($ruc, self::LIBROS[$libro], $this->periodo($request));
        if (! $s) {
            return response()->json(['estado' => 'nada']);
        }

        return response()->json(SireSolicitudes::revisar($empresa, $s));
    }
}
