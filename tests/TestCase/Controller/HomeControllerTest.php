<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\HomeController Test Case
 *
 * @link \App\Controller\HomeController
 */
class HomeControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Bookings',
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \App\Controller\HomeController::index()
     */
    public function testIndex(): void
    {
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $this->get('/home');

        $this->assertResponseOk();
        // Auf das Ziel des Downloads pruefen statt auf seine Beschriftung: die
        // Monatszeile traegt inzwischen den ausgeschriebenen Monatsnamen, und
        // der haengt an der eingestellten Sprache. Die URL tut das nicht.
        $this->assertResponseContains('/bookings/genxls/2025/9');
        $this->assertResponseContains('150 Minuten');
        $this->assertResponseContains('2.5 Stunden');
        // Buchung 3 gehoert einem anderen Nutzer in einer anderen Gruppe —
        // ihr August darf hier nicht auftauchen.
        $this->assertResponseNotContains('/bookings/genxls/2025/8');
    }
}
