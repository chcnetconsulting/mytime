<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class GroupsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Groups',
        'app.Users',
    ];

    public function testIndex(): void
    {
        $this->get('/groups');

        $this->assertResponseOk();
        $this->assertResponseContains('Shared');
        $this->assertResponseContains('Solo');
    }

    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->post('/groups/add', ['name' => 'Team A']);

        $this->assertRedirect(['controller' => 'Groups', 'action' => 'index']);
        $groups = $this->getTableLocator()->get('Groups');
        $this->assertTrue($groups->exists(['name' => 'Team A']));
    }

    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->post('/groups/edit/2', ['name' => 'Private']);

        $this->assertRedirect(['controller' => 'Groups', 'action' => 'index']);
        $group = $this->getTableLocator()->get('Groups')->get(2);
        $this->assertSame('Private', $group->name);
    }
}
