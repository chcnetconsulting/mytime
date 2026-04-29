<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'group_id' => 1,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'is_admin' => true,
                'first_name' => 'Test',
                'last_name' => 'User',
            ],
            [
                'id' => 2,
                'group_id' => 1,
                'username' => 'seconduser',
                'email' => 'second@example.com',
                'is_admin' => false,
                'first_name' => 'Second',
                'last_name' => 'User',
            ],
            [
                'id' => 3,
                'group_id' => 2,
                'username' => 'thirduser',
                'email' => 'third@example.com',
                'is_admin' => false,
                'first_name' => 'Third',
                'last_name' => 'User',
            ],
        ];
        parent::init();
    }
}
