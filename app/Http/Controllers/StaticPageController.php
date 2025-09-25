<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaticPageController extends Controller
{
    public function privacy()
    {
        return view('static.privacy');
    }

    public function dataDeletion()
    {
        return view('static.data-deletion');
    }
}
