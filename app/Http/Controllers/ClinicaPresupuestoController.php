<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Support\Clinica;
use App\Support\ClinicaFirmas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Presupuesto o plan de tratamiento del paciente: los procedimientos (por pieza) con su precio, descuento y validez.
 * Se imprime, el paciente lo firma (en la pantalla o en su celular) y al aceptarlo pasa al registro de tratamientos.
 */
class ClinicaPresupuestoController extends Controller
{
    private const REGLAS = [
        'items' => 'required|array|min:1|max:60',
        'items.*.pieza' => 'nullable|string|max:20',
        'items.*.descripcion' => 'required|string|max:200',
        'items.*.cantidad' => 'required|numeric|min:0.01|max:999',
        'items.*.precio' => 'required|numeric|min:0|max:999999',
        'descuento' => 'nullable|numeric|min:0|max:999999',
        'validez_dias' => 'nullable|integer|min:1|max:365',
        'observaciones' => 'nullable|string|max:2000',
    ];

    private const NOMBRES = ['items' => 'procedimientos', 'items.*.descripcion' => 'procedimiento', 'items.*.precio' => 'precio', 'items.*.cantidad' => 'cantidad'];

    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function usa(): void
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
    }

    private function presupuesto(int $id): object
    {
        $p = DB::table('clinica_presupuestos')->where('pre_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($p, 404);

        return $p;
    }

    private function json(callable $f): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) DB::transaction($f));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Líneas limpias y montos: subtotal, descuento (no más que el subtotal) y total */
    private static function montos(array $d): array
    {
        $items = array_values(array_map(fn ($i) => ['pieza' => trim((string) ($i['pieza'] ?? '')), 'descripcion' => mb_strtoupper(trim($i['descripcion'])),
            'cantidad' => round((float) $i['cantidad'], 2), 'precio' => round((float) $i['precio'], 2)], $d['items']));
        $subtotal = round(array_sum(array_map(fn ($i) => $i['cantidad'] * $i['precio'], $items)), 2);
        $descuento = min($subtotal, round((float) ($d['descuento'] ?? 0), 2));

        return ['items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'subtotal' => $subtotal, 'descuento' => $descuento, 'total' => round($subtotal - $descuento, 2),
            'validez_dias' => (int) ($d['validez_dias'] ?? 30), 'observaciones' => trim((string) ($d['observaciones'] ?? '')) ?: null];
    }

    public function lista(int $hisId): JsonResponse
    {
        $this->usa();
        abort_unless(DB::table('historia_clinica')->where('his_cli_id', $hisId)->where('id_empresa_negocio', $this->suc())->exists(), 404);

        return response()->json(DB::table('clinica_presupuestos')->where('his_cli_id', $hisId)->orderByDesc('pre_id')->get()->map(function ($p) {
            $p->items = json_decode((string) $p->items, true) ?: [];
            $p->firma_url = $p->firma ? route('clinica.firma.imagen', ['presupuesto', $p->pre_id]) : null;
            $p->vence = Carbon::parse($p->fecha)->addDays((int) $p->validez_dias)->toDateString();

            return $p;
        }));
    }

    public function guardar(Request $request, int $hisId): JsonResponse
    {
        $this->usa();
        abort_unless(DB::table('historia_clinica')->where('his_cli_id', $hisId)->where('id_empresa_negocio', $this->suc())->exists(), 404);
        $d = $request->validate(self::REGLAS, [], self::NOMBRES);

        return $this->json(function () use ($hisId, $d) {
            $user = Auth::user();
            $numero = (int) DB::table('clinica_presupuestos')->where('id_empresa_negocio', $this->suc())->lockForUpdate()->max('numero') + 1;
            $id = DB::table('clinica_presupuestos')->insertGetId(self::montos($d) + [
                'his_cli_id' => $hisId, 'numero' => $numero, 'fecha' => now()->toDateString(), 'estado' => 'PENDIENTE',
                'doctor' => Clinica::esDoctor($user) ? $user->IdUsuario : null, 'IdUsuario' => $user->IdUsuario,
                'id_empresa_negocio' => $this->suc(), 'creado' => now(),
            ]);

            return ['mensaje' => 'Presupuesto N° '.str_pad((string) $numero, 5, '0', STR_PAD_LEFT).' guardado.', 'pre_id' => $id];
        });
    }

    /** Corregir un presupuesto pendiente (si ya estaba firmado, la firma se borra: el paciente firma lo nuevo) */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $p = $this->presupuesto($id);
        $d = $request->validate(self::REGLAS, [], self::NOMBRES);

        return $this->json(function () use ($p, $d) {
            if ($p->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Solo se corrige un presupuesto pendiente.');
            }
            DB::table('clinica_presupuestos')->where('pre_id', $p->pre_id)->update(self::montos($d) + ['firma' => null, 'firmado' => null]);

            return ['mensaje' => 'Presupuesto actualizado.'.($p->firma ? ' El paciente debe volver a firmarlo.' : '')];
        });
    }

    /**
     * Aceptado: pasa al registro de tratamientos (costo total, se paga con adelantos; si es ortodoncia se cambia a mensual con ✏️).
     * Rechazado o anulado: queda en la historia como referencia.
     */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $p = $this->presupuesto($id);
        $d = $request->validate(['estado' => 'required|in:ACEPTADO,RECHAZADO,PENDIENTE']);

        return $this->json(function () use ($p, $d) {
            if ($p->tra_id && $d['estado'] !== 'ACEPTADO') {
                throw new \RuntimeException('Este presupuesto ya pasó al registro de tratamientos.');
            }
            $cambios = ['estado' => $d['estado']];
            $mensaje = $d['estado'] === 'RECHAZADO' ? 'Presupuesto marcado como no aceptado.' : 'Presupuesto pendiente.';
            if ($d['estado'] === 'ACEPTADO' && ! $p->tra_id) {
                $items = json_decode((string) $p->items, true) ?: [];
                $descripcion = count($items) === 1 ? trim(($items[0]['pieza'] ? 'PIEZA '.$items[0]['pieza'].' ' : '').$items[0]['descripcion'])
                    : 'PRESUPUESTO N° '.str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT).': '.collect($items)->pluck('descripcion')->unique()->implode(', ');
                $user = Auth::user();
                $cambios['tra_id'] = DB::table('clinica_tratamientos')->insertGetId([
                    'his_cli_id' => $p->his_cli_id, 'descripcion' => mb_substr($descripcion, 0, 200), 'costo' => $p->total, 'estado' => 'EN CURSO',
                    'modalidad' => 'UNICO', 'cuota_inicial' => 0, 'cuotas' => 0, 'cuota' => 0,
                    'doctor' => $p->doctor ?: (Clinica::esDoctor($user) ? $user->IdUsuario : null), 'IdUsuario' => $user->IdUsuario,
                    'id_empresa_negocio' => $p->id_empresa_negocio, 'creado' => now(),
                ]);
                $mensaje = 'Presupuesto aceptado: ya está en el registro de tratamientos por '.number_format((float) $p->total, 2).'.';
            }
            DB::table('clinica_presupuestos')->where('pre_id', $p->pre_id)->update($cambios);

            return ['mensaje' => $mensaje];
        });
    }

    public function imprimir(int $id): View
    {
        $this->usa();
        $p = $this->presupuesto($id);
        $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')->where('h.his_cli_id', $p->his_cli_id)
            ->first(['h.*', 'c.clinom', 'c.clinum', 'c.telefono']);
        $n = EmpresaNegocio::find($this->suc());
        $e = DB::table('empresa')->where('IdEmpresa', $n->IdEmpresa)->first();
        $n->logo = collect([$n->logo_suc, $e->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l)));
        $n->razon = $e->NomEmpresa ?? '';
        $doctor = $p->doctor ? DB::table('users')->where('IdUsuario', $p->doctor)->first() : null;

        return view('empresas.clinica.presupuesto_imprimir', [
            'p' => $p, 'items' => json_decode((string) $p->items, true) ?: [], 'h' => $h, 'negocio' => $n,
            'doctor' => $doctor ? trim(($doctor->apeusu ?: $doctor->name)) : null, 'firma' => ClinicaFirmas::imagen($p->firma),
        ]);
    }
}
