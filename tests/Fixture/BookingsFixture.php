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
                'bookingdate' => '2025-09-02',
                'ticket' => 'Lorem ipsum d',
                'bookingpsp' => 'Lorem ipsum dolor ',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'minutes' => 1,
                'kunde' => 'Lorem ipsum dolor ',
                'created' => '2025-09-02 12:53:58',
                'modified' => '2025-09-02 12:53:58',
            ],
        ];
        parent::init();
    }
}
