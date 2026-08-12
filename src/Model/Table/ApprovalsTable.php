<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Approvals Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\MandantenTable&\Cake\ORM\Association\BelongsTo $Mandanten
 * @method \App\Model\Entity\Approval newEmptyEntity()
 * @method \App\Model\Entity\Approval newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Approval patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Approval|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Approval saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ApprovalsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('approvals');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
        $this->belongsTo('Mandanten', [
            'foreignKey' => 'mandant_id',
            'propertyName' => 'mandant',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('user_id')->notEmptyString('user_id')
            ->integer('year')->range('year', [2000, 2100])
            ->integer('month')->range('month', [1, 12])
            ->scalar('filename')->maxLength('filename', 255)->notEmptyString('filename')
            ->scalar('mime')->maxLength('mime', 100)
            ->notEmptyString('content')
            ->allowEmptyString('mandant_id')
            ->allowEmptyString('uploaded_by');

        return $validator;
    }

    /**
     * Genau ein Approval je (user, mandant, year, month) — Upsert-Helfer.
     */
    public function findForPeriod(int $userId, ?int $mandantId, int $year, int $month): SelectQuery
    {
        return $this->find()->where([
            'user_id' => $userId,
            'mandant_id IS' => $mandantId,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
