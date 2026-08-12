<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TimesheetPdfService;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * REST-API für Timesheet-PDF und Approval-Dateien.
 *
 * Authentifizierung über einen statischen Bearer-Token (MYTIME_API_TOKEN),
 * NICHT über die OIDC-Session. Der Token handelt fest als Owner-Account
 * (Auth.ownerEmail), weil es ausschließlich um die eigenen Timesheets geht.
 *
 * Endpunkte:
 *   GET  /api/timesheet/{year}/{month}          → Monats-PDF (wie Web-Export)
 *   GET  /api/approval/{year}/{month}           → gespeichertes Approval-PDF
 *   POST /api/approval/{year}/{month}           → Approval-PDF ablegen (Upsert)
 *   GET  /api/approvals                          → JSON-Liste vorhandener Approvals
 *
 * Optionaler Query-Parameter `mandant_id` grenzt auf einen Mandanten ein.
 */
class ApiController extends AppController
{
    /**
     * @param \Cake\Event\EventInterface $event The beforeFilter event.
     * @return \Cake\Http\Response|null
     */
    public function beforeFilter(EventInterface $event)
    {
        // AppController lässt 'Api' bewusst an der Session-Auth vorbei (siehe dort).
        parent::beforeFilter($event);

        $expected = (string)Configure::read('Mytime.apiToken', '');
        if ($expected === '') {
            throw new NotFoundException(); // API absichtlich unsichtbar, solange kein Token gesetzt ist
        }

        $header = (string)$this->request->getHeaderLine('Authorization');
        $token = preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : '';
        if ($token === '' || !hash_equals($expected, $token)) {
            $this->response = $this->response->withStatus(401);
            $event->setResult($this->jsonError('Unauthorized', 401));

            return null;
        }

        return null;
    }

    /**
     * Auth.ownerEmail → Datensatz aus Users. Ohne konfigurierten Owner keine API.
     *
     * @return object User-Record mit id/username/first_name/last_name
     */
    private function owner(): object
    {
        $email = trim((string)Configure::read('Auth.ownerEmail', ''));
        if ($email === '') {
            throw new BadRequestException('No Auth.ownerEmail configured — API acts as the owner account.');
        }
        $user = $this->fetchTable('Users')->find()
            ->select(['id', 'username', 'first_name', 'last_name', 'email'])
            ->where(['email' => $email])
            ->first();
        if ($user === null) {
            throw new NotFoundException('Owner user not found for ' . $email);
        }

        return $user;
    }

    /**
     * Prueft Jahr und Monat aus der URL und gibt sie als Ganzzahlen zurueck.
     *
     * @return array{int, int}
     */
    private function period(mixed $year, mixed $month): array
    {
        $year = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        $month = filter_var($month, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($year === false || $month === false) {
            throw new BadRequestException('Invalid period. Expected /{year}/{month}.');
        }

        return [$year, $month];
    }

    /**
     * Mandant aus dem Query-String, sofern angegeben und vorhanden.
     *
     * @return array{int|null, string|null}
     */
    private function mandant(): array
    {
        $raw = $this->request->getQuery('mandant_id');
        $id = $raw === null || $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT);
        $id = $id !== false && $id !== null && $id > 0 ? $id : null;
        $name = null;
        if ($id !== null) {
            $m = $this->fetchTable('Mandanten')->find()->select(['id', 'name'])->where(['id' => $id])->first();
            if ($m === null) {
                throw new NotFoundException('Mandant not found.');
            }
            $name = (string)$m->name;
        }

        return [$id, $name];
    }

    /**
     * GET /api/timesheet/{year}/{month}
     *
     * @param string|int|null $year Jahr aus der URL.
     * @param string|int|null $month Monat aus der URL.
     */
    public function timesheet(string|int|null $year = null, string|int|null $month = null): Response
    {
        [$year, $month] = $this->period($year, $month);
        [$mandantId, $mandantName] = $this->mandant();
        $owner = $this->owner();

        $service = new TimesheetPdfService();
        $pdf = $service->render(
            $year,
            $month,
            ['Bookings.user_id' => (int)$owner->id],
            $mandantId,
            trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) ?: (string)$owner->username,
            $mandantName,
        );
        $filename = $service->filename(
            $mandantName,
            $owner->first_name,
            $owner->last_name,
            $owner->username,
            $year,
            $month,
        );

        return $this->response
            ->withType('application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($pdf);
    }

    /**
     * GET  /api/approval/{year}/{month} → Download
     * POST /api/approval/{year}/{month} → Upload (Upsert)
     *
     * @param string|int|null $year Jahr aus der URL.
     * @param string|int|null $month Monat aus der URL.
     */
    public function approval(string|int|null $year = null, string|int|null $month = null): Response
    {
        [$year, $month] = $this->period($year, $month);
        [$mandantId] = $this->mandant();
        $owner = $this->owner();
        $Approvals = $this->fetchTable('Approvals');

        if ($this->request->is(['post', 'put'])) {
            [$content, $filename, $mime] = $this->readUpload();

            $existing = $Approvals->findForPeriod((int)$owner->id, $mandantId, $year, $month)->first();
            $approval = $existing ?? $Approvals->newEmptyEntity();
            $approval = $Approvals->patchEntity($approval, [
                'user_id' => (int)$owner->id,
                'mandant_id' => $mandantId,
                'year' => $year,
                'month' => $month,
                'filename' => $filename,
                'mime' => $mime,
                'content' => $content,
                'byte_size' => strlen($content),
                'uploaded_by' => 'api',
            ]);
            if (!$Approvals->save($approval)) {
                throw new BadRequestException('Could not store approval: '
                    . json_encode($approval->getErrors()));
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'ok' => true,
                    'id' => $approval->id,
                    'year' => $year,
                    'month' => $month,
                    'filename' => $approval->filename,
                    'byte_size' => $approval->byte_size,
                    'replaced' => $existing !== null,
                ], JSON_PRETTY_PRINT));
        }

        // GET → Download
        $approval = $Approvals->findForPeriod((int)$owner->id, $mandantId, $year, $month)
            ->select(['filename', 'mime', 'content'])->first();
        if ($approval === null) {
            throw new NotFoundException('No approval stored for this period.');
        }

        return $this->response
            ->withType($approval->mime ?: 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $approval->filename . '"')
            ->withStringBody(self::binaryToString($approval->content));
    }

    /**
     * Die BLOB-Spalte kommt beim Lesen als Stream-Resource zurück (CakePHP
     * BinaryType) — für die Ausgabe in einen String wandeln.
     */
    public static function binaryToString(mixed $value): string
    {
        if (is_resource($value)) {
            return (string)stream_get_contents($value);
        }

        return (string)$value;
    }

    /**
     * GET /api/approvals → JSON-Liste (ohne Dateiinhalt)
     */
    public function approvals(): Response
    {
        $owner = $this->owner();
        $rows = $this->fetchTable('Approvals')->find()
            ->select(['id', 'mandant_id', 'year', 'month', 'filename', 'byte_size', 'uploaded_by', 'modified'])
            ->where(['user_id' => (int)$owner->id])
            ->orderBy(['year' => 'DESC', 'month' => 'DESC'])
            ->all()
            ->map(fn($r) => [
                'id' => $r->id,
                'mandant_id' => $r->mandant_id,
                'year' => $r->year,
                'month' => $r->month,
                'filename' => $r->filename,
                'byte_size' => $r->byte_size,
                'uploaded_by' => $r->uploaded_by,
                'modified' => $r->modified?->format('c'),
            ])
            ->toList();

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(['approvals' => $rows], JSON_PRETTY_PRINT));
    }

    /**
     * Nimmt entweder einen Multipart-Upload (Feld `file`) oder einen rohen
     * Request-Body (application/pdf) entgegen. Akzeptiert nur echte PDFs.
     *
     * @return array{0:string,1:string,2:string} content, filename, mime
     */
    private function readUpload(): array
    {
        $file = $this->request->getUploadedFile('file');
        if ($file !== null) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                throw new BadRequestException('Upload error.');
            }
            $content = (string)$file->getStream()->getContents();
            $filename = (string)($file->getClientFilename() ?: 'approval.pdf');
        } else {
            $content = (string)$this->request->getBody()->getContents();
            $header = $this->request->getHeaderLine('X-Filename');
            $filename = $header !== '' ? basename($header) : 'approval.pdf';
        }

        if ($content === '') {
            throw new BadRequestException('Empty upload.');
        }
        if (strncmp($content, '%PDF-', 5) !== 0) {
            throw new BadRequestException('Only PDF files are accepted.');
        }
        if (strlen($content) > 16 * 1024 * 1024) {
            throw new BadRequestException('File too large (max 16 MB).');
        }
        if (!preg_match('/\.pdf$/i', $filename)) {
            $filename .= '.pdf';
        }

        return [$content, $filename, 'application/pdf'];
    }

    /**
     * Einheitliche Fehlerantwort der REST-API.
     */
    private function jsonError(string $message, int $status): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody((string)json_encode(['error' => $message]));
    }
}
