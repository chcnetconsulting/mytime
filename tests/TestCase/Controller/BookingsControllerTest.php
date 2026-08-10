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
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Bookings',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 1,
                    'group_id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ],
        ]);
    }

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
        $this->assertResponseNotContains('MYT-3');
        $this->assertResponseNotContains('PSP-OPS');
    }

    public function testIndexSearchFiltersResults(): void
    {
        $this->get('/bookings?table_search=CORE');

        $this->assertResponseOk();
        $this->assertResponseContains('PSP-CORE');
        $this->assertResponseNotContains('PSP-OPS');
    }

    public function testIndexOnlyShowsCurrentUsersBookings(): void
    {
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 3,
                    'group_id' => 2,
                    'username' => 'thirduser',
                    'email' => 'third@example.com',
                ],
            ],
        ]);

        $this->get('/bookings');

        $this->assertResponseOk();
        $this->assertResponseContains('MYT-3');
        $this->assertResponseNotContains('MYT-1');
        $this->assertResponseNotContains('MYT-2');
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
        $booking = $bookings->find()->where(['ticket' => 'MYT-4'])->firstOrFail();
        $this->assertSame(1, $booking->user_id);
        $this->assertSame(1, $booking->group_id);
    }

    public function testAddIncludesTicketLookupScript(): void
    {
        $this->get('/bookings/add');

        $this->assertResponseOk();
        $this->assertResponseContains('/bookings/ticket-lookup');
        $this->assertResponseContains('loadTicketDefaults');
        $this->assertResponseContains('$("#minutes").val("");');
        $this->assertResponseContains('id="mandant-id"');
        $this->assertResponseContains('$mandant.select2();');
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

    public function testTicketLookupDoesNotUseAnotherUsersBooking(): void
    {
        $this->get('/bookings/ticket-lookup?ticket=MYT-3');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($payload['found']);
    }

    public function testUsersInSameGroupCanUseSharedBookings(): void
    {
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 2,
                    'group_id' => 1,
                    'username' => 'seconduser',
                    'email' => 'second@example.com',
                ],
            ],
        ]);

        $this->get('/bookings');

        $this->assertResponseOk();
        $this->assertResponseContains('MYT-1');
        $this->assertResponseContains('MYT-2');
        $this->assertResponseNotContains('MYT-3');
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
        $this->assertSame(1, $booking->user_id);
        $this->assertSame(1, $booking->group_id);
    }

    public function testEditRejectsAnotherUsersBooking(): void
    {
        $this->enableCsrfToken();
        $this->post('/bookings/edit/3', [
            'bookingdate' => '2025-08-29',
            'mandant_id' => 1,
            'ticket' => 'MYT-3',
            'bookingpsp' => 'PSP-OPS',
            'description' => 'Should not update',
            'minutes' => 99,
            'kunde' => 'ACME',
        ]);

        $this->assertResponseCode(404);
        $booking = $this->getTableLocator()->get('Bookings')->get(3);
        $this->assertSame('Ops review', $booking->description);
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
