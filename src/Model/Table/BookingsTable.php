<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Bookings Model
 *
 * @method \App\Model\Entity\Booking newEmptyEntity()
 * @method \App\Model\Entity\Booking newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Booking get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Booking findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Booking patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Booking|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Booking saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Booking>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Booking>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Booking>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Booking> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Booking>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Booking>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Booking>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Booking> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class BookingsTable extends Table
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

        $this->setTable('bookings');
        $this->setDisplayField('ticket');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Mandanten', [
            'foreignKey' => 'mandant_id',
            'propertyName' => 'mandant',
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
        $this->belongsTo('Groups', [
            'foreignKey' => 'group_id',
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
            ->date('bookingdate')
            ->notEmptyDate('bookingdate');

        $validator
            ->scalar('ticket')
            ->maxLength('ticket', 15)
            ->requirePresence('ticket', 'create')
            ->notEmptyString('ticket');

        $validator
            ->scalar('bookingpsp')
            ->maxLength('bookingpsp', 20)
            ->requirePresence('bookingpsp', 'create')
            ->notEmptyString('bookingpsp');

        $validator
            ->scalar('description')
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        $validator
            ->integer('minutes')
            ->requirePresence('minutes', 'create')
            ->notEmptyString('minutes');

        $validator
            ->scalar('kunde')
            ->maxLength('kunde', 20)
            ->notEmptyString('kunde');

        $validator
            ->integer('mandant_id')
            ->notEmptyString('mandant_id');

        $validator
            ->integer('user_id')
            ->notEmptyString('user_id');

        $validator
            ->integer('group_id')
            ->notEmptyString('group_id');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['mandant_id'], 'Mandanten'), ['errorField' => 'mandant_id']);
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['group_id'], 'Groups'), ['errorField' => 'group_id']);

        return $rules;
    }
}
