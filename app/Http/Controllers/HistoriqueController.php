<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HistoriqueController extends Controller
{
    public function index()
    {
        return view('historique');
    }
}
