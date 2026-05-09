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
                ?>
                <label for="ticket">Ticket</label>
                <select id="ticket" name="ticket" class="select2">
                    <?php foreach ($tickets as $t): ?>
                    <option value="<?= h($t->ticket) ?>" data-desc="<?= h(mb_substr($t->last_desc, 0, 50)) ?>" data-mandanten="<?= h((string)$t->mandanten) ?>"><?= h($t->ticket) ?> (<?= h(date('d.m.Y', strtotime(substr((string)$t->last_date, 0, 10)))) ?>) - <?= h($t->last_psp) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="bookingpsp">Booking PSP</label>
                <select id="bookingpsp" name="bookingpsp" class="select2">
		<?php
		    foreach($psps as $psp):
		?>
		<option value="<?= h($psp->bookingpsp) ?>" data-mandanten="<?= h((string)$psp->mandanten) ?>"><?= h($psp->bookingpsp) ?></option>
		<?php endforeach; ?>
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
    const $ticket = $("#ticket");
    const $bookingPsp = $("#bookingpsp");
    const $mandant = $("#mandant-id");
    const ticketLookupUrl = "<?= $this->Url->build(['action' => 'ticketLookup']) ?>";
    let lastLookupTicket = null;

    const ticketOptionsAll = $ticket.find("option").toArray();
    const pspOptionsAll = $bookingPsp.find("option").toArray();

    function ticketSelect2() {
        $ticket.select2({
            tags: true,
            templateResult: function(data) {
                if (!data.element) return data.text;
                var desc = $(data.element).data('desc');
                if (!desc) return data.text;
                var $el = $('<span>').append(document.createTextNode(data.text + ' '));
                $el.append($('<em>').text(desc));
                return $el;
            }
        });
    }
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

    function applyFilter($select, allOptions, initFn) {
        var mandantId = $mandant.val();
        var current = $select.val();

        if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
        }
        $select.empty();
        allOptions.forEach(function(opt) {
            if (optionMatchesMandant(opt, mandantId)) {
                $select.append(opt.cloneNode(true));
            }
        });
        if (current && current !== "" &&
            $select.find("option").filter(function() { return this.value === current; }).length === 0) {
            $select.append(new Option(current, current, true, true));
        }
        if (current) $select.val(current);

        initFn();
    }

    function refreshAll() {
        applyFilter($ticket, ticketOptionsAll, ticketSelect2);
        applyFilter($bookingPsp, pspOptionsAll, pspSelect2);
    }

    function setSelect2Value(value) {
        if (!value) return;
        if ($bookingPsp.find("option").filter(function () {
            return this.value === value;
        }).length === 0) {
            $bookingPsp.append(new Option(value, value, true, true));
        }
        $bookingPsp.val(value).trigger("change");
    }

    async function loadTicketDefaults() {
        const ticket = $ticket.val().trim();
        if (ticket === "" || ticket === lastLookupTicket) return;

        lastLookupTicket = ticket;
        const response = await fetch(`${ticketLookupUrl}?ticket=${encodeURIComponent(ticket)}`, {
            headers: { "Accept": "application/json" }
        });
        if (!response.ok) return;

        const payload = await response.json();
        if (!payload.found) return;

        const booking = payload.booking;
        $("#description").val(booking.description);
        $("#minutes").val("");
        $mandant.val(booking.mandant_id).trigger("change");
        $("#kunde").val(booking.kunde);
        setSelect2Value(booking.bookingpsp);
    }

    ticketSelect2();
    pspSelect2();
    $mandant.select2();

    $ticket.on("change", loadTicketDefaults);
    $mandant.on("change", refreshAll);
});
</script>
