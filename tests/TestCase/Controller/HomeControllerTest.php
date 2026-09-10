<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\HomeController Test Case
 *
 * @link \App\Controller\HomeController
 */
class HomeControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
        'app.Mandanten',
        'app.Bookings',
        'app.Approvals',
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \App\Controller\HomeController::index()
     */
    public function testIndex(): void
    {
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $this->get('/home');

        $this->assertResponseOk();
        // Auf das Ziel des Downloads pruefen statt auf seine Beschriftung: die
        // Monatszeile traegt inzwischen den ausgeschriebenen Monatsnamen, und
        // der haengt an der eingestellten Sprache. Die URL tut das nicht.
        $this->assertResponseContains('/bookings/genxls/2025/9');
        $this->assertResponseContains('150 Minuten');
        $this->assertResponseContains('2.5 Stunden');
        // Buchung 3 gehoert einem anderen Nutzer in einer anderen Gruppe —
        // ihr August darf hier nicht auftauchen.
        $this->assertResponseNotContains('/bookings/genxls/2025/8');
    }

    /**
     * Zwei Konten derselben Gruppe sehen dieselben Buchungen — und damit auch
     * dieselben Approvals, egal mit welchem Konto sie hochgeladen wurden.
     */
    public function testGroupMembersSeeEachOthersApprovals(): void
    {
        $this->storeApproval(1, 2025, 9);
        $this->loginAs(2, 'second@example.com');

        $this->get('/home');

        $this->assertResponseOk();
        $this->assertResponseContains('/bookings/download-approval/2025/9');
    }

    /**
     * Die Gruppe ist zugleich die Grenze: ein Approval aus einer fremden Gruppe
     * bleibt unsichtbar, auch wenn der Monat in der eigenen Liste steht.
     */
    public function testApprovalsOfOtherGroupsStayHidden(): void
    {
        $this->storeApproval(1, 2025, 8);
        $this->loginAs(3, 'third@example.com');

        $this->get('/home');

        $this->assertResponseOk();
        $this->assertResponseContains('/bookings/genxls/2025/8');
        $this->assertResponseNotContains('/bookings/download-approval/2025/8');
    }

    private function loginAs(int $userId, string $email): void
    {
        $this->session(['Auth' => ['User' => ['id' => $userId, 'email' => $email]]]);
    }

    /**
     * Legt ein Approval ohne Mandant an, wie es der Upload unter "Alle
     * Mandanten" tut.
     */
    private function storeApproval(int $userId, int $year, int $month): void
    {
        $approvals = $this->getTableLocator()->get('Approvals');
        $approvals->saveOrFail($approvals->newEntity([
            'user_id' => $userId,
            'mandant_id' => null,
            'year' => $year,
            'month' => $month,
            'filename' => 'approval.pdf',
            'mime' => 'application/pdf',
            'content' => '%PDF-1.7 test',
            'byte_size' => 13,
        ]));
    }
}
