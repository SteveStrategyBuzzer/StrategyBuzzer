<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Détecte la langue préférée depuis le header Accept-Language du navigateur.
     * Retourne un code de langue supporté ('fr', 'en', etc.) ou 'fr' par défaut.
     */
    private function detectBrowserLanguage(Request $request): string
    {
        $supported = array_keys(config('languages.supported', []));
        return $request->getPreferredLanguage($supported) ?? 'fr';
    }

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
    public function handleGoogleCallback(Request $request)
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

            // Sauvegarder la langue du navigateur uniquement pour les nouveaux comptes
            if ($user->wasRecentlyCreated) {
                $user->preferred_language = $this->detectBrowserLanguage($request);
                $user->save();
            }

            Auth::login($user);

            // Rediriger vers la page de profil si non complété
            if (!($user->profile_completed ?? false)) {
                return redirect()->route('profile.show')->with('info', 'Veuillez compléter votre profil avant de continuer.');
            }

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
    public function handleFacebookCallback(Request $request)
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

            // Sauvegarder la langue du navigateur uniquement pour les nouveaux comptes
            if ($user->wasRecentlyCreated) {
                $user->preferred_language = $this->detectBrowserLanguage($request);
                $user->save();
            }

            Auth::login($user);

            // Rediriger vers la page de profil si non complété
            if (!($user->profile_completed ?? false)) {
                return redirect()->route('profile.show')->with('info', 'Veuillez compléter votre profil avant de continuer.');
            }

            return redirect('/menu')->with('success', 'Connecté avec Facebook !');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la connexion Facebook", ['exception' => $e]);
            return redirect('/connexion')->withErrors(['facebook_error' => 'Erreur lors de la connexion Facebook']);
        }
    }

    public function showEmailLogin()
    {
        return view('auth.email-login');
    }

    public function handleEmailLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Vérifier si le profil est complété
            $user = Auth::user();
            if (!($user->profile_completed ?? false)) {
                return redirect()->route('profile.show')->with('info', 'Veuillez compléter votre profil avant de continuer.');
            }
            
            return redirect()->intended('/menu')->with('success', 'Connexion réussie !');
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('email');
    }

    public function showEmailRegister()
    {
        return view('auth.email-register');
    }

    public function handleEmailRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'coins' => 30,
            'competence_coins' => 250,
            'preferred_language' => $this->detectBrowserLanguage($request),
        ]);

        Auth::login($user);

        // Vérifier si le profil est complété (toujours false pour un nouveau compte)
        if (!($user->profile_completed ?? false)) {
            return redirect()->route('profile.show')->with('success', 'Compte créé avec succès ! Veuillez compléter votre profil.');
        }

        return redirect('/menu')->with('success', 'Compte créé avec succès !');
    }

    public function redirectToApple()
    {
        return redirect('/login')->with('info', 'La connexion Apple sera bientôt disponible !');
    }

    /**
     * Callback Apple (à implémenter)
     */
    public function handleAppleCallback()
    {
        try {
            // TODO: Implémenter la logique Apple quand disponible
            // $appleUser = Socialite::driver('apple')->stateless()->user();
            
            // $user = User::updateOrCreate(
            //     ['email' => $appleUser->getEmail()],
            //     [
            //         'name' => $appleUser->getName() ?? $appleUser->getNickname(),
            //         'apple_id' => $appleUser->getId(),
            //         'avatar' => $appleUser->getAvatar(),
            //     ]
            // );
            
            // Auth::login($user);
            
            // // IMPORTANT: Vérifier si le profil est complété
            // if (!($user->profile_completed ?? false)) {
            //     return redirect()->route('profile.show')->with('info', 'Veuillez compléter votre profil avant de continuer.');
            // }
            
            // return redirect('/menu')->with('success', 'Connecté avec Apple !');
            
            return redirect('/login')->with('info', 'La connexion Apple n\'est pas encore disponible.');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la connexion Apple", ['exception' => $e]);
            return redirect('/connexion')->withErrors(['apple_error' => 'Erreur lors de la connexion Apple']);
        }
    }

    public function showPhoneLogin()
    {
        return view('auth.phone-login');
    }

    /**
     * Traitement connexion par téléphone (à implémenter)
     */
    public function handlePhoneLogin(Request $request)
    {
        // TODO: Implémenter la logique de vérification SMS
        // $validated = $request->validate([
        //     'phone' => 'required|string',
        //     'code' => 'required|string',
        // ]);
        
        // Vérifier le code SMS et trouver/créer l'utilisateur
        // $user = User::updateOrCreate(
        //     ['phone' => $validated['phone']],
        //     [
        //         'coins' => 30,
        //         'competence_coins' => 250,
        //     ]
        // );
        
        // if ($user) {
        //     Auth::login($user);
        //     
        //     // IMPORTANT: Vérifier si le profil est complété
        //     if (!($user->profile_completed ?? false)) {
        //         return redirect()->route('profile.show')->with('info', 'Veuillez compléter votre profil avant de continuer.');
        //     }
        //     
        //     return redirect('/menu')->with('success', 'Connecté avec succès !');
        // }
        
        return back()->withErrors(['phone' => 'Numéro de téléphone invalide ou code incorrect.']);
    }
}
