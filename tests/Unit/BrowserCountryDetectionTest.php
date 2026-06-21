<?php

namespace Tests\Unit;

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #119 — Détection du PAYS depuis le navigateur (Accept-Language), pas l'IP.
 *
 * Verrouille le comportement de AuthController::detectBrowserCountry() :
 *   - fr-CA  -> CA
 *   - en-US  -> US
 *   - fr     -> null (langue sans région : aucune supposition, pas d'IP)
 *   - header vide -> null
 *
 * Ne touche jamais la base : test pur (réflexion sur une méthode privée),
 * exécuté en sqlite in-memory comme tout le reste de la suite.
 */
class BrowserCountryDetectionTest extends TestCase
{
    private function detect(?string $acceptLanguage): ?string
    {
        $request = Request::create('/', 'GET');

        if ($acceptLanguage === null) {
            // Symfony Request::create() injecte un Accept-Language par défaut :
            // on le retire pour simuler un navigateur SANS en-tête de langue.
            $request->headers->remove('Accept-Language');
            $request->server->remove('HTTP_ACCEPT_LANGUAGE');
        } else {
            $request->headers->set('Accept-Language', $acceptLanguage);
            $request->server->set('HTTP_ACCEPT_LANGUAGE', $acceptLanguage);
        }

        $method = new ReflectionMethod(AuthController::class, 'detectBrowserCountry');
        $method->setAccessible(true);

        return $method->invoke(new AuthController(), $request);
    }

    public function test_fr_ca_donne_ca(): void
    {
        $this->assertSame('CA', $this->detect('fr-CA,fr;q=0.9'));
    }

    public function test_en_us_donne_us(): void
    {
        $this->assertSame('US', $this->detect('en-US,en;q=0.9'));
    }

    public function test_pt_br_donne_br(): void
    {
        $this->assertSame('BR', $this->detect('pt-BR,pt;q=0.8'));
    }

    public function test_langue_sans_region_donne_null(): void
    {
        $this->assertNull($this->detect('fr'));
    }

    public function test_header_vide_donne_null(): void
    {
        $this->assertNull($this->detect(null));
    }
}
