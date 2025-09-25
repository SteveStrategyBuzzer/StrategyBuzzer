<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BadgesController extends Controller
{
    public function index()
    {
        return view('badges');
    }
}
