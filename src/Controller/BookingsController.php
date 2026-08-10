<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TimesheetPdfService;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;
use Cake\ORM\Entity;
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

    private function queryMandantId(): ?int
    {
        $raw = $this->request->getQuery('mandant_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $parsed = filter_var($raw, FILTER_VALIDATE_INT);

        return ($parsed !== false && $parsed > 0) ? $parsed : null;
    }

    /**
     * PSP-Auswahlliste: je PSP die Mandanten, unter denen er schon gebucht
     * wurde, kommasepariert. Das JavaScript in templates/Bookings/{add,edit}.php
     * splittet diesen String und blendet damit Optionen aus, die nicht zum
     * gewaehlten Mandanten gehoeren.
     *
     * Frueher eine GROUP_CONCAT-Aggregation. Die gibt es in PostgreSQL nicht,
     * und string_agg haette den Code an einen Dialekt gebunden. Bei rund 500
     * Buchungen ist das Zusammenfassen in PHP ohnehin billiger als das Aggregat.
     *
     * @return array<\Cake\ORM\Entity>
     */
    private function pspOptions(): array
    {
        $rows = $this->Bookings->find()
            ->select(['bookingpsp', 'mandant_id'])
            ->where($this->bookingScopeConditions())
            ->orderBy(['Bookings.bookingpsp' => 'ASC'])
            ->disableHydration()
            ->toArray();

        $byPsp = [];
        foreach ($rows as $row) {
            $psp = (string)$row['bookingpsp'];
            $byPsp[$psp] ??= [];
            if ($row['mandant_id'] !== null) {
                $byPsp[$psp][(int)$row['mandant_id']] = true;
            }
        }

        $out = [];
        foreach ($byPsp as $psp => $mandantIds) {
            $out[] = new Entity([
                'bookingpsp' => $psp,
                'mandanten' => implode(',', array_keys($mandantIds)),
            ], ['markClean' => true]);
        }

        return $out;
    }

    /**
     * Ticket-Auswahlliste: je Ticket die juengste Buchung (Datum, PSP,
     * Beschreibung) plus alle Mandanten, unter denen es gebucht wurde.
     *
     * Ersetzt drei MySQL-Eigenheiten auf einmal: SUBSTRING_INDEX/MAX(CONCAT())
     * als "letzter Wert je Gruppe", GROUP_CONCAT fuer die Mandantenliste und
     * den REGEXP-Operator, mit dem Platzhalter-Tickets wie "-" oder "."
     * herausgefiltert wurden. Keines davon kennt PostgreSQL unter diesem Namen.
     *
     * Nebeneffekt: der alte SUBSTRING(..., 11, 50) schnitt die Beschreibung
     * nach 50 *Bytes* ab und konnte dabei Umlaute zerlegen. Das Kuerzen macht
     * ohnehin die View mit mb_substr().
     *
     * @return array<\Cake\ORM\Entity>
     */
    private function ticketOptions(): array
    {
        $rows = $this->Bookings->find()
            ->select(['ticket', 'bookingdate', 'bookingpsp', 'description', 'mandant_id'])
            ->where($this->bookingScopeConditions())
            ->orderBy(['Bookings.bookingdate' => 'DESC', 'Bookings.id' => 'DESC'])
            ->disableHydration()
            ->toArray();

        $tickets = [];
        foreach ($rows as $row) {
            $ticket = (string)$row['ticket'];
            if (!preg_match('/^[A-Za-z0-9]/', $ticket)) {
                continue;
            }
            if (!isset($tickets[$ticket])) {
                // Erste gesehene Zeile ist dank der Sortierung die juengste.
                $tickets[$ticket] = [
                    'ticket' => $ticket,
                    'last_date' => $row['bookingdate']?->format('Y-m-d') ?? '',
                    'last_psp' => (string)$row['bookingpsp'],
                    'last_desc' => (string)$row['description'],
                    'mandanten' => [],
                ];
            }
            if ($row['mandant_id'] !== null) {
                $tickets[$ticket]['mandanten'][(int)$row['mandant_id']] = true;
            }
        }

        // Die Reihenfolge stammt aus der Abfrage: das Ticket mit der juengsten
        // Buchung zuerst — wie das fruehere ORDER BY last_date DESC.
        $out = [];
        foreach ($tickets as $data) {
            $data['mandanten'] = implode(',', array_keys($data['mandanten']));
            $out[] = new Entity($data, ['markClean' => true]);
        }

        return $out;
    }

    private function exportFilename(string $ext, ?string $mandantName, int $year, int $month): string
    {
        $userId = $this->currentUserId();
        $userRecord = $userId ? $this->fetchTable('Users')
            ->find()->select(['username', 'first_name', 'last_name'])
            ->where(['id' => $userId])->first() : null;
        $first = trim((string)($userRecord?->first_name ?? ''));
        $last = trim((string)($userRecord?->last_name ?? ''));
        if ($first === '' && $last === '') {
            $first = (string)($userRecord?->username ?? '');
        }

        $sanitize = static function (string $s): string {
            $s = preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?? '';
            return trim($s, '-');
        };

        $parts = ['timesheet'];
        if ($mandantName !== null && $mandantName !== '') {
            $parts[] = $sanitize($mandantName);
        }
        if ($first !== '') {
            $parts[] = $sanitize($first);
        }
        if ($last !== '') {
            $parts[] = $sanitize($last);
        }
        $parts[] = sprintf('%04d', $year);
        $parts[] = sprintf('%02d', $month);

        return implode('_', array_filter($parts, static fn($p) => $p !== '')) . '.' . $ext;
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
        $selectedMandantId = $this->queryMandantId();

        $mandantIds = $this->Bookings->find()
            ->select(['mandant_id'])
            ->where($this->bookingScopeConditions())
            ->where(['Bookings.mandant_id IS NOT' => null])
            ->groupBy(['Bookings.mandant_id'])
            ->all()
            ->extract('mandant_id')
            ->toList();

        $mandanten = $mandantIds
            ? $this->Bookings->Mandanten->find('list')
                ->where(['id IN' => $mandantIds])
                ->orderBy(['name' => 'ASC'])
                ->toArray()
            : [];

        if ($selectedMandantId !== null && !isset($mandanten[$selectedMandantId])) {
            $selectedMandantId = null;
        }

        if (!is_null($suche) && $suche !== "") {
            $query = $this->Bookings->find()
                ->contain(['Mandanten'])
                ->leftJoinWith('Mandanten')
                ->where($this->bookingScopeConditions());

            // Beidseitig kleinschreiben, statt sich auf die Kollation zu
            // verlassen: MySQL suchte mit utf8mb4_*_ci unabhaengig von der
            // Gross-/Kleinschreibung, PostgreSQLs LIKE ist dagegen immer
            // exakt. Ohne das hier faende die Suche nach der Umstellung
            // stillschweigend weniger — ILIKE scheidet aus, weil SQLite (die
            // Testdatenbank) es nicht kennt.
            $needle = '%' . mb_strtolower($suche) . '%';
            $lower = static fn($q, string $field) => $q->func()->lower([$field => 'identifier']);
            $query->where(function ($exp, $q) use ($lower, $needle) {
                return $exp->or([
                    $q->expr()->like($lower($q, 'Bookings.bookingpsp'), $needle),
                    $q->expr()->like($lower($q, 'Bookings.description'), $needle),
                    $q->expr()->like($lower($q, 'Mandanten.name'), $needle),
                ]);
            });

            $query->orderBy(['Bookings.bookingdate' => 'DESC']);
        } else {
 	    $query = $this->Bookings->find()
                ->contain(['Mandanten'])
                ->where($this->bookingScopeConditions())
	        ->orderBy(['Bookings.bookingdate' => 'DESC']);
        }

        if ($selectedMandantId !== null) {
            $query->where(['Bookings.mandant_id' => $selectedMandantId]);
        }

        $this->set('suche', $suche);
	$bookings = $this->paginate($query);
        $this->set(compact('bookings', 'mandanten', 'selectedMandantId'));
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
        $session = $this->request->getSession();
        $activeMandantId = $session->read('Bookings.activeMandantId');
        if ($activeMandantId === null) {
            $lastBooking = $this->Bookings->find()
                ->select(['Bookings.mandant_id'])
                ->where($this->bookingScopeConditions())
                ->where(['Bookings.mandant_id IS NOT' => null])
                ->orderBy(['Bookings.bookingdate' => 'DESC', 'Bookings.id' => 'DESC'])
                ->first();
            if ($lastBooking !== null) {
                $activeMandantId = $lastBooking->mandant_id;
            }
        }
        if ($activeMandantId !== null) {
            $booking->mandant_id = (int)$activeMandantId;
        }
        $psps = $this->pspOptions();
        $tickets = $this->ticketOptions();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is('post')) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
            $booking->user_id = parent::currentUserId();
            $booking->group_id = $this->currentGroupId();
            if ($this->Bookings->save($booking)) {
                if ($booking->mandant_id !== null) {
                    $session->write('Bookings.activeMandantId', (int)$booking->mandant_id);
                }
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

        $query = $this->Bookings->find()
            ->where($this->bookingScopeConditions())
            ->where(['ticket' => $ticket]);

        // Restrict the lookup to the active Mandant so a ticket that also exists
        // under another Mandant can never pull in that Mandant's PSP.
        $mandantId = $this->request->getQuery('mandant_id');
        if ($mandantId !== null && $mandantId !== '' && ctype_digit((string)$mandantId)) {
            $query->where(['Bookings.mandant_id' => (int)$mandantId]);
        }

        $booking = $query
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
        $psps = $this->pspOptions();
        $mandanten = $this->Bookings->Mandanten->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $booking = $this->Bookings->patchEntity($booking, $this->request->getData());
            $booking->user_id = parent::currentUserId();
            $booking->group_id = $this->currentGroupId();
            if ($this->Bookings->save($booking)) {
                if ($booking->mandant_id !== null) {
                    $this->request->getSession()->write('Bookings.activeMandantId', (int)$booking->mandant_id);
                }
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

        $mandantId = $this->queryMandantId();
        $mandantName = null;
        if ($mandantId !== null) {
            $mandant = $this->Bookings->Mandanten->find()
                ->select(['id', 'name'])
                ->where(['id' => $mandantId])
                ->first();
            if ($mandant === null) {
                $mandantId = null;
            } else {
                $mandantName = (string)$mandant->name;
            }
        }
        $mandantConditions = $mandantId !== null ? ['Bookings.mandant_id' => $mandantId] : [];

	$spreadsheet = new Spreadsheet();
	$activeWorksheet = $spreadsheet->getActiveSheet();
	$activeWorksheet->setCellValue('A1', 'Date');
	$activeWorksheet->setCellValue('A2', 'Ticket');
	$activeWorksheet->setCellValue('A3', 'Booking PSP');
	$activeWorksheet->setCellValue('A4', 'Description');
	$activeWorksheet->setCellValue('A5', 'Minutes');

	$activeWorksheet->getStyle('A1:E1')->getFont()->setBold(true);
	$activeWorksheet->getStyle('A1:E1')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		

        // Bereichsfilter statt YEAR()/MONTH() — datenbank-portabel und nutzt
        // einen Index auf bookingdate. Dasselbe Muster wie in
        // TimesheetPdfService, damit Excel- und PDF-Export denselben Monat
        // auf dieselbe Weise abgrenzen.
        $firstOfMonth = Date::create($year, $month, 1);
        $lastOfMonth = $firstOfMonth->lastOfMonth();
        $dateConditions = [
            'Bookings.bookingdate >=' => $firstOfMonth->format('Y-m-d'),
            'Bookings.bookingdate <=' => $lastOfMonth->format('Y-m-d'),
        ];

	$results = $this->Bookings->find()
             ->where($this->bookingScopeConditions())
			 ->where($dateConditions)
			 ->where($mandantConditions)
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
	    ->where($mandantConditions)
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
        ['download' => true, 'name' => $this->exportFilename('xlsx', $mandantName, $year, $month)]
    );
    return $this->response;
    }

    public function genpdf($year, $month)
    {
        $year = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        $month = filter_var($month, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);

        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid export period.');
        }

        $mandantId = $this->queryMandantId();
        $mandantName = null;
        if ($mandantId !== null) {
            $mandant = $this->Bookings->Mandanten->find()
                ->select(['id', 'name'])
                ->where(['id' => $mandantId])
                ->first();
            if ($mandant === null) {
                $mandantId = null;
            } else {
                $mandantName = (string)$mandant->name;
            }
        }
        $mandantConditions = $mandantId !== null ? ['Bookings.mandant_id' => $mandantId] : [];

        $userId = $this->currentUserId();
        $userRecord = $userId ? $this->fetchTable('Users')
            ->find()->select(['username', 'first_name', 'last_name'])
            ->where(['id' => $userId])->first() : null;
        $username = trim(($userRecord?->first_name ?? '') . ' ' . ($userRecord?->last_name ?? ''))
            ?: ($userRecord?->username ?? '');
        // Gemeinsame PDF-Erzeugung (auch von der REST-API genutzt).
        $pdf = (new TimesheetPdfService())->render(
            $year,
            $month,
            $this->bookingScopeConditions(),
            $mandantId,
            $username,
            $mandantName
        );

        $filename = $this->exportFilename('pdf', $mandantName, $year, $month);
        $this->response = $this->response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($pdf);

        return $this->response;
    }

    /**
     * Approval-PDF (z. B. die "Approved"-Mail des Kunden) für einen Monat
     * über die Weboberfläche hochladen. Session-Auth; speichert je
     * User/Mandant/Monat genau einen Datensatz (Upsert) als BLOB.
     */
    public function uploadApproval($year, $month)
    {
        $this->request->allowMethod(['post']);
        $year = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        $month = filter_var($month, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid period.');
        }

        $userId = $this->currentUserId();
        if ($userId === null) {
            throw new BadRequestException('No user in session.');
        }
        $mandantId = $this->queryMandantId();

        $file = $this->request->getUploadedFile('approval');
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error('Bitte eine PDF-Datei auswählen.');

            return $this->redirect($this->referer(['action' => 'index']));
        }
        $content = (string)$file->getStream()->getContents();
        if (strncmp($content, '%PDF-', 5) !== 0) {
            $this->Flash->error('Nur PDF-Dateien werden akzeptiert.');

            return $this->redirect($this->referer(['action' => 'index']));
        }
        if (strlen($content) > 16 * 1024 * 1024) {
            $this->Flash->error('Datei zu groß (max. 16 MB).');

            return $this->redirect($this->referer(['action' => 'index']));
        }
        $filename = (string)($file->getClientFilename() ?: 'approval.pdf');
        if (!preg_match('/\.pdf$/i', $filename)) {
            $filename .= '.pdf';
        }

        $Approvals = $this->fetchTable('Approvals');
        $existing = $Approvals->findForPeriod($userId, $mandantId, $year, $month)->first();
        $approval = $existing ?? $Approvals->newEmptyEntity();
        $approval = $Approvals->patchEntity($approval, [
            'user_id' => $userId,
            'mandant_id' => $mandantId,
            'year' => $year,
            'month' => $month,
            'filename' => $filename,
            'mime' => 'application/pdf',
            'content' => $content,
            'byte_size' => strlen($content),
            'uploaded_by' => (string)($this->currentUser()['email'] ?? 'web'),
        ]);

        if ($Approvals->save($approval)) {
            $this->Flash->success('Approval gespeichert.');
        } else {
            $this->Flash->error('Approval konnte nicht gespeichert werden.');
        }

        return $this->redirect($this->referer(['action' => 'index']));
    }

    /**
     * Gespeichertes Approval-PDF eines Monats herunterladen (Session-Auth).
     */
    public function downloadApproval($year, $month)
    {
        $year = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        $month = filter_var($month, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid period.');
        }
        $userId = $this->currentUserId();
        if ($userId === null) {
            throw new BadRequestException('No user in session.');
        }
        $mandantId = $this->queryMandantId();

        $approval = $this->fetchTable('Approvals')
            ->findForPeriod($userId, $mandantId, $year, $month)
            ->select(['filename', 'mime', 'content'])
            ->first();
        if ($approval === null) {
            throw new NotFoundException('Kein Approval für diesen Monat gespeichert.');
        }

        // BLOB kommt beim Lesen als Stream-Resource zurück → in String wandeln.
        return $this->response
            ->withType($approval->mime ?: 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $approval->filename . '"')
            ->withStringBody(\App\Controller\ApiController::binaryToString($approval->content));
    }
}
