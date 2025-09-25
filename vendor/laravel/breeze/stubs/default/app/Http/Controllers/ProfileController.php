<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileeUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileeController extends Controller
{
    /**
     * Display the user's profilee form.
     */
    public function edit(Request $request): View
    {
        return view('profilee.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profilee information.
     */
    public function update(ProfileeUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profilee.edit')->with('status', 'profilee-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
