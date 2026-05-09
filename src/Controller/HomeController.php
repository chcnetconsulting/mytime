<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
/**
 * Home Controller
 *
 */
class HomeController extends AppController
{
    use LocatorAwareTrait;

    private function groupScopeCondition(): array
    {
        if (Configure::read('Auth.disabled') && $this->currentUserId() === null) {
            return [];
        }

        $groupId = $this->currentGroupId();
        if ($groupId !== null) {
            return ['group_id' => $groupId];
        }

        return ['id IS' => null];
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $bookingsTable = $this->fetchTable('Bookings');
        $scope = $this->groupScopeCondition();

        $rawMandantId = $this->request->getQuery('mandant_id');
        $selectedMandantId = null;
        if ($rawMandantId !== null && $rawMandantId !== '') {
            $parsed = filter_var($rawMandantId, FILTER_VALIDATE_INT);
            if ($parsed !== false && $parsed > 0) {
                $selectedMandantId = $parsed;
            }
        }

        $mandantIds = $bookingsTable->find()
            ->select(['mandant_id'])
            ->where($scope)
            ->where(['mandant_id IS NOT' => null])
            ->groupBy(['mandant_id'])
            ->all()
            ->extract('mandant_id')
            ->toList();

        $mandanten = $mandantIds
            ? $this->fetchTable('Mandanten')->find('list')
                ->where(['id IN' => $mandantIds])
                ->orderBy(['name' => 'ASC'])
                ->toArray()
            : [];

        if ($selectedMandantId !== null && !isset($mandanten[$selectedMandantId])) {
            $selectedMandantId = null;
        }

        $query = $bookingsTable->find()
            ->select(['id', 'bookingdate', 'bookingpsp', 'ticket', 'description', 'minutes'])
            ->where($scope)
            ->orderBy(['bookingdate' => 'DESC']);

        if ($selectedMandantId !== null) {
            $query->where(['mandant_id' => $selectedMandantId]);
        }

        $rows = $query->all();

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
                    'psps' => [],
                    'pspTickets' => [],
                ];
            }

            $monthly[$key]->count++;
            $monthly[$key]->sum += $row->minutes;

            $psp = $row->bookingpsp;
            $monthly[$key]->psps[$psp] = ($monthly[$key]->psps[$psp] ?? 0) + $row->minutes;
            $monthly[$key]->pspTickets[$psp][$row->ticket] = ['id' => $row->id, 'description' => $row->description];
        }

        foreach ($monthly as $m) {
            ksort($m->psps);
            foreach ($m->pspTickets as &$tickets) {
                ksort($tickets);
            }
            unset($tickets);
        }

        $bookings = array_values($monthly);

        $this->set(compact('bookings', 'mandanten', 'selectedMandantId'));
    }
}
