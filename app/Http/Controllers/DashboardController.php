<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $usuario = Auth::user();
        $menu = $usuario->modulos()->get()->groupBy('mod_gen');

        return view('dashboard', compact('menu', 'usuario'));
    }
}