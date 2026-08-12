<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Booking> $bookings
 * @var array{year:int,month:int,label:string,psps:array<int,array{bookingpsp:string,minutes:int}>,totalMinutes:int} $monthSummary
 */
$hours = static fn(int $minutes): string => number_format($minutes / 60, 2, ',', '.');
?>
<style>
.month-summary {
    border: 1px solid #ccc; border-radius: 4px; padding: 8px 12px;
    margin: 0 0 16px 0; display: inline-block; min-width: 320px;
}
.month-summary h4 { margin: 0 0 6px 0; font-size: 1em; }
.month-summary table { border-collapse: collapse; margin: 0; width: 100%; }
.month-summary td { padding: 2px 12px 2px 0; font-size: 0.9em; border: none; }
.month-summary td.right { text-align: right; padding-right: 0; }
.month-summary tr.total td { font-weight: bold; border-top: 1px solid #999; padding-top: 4px; }
.month-summary .empty { font-size: 0.9em; color: #666; }
</style>
<div class="bookings index content">
    <?= $this->Html->link(__('New Booking'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Bookings') ?></h3>
    <div class="table-responsive">

	<div>
		 <?php echo $this->Form->create(null, ['action' => $this->Url->build(), 'method' => 'get', 'role' => 'form', 'id' => 'bookings-filter-form']); ?>
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <label for="mandant_id" style="font-weight: bold;">Mandant:</label>
                            <select name="mandant_id" id="mandant_id" onchange="document.getElementById('bookings-filter-form').submit()">
                                <option value="">— Alle Mandanten —</option>
                                <?php foreach ($mandanten as $id => $name): ?>
                                <option value="<?= h($id) ?>"<?= $selectedMandantId === (int)$id ? ' selected' : '' ?>><?= h($name) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="input-group input-group-sm" style="width: 200px;">
                                <input type="text" name="table_search" class="form-control pull-right"
                                    value="<?= h((string)($suche ?? '')) ?>"
                                    placeholder="<?php echo __('Search'); ?>">

                                <div class="input-group-btn">
                                    <button type="submit" class="btn">Go!</button>
                                </div>
                            </div>
                        </div>
                 </form>

	</div>

	<div class="month-summary">
	    <h4>
	        Stunden <?= h($monthSummary['label']) ?>
	        <?php if ($selectedMandantId !== null): ?>
	            &ndash; <?= h((string)$mandanten[$selectedMandantId]) ?>
	        <?php else: ?>
	            &ndash; alle Mandanten
	        <?php endif; ?>
	    </h4>
	    <?php if ($monthSummary['psps'] === []): ?>
	        <p class="empty">Keine Buchungen in diesem Monat.</p>
	    <?php else: ?>
	    <table>
	        <?php foreach ($monthSummary['psps'] as $psp): ?>
	        <tr>
	            <td><?= h($psp['bookingpsp']) ?></td>
	            <td class="right"><?= $this->Number->format($psp['minutes']) ?> min</td>
	            <td class="right"><?= $hours($psp['minutes']) ?> h</td>
	        </tr>
	        <?php endforeach; ?>
	        <tr class="total">
	            <td>Total</td>
	            <td class="right"><?= $this->Number->format($monthSummary['totalMinutes']) ?> min</td>
	            <td class="right"><?= $hours($monthSummary['totalMinutes']) ?> h</td>
	        </tr>
	    </table>
	    <?php endif; ?>
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
