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
        $this->get('/home');

        $this->assertResponseOk();
        $this->assertResponseContains('Download Buchungen 9 2025');
        $this->assertResponseContains('150 Minuten');
        $this->assertResponseContains('2.5 Stunden');
        $this->assertResponseContains('Download Buchungen 8 2025');
    }
}
