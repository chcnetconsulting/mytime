<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Booking $booking
 * @var iterable<\App\Model\Entity\Mandant> $mandanten
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
                ?>
                <select id="bookingpsp" name="bookingpsp" class="select2">
                    <?php
                    $hasCurrentPsp = false;
                    foreach ($psps as $psp):
                        $selected = $psp->bookingpsp === $booking->bookingpsp;
                        $hasCurrentPsp = $hasCurrentPsp || $selected;
                    ?>
                    <option value="<?= h($psp->bookingpsp) ?>" data-mandanten="<?= h((string)$psp->mandanten) ?>" <?= $selected ? 'selected' : '' ?>><?= h($psp->bookingpsp) ?></option>
                    <?php endforeach; ?>
                    <?php if ($booking->bookingpsp !== null && !$hasCurrentPsp): ?>
                    <option value="<?= h($booking->bookingpsp) ?>" selected><?= h($booking->bookingpsp) ?></option>
                    <?php endif; ?>
                </select>
                <?php
                    echo $this->Form->control('description');
                    echo $this->Form->control('minutes');
                    echo $this->Form->control('mandant_id', [
                        'options' => $mandanten,
                        'empty' => true,
                        'class' => 'select2',
                        'label' => 'Mandant',
                    ]);
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
    const $bookingPsp = $("#bookingpsp");
    const $mandant = $("#mandant-id");
    const pspOptionsAll = $bookingPsp.find("option").toArray();

    function pspSelect2() {
        $bookingPsp.select2({ tags: true });
    }

    function optionMatchesMandant(opt, mandantId) {
        if (!mandantId) return true;
        var raw = opt.getAttribute("data-mandanten") || "";
        var ids = raw.split(",").map(function(s) { return s.trim(); }).filter(Boolean);
        if (ids.length === 0) return false;
        return ids.indexOf(String(mandantId)) >= 0;
    }

    function refreshPsp() {
        var mandantId = $mandant.val();
        var current = $bookingPsp.val();

        if ($bookingPsp.hasClass("select2-hidden-accessible")) {
            $bookingPsp.select2("destroy");
        }
        $bookingPsp.empty();
        pspOptionsAll.forEach(function(opt) {
            if (optionMatchesMandant(opt, mandantId)) {
                $bookingPsp.append(opt.cloneNode(true));
            }
        });
        if (current && current !== "" &&
            $bookingPsp.find("option").filter(function() { return this.value === current; }).length === 0) {
            $bookingPsp.append(new Option(current, current, true, true));
        }
        if (current) $bookingPsp.val(current);

        pspSelect2();
    }

    pspSelect2();
    $mandant.select2();
    $mandant.on("change", refreshPsp);
});
</script>
