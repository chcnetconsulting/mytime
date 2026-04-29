<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Group> $groups
 */
?>
<div class="groups index content">
    <?= $this->Html->link(__('New Group'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Groups') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= __('Users') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?= $this->Number->format($group->id) ?></td>
                    <td><?= h($group->name) ?></td>
                    <td><?= count($group->users ?? []) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $group->id]) ?>
                        <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $group->id], ['method' => 'delete', 'confirm' => __('Are you sure you want to delete # {0}?', $group->id)]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
