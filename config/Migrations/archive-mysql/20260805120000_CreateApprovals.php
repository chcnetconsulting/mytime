<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Speichert das je Monat vom Kunden freigegebene Approval (z. B. die E-Mail
 * "Approved" als PDF) direkt in der Datenbank. Bewusst als BLOB und nicht im
 * Dateisystem, weil die App im Cluster kein persistentes Volume hat und
 * Dateien einen Pod-Restart nicht überleben würden.
 */
class CreateApprovals extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('approvals');
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('mandant_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('year', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('month', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('filename', 'string', ['null' => false, 'limit' => 255])
            ->addColumn('mime', 'string', ['null' => false, 'limit' => 100, 'default' => 'application/pdf'])
            // 16 MB (MySQL: MEDIUMBLOB) — reicht für Approval-PDFs weit aus.
            // Typ 'binary' ist der CakePHP-Schema-Typ für BLOB-Spalten.
            ->addColumn('content', 'binary', ['null' => false, 'limit' => 16777215])
            ->addColumn('byte_size', 'integer', ['null' => false, 'default' => 0, 'signed' => false])
            ->addColumn('uploaded_by', 'string', ['null' => true, 'limit' => 255])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            // Ein Approval je Nutzer/Mandant/Monat; erneuter Upload überschreibt.
            ->addIndex(['user_id', 'mandant_id', 'year', 'month'], [
                'unique' => true,
                'name' => 'approvals_period_unique',
            ])
            ->create();
    }
}
