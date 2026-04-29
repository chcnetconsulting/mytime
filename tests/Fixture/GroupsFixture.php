<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class GroupsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Shared',
                'created' => '2025-09-01 12:00:00',
                'modified' => '2025-09-01 12:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Solo',
                'created' => '2025-09-01 12:00:00',
                'modified' => '2025-09-01 12:00:00',
            ],
        ];
        parent::init();
    }
}
