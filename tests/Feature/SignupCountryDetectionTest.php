<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * #119 — À l'inscription/au premier login, le pays est enregistré dans
 * profile_settings['country'] DEPUIS LE NAVIGATEUR (en-tête Accept-Language),
 * et JAMAIS depuis l'IP.
 *
 * Couvre les 3 flux : inscription e-mail, Google, Facebook.
 * sqlite in-memory uniquement (RefreshDatabase), aucune base réelle.
 */
class SignupCountryDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inscription_email_enregistre_le_pays_depuis_le_navigateur(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-CA,fr;q=0.9'])
            ->post(route('email.register.submit'), [
                'name'                  => 'Joueur Test',
                'email'                 => 'email-ca@example.com',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $user = User::where('email', 'email-ca@example.com')->first();

        $this->assertNotNull($user, 'Le compte e-mail aurait dû être créé.');
        $this->assertSame('CA', data_get($user->profile_settings, 'country'));
        $this->assertSame('CA', $user->country);
    }

    public function test_inscription_email_sans_region_ne_met_aucun_pays(): void
    {
        // Langue sans région -> aucun pays deviné, et surtout aucun repli IP.
        $this->withHeaders(['Accept-Language' => 'fr'])
            ->post(route('email.register.submit'), [
                'name'                  => 'Joueur Sans Region',
                'email'                 => 'email-noregion@example.com',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $user = User::where('email', 'email-noregion@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull(data_get($user->profile_settings, 'country'));
        $this->assertNull($user->country);
    }

    public function test_connexion_google_enregistre_le_pays_depuis_le_navigateur(): void
    {
        $this->mockSocialite('google', 'google-123', 'google-user@example.com', 'Google User');

        $this->withHeaders(['Accept-Language' => 'pt-BR,pt;q=0.9'])
            ->get(route('google.callback'));

        $user = User::where('email', 'google-user@example.com')->first();

        $this->assertNotNull($user, 'Le compte Google aurait dû être créé.');
        $this->assertSame('BR', data_get($user->profile_settings, 'country'));
        $this->assertSame('BR', $user->country);
    }

    public function test_connexion_facebook_enregistre_le_pays_depuis_le_navigateur(): void
    {
        $this->mockSocialite('facebook', 'fb-456', 'fb-user@example.com', 'FB User');

        $this->withHeaders(['Accept-Language' => 'es-MX,es;q=0.9'])
            ->get(route('facebook.callback'));

        $user = User::where('email', 'fb-user@example.com')->first();

        $this->assertNotNull($user, 'Le compte Facebook aurait dû être créé.');
        $this->assertSame('MX', data_get($user->profile_settings, 'country'));
        $this->assertSame('MX', $user->country);
    }

    private function mockSocialite(string $driver, string $id, string $email, string $name): void
    {
        $socialUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $socialUser->shouldReceive('getId')->andReturn($id);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn($name);
        $socialUser->shouldReceive('getNickname')->andReturn($name);
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
    }
}
