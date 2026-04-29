<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\BookingsController Test Case
 *
 * @link \App\Controller\BookingsController
 */
class BookingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Mandanten',
        'app.Bookings',
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \App\Controller\BookingsController::index()
     */
    public function testIndex(): void
    {
        $this->get('/bookings');

        $this->assertResponseOk();
        $this->assertResponseContains('MYT-2');
        $this->assertResponseContains('Implementation work');
        $this->assertResponseContains('PSP-OPS');
    }

    public function testIndexSearchFiltersResults(): void
    {
        $this->get('/bookings?table_search=OPS');

        $this->assertResponseOk();
        $this->assertResponseContains('PSP-OPS');
        $this->assertResponseNotContains('PSP-CORE');
    }

    /**
     * Test view method
     *
     * @return void
     * @link \App\Controller\BookingsController::view()
     */
    public function testView(): void
    {
        $this->get('/bookings/view/1');

        $this->assertResponseOk();
        $this->assertResponseContains('MYT-1');
        $this->assertResponseContains('Planning session');
    }

    /**
     * Test add method
     *
     * @return void
     * @link \App\Controller\BookingsController::add()
     */
    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->post('/bookings/add', [
            'bookingdate' => '2025-10-01',
            'mandant_id' => 1,
            'ticket' => 'MYT-4',
            'bookingpsp' => 'PSP-NEW',
            'description' => 'New feature work',
            'minutes' => 45,
            'kunde' => 'ACME',
        ]);

        $this->assertRedirect(['controller' => 'Bookings', 'action' => 'index']);
        $bookings = $this->getTableLocator()->get('Bookings');
        $this->assertSame(1, $bookings->find()->where(['ticket' => 'MYT-4'])->count());
    }

    public function testAddIncludesTicketLookupScript(): void
    {
        $this->get('/bookings/add');

        $this->assertResponseOk();
        $this->assertResponseContains('/bookings/ticket-lookup');
        $this->assertResponseContains('loadTicketDefaults');
        $this->assertResponseContains('$("#minutes").val("");');
        $this->assertResponseContains('id="mandant-id"');
        $this->assertResponseContains('$("#mandant-id").select2();');
    }

    public function testTicketLookupReturnsLatestBookingDefaults(): void
    {
        $this->get('/bookings/ticket-lookup?ticket=MYT-2');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($payload['found']);
        $this->assertSame('2025-09-03', $payload['booking']['bookingdate']);
        $this->assertSame('PSP-CORE', $payload['booking']['bookingpsp']);
        $this->assertSame(1, $payload['booking']['mandant_id']);
        $this->assertSame('Implementation work', $payload['booking']['description']);
        $this->assertSame(90, $payload['booking']['minutes']);
        $this->assertSame('ACME', $payload['booking']['kunde']);
    }

    public function testTicketLookupReturnsNotFoundPayload(): void
    {
        $this->get('/bookings/ticket-lookup?ticket=UNKNOWN');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($payload['found']);
    }

    /**
     * Test edit method
     *
     * @return void
     * @link \App\Controller\BookingsController::edit()
     */
    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->post('/bookings/edit/1', [
            'bookingdate' => '2025-09-02',
            'mandant_id' => 2,
            'ticket' => 'MYT-1',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Updated planning session',
            'minutes' => 75,
            'kunde' => 'ACME',
        ]);

        $this->assertRedirect(['controller' => 'Bookings', 'action' => 'index']);
        $booking = $this->getTableLocator()->get('Bookings')->get(1);
        $this->assertSame('Updated planning session', $booking->description);
        $this->assertSame(75, $booking->minutes);
        $this->assertSame(2, $booking->mandant_id);
    }

    /**
     * Test delete method
     *
     * @return void
     * @link \App\Controller\BookingsController::delete()
     */
    public function testDelete(): void
    {
        $this->enableCsrfToken();
        $this->delete('/bookings/delete/1');

        $this->assertRedirect(['controller' => 'Bookings', 'action' => 'index']);
        $bookings = $this->getTableLocator()->get('Bookings');
        $this->assertFalse($bookings->exists(['id' => 1]));
    }

    public function testGenxlsRejectsInvalidPeriod(): void
    {
        $this->get('/bookings/genxls/2025/13');

        $this->assertResponseCode(400);
    }
}
