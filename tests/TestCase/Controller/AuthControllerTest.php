<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\AuthController Test Case
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Bookings',
    ];

    /**
     * Der OIDC-Block steht in config/app_local.php, und die Datei ist nicht im
     * Repository. Auf dem CI-Runner existiert sie nicht — die Tests liefen dort
     * in einen 500 ("Missing OIDC provider configuration"), waehrend sie lokal
     * gruen waren, weil sie die echten Entra-Werte des Entwicklungsrechners
     * benutzten. Die Konfiguration bringen sie deshalb selbst mit.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('Oidc.provider', 'entra');
        Configure::write('Oidc.providers.entra', [
            'label' => 'Microsoft Entra ID',
            'tenantId' => self::TEST_TENANT_ID,
            'clientId' => 'entra-client',
            'clientSecret' => 'entra-secret',
            'redirectUri' => 'http://localhost:8765/auth/callback',
            'scope' => 'openid profile email',
        ]);
        // Google leitet seine URLs nicht aus einem Issuer ab, sie stehen sonst
        // vollstaendig in app_local.php.
        Configure::write('Oidc.providers.google', [
            'label' => 'Google',
            'issuer' => 'https://accounts.google.com',
            'redirectUri' => 'http://localhost:8765/auth/callback',
            'scope' => 'openid profile email',
            'authorizeUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tokenUrl' => 'https://oauth2.googleapis.com/token',
            'keysUrl' => 'https://www.googleapis.com/oauth2/v3/certs',
        ]);
    }

    private const TEST_TENANT_ID = '11111111-2222-3333-4444-555555555555';

    public function testLoginRedirectsToMicrosoftAuthorizeEndpoint(): void
    {
        $this->get('/auth/login');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/' . self::TEST_TENANT_ID . '/oauth2/v2.0/authorize?',
            $location,
        );
        $this->assertStringContainsString('client_id=entra-client', $location);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%3A8765%2Fauth%2Fcallback', $location);
        $this->assertStringContainsString('scope=openid+profile+email', $location);
    }

    public function testLoginCanUseKeycloakProvider(): void
    {
        Configure::write('Oidc.provider', 'keycloak');
        Configure::write('Oidc.providers.keycloak', [
            'issuer' => 'https://keycloak.example.test/realms/mytime',
            'clientId' => 'keycloak-client',
            'clientSecret' => 'keycloak-secret',
            'redirectUri' => 'http://localhost:8765/auth/callback',
            'scope' => 'openid profile email',
        ]);

        $this->get('/auth/login');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringStartsWith(
            'https://keycloak.example.test/realms/mytime/protocol/openid-connect/auth?',
            $location,
        );
        $this->assertStringContainsString('client_id=keycloak-client', $location);
    }

    public function testLoginCanUseGoogleProvider(): void
    {
        Configure::write('Oidc.provider', 'google');
        Configure::write('Oidc.providers.google.clientId', 'google-client');
        Configure::write('Oidc.providers.google.clientSecret', 'google-secret');
        Configure::write('Oidc.providers.google.redirectUri', 'http://localhost:8765/auth/callback');

        $this->get('/auth/login');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringStartsWith(
            'https://accounts.google.com/o/oauth2/v2/auth?',
            $location,
        );
        $this->assertStringContainsString('client_id=google-client', $location);
    }

    public function testCallbackRejectsInvalidState(): void
    {
        $this->get('/auth/callback?state=bad&code=abc');

        $this->assertResponseCode(400);
        $this->assertResponseContains('Invalid login state.');
    }

    public function testUnauthenticatedRequestsRedirectToLogin(): void
    {
        Configure::write('Auth.disabled', false);

        $this->get('/bookings');

        $this->assertRedirect(['controller' => 'Auth', 'action' => 'login']);
        Configure::write('Auth.disabled', true);
    }
}
