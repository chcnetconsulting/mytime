<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Booking> $bookings
 */
?>
<div class="bookings index content">
    <?= $this->Html->link(__('New Booking'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Bookings') ?></h3>
    <div class="table-responsive">

	<div>
		 <?php echo $this->Form->create(null, ['action' => $this->Url->build(), 'method' => 'get', 'role' => 'form']); ?>
                        <div class="input-group input-group-sm" style="width: 150px;">
                            <input type="text" name="table_search" class="form-control pull-right"
                                placeholder="<?php echo __('Search'); ?>">

                            <div class="input-group-btn">
                                <button type="submit" class="btn">Go!</button>
                            </div>
                        </div>
                 </form>

	</div>	
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('mandant_id', 'Mandant') ?></th>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('bookingdate') ?></th>
                    <th><?= $this->Paginator->sort('ticket') ?></th>
                    <th><?= $this->Paginator->sort('bookingpsp') ?></th>
                    <th><?= $this->Paginator->sort('minutes') ?></th>
		    <th>Description</th>
		    <!-- th><?= $this->Paginator->sort('kunde') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th-->
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?= h($booking->mandant?->name) ?></td>
                    <td><?= $this->Number->format($booking->id) ?></td>
                    <td><?= h($booking->bookingdate) ?></td>
                    <td><?= h($booking->ticket) ?></td>
                    <td><?= h($booking->bookingpsp) ?></td>
		    <td><?= $this->Number->format($booking->minutes) ?></td>
		    <td><?= h($booking->description) ?></td>
                    <!--td><?= h($booking->kunde) ?></td>
                    <td><?= h($booking->created) ?></td>
                    <td><?= h($booking->modified) ?></td-->
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $booking->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $booking->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $booking->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $booking->id),
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</div>
