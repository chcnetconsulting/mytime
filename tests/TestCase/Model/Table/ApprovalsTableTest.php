<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ApprovalsTable;
use Cake\TestSuite\TestCase;

/**
 * Approvals speichern das Freigabe-PDF des Kunden als Binaerinhalt in der
 * Datenbank. Das ist die Spalte, die beim Wechsel von MySQL auf PostgreSQL
 * ihren Typ wechselt: aus MEDIUMBLOB wird BYTEA.
 *
 * Beide Datenbanken erreichen die Spalte ueber PDO::PARAM_LOB, geben den Wert
 * beim Lesen aber unterschiedlich zurueck — mal als String, mal als
 * Stream-Ressource. Die Tests hier pruefen deshalb nicht nur, dass etwas
 * ankommt, sondern dass Byte fuer Byte dasselbe ankommt.
 */
class ApprovalsTableTest extends TestCase
{
    protected ApprovalsTable $Approvals;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Approvals',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->Approvals = $this->getTableLocator()->get('Approvals');
    }

    protected function tearDown(): void
    {
        unset($this->Approvals);
        parent::tearDown();
    }

    /**
     * Liest den Inhalt so aus, wie es auch die Anwendung tut: der Treiber darf
     * einen String oder eine Stream-Ressource liefern.
     *
     * ⚠ Nur EINMAL je gelesener Entity aufrufen. Kommt der Wert als Stream,
     * steht der Zeiger danach am Ende und jeder weitere Aufruf liefert einen
     * leeren String. Die Anwendung macht es richtig (je ein Aufruf pro
     * Download in ApiController und BookingsController) — ein Test, der den
     * Wert zweimal abholt, prueft dagegen nur noch sich selbst.
     */
    private function contentOf(mixed $value): string
    {
        return is_resource($value) ? (string)stream_get_contents($value) : (string)$value;
    }

    private function saveApproval(string $content, ?int $mandantId = 1): int
    {
        $approval = $this->Approvals->newEmptyEntity();
        $approval = $this->Approvals->patchEntity($approval, [
            'user_id' => 1,
            'mandant_id' => $mandantId,
            'year' => 2025,
            'month' => 9,
            'filename' => 'approval.pdf',
            'mime' => 'application/pdf',
            'content' => $content,
            'byte_size' => strlen($content),
            'uploaded_by' => 'test@example.com',
        ]);
        $this->Approvals->saveOrFail($approval);

        return (int)$approval->id;
    }

    /**
     * Der eigentliche Rundlauf: echte Binaerbytes, keine Textzeichen.
     * Enthaelt bewusst ein Nullbyte, hohe Bytes und Backslash-Folgen, weil
     * genau daran ein falsch behandelter Binaertyp scheitert.
     */
    public function testStoresBinaryContentByteForByte(): void
    {
        $content = "%PDF-1.7\n"
            . "\x00\x01\x02\xFF\xFE"
            . "\\r\\n literal backslash-r-n"
            . "\r\n echte Zeilenschaltung"
            . random_bytes(512)
            . "\n%%EOF";

        $id = $this->saveApproval($content);
        // Frisch aus der Datenbank lesen, nicht die noch im Speicher liegende
        // Entity — sonst prueft der Test nur PHP, nicht die Rundreise.
        $this->Approvals->getConnection()->getDriver()->disconnect();
        $stored = $this->Approvals->get($id);
        $roundtripped = $this->contentOf($stored->content);

        $this->assertSame(strlen($content), strlen($roundtripped));
        $this->assertSame(md5($content), md5($roundtripped));
        $this->assertSame($content, $roundtripped);
        $this->assertSame(strlen($content), $stored->byte_size);
    }

    /**
     * byte_size ist der Wert, gegen den sich der Download pruefen laesst.
     * Er muss zur tatsaechlichen Laenge des gespeicherten Inhalts passen.
     */
    public function testByteSizeMatchesStoredLength(): void
    {
        $content = random_bytes(4096);
        $id = $this->saveApproval($content);

        $stored = $this->Approvals->get($id);
        $this->assertSame(4096, $stored->byte_size);
        $this->assertSame($stored->byte_size, strlen($this->contentOf($stored->content)));
    }

    /**
     * findForPeriod sucht mit "mandant_id IS NULL". Das muss einen Datensatz
     * ohne Mandant finden und darf keinen mit Mandant erwischen.
     */
    public function testFindForPeriodDistinguishesNullMandant(): void
    {
        $withMandant = $this->saveApproval('mit-mandant', 1);
        $withoutMandant = $this->saveApproval('ohne-mandant', null);

        $found = $this->Approvals->findForPeriod(1, null, 2025, 9)->first();
        $this->assertNotNull($found);
        $this->assertSame($withoutMandant, (int)$found->id);

        $found = $this->Approvals->findForPeriod(1, 1, 2025, 9)->first();
        $this->assertNotNull($found);
        $this->assertSame($withMandant, (int)$found->id);
    }

    /**
     * Der zusammengesetzte Eindeutigkeitsindex greift nicht, wenn mandant_id
     * NULL ist: zwei NULL-Werte gelten in SQL nie als gleich. Genau danach
     * sucht findForPeriod() aber. Die Baseline-Migration legt deshalb auf
     * PostgreSQL zusaetzlich einen Teilindex an.
     *
     * Der Test prueft das Verhalten und nicht die Existenz des Index — der
     * Guard in der Migration hing schon einmal am falschen Bezeichner
     * ('postgres' statt 'pgsql') und lief lautlos ins Leere. Ein Test auf das
     * Ergebnis faellt darauf nicht herein.
     */
    public function testSecondApprovalWithoutMandantIsRejected(): void
    {
        $connection = $this->Approvals->getConnection();
        if (!$connection->getDriver() instanceof \Cake\Database\Driver\Postgres) {
            $this->markTestSkipped('Teilindex nur auf PostgreSQL — dort laeuft auch die Produktion.');
        }

        $this->saveApproval('erstes', null);

        $this->expectException(\Cake\Database\Exception\QueryException::class);
        $this->saveApproval('zweites', null);
    }

    /**
     * Der rohe Inhalt darf nie in einer Array-/JSON-Ausgabe landen — sonst
     * steckt ein komplettes PDF in einer API-Antwort oder einem Log.
     */
    public function testContentIsHiddenFromArrayOutput(): void
    {
        $id = $this->saveApproval('geheim');
        $array = $this->Approvals->get($id)->toArray();

        $this->assertArrayNotHasKey('content', $array);
        $this->assertArrayHasKey('byte_size', $array);
    }
}
