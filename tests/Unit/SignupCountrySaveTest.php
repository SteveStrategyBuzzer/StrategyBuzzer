<?php

namespace Tests\Unit;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #119 — Preuve, SANS base de données, que l'inscription enregistre le pays
 * dans profile_settings['country'] DEPUIS LE NAVIGATEUR (Accept-Language).
 *
 * Les 3 flux d'inscription (Google, Facebook, e-mail) délèguent tous à la
 * méthode partagée AuthController::saveInitialCountry(). On teste donc :
 *   1. saveInitialCountry() écrit bien le pays du navigateur (fr-CA -> CA).
 *   2. Il préserve les autres clés de profile_settings.
 *   3. Sans région (ex. "fr"), il n'écrit aucun pays ET n'appelle pas save()
 *      (aucun repli IP).
 *   4. Les 3 flux appellent réellement saveInitialCountry() (preuve statique).
 *
 * Aucune DB, aucune migration, aucun Neon : User::save() est neutralisé.
 */
class SignupCountrySaveTest extends TestCase
{
    private function makeRequest(?string $acceptLanguage): Request
    {
        $request = Request::create('/', 'GET');

        if ($acceptLanguage === null) {
            $request->headers->remove('Accept-Language');
            $request->server->remove('HTTP_ACCEPT_LANGUAGE');
        } else {
            $request->headers->set('Accept-Language', $acceptLanguage);
            $request->server->set('HTTP_ACCEPT_LANGUAGE', $acceptLanguage);
        }

        return $request;
    }

    private function callSaveInitialCountry(User $user, Request $request): void
    {
        $method = new ReflectionMethod(AuthController::class, 'saveInitialCountry');
        $method->setAccessible(true);
        $method->invoke(new AuthController(), $user, $request);
    }

    public function test_ecrit_le_pays_du_navigateur(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturnTrue();
        $user->profile_settings = null;

        $this->callSaveInitialCountry($user, $this->makeRequest('fr-CA,fr;q=0.9'));

        $this->assertSame('CA', data_get($user->profile_settings, 'country'));
    }

    public function test_preserve_les_settings_existants(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturnTrue();
        $user->profile_settings = ['language' => 'fr'];

        $this->callSaveInitialCountry($user, $this->makeRequest('es-MX,es;q=0.9'));

        $this->assertSame('MX', data_get($user->profile_settings, 'country'));
        $this->assertSame('fr', data_get($user->profile_settings, 'language'));
    }

    public function test_sans_region_n_ecrit_aucun_pays_et_ne_save_pas(): void
    {
        // Langue sans région -> aucun pays deviné, aucun repli IP, pas de save().
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->never();
        $user->profile_settings = null;

        $this->callSaveInitialCountry($user, $this->makeRequest('fr'));

        $this->assertNull(data_get($user->profile_settings, 'country'));
    }

    public function test_les_trois_flux_inscription_utilisent_la_methode_partagee(): void
    {
        $reflection = new ReflectionClass(AuthController::class);
        $file = $reflection->getFileName();
        $lines = file($file);

        foreach (['handleGoogleCallback', 'handleFacebookCallback', 'handleEmailRegister'] as $flow) {
            $method = $reflection->getMethod($flow);
            $body = implode('', array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1
            ));

            $this->assertStringContainsString(
                '$this->saveInitialCountry(',
                $body,
                "Le flux d'inscription {$flow}() doit appeler la méthode partagée saveInitialCountry()."
            );
        }
    }
}
