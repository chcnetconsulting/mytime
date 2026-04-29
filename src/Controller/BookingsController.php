<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bookings Controller
 *
 * @property \App\Model\Table\BookingsTable $Bookings
 */
class BookingsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function indexall()
    {
        $query = $this->Bookings->find()->orderBy(['bookingdate' => 'DESC']);
        $bookings = $this->paginate($query);

        $this->set(compact('bookings'));
    }

    public function index() {
        $suche = $this->request->getQuery('table_search');
        if (!is_null($suche) && $suche !== "") {
            $query = $this->Bookings->find()
                ->contain(['Mandanten'])
                ->leftJoinWith('Mandanten')
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
        $booking = $this->Bookings->get($id, contain: ['Mandanten']);
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
	$psps = $this->Bookings->find()->select(['bookingpsp'])->groupBy(['bookingpsp'])->all();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is('post')) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
            if ($this->Bookings->save($booking)) {
                $this->Flash->success(__('The booking has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The booking could not be saved. Please, try again.'));
        }
        $this->set(compact('booking','psps', 'mandanten'));
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
        $booking = $this->Bookings->get($id, contain: []);
        $psps = $this->Bookings->find()->select(['bookingpsp'])->groupBy(['bookingpsp'])->all();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
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
        $booking = $this->Bookings->get($id);
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
	    ->where($dateConditions)
            ->select(['bookingpsp','minutes'=>$this->Bookings->query()->func()->sum('minutes')])
	    ->groupBy(['bookingpsp'])
            ->orderBy(['bookingdate' => 'ASC'])
            ->toArray();

    $activeWorksheet->setCellValue('B'.$i, 'Booking PSP');
    $activeWorksheet->setCellValue('C'.$i, 'Minutes Consolidated');
    $activeWorksheet->setCellValue('D'.$i, 'Hours Consolidated');

    $activeWorksheet->getStyle('B'.$i.':D'.$i)->getFont()->setBold(true);
    $activeWorksheet->getStyle('B'.$i.':D'.$i)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);    
    $activeWorksheet->getStyle('C'.$i.':D'.$i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $i++;
    foreach($results as $row) {
        // Set cell A6 with the Excel date/time value
        $activeWorksheet->setCellValue('B'.$i, $row['bookingpsp']);
        $activeWorksheet->setCellValue('C'.$i, $row['minutes']);
        $activeWorksheet->setCellValue('D'.$i, round($row['minutes']/60, 2));
        $i++;
    }

    $activeWorksheet->getStyle('A1:Z99')
    ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

	$writer = new Xlsx($spreadsheet);
    $tmpname = tempnam(sys_get_temp_dir(), 'xlsx');
	$writer->save($tmpname);
    $this->response = $this->response->withFile(
        $tmpname,
        ['download' => true, 'name' => 'timesheet_christ_'.$year.'-'.str_pad($month, 2, '0',STR_PAD_LEFT).'.xlsx']
    );
    return $this->response;
	exit;
    }

}
