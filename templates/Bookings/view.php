<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Booking $booking
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Booking'), ['action' => 'edit', $booking->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Booking'), ['action' => 'delete', $booking->id], ['confirm' => __('Are you sure you want to delete # {0}?', $booking->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Bookings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Booking'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="bookings view content">
            <h3><?= h($booking->ticket) ?></h3>
            <table>
                <tr>
                    <th><?= __('Ticket') ?></th>
                    <td><?= h($booking->ticket) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bookingpsp') ?></th>
                    <td><?= h($booking->bookingpsp) ?></td>
                </tr>
<tr>
                    <th><?= __('Mandant') ?></th>
                    <td><?= h($booking->mandant?->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= $this->Number->format($booking->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Minutes') ?></th>
                    <td><?= $this->Number->format($booking->minutes) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bookingdate') ?></th>
                    <td><?= h($booking->bookingdate) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($booking->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($booking->modified) ?></td>
                </tr>
            </table>
            <div class="text">
                <strong><?= __('Description') ?></strong>
                <blockquote>
                    <?= $this->Text->autoParagraph(h($booking->description)); ?>
                </blockquote>
            </div>
        </div>
    </div>
</div>
