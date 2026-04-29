<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\Locator\LocatorAwareTrait;
/**
 * Home Controller
 *
 */
class HomeController extends AppController
{
    use LocatorAwareTrait;
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $rows = $this->fetchTable('Bookings')
            ->find()
            ->select(['bookingdate', 'minutes'])
            ->orderBy(['bookingdate' => 'DESC'])
            ->all();

        $monthly = [];
        foreach ($rows as $row) {
            $year = (int)$row->bookingdate->format('Y');
            $month = (int)$row->bookingdate->format('n');
            $key = sprintf('%04d-%02d', $year, $month);

            if (!isset($monthly[$key])) {
                $monthly[$key] = (object)[
                    'year' => $year,
                    'month' => $month,
                    'count' => 0,
                    'sum' => 0,
                ];
            }

            $monthly[$key]->count++;
            $monthly[$key]->sum += $row->minutes;
        }

        $bookings = array_values($monthly);

        $this->set(compact('bookings'));
    }
}
