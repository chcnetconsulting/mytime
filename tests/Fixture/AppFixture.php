<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionInterface;
use Cake\TestSuite\Fixture\TestFixture;

/**
 * Gemeinsame Basis aller Fixtures dieser Anwendung.
 *
 * Sie loest ein Problem, das es unter MySQL nicht gab: Fixtures schreiben ihre
 * Datensaetze mit fest vorgegebenen IDs (1, 2, 3 …). MySQL zieht den
 * AUTO_INCREMENT-Zaehler dabei stillschweigend mit — PostgreSQL tut das nicht.
 * Dessen Identity-Sequenz bleibt auf 1 stehen, und die erste Buchung, die die
 * Anwendung anschliessend selbst anlegt, laeuft in
 *
 *     SQLSTATE[23505]: Unique violation … duplicate key value violates
 *     unique constraint "bookings_pkey"
 *
 * Deshalb wird nach jedem Einfuegen die Sequenz auf den hoechsten vergebenen
 * Wert gesetzt. Dieselbe Falle gilt beim einmaligen Uebernehmen der
 * Produktionsdaten — dort erledigt es src/Command/ImportMysqlCommand.php.
 */
abstract class AppFixture extends TestFixture
{
    /**
     * @param \Cake\Datasource\ConnectionInterface $connection Verbindung
     * @return bool
     */
    public function insert(ConnectionInterface $connection): bool
    {
        $result = parent::insert($connection);

        assert($connection instanceof Connection);
        if ($this->records && $connection->getDriver() instanceof \Cake\Database\Driver\Postgres) {
            $table = $this->sourceName();
            // is_called = false, damit nextval() genau diesen Wert liefert.
            // Bei leerer Tabelle waere setval(seq, 0) unzulaessig, daher +1 auf
            // das Maximum und nicht das Maximum selbst.
            $connection->execute(
                "SELECT setval(
                     pg_get_serial_sequence('\"{$table}\"', 'id'),
                     COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1,
                     false
                 )"
            );
        }

        return $result;
    }
}
