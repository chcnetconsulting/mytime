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
        $this->markTestIncomplete('Not implemented yet.');
    }
}
