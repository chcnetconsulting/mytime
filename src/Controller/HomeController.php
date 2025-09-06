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
        $query = $this->fetchTable('Bookings');
        $bookings = $query->find()->groupBy(
            ['year(bookingdate)', 'month(bookingdate)']
        )->select(
            [
                'year' => 'year(bookingdate)',
                'month' => 'month(bookingdate)',
                'count' => 'count(id)',
                'sum' => 'sum(minutes)'
            ]
        )->order(['year(bookingdate)'=>'DESC', 'month(bookingdate)'=>'DESC']);

        $this->set(compact('bookings'));
    }
}

