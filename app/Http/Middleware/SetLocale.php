<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si l'utilisateur est connecté et a une langue préférée
        if (Auth::check() && Auth::user()->preferred_language) {
            $locale = Auth::user()->preferred_language;
        } else {
            // Sinon, utiliser la langue par défaut du navigateur ou français
            $locale = $request->getPreferredLanguage(array_keys(Config::get('languages.supported', []))) ?? 'fr';
        }

        // Définir la locale Laravel
        App::setLocale($locale);

        return $next($request);
    }
}
