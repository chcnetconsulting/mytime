<style>
.psp-detail { display: none; }
.psp-detail.open { display: table-row; }
.toggle-btn {
    background: #e0e0e0; border: 1px solid #aaa; border-radius: 3px;
    cursor: pointer; font-size: 1em; width: 26px; height: 26px;
    line-height: 24px; text-align: center; padding: 0;
}
.toggle-btn:hover { background: #c8c8c8; }
.psp-summary { border-collapse: collapse; margin: 4px 0; }
.psp-summary td { padding: 2px 12px; font-size: 0.9em; }
.psp-summary td:nth-child(2), .psp-summary td:nth-child(3) { text-align: right; }
.psp-name { cursor: pointer; text-decoration: underline dotted; }
.psp-name:hover { color: #333; }
.ticket-list { display: none; padding: 4px 0 4px 16px; font-size: 0.85em; color: #444; }
.ticket-list.open { display: block; }
.ticket-list table { border-collapse: collapse; }
.ticket-list td { padding: 1px 6px 1px 0; vertical-align: top; white-space: nowrap; }
.ticket-list td:first-child { font-weight: bold; }
.ticket-list td:last-child { white-space: normal; text-align: left; }
</style>

<table>
    <?php foreach($bookings as $i => $b): ?>
        <?php $label = sprintf('%s %d', \Cake\I18n\Date::create($b->year, $b->month, 1)->i18nFormat('MMMM'), $b->year); ?>
        <tr class="month-row">
            <td><button class="toggle-btn" type="button" data-idx="<?= $i ?>">▶</button></td>
            <td><strong><?= h($label) ?></strong></td>
            <td><?= $b->count ?> Buchungen</td>
            <td><?= $b->sum ?> Minuten</td>
            <td><?= round($b->sum / 60, 2) ?> Stunden</td>
            <td>
                <a href="/bookings/genxls/<?= $b->year . '/' . $b->month ?>" title="Excel <?= h($label) ?>">&#x2B07; XLS</a>
                &nbsp;
                <a href="/bookings/genpdf/<?= $b->year . '/' . $b->month ?>" title="PDF <?= h($label) ?>">&#x2B07; PDF</a>
            </td>
        </tr>
        <tr class="psp-detail" id="psp-<?= $i ?>">
            <td></td>
            <td colspan="4">
                <table class="psp-summary">
                    <?php foreach ($b->psps as $psp => $minutes): ?>
                    <tr>
                        <td>
                            <?php if (!empty($b->pspTickets[$psp])): ?>
                            <span class="psp-name"><?= h($psp) ?></span>
                            <div class="ticket-list">
                                <table>
                                <?php foreach ($b->pspTickets[$psp] as $ticket => $entry): ?>
                                <tr>
                                    <td><a href="/bookings/view/<?= $entry['id'] ?>"><?= h($ticket) ?></a></td>
                                    <td>&ndash;</td>
                                    <td><?= h($entry['description']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </table>
                            </div>
                            <?php else: ?>
                            <?= h($psp) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $minutes ?> Min</td>
                        <td><?= round($minutes / 60, 2) ?> Std</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>
document.querySelectorAll('.toggle-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var detail = document.getElementById('psp-' + this.dataset.idx);
        var open = detail.classList.toggle('open');
        this.textContent = open ? '▼' : '▶';
    });
});

document.querySelectorAll('.psp-name').forEach(function(name) {
    name.addEventListener('click', function() {
        var list = this.nextElementSibling;
        list.classList.toggle('open');
    });
});
</script>
