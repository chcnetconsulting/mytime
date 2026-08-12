<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Approval Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $mandant_id
 * @property int $year
 * @property int $month
 * @property string $filename
 * @property string $mime
 * @property string $content
 * @property int $byte_size
 * @property string|null $uploaded_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class Approval extends Entity
{
    /**
     * `content` ist nur über die dedizierten Download-Endpunkte erreichbar,
     * nie über Massenzuweisung.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'mandant_id' => true,
        'year' => true,
        'month' => true,
        'filename' => true,
        'mime' => true,
        'content' => true,
        'byte_size' => true,
        'uploaded_by' => true,
        'created' => true,
        'modified' => true,
    ];

    /**
     * Rohe PDF-Bytes nie in JSON/Array-Ausgaben mitschleppen.
     *
     * @var list<string>
     */
    protected array $_hidden = ['content'];
}
