<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\I18n\Date;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bookings Controller
 *
 * @property \App\Model\Table\BookingsTable $Bookings
 */
class BookingsController extends AppController
{
    private function bookingScopeConditions(): array
    {
        if (Configure::read('Auth.disabled') && $this->currentUserId() === null) {
            return [];
        }

        $groupId = $this->currentGroupId();
        if ($groupId !== null) {
            return ['Bookings.group_id' => $groupId];
        }

        $userId = parent::currentUserId();
        if ($userId !== null) {
            return ['Bookings.user_id' => $userId];
        }

        if ($this->currentUserIsAdmin()) {
            return [];
        }

        return ['Bookings.id IS' => null];
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function indexall()
    {
        $query = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->orderBy(['bookingdate' => 'DESC']);
        $bookings = $this->paginate($query);

        $this->set(compact('bookings'));
    }

    public function index() {
        $suche = $this->request->getQuery('table_search');
        if (!is_null($suche) && $suche !== "") {
            $query = $this->Bookings->find()
                ->contain(['Mandanten'])
                ->leftJoinWith('Mandanten')
                ->where($this->bookingScopeConditions())
                ->where([
                    'OR' => [
                        'Bookings.bookingpsp like ' => "%{$suche}%",
                        'Bookings.description like' => "%{$suche}%",
                        'Mandanten.name like' => "%{$suche}%",
                    ],
                ])->orderBy(['Bookings.bookingdate' => 'DESC']);
        } else {
 	    $query = $this->Bookings->find()
                ->contain(['Mandanten'])
                ->where($this->bookingScopeConditions())
	        ->orderBy(['Bookings.bookingdate' => 'DESC']);
        }
        $this->set('suche', $suche);
	$bookings = $this->paginate($query);
        $this->set(compact('bookings'));	
    }

    /**
     * View method
     *
     * @param string|null $id Booking id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $booking = $this->Bookings->find()
            ->contain(['Mandanten'])
            ->where($this->bookingScopeConditions())
            ->where(['Bookings.id' => $id])
            ->firstOrFail();
        $this->set(compact('booking'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
	$booking = $this->Bookings->newEmptyEntity();
	$psps = $this->Bookings->find()
            ->select(['bookingpsp'])
            ->where($this->bookingScopeConditions())
            ->groupBy(['bookingpsp'])
            ->all();
        $tickets = $this->Bookings->find()
            ->select([
                'ticket',
                'last_date' => $this->Bookings->query()->func()->max('bookingdate'),
                'last_psp' => $this->Bookings->query()->newExpr("SUBSTRING_INDEX(MAX(CONCAT(bookingdate, '|', bookingpsp)), '|', -1)"),
                'last_desc' => $this->Bookings->query()->newExpr('SUBSTRING(MAX(CONCAT(bookingdate, description)), 11, 50)'),
            ])
            ->where($this->bookingScopeConditions())
            ->where(['ticket REGEXP' => '^[A-Za-z0-9]'])
            ->groupBy(['ticket'])
            ->orderBy(['last_date' => 'DESC'])
            ->all();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is('post')) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
            $booking->user_id = parent::currentUserId();
            $booking->group_id = $this->currentGroupId();
            if ($this->Bookings->save($booking)) {
                $this->Flash->success(__('The booking has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The booking could not be saved. Please, try again.'));
        }
        $this->set(compact('booking', 'psps', 'tickets', 'mandanten'));
    }

    public function ticketLookup()
    {
        $ticket = trim((string)$this->request->getQuery('ticket', ''));
        if ($ticket === '') {
            $payload = ['found' => false];

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($payload));
        }

        $booking = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where(['ticket' => $ticket])
            ->orderBy(['bookingdate' => 'DESC', 'id' => 'DESC'])
            ->first();

        if (!$booking) {
            $payload = ['found' => false];

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($payload));
        }

        $payload = [
            'found' => true,
            'booking' => [
                'bookingdate' => $booking->bookingdate->i18nFormat('yyyy-MM-dd'),
                'ticket' => $booking->ticket,
                'bookingpsp' => $booking->bookingpsp,
                'mandant_id' => $booking->mandant_id,
                'description' => $booking->description,
                'minutes' => $booking->minutes,
                'kunde' => $booking->kunde,
            ],
        ];

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($payload));
    }

    /**
     * Edit method
     *
     * @param string|null $id Booking id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $booking = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where(['Bookings.id' => $id])
            ->firstOrFail();
        $psps = $this->Bookings->find()
            ->select(['bookingpsp'])
            ->where($this->bookingScopeConditions())
            ->groupBy(['bookingpsp'])
            ->all();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
            $booking->user_id = parent::currentUserId();
            $booking->group_id = $this->currentGroupId();
            if ($this->Bookings->save($booking)) {
                $this->Flash->success(__('The booking has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The booking could not be saved. Please, try again.'));
        }
        $this->set(compact('booking', 'psps', 'mandanten'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Booking id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $booking = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where(['Bookings.id' => $id])
            ->firstOrFail();
        if ($this->Bookings->delete($booking)) {
            $this->Flash->success(__('The booking has been deleted.'));
        } else {
            $this->Flash->error(__('The booking could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /* Generiert ein excelsheet aus den Daten */
    public function genxls($year, $month) {
        $year = filter_var($year, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 2000, 'max_range' => 2100],
        ]);
        $month = filter_var($month, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12],
        ]);

        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid export period.');
        }

	$spreadsheet = new Spreadsheet();
	$activeWorksheet = $spreadsheet->getActiveSheet();
	$activeWorksheet->setCellValue('A1', 'Date');
	$activeWorksheet->setCellValue('A2', 'Ticket');
	$activeWorksheet->setCellValue('A3', 'Booking PSP');
	$activeWorksheet->setCellValue('A4', 'Description');
	$activeWorksheet->setCellValue('A5', 'Minutes');

	$activeWorksheet->getStyle('A1:E1')->getFont()->setBold(true);
	$activeWorksheet->getStyle('A1:E1')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		

        $bookingDate = $this->Bookings->aliasField('bookingdate');
        $dateConditions = function ($exp) use ($bookingDate, $year, $month) {
            return $exp
                ->eq("YEAR($bookingDate)", $year)
                ->eq("MONTH($bookingDate)", $month);
        };

	$results = $this->Bookings->find()
             ->where($this->bookingScopeConditions())
			 ->where($dateConditions)
			 ->orderBy(['bookingdate' => 'ASC'])
			 ->toArray();

    $activeWorksheet->setCellValue('A1', 'Date');
    $activeWorksheet->getColumnDimension('A')->setWidth(10);
    $activeWorksheet->setCellValue('B1', 'Ticket');
    $activeWorksheet->getColumnDimension('B')->setWidth(15);
    $activeWorksheet->setCellValue('C1', 'Booking PSP');
    $activeWorksheet->getColumnDimension('C')->setWidth(20);
    $activeWorksheet->setCellValue('D1', 'Description');
    $activeWorksheet->getColumnDimension('D')->setWidth(50);
    $activeWorksheet->getStyle('D')->getAlignment()->setWrapText(true);
    $activeWorksheet->setCellValue('E1', 'Minutes');


    $i = 2;
	foreach($results as $row) {
        // Set cell A6 with the Excel date/time value
        $activeWorksheet->setCellValue('A'.$i, $row['bookingdate']->i18nFormat('yyyy-MM-dd'));
        $activeWorksheet->setCellValue('B'.$i, trim($row['ticket']));
        $activeWorksheet->setCellValue('C'.$i, trim($row['bookingpsp']));
        $activeWorksheet->setCellValue('D'.$i, trim($row['description']));
        $activeWorksheet->setCellValue('E'.$i, $row['minutes']);
        $i++;
	}
    $i = $i + 2;

    $results = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
	    ->where($dateConditions)
            ->select(['bookingpsp','minutes'=>$this->Bookings->query()->func()->sum('minutes')])
	    ->groupBy(['bookingpsp'])
            ->orderBy(['bookingpsp' => 'ASC'])
            ->toArray();

    $activeWorksheet->setCellValue('B'.$i, 'Booking PSP');
    $activeWorksheet->setCellValue('C'.$i, 'Minutes Consolidated');
    $activeWorksheet->setCellValue('D'.$i, 'Hours Consolidated');

    $activeWorksheet->getStyle('B'.$i.':D'.$i)->getFont()->setBold(true);
    $activeWorksheet->getStyle('B'.$i.':D'.$i)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);    
    $activeWorksheet->getStyle('C'.$i.':D'.$i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $i++;
    $totalMinutes = 0;
    foreach($results as $row) {
        $activeWorksheet->setCellValue('B'.$i, $row['bookingpsp']);
        $activeWorksheet->setCellValue('C'.$i, $row['minutes']);
        $activeWorksheet->setCellValue('D'.$i, round($row['minutes']/60, 2));
        $totalMinutes += $row['minutes'];
        $i++;
    }

    $activeWorksheet->getStyle('B'.($i-1).':D'.($i-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $activeWorksheet->setCellValue('B'.$i, 'Total');
    $activeWorksheet->setCellValue('C'.$i, $totalMinutes);
    $activeWorksheet->setCellValue('D'.$i, round($totalMinutes / 60, 2));
    $activeWorksheet->getStyle('B'.$i.':D'.$i)->getFont()->setBold(true);
    $activeWorksheet->getStyle('C'.$i.':D'.$i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

    $activeWorksheet->getStyle('A1:Z99')
    ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

	$writer = new Xlsx($spreadsheet);
    $tmpname = tempnam(sys_get_temp_dir(), 'xlsx');
	$writer->save($tmpname);
    $this->response = $this->response->withFile(
        $tmpname,
        ['download' => true, 'name' => sprintf('timesheet_%d-%02d.xlsx', $year, $month)]
    );
    return $this->response;
	exit;
    }

    public function genpdf($year, $month)
    {
        $year = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        $month = filter_var($month, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);

        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid export period.');
        }

        $userId = $this->currentUserId();
        $userRecord = $userId ? $this->fetchTable('Users')
            ->find()->select(['username', 'first_name', 'last_name'])
            ->where(['id' => $userId])->first() : null;
        $username = trim(($userRecord?->first_name ?? '') . ' ' . ($userRecord?->last_name ?? ''))
            ?: ($userRecord?->username ?? '');
        $monthLabel = Date::create($year, $month, 1)->i18nFormat('MMMM yyyy');
        $title = "timesheet {$username} {$monthLabel}";

        $bookingDate = $this->Bookings->aliasField('bookingdate');
        $dateConditions = function ($exp) use ($bookingDate, $year, $month) {
            return $exp
                ->eq("YEAR($bookingDate)", $year)
                ->eq("MONTH($bookingDate)", $month);
        };

        $results = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where($dateConditions)
            ->orderBy(['bookingdate' => 'ASC'])
            ->toArray();

        $pspResults = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where($dateConditions)
            ->select(['bookingpsp', 'minutes' => $this->Bookings->query()->func()->sum('minutes')])
            ->groupBy(['bookingpsp'])
            ->orderBy(['bookingpsp' => 'ASC'])
            ->toArray();

        $totalMinutes = array_sum(array_map(fn($r) => $r->minutes, $pspResults));

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

        $html .= '<table>';
        $html .= '<thead><tr><th>Date</th><th>Ticket</th><th>PSP</th><th>Description</th><th class="right">Minutes</th></tr></thead><tbody>';
        foreach ($results as $row) {
            $html .= '<tr>'
                . '<td>' . h($row->bookingdate->i18nFormat('dd.MM.yyyy')) . '</td>'
                . '<td>' . h(trim($row->ticket)) . '</td>'
                . '<td>' . h(trim($row->bookingpsp)) . '</td>'
                . '<td>' . h(trim($row->description)) . '</td>'
                . '<td class="right">' . $row->minutes . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<table class="summary">';
        $html .= '<thead><tr><th>Booking PSP</th><th class="right">Minutes</th><th class="right">Hours</th></tr></thead><tbody>';
        foreach ($pspResults as $row) {
            $html .= '<tr>'
                . '<td>' . h($row->bookingpsp) . '</td>'
                . '<td class="right">' . $row->minutes . '</td>'
                . '<td class="right">' . round($row->minutes / 60, 2) . '</td>'
                . '</tr>';
        }
        $html .= '<tr class="total">'
            . '<td>Total</td>'
            . '<td class="right">' . $totalMinutes . '</td>'
            . '<td class="right">' . round($totalMinutes / 60, 2) . '</td>'
            . '</tr>';
        $html .= '</tbody></table>';

        $mpdf = new Mpdf([
            'orientation' => 'L',
            'margin_top'    => 22,
            'margin_bottom' => 15,
        ]);

        $mpdf->SetHTMLHeader(
            '<div style="text-align:center;font-weight:bold;font-size:11pt;">' . h($title) . '</div>'
        );
        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:9pt;">- {PAGENO} -</div>'
        );

        $mpdf->WriteHTML($html);

        $filename = sprintf('timesheet_%d-%02d.pdf', $year, $month);
        $this->response = $this->response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($mpdf->Output('', 'S'));

        return $this->response;
    }

}
