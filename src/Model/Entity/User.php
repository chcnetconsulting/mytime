<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property int $group_id
 * @property bool $is_admin
 *
 * @property array<\App\Model\Entity\Booking> $bookings
 * @property \App\Model\Entity\Group $group
 */
class User extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'username' => true,
        'email' => true,
        'group_id' => true,
        'is_admin' => true,
        'first_name' => true,
        'last_name' => true,
        'bookings' => true,
        'group' => true,
    ];
}
