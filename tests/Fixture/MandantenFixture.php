<?php
declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * MandantenFixture
 */
class MandantenFixture extends AppFixture
{
    /**
     * @var string
     */
    public string $table = 'mandanten';

    /**
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'ACME',
                'created' => '2025-09-01 12:00:00',
                'modified' => '2025-09-01 12:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Globex',
                'created' => '2025-09-01 12:00:00',
                'modified' => '2025-09-01 12:00:00',
            ],
        ];
        parent::init();
    }
}
