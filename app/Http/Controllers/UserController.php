<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Retourne la liste des utilisateurs en JSON.
     */
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }
}
