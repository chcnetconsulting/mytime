<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * BookingsFixture
 */
class BookingsFixture extends TestFixture
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
                'user_id' => 1,
                'group_id' => 1,
                'mandant_id' => 1,
                'bookingdate' => '2025-09-02',
                'ticket' => 'MYT-1',
                'bookingpsp' => 'PSP-CORE',
                'description' => 'Planning session',
                'minutes' => 60,
                'kunde' => 'ACME',
                'created' => '2025-09-02 12:53:58',
                'modified' => '2025-09-02 12:53:58',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'group_id' => 1,
                'mandant_id' => 1,
                'bookingdate' => '2025-09-03',
                'ticket' => 'MYT-2',
                'bookingpsp' => 'PSP-CORE',
                'description' => 'Implementation work',
                'minutes' => 90,
                'kunde' => 'ACME',
                'created' => '2025-09-03 12:53:58',
                'modified' => '2025-09-03 12:53:58',
            ],
            [
                'id' => 3,
                'user_id' => 3,
                'group_id' => 2,
                'mandant_id' => 1,
                'bookingdate' => '2025-08-29',
                'ticket' => 'MYT-3',
                'bookingpsp' => 'PSP-OPS',
                'description' => 'Ops review',
                'minutes' => 30,
                'kunde' => 'ACME',
                'created' => '2025-08-29 12:53:58',
                'modified' => '2025-08-29 12:53:58',
            ],
        ];
        parent::init();
    }
}
