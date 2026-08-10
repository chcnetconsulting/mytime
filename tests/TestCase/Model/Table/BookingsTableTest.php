<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BookingsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\BookingsTable Test Case
 */
class BookingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\BookingsTable
     */
    protected $Bookings;

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
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Bookings') ? [] : ['className' => BookingsTable::class];
        $this->Bookings = $this->getTableLocator()->get('Bookings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Bookings);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\BookingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $valid = $this->Bookings->newEntity([
            'bookingdate' => '2025-10-01',
            'user_id' => 1,
            'group_id' => 1,
            'mandant_id' => 1,
            'ticket' => 'MYT-4',
            'bookingpsp' => 'PSP-NEW',
            'description' => 'New feature work',
            'minutes' => 45,
            'kunde' => 'ACME',
        ]);
        $this->assertEmpty($valid->getErrors());

        $invalid = $this->Bookings->newEntity([
            'bookingdate' => '',
            'user_id' => '',
            'group_id' => '',
            'mandant_id' => '',
            'ticket' => '',
            'bookingpsp' => '',
            'description' => '',
            'minutes' => 'not-a-number',
            'kunde' => '',
        ]);
        $this->assertNotEmpty($invalid->getError('bookingdate'));
        $this->assertNotEmpty($invalid->getError('user_id'));
        $this->assertNotEmpty($invalid->getError('group_id'));
        $this->assertNotEmpty($invalid->getError('mandant_id'));
        $this->assertNotEmpty($invalid->getError('ticket'));
        $this->assertNotEmpty($invalid->getError('bookingpsp'));
        $this->assertNotEmpty($invalid->getError('description'));
        $this->assertNotEmpty($invalid->getError('minutes'));

        // "kunde" ist bewusst optional: das Feld wurde aus dem Buchungsformular
        // entfernt, die Zuordnung laeuft seither ueber mandant_id. Ein leerer
        // Wert darf deshalb keinen Fehler mehr ausloesen.
        $this->assertEmpty($invalid->getError('kunde'));
    }

    public function testMandantMustExist(): void
    {
        $booking = $this->Bookings->newEntity([
            'bookingdate' => '2025-10-01',
            'user_id' => 1,
            'group_id' => 1,
            'mandant_id' => 999,
            'ticket' => 'MYT-4',
            'bookingpsp' => 'PSP-NEW',
            'description' => 'New feature work',
            'minutes' => 45,
            'kunde' => 'Missing',
        ]);

        $this->assertFalse($this->Bookings->save($booking));
        $this->assertNotEmpty($booking->getError('mandant_id'));
    }

    public function testUserMustExist(): void
    {
        $booking = $this->Bookings->newEntity([
            'bookingdate' => '2025-10-01',
            'user_id' => 999,
            'group_id' => 1,
            'mandant_id' => 1,
            'ticket' => 'MYT-4',
            'bookingpsp' => 'PSP-NEW',
            'description' => 'New feature work',
            'minutes' => 45,
            'kunde' => 'Missing',
        ]);

        $this->assertFalse($this->Bookings->save($booking));
        $this->assertNotEmpty($booking->getError('user_id'));
    }

    public function testGroupMustExist(): void
    {
        $booking = $this->Bookings->newEntity([
            'bookingdate' => '2025-10-01',
            'user_id' => 1,
            'group_id' => 999,
            'mandant_id' => 1,
            'ticket' => 'MYT-4',
            'bookingpsp' => 'PSP-NEW',
            'description' => 'New feature work',
            'minutes' => 45,
            'kunde' => 'Missing',
        ]);

        $this->assertFalse($this->Bookings->save($booking));
        $this->assertNotEmpty($booking->getError('group_id'));
    }
}
