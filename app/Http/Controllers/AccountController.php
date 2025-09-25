<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function showDelete(Request $request)
    {
        return view('account.delete');
    }

    public function delete(Request $request)
    {
        $user = $request->user();

        Auth::logout();

        // TODO: si besoin, supprimer ici les données liées au user (scores, achats, tokens, etc.)

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Votre compte a été supprimé avec succès.');
    }
}
