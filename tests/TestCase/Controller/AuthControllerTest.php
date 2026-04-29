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

    public function testLoginRedirectsToMicrosoftAuthorizeEndpoint(): void
    {
        $this->get('/auth/login');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/33a356e1-74b6-4bc3-9ef5-dd68c5d83998/oauth2/v2.0/authorize?',
            $location
        );
        $this->assertStringContainsString('client_id=967fec6d-e828-4c8d-87b8-0a15421cb74d', $location);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%3A8765%2Fauth%2Fcallback', $location);
        $this->assertStringContainsString('scope=openid+profile+email', $location);
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
