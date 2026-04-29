<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UsersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\UsersTable Test Case
 */
class UsersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\UsersTable
     */
    protected $Users;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Users') ? [] : ['className' => UsersTable::class];
        $this->Users = $this->getTableLocator()->get('Users', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Users);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\UsersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $valid = $this->Users->newEntity([
            'username' => 'newuser',
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
        ]);
        $this->assertEmpty($valid->getErrors());

        $invalid = $this->Users->newEntity([
            'username' => '',
            'email' => 'not-an-email',
        ]);
        $this->assertNotEmpty($invalid->getError('username'));
        $this->assertNotEmpty($invalid->getError('email'));
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\UsersTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $duplicate = $this->Users->newEntity([
            'username' => 'testuser',
            'email' => 'other@example.com',
        ]);

        $this->assertFalse($this->Users->save($duplicate));
        $this->assertNotEmpty($duplicate->getError('username'));

        $duplicateEmail = $this->Users->newEntity([
            'username' => 'otheruser',
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($this->Users->save($duplicateEmail));
        $this->assertNotEmpty($duplicateEmail->getError('email'));
    }
}
