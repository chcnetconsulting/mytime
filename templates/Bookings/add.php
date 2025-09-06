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
            <?= $this->Html->link(__('List Bookings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="bookings form content">
            <?= $this->Form->create($booking) ?>
            <fieldset>
                <legend><?= __('Add Booking') ?></legend>
                <?php
                    echo $this->Form->control('bookingdate');
                    echo $this->Form->control('ticket');
		    //echo $this->Form->select('bookingpsp', $psps);
		?>
		<select id="bookingpsp" name="bookingpsp" class="select2">
		<?php
		    foreach($psps as $psp):
		?>
		<option value="<?= $psp->bookingpsp ?>"><?= $psp->bookingpsp ?></option>
		<?php endforeach; ?>
		</select>
		<?php
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
<script>
$(document).ready(function () {
   $("#bookingpsp").select2( {
  tags: true
}  );
});
</script>
