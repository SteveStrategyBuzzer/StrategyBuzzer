<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    // DEBUG : enregistrer le profile et la session

    /**
     * Redirection vers Google
     */
public function redirectToGoogle()
{
    return Socialite::driver('google')
        ->scopes([
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ])
        ->redirect();
}

    /**
     * Callback Google
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            Log::info("Google User récupéré", [
                "email" => $googleUser->getEmail(),
                "name" => $googleUser->getName(),
                "id" => $googleUser->getId(),
                "avatar" => $googleUser->getAvatar(),
            ]);

            Log::info("Session après login :", session()->all());

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName() ?? $googleUser->getNickname(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]
            );

            Auth::login($user);

            return redirect('/menu')->with('success', 'Connecté avec Google !');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la connexion Google", ['exception' => $e]);
            return redirect('/connexion')->withErrors(['google_error' => 'Erreur lors de la connexion Google']);
        }
    }

    /**
     * Redirection vers Facebook
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')
            ->scopes(['email', 'public_profile'])
            ->redirect();
    }

    /**
     * Callback Facebook
     */
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            Log::info("Facebook User récupéré", [
                "email" => $facebookUser->getEmail(),
                "name" => $facebookUser->getName(),
                "id" => $facebookUser->getId(),
                "avatar" => $facebookUser->getAvatar(),
            ]);

            Log::info("Session après login :", session()->all());

            $user = User::updateOrCreate(
                ['email' => $facebookUser->getEmail()],
                [
                    'name' => $facebookUser->getName() ?? $facebookUser->getNickname(),
                    'facebook_id' => $facebookUser->getId(),
                    'avatar' => $facebookUser->getAvatar(),
                ]
            );

            Auth::login($user);

            return redirect('/menu')->with('success', 'Connecté avec Facebook !');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la connexion Facebook", ['exception' => $e]);
            return redirect('/connexion')->withErrors(['facebook_error' => 'Erreur lors de la connexion Facebook']);
        }
    }
}
