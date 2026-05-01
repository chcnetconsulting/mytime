<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMandantenAndLinkBookings extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('mandanten')) {
            $table = $this->table('mandanten');
            $table
                ->addColumn('name', 'string', [
                    'default' => null,
                    'limit' => 100,
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
                ->addIndex(['name'], ['unique' => true])
                ->create();
        }

        $bookings = $this->table('bookings');
        if (!$bookings->hasColumn('mandant_id')) {
            $bookings
                ->addColumn('mandant_id', 'integer', [
                    'default' => null,
                    'null' => true,
                    'signed' => true,
                ])
                ->addIndex(['mandant_id'])
                ->update();
        }

        $this->execute("
            INSERT INTO mandanten (name, created, modified)
            SELECT DISTINCT TRIM(kunde), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM bookings
            WHERE kunde IS NOT NULL AND TRIM(kunde) <> ''
                AND NOT EXISTS (
                    SELECT 1
                    FROM mandanten
                    WHERE mandanten.name = TRIM(bookings.kunde)
                )
        ");

        $this->execute("
            UPDATE bookings
            SET mandant_id = (
                SELECT mandanten.id
                FROM mandanten
                WHERE mandanten.name = TRIM(bookings.kunde)
                LIMIT 1
            )
            WHERE mandant_id IS NULL
                AND kunde IS NOT NULL
                AND TRIM(kunde) <> ''
        ");

        $bookings = $this->table('bookings');
        if ($bookings->hasColumn('mandant_id')) {
            $bookings
                ->addForeignKey('mandant_id', 'mandanten', 'id', [
                    'delete' => 'RESTRICT',
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
        if ($bookings->hasColumn('mandant_id')) {
            $bookings
                ->dropForeignKey('mandant_id')
                ->removeColumn('mandant_id')
                ->update();
        }

        if ($this->hasTable('mandanten')) {
            $this->table('mandanten')->drop()->save();
        }
    }
}
