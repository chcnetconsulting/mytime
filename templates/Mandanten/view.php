<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Mandant $mandant
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Mandant'), ['action' => 'edit', $mandant->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Mandant'), ['action' => 'delete', $mandant->id], ['confirm' => __('Are you sure you want to delete # {0}?', $mandant->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Mandanten'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Mandant'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="mandanten view content">
            <h3><?= h($mandant->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($mandant->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= $this->Number->format($mandant->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($mandant->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($mandant->modified) ?></td>
                </tr>
            </table>

            <div class="related">
                <h4><?= __('Related Bookings') ?></h4>
                <?php if (!empty($mandant->bookings)): ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Bookingdate') ?></th>
                            <th><?= __('Ticket') ?></th>
                            <th><?= __('Bookingpsp') ?></th>
                            <th><?= __('Minutes') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($mandant->bookings as $booking): ?>
                        <tr>
                            <td><?= h($booking->id) ?></td>
                            <td><?= h($booking->bookingdate) ?></td>
                            <td><?= h($booking->ticket) ?></td>
                            <td><?= h($booking->bookingpsp) ?></td>
                            <td><?= h($booking->minutes) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Bookings', 'action' => 'view', $booking->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Bookings', 'action' => 'edit', $booking->id]) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
