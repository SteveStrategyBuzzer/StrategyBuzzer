<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class DuoController extends Controller
{
    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $invitation = Invitation::create([
            'user_id' => Auth::id(),
            'email' => $request->email,
            'token' => Str::random(32),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', "Invitation envoyée à {$request->email}");
    }

    public function random()
    {
        return view('duo-random');
    }
}
