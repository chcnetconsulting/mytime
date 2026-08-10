<?php
declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * ApprovalsFixture
 *
 * Bewusst ohne Datensaetze: Approvals sind Binaerinhalte, und die Tests legen
 * sich die Bytes, um die es ihnen jeweils geht, selbst an. Die Fixture sorgt
 * nur dafuer, dass die Tabelle zwischen den Tests geleert wird.
 */
class ApprovalsFixture extends AppFixture
{
    /**
     * @return void
     */
    public function init(): void
    {
        $this->records = [];
        parent::init();
    }
}
