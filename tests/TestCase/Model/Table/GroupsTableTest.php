<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\GroupsTable;
use Cake\TestSuite\TestCase;

class GroupsTableTest extends TestCase
{
    protected $Groups;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Groups') ? [] : ['className' => GroupsTable::class];
        $this->Groups = $this->getTableLocator()->get('Groups', $config);
    }

    protected function tearDown(): void
    {
        unset($this->Groups);
        parent::tearDown();
    }

    public function testValidationDefault(): void
    {
        $valid = $this->Groups->newEntity(['name' => 'Team A']);
        $this->assertEmpty($valid->getErrors());

        $invalid = $this->Groups->newEntity(['name' => '']);
        $this->assertNotEmpty($invalid->getError('name'));
    }

    public function testUniqueNameRule(): void
    {
        $duplicate = $this->Groups->newEntity(['name' => 'Shared']);
        $this->assertFalse($this->Groups->save($duplicate));
        $this->assertNotEmpty($duplicate->getError('name'));
    }
}
