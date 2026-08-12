<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\Date;
use Cake\ORM\Locator\LocatorAwareTrait;
use Mpdf\Mpdf;

/**
 * Erzeugt die Monats-Timesheet-PDF aus den Buchungen.
 *
 * Die Logik lag bisher in BookingsController::genpdf(). Sie ist hierher
 * ausgelagert, damit sowohl die Weboberfläche (Session-Login) als auch die
 * REST-API (Token) exakt dieselbe PDF erzeugen, ohne den Code zu duplizieren.
 */
class TimesheetPdfService
{
    use LocatorAwareTrait;

    /**
     * @param array $scopeConditions WHERE-Bedingungen für die Sichtbarkeit
     *        (z. B. ['Bookings.user_id' => 42]) — vom Aufrufer bestimmt.
     * @return string Rohe PDF-Bytes.
     */
    public function render(
        int $year,
        int $month,
        array $scopeConditions,
        ?int $mandantId,
        string $username,
        ?string $mandantName
    ): string {
        $bookings = $this->fetchTable('Bookings');

        // Bereichsfilter statt YEAR()/MONTH() — datenbank-portabel und nutzt
        // einen Index auf bookingdate.
        $first = Date::create($year, $month, 1);
        $last = $first->lastOfMonth();
        $dateConditions = [
            'Bookings.bookingdate >=' => $first->format('Y-m-d'),
            'Bookings.bookingdate <=' => $last->format('Y-m-d'),
        ];
        $mandantConditions = $mandantId !== null ? ['Bookings.mandant_id' => $mandantId] : [];

        $results = $bookings->find()
            ->where($scopeConditions)
            ->where($dateConditions)
            ->where($mandantConditions)
            ->orderBy(['bookingdate' => 'ASC'])
            ->toArray();

        $pspResults = $bookings->find()
            ->where($scopeConditions)
            ->where($dateConditions)
            ->where($mandantConditions)
            ->select(['bookingpsp', 'minutes' => $bookings->query()->func()->sum('minutes')])
            ->groupBy(['bookingpsp'])
            ->orderBy(['bookingpsp' => 'ASC'])
            ->toArray();

        $totalMinutes = array_sum(array_map(fn($r) => (int)$r->minutes, $pspResults));

        $monthLabel = Date::create($year, $month, 1)->i18nFormat('MMMM yyyy');
        $title = "timesheet {$username} {$monthLabel}";
        if ($mandantName !== null && $mandantName !== '') {
            $title .= " — {$mandantName}";
        }

        $html = '
        <style>
            body { font-family: sans-serif; font-size: 9pt; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #ddd; font-weight: bold; padding: 3px 6px; border-bottom: 2px solid #999; text-align: left; }
            td { padding: 2px 6px; vertical-align: top; border-bottom: 1px solid #eee; }
            .right { text-align: right; }
            .summary { width: 50%; margin-top: 24px; }
            .total td { font-weight: bold; border-top: 2px solid #666; border-bottom: none; }
        </style>';

        $html .= '<table><thead><tr><th>Date</th><th>Ticket</th><th>PSP</th>'
            . '<th>Description</th><th class="right">Minutes</th></tr></thead><tbody>';
        foreach ($results as $row) {
            $html .= '<tr>'
                . '<td>' . h($row->bookingdate->i18nFormat('dd.MM.yyyy')) . '</td>'
                . '<td>' . h(trim((string)$row->ticket)) . '</td>'
                . '<td>' . h(trim((string)$row->bookingpsp)) . '</td>'
                . '<td>' . h(trim((string)$row->description)) . '</td>'
                . '<td class="right">' . (int)$row->minutes . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<table class="summary"><thead><tr><th>Booking PSP</th>'
            . '<th class="right">Minutes</th><th class="right">Hours</th></tr></thead><tbody>';
        foreach ($pspResults as $row) {
            $html .= '<tr>'
                . '<td>' . h($row->bookingpsp) . '</td>'
                . '<td class="right">' . (int)$row->minutes . '</td>'
                . '<td class="right">' . round((int)$row->minutes / 60, 2) . '</td>'
                . '</tr>';
        }
        $html .= '<tr class="total"><td>Total</td>'
            . '<td class="right">' . $totalMinutes . '</td>'
            . '<td class="right">' . round($totalMinutes / 60, 2) . '</td>'
            . '</tr></tbody></table>';

        $mpdf = new Mpdf([
            'orientation' => 'L',
            'margin_top' => 22,
            'margin_bottom' => 15,
        ]);
        $mpdf->SetHTMLHeader(
            '<div style="text-align:center;font-weight:bold;font-size:11pt;">' . h($title) . '</div>'
        );
        $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:9pt;">- {PAGENO} -</div>');
        $mpdf->WriteHTML($html);

        return (string)$mpdf->Output('', 'S');
    }

    /**
     * Einheitlicher Dateiname für Export und API.
     */
    public function filename(?string $mandantName, ?string $first, ?string $last, ?string $username, int $year, int $month): string
    {
        $sanitize = static fn(string $s): string => trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?? '', '-');

        $first = trim((string)$first);
        $last = trim((string)$last);
        if ($first === '' && $last === '') {
            $first = (string)$username;
        }

        $parts = ['timesheet'];
        foreach ([$mandantName, $first, $last] as $p) {
            $p = (string)$p;
            if ($p !== '') {
                $parts[] = $sanitize($p);
            }
        }
        $parts[] = sprintf('%04d', $year);
        $parts[] = sprintf('%02d', $month);

        return implode('_', array_filter($parts, static fn($p) => $p !== '')) . '.pdf';
    }
}
