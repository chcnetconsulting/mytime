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
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $booking->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $booking->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Bookings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="bookings form content">
            <?= $this->Form->create($booking) ?>
            <fieldset>
                <legend><?= __('Edit Booking') ?></legend>
                <?php
                    echo $this->Form->control('bookingdate');
                    echo $this->Form->control('ticket');
                    echo $this->Form->control('bookingpsp');
                    echo $this->Form->control('description');
                    echo $this->Form->control('minutes');
                    echo $this->Form->control('kunde');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
