<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AchatsController extends Controller
{
    public function index()
    {
        return view('achats');
    }
}
