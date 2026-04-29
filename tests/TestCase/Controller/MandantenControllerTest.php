<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\MandantenController Test Case
 */
class MandantenControllerTest extends TestCase
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

    public function testIndex(): void
    {
        $this->get('/mandanten');

        $this->assertResponseOk();
        $this->assertResponseContains('ACME');
    }

    public function testViewIncludesRelatedBookings(): void
    {
        $this->get('/mandanten/view/1');

        $this->assertResponseOk();
        $this->assertResponseContains('ACME');
        $this->assertResponseContains('MYT-1');
    }

    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->post('/mandanten/add', [
            'name' => 'Initech',
        ]);

        $this->assertRedirect(['controller' => 'Mandanten', 'action' => 'index']);
        $mandanten = $this->getTableLocator()->get('Mandanten');
        $this->assertTrue($mandanten->exists(['name' => 'Initech']));
    }

    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->post('/mandanten/edit/2', [
            'name' => 'Globex GmbH',
        ]);

        $this->assertRedirect(['controller' => 'Mandanten', 'action' => 'index']);
        $mandant = $this->getTableLocator()->get('Mandanten')->get(2);
        $this->assertSame('Globex GmbH', $mandant->name);
    }

    public function testDelete(): void
    {
        $this->enableCsrfToken();
        $this->delete('/mandanten/delete/2');

        $this->assertRedirect(['controller' => 'Mandanten', 'action' => 'index']);
        $mandanten = $this->getTableLocator()->get('Mandanten');
        $this->assertFalse($mandanten->exists(['id' => 2]));
    }
}
