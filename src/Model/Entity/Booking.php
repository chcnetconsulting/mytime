<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Booking Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $group_id
 * @property int $mandant_id
 * @property \Cake\I18n\Date $bookingdate
 * @property string $ticket
 * @property string $bookingpsp
 * @property string $description
 * @property int $minutes
 * @property string $kunde
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Mandant $mandant
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Group $group
 */
class Booking extends Entity
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
        'bookingdate' => true,
        'user_id' => true,
        'group_id' => true,
        'mandant_id' => true,
        'ticket' => true,
        'bookingpsp' => true,
        'description' => true,
        'minutes' => true,
        'kunde' => true,
        'created' => true,
        'modified' => true,
        'mandant' => true,
        'user' => true,
        'group' => true,
    ];
}
