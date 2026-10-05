<?php
namespace App\View\Composers;

use App\Support\Sunat\SunatService;
use Illuminate\View\View;
use Illuminate\Support\Facades\{Auth, DB};

class MenuComposer
{
    public function compose(View $view): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $view->with('menu', $user->modulos()->orderBy('mod_id')->get()->groupBy('mod_gen'));
            $view->with('usuario', $user);
            $view->with('notifSunat', $user->esAdminOCaja() ? $this->pendientesSunat($user) : null);
        }
    }

    /** Comprobantes electrónicos que aún no tienen respuesta final de SUNAT (para la campanita) */
    private function pendientesSunat($user): array
    {
        $base = DB::table('cpe_cabecera')
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->whereIn('tdocod', SunatService::TIPOS_ELECTRONICOS)
            ->whereIn('est_sunat', array_merge(SunatService::ESTADOS_REENVIABLES, ['EN RESUMEN']))
            ->whereNull('ccabaj');

        $total = (clone $base)->count();

        $items = (clone $base)
            ->orderBy('ccafem')->orderBy('IdCpe_cabecera')
            ->limit(8)
            ->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccaitv', 'est_sunat']);

        // Boletas cerca del límite de 7 días para el resumen
        $vencidas = (clone $base)->where('ccafem', '<=', now()->subDays(5)->toDateString())->count();

        return compact('total', 'items', 'vencidas');
    }
}
