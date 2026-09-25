<?php
namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class MenuComposer
{
    public function compose(View $view): void
    {
        if (Auth::check()) {
            $view->with('menu', Auth::user()->modulos()->orderBy('mod_id')->get()->groupBy('mod_gen'));
            $view->with('usuario', Auth::user());
        }
    }
}