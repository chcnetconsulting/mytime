<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateBookings extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('bookings');
        $table
            ->addColumn('bookingdate', 'date', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('ticket', 'string', [
                'default' => null,
                'limit' => 15,
                'null' => false,
            ])
            ->addColumn('bookingpsp', 'string', [
                'default' => null,
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('minutes', 'integer', [
                'default' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addColumn('kunde', 'string', [
                'default' => null,
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->create();
    }
}
