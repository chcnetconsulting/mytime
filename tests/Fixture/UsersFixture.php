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
                'username' => 'testuser',
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
            ],
            [
                'id' => 2,
                'username' => 'seconduser',
                'email' => 'second@example.com',
                'first_name' => 'Second',
                'last_name' => 'User',
            ],
        ];
        parent::init();
    }
}
