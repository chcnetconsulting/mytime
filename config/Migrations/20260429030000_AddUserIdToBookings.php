<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUserIdToBookings extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $bookings = $this->table('bookings');
        if (!$bookings->hasColumn('user_id')) {
            $bookings
                ->addColumn('user_id', 'integer', [
                    'default' => null,
                    'null' => true,
                    'signed' => false,
                    'after' => 'id',
                ])
                ->addIndex(['user_id'])
                ->update();
        }

        $this->execute('UPDATE bookings SET user_id = (SELECT id FROM users ORDER BY id ASC LIMIT 1) WHERE user_id IS NULL');

        $bookings = $this->table('bookings');
        if ($bookings->hasColumn('user_id')) {
            $bookings
                ->changeColumn('user_id', 'integer', [
                    'default' => null,
                    'null' => false,
                    'signed' => false,
                ])
                ->addForeignKey('user_id', 'users', 'id', [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE',
                ])
                ->update();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $bookings = $this->table('bookings');
        if ($bookings->hasColumn('user_id')) {
            $bookings
                ->dropForeignKey('user_id')
                ->removeColumn('user_id')
                ->update();
        }
    }
}
