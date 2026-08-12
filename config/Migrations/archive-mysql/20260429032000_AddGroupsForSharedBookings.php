<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddGroupsForSharedBookings extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('groups')) {
            $groups = $this->table('groups');
            $groups
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

        $this->execute("INSERT INTO `groups` (name, created, modified) SELECT 'Default', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM `groups` WHERE name = 'Default')");

        $users = $this->table('users');
        if (!$users->hasColumn('group_id')) {
            $users
                ->addColumn('group_id', 'integer', [
                    'default' => null,
                    'null' => true,
                    'signed' => true,
                ])
                ->addColumn('is_admin', 'boolean', [
                    'default' => false,
                    'null' => false,
                ])
                ->addIndex(['group_id'])
                ->update();
        }

        $this->execute("UPDATE users SET group_id = (SELECT id FROM `groups` WHERE name = 'Default' LIMIT 1) WHERE group_id IS NULL");

        $bookings = $this->table('bookings');
        if (!$bookings->hasColumn('group_id')) {
            $bookings
                ->addColumn('group_id', 'integer', [
                    'default' => null,
                    'null' => true,
                    'signed' => true,
                    'after' => 'user_id',
                ])
                ->addIndex(['group_id'])
                ->update();
        }

        $this->execute("
            UPDATE bookings
            SET group_id = (
                SELECT users.group_id
                FROM users
                WHERE users.id = bookings.user_id
                LIMIT 1
            )
            WHERE group_id IS NULL
        ");

        $users = $this->table('users');
        if ($users->hasColumn('group_id')) {
            $users
                ->changeColumn('group_id', 'integer', [
                    'default' => null,
                    'null' => false,
                    'signed' => true,
                ])
                ->addForeignKey('group_id', 'groups', 'id', [
                    'delete' => 'RESTRICT',
                    'update' => 'CASCADE',
                ])
                ->update();
        }

        $bookings = $this->table('bookings');
        if ($bookings->hasColumn('group_id')) {
            $bookings
                ->changeColumn('group_id', 'integer', [
                    'default' => null,
                    'null' => false,
                    'signed' => true,
                ])
                ->addForeignKey('group_id', 'groups', 'id', [
                    'delete' => 'RESTRICT',
                    'update' => 'CASCADE',
                ])
                ->update();
        }
    }

    public function down(): void
    {
        $bookings = $this->table('bookings');
        if ($bookings->hasColumn('group_id')) {
            $bookings
                ->dropForeignKey('group_id')
                ->removeColumn('group_id')
                ->update();
        }

        $users = $this->table('users');
        if ($users->hasColumn('group_id')) {
            $users
                ->dropForeignKey('group_id')
                ->removeColumn('group_id')
                ->removeColumn('is_admin')
                ->update();
        }

        if ($this->hasTable('groups')) {
            $this->table('groups')->drop()->save();
        }
    }
}
