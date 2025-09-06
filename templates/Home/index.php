<table>
    <?php foreach($bookings as $b): ?>
        <tr>
            <td><a href="/bookings/genxls/<?php echo $b->year.'/'.$b->month; ?>">Download Buchungen <?php echo $b->month.' '.$b->year; ?></a></td>
            <td><?php echo $b->count; ?> Buchungen</td>
            <td><?php echo $b->sum; ?> Minuten</td>       
            <td><?php echo round($b->sum/60, 2); ?> Stunden</td>       
        </tr>
    <?php endforeach; ?>
</table>
