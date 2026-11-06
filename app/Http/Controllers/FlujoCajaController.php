<?php

namespace App\Http\Controllers;

use App\Support\Bitacora;
use App\Support\Excel;
use App\Support\FlujoCaja;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Flujo de caja proyectado: lo que se va a cobrar y pagar en las próximas semanas, y con cuánto se queda la sucursal */
class FlujoCajaController extends Controller
{
    public function index(Request $request): View
    {
        $this->autorizar();
        [$semanas, $estimados] = $this->opciones($request);

        return view('empresas.flujo_caja.index', [
            'flujo' => FlujoCaja::proyeccion((int) Auth::user()->id_empresa_negocio, $semanas, $estimados),
            'nSemanas' => $semanas, 'estimados' => $estimados,
        ]);
    }

    public function saldo(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['saldo' => 'required|numeric|min:-99999999|max:999999999'], [], ['saldo' => 'saldo de hoy']);
        FlujoCaja::guardarSaldo((int) Auth::user()->id_empresa_negocio, (float) $d['saldo'], Auth::id());
        Bitacora::registrar('cuenta.flujo_saldo', 'Puso el saldo de hoy del flujo de caja en S/ '.number_format((float) $d['saldo'], 2));

        return back()->with('success', 'Saldo de hoy guardado: el flujo parte de S/ '.number_format((float) $d['saldo'], 2).'.');
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $this->autorizar();
        [$semanas, $estimados] = $this->opciones($request);
        $flujo = FlujoCaja::proyeccion((int) Auth::user()->id_empresa_negocio, $semanas, $estimados);
        $fecha = fn ($f) => Carbon::parse($f)->format('d/m/Y');

        $conceptos = array_keys(($flujo['semanas'][0]['entradas'] ?? []) + ($flujo['semanas'][0]['salidas'] ?? []));
        $resumen = [];
        foreach ($flujo['semanas'] as $s) {
            $resumen[] = array_merge([$fecha($s['desde']).' al '.$fecha($s['hasta'])],
                array_map(fn ($c) => $s['entradas'][$c] ?? $s['salidas'][$c] ?? 0, $conceptos), [$s['entra'], $s['sale'], $s['neto'], $s['saldo']]);
        }
        $detalle = [];
        foreach (array_merge($flujo['atrasado']['detalle'], ...array_column($flujo['semanas'], 'detalle')) as $d) {
            $detalle[] = [$fecha($d['fecha']), self::TIPOS[$d['tipo']], $d['documento'], $d['nombre'], in_array($d['tipo'], self::SALEN, true) ? -$d['monto'] : $d['monto'],
                $d['fecha'] < now()->toDateString() ? 'Vencido' : ''];
        }

        $ruta = (new Excel)
            ->hoja('Flujo por semana', array_merge(['Semana'], $conceptos, ['Entra', 'Sale', 'Neto', 'Saldo']), $resumen)
            ->hoja('Detalle', ['Fecha', 'Tipo', 'Documento', 'Cliente / proveedor', 'Monto S/', 'Estado'], $detalle)
            ->guardar();

        return response()->download($ruta, 'flujo_de_caja_'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public const TIPOS = ['cobrar' => 'Por cobrar', 'prestamo' => 'Cuota de préstamo', 'pagar' => 'Por pagar', 'orden' => 'Orden de compra aprobada'];

    /** Lo que sale de caja (se muestra en negativo) */
    public const SALEN = ['pagar', 'orden'];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/flujo-caja'), 403, 'No tienes permiso para el flujo de caja.');
    }

    /** @return array{0: int, 1: bool} */
    private function opciones(Request $request): array
    {
        return [max(1, min(FlujoCaja::MAX_SEMANAS, (int) $request->query('semanas', 8))), $request->query('estimados', '1') !== '0'];
    }
}
