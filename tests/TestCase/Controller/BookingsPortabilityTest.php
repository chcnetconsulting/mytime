<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Prueft die Stellen, an denen BookingsController frueher MySQL-eigene
 * SQL-Ausdruecke benutzt hat: die Ticket- und PSP-Listen (GROUP_CONCAT,
 * SUBSTRING_INDEX, REGEXP), die Suche (Kollation) und die Monatsabgrenzung
 * der Exporte (YEAR()/MONTH()).
 *
 * Die Suite laeuft standardmaessig auf SQLite. Damit ist sie zugleich der
 * Nachweis, dass die Abfragen keinen Dialekt mehr voraussetzen: das alte
 * "ticket REGEXP" liess diese Tests auf SQLite gar nicht erst durchlaufen.
 * Gegen echtes PostgreSQL laufen dieselben Tests ueber
 * DATABASE_TEST_URL (siehe docs/testing.md).
 */
class BookingsPortabilityTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Bookings',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 1,
                    'group_id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ],
        ]);
    }

    /**
     * Legt eine zusaetzliche Buchung fuer Nutzer 1 / Gruppe 1 an.
     */
    private function addBooking(array $data): void
    {
        $bookings = $this->getTableLocator()->get('Bookings');
        $booking = $bookings->newEntity($data + [
            'user_id' => 1,
            'group_id' => 1,
            'mandant_id' => 1,
            'kunde' => 'ACME',
        ]);
        $bookings->saveOrFail($booking);
    }

    /**
     * @return array<string, \Cake\ORM\Entity>
     */
    private function ticketsByName(): array
    {
        $out = [];
        foreach ((array)$this->viewVariable('tickets') as $ticket) {
            $out[$ticket->ticket] = $ticket;
        }

        return $out;
    }

    /**
     * Platzhalter wie "-" oder "." sind keine Tickets und gehoeren nicht in
     * die Auswahlliste. Das erledigte frueher "ticket REGEXP '^[A-Za-z0-9]'".
     */
    public function testTicketListSkipsPlaceholderTickets(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-09-10',
            'ticket' => '-',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Platzhalter',
            'minutes' => 15,
        ]);
        $this->addBooking([
            'bookingdate' => '2025-09-11',
            'ticket' => '.',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Noch ein Platzhalter',
            'minutes' => 15,
        ]);

        $this->get('/bookings/add');
        $this->assertResponseOk();

        $tickets = $this->ticketsByName();
        $this->assertArrayNotHasKey('-', $tickets);
        $this->assertArrayNotHasKey('.', $tickets);
        $this->assertArrayHasKey('MYT-1', $tickets);
    }

    /**
     * Je Ticket zaehlt die juengste Buchung. Frueher ueber
     * SUBSTRING_INDEX(MAX(CONCAT(bookingdate, '|', bookingpsp)), '|', -1)
     * geloest — ein Ausdruck, den PostgreSQL nicht kennt.
     */
    public function testTicketListTakesValuesFromNewestBooking(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-10-05',
            'ticket' => 'MYT-1',
            'bookingpsp' => 'PSP-LATER',
            'description' => 'Juengster Eintrag',
            'minutes' => 20,
        ]);

        $this->get('/bookings/add');
        $this->assertResponseOk();

        $ticket = $this->ticketsByName()['MYT-1'];
        $this->assertSame('2025-10-05', $ticket->last_date);
        $this->assertSame('PSP-LATER', $ticket->last_psp);
        $this->assertSame('Juengster Eintrag', $ticket->last_desc);
    }

    /**
     * Bei gleichem Datum entscheidet die hoehere id — ohne diesen Tiebreak
     * waere das Ergebnis von der Reihenfolge der Datenbank abhaengig und
     * zwischen MySQL und PostgreSQL verschieden.
     */
    public function testTicketListBreaksTiesOnSameDayByNewestRow(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-09-02',
            'ticket' => 'MYT-1',
            'bookingpsp' => 'PSP-SAME-DAY',
            'description' => 'Zweite Buchung am selben Tag',
            'minutes' => 10,
        ]);

        $this->get('/bookings/add');
        $this->assertResponseOk();

        $ticket = $this->ticketsByName()['MYT-1'];
        $this->assertSame('PSP-SAME-DAY', $ticket->last_psp);
    }

    /**
     * Die Mandantenliste je Ticket ist der kommaseparierte String, den das
     * JavaScript in templates/Bookings/add.php per split(",") zerlegt.
     * Frueher CAST(GROUP_CONCAT(DISTINCT mandant_id) AS CHAR).
     */
    public function testTicketListCollectsEveryMandantWithoutDuplicates(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-09-20',
            'ticket' => 'MYT-1',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Zweiter Mandant',
            'minutes' => 30,
            'mandant_id' => 2,
        ]);
        // Wiederholung desselben Mandanten darf die Liste nicht verlaengern.
        $this->addBooking([
            'bookingdate' => '2025-09-21',
            'ticket' => 'MYT-1',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Nochmal Mandant 2',
            'minutes' => 30,
            'mandant_id' => 2,
        ]);

        $this->get('/bookings/add');
        $this->assertResponseOk();

        $ids = explode(',', (string)$this->ticketsByName()['MYT-1']->mandanten);
        sort($ids);
        $this->assertSame(['1', '2'], $ids);
    }

    /**
     * Dasselbe fuer die PSP-Liste, die add() und edit() gemeinsam nutzen.
     */
    public function testPspListCollectsEveryMandantWithoutDuplicates(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-09-20',
            'ticket' => 'MYT-9',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Anderer Mandant auf bekanntem PSP',
            'minutes' => 30,
            'mandant_id' => 2,
        ]);

        $this->get('/bookings/edit/1');
        $this->assertResponseOk();

        $psps = [];
        foreach ((array)$this->viewVariable('psps') as $psp) {
            $psps[$psp->bookingpsp] = $psp;
        }
        $this->assertArrayHasKey('PSP-CORE', $psps);
        $ids = explode(',', (string)$psps['PSP-CORE']->mandanten);
        sort($ids);
        $this->assertSame(['1', '2'], $ids);
    }

    /**
     * Der wichtigste Test dieser Datei.
     *
     * MySQL suchte mit der Kollation utf8mb4_*_ci ohne Ruecksicht auf Gross-
     * und Kleinschreibung. PostgreSQLs LIKE tut das nicht. Ohne das
     * LOWER()-Paar in index() faende die Suche nach der Umstellung
     * stillschweigend weniger — ohne Fehlermeldung, ohne dass es jemandem
     * auffiele.
     *
     * @dataProvider searchTermProvider
     */
    public function testSearchIgnoresCase(string $term, string $expectedTicket): void
    {
        $this->get('/bookings?table_search=' . urlencode($term));

        $this->assertResponseOk();
        $this->assertResponseContains($expectedTicket);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function searchTermProvider(): array
    {
        return [
            // PSP-Feld, in beiden Schreibweisen
            'PSP gross' => ['PSP-CORE', 'MYT-1'],
            'PSP klein' => ['psp-core', 'MYT-1'],
            'PSP gemischt' => ['Psp-CoRe', 'MYT-1'],
            // Beschreibung
            'Beschreibung gross' => ['PLANNING', 'MYT-1'],
            'Beschreibung klein' => ['planning', 'MYT-1'],
            // Mandantenname (ueber den LEFT JOIN)
            'Mandant gross' => ['ACME', 'MYT-1'],
            'Mandant klein' => ['acme', 'MYT-1'],
        ];
    }

    /**
     * Die Suche darf nicht plötzlich alles finden: ein Begriff, den es nicht
     * gibt, liefert weiterhin nichts.
     */
    public function testSearchStillExcludesNonMatches(): void
    {
        $this->get('/bookings?table_search=' . urlencode('gibtesnicht'));

        $this->assertResponseOk();
        $this->assertResponseNotContains('MYT-1');
        $this->assertResponseNotContains('MYT-2');
    }

    /**
     * Der Excel-Export grenzte den Monat frueher mit YEAR()/MONTH() ab, jetzt
     * mit einem Bereichsfilter. Beide Randtage muessen drin sein und die
     * Nachbartage draussen — ein Fehler um einen Tag faellt sonst erst beim
     * Kunden auf.
     */
    public function testXlsExportCoversTheWholeMonthAndNothingBeyond(): void
    {
        $this->addBooking([
            'bookingdate' => '2025-09-01',
            'ticket' => 'RAND-ERSTER',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Erster Tag im Monat',
            'minutes' => 11,
        ]);
        $this->addBooking([
            'bookingdate' => '2025-09-30',
            'ticket' => 'RAND-LETZTER',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Letzter Tag im Monat',
            'minutes' => 22,
        ]);
        $this->addBooking([
            'bookingdate' => '2025-08-31',
            'ticket' => 'RAND-DAVOR',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Tag davor',
            'minutes' => 33,
        ]);
        $this->addBooking([
            'bookingdate' => '2025-10-01',
            'ticket' => 'RAND-DANACH',
            'bookingpsp' => 'PSP-CORE',
            'description' => 'Tag danach',
            'minutes' => 44,
        ]);

        $this->get('/bookings/genxls/2025/9');
        $this->assertResponseOk();

        $tickets = $this->xlsxTicketColumn((string)$this->_response->getBody());

        $this->assertContains('RAND-ERSTER', $tickets);
        $this->assertContains('RAND-LETZTER', $tickets);
        $this->assertNotContains('RAND-DAVOR', $tickets);
        $this->assertNotContains('RAND-DANACH', $tickets);
    }

    /**
     * Liest Spalte B (Ticket) aus der erzeugten Arbeitsmappe zurueck. Der
     * Export soll nicht nur ohne Fehler laufen, sondern die richtigen Zeilen
     * enthalten — das laesst sich nur am fertigen Dokument pruefen.
     *
     * @return list<string>
     */
    private function xlsxTicketColumn(string $body): array
    {
        $file = TMP . 'test-export-' . uniqid() . '.xlsx';
        file_put_contents($file, $body);

        try {
            $sheet = IOFactory::load($file)->getActiveSheet();
            $tickets = [];
            foreach ($sheet->getRowIterator(2) as $row) {
                $value = (string)$sheet->getCell('B' . $row->getRowIndex())->getValue();
                if ($value !== '') {
                    $tickets[] = $value;
                }
            }

            return $tickets;
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
