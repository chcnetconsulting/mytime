<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\UsersController Test Case
 *
 * @link \App\Controller\UsersController
 */
class UsersControllerTest extends TestCase
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
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \App\Controller\UsersController::index()
     */
    public function testIndex(): void
    {
        $this->get('/users');

        $this->assertResponseOk();
        $this->assertResponseContains('testuser');
        $this->assertResponseContains('second@example.com');
    }

    /**
     * Test view method
     *
     * @return void
     * @link \App\Controller\UsersController::view()
     */
    public function testView(): void
    {
        $this->get('/users/view/1');

        $this->assertResponseOk();
        $this->assertResponseContains('testuser');
        $this->assertResponseContains('test@example.com');
    }

    /**
     * Test add method
     *
     * @return void
     * @link \App\Controller\UsersController::add()
     */
    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->post('/users/add', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'group_id' => 1,
            'is_admin' => false,
            'first_name' => 'New',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $users = $this->getTableLocator()->get('Users');
        $user = $users->find()->where(['username' => 'newuser'])->firstOrFail();
        $this->assertFalse($user->is_admin);
    }

    public function testAddAllowsUncheckedAdmin(): void
    {
        $this->enableCsrfToken();
        $this->post('/users/add', [
            'username' => 'plainuser',
            'email' => 'plain@example.com',
            'group_id' => 1,
            'first_name' => 'Plain',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $user = $this->getTableLocator()->get('Users')
            ->find()
            ->where(['username' => 'plainuser'])
            ->firstOrFail();
        $this->assertFalse($user->is_admin);
    }

    public function testAddDefaultsMissingGroup(): void
    {
        $this->enableCsrfToken();
        $this->post('/users/add', [
            'username' => 'nogroupuser',
            'email' => 'nogroup@example.com',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $user = $this->getTableLocator()->get('Users')
            ->find()
            ->where(['username' => 'nogroupuser'])
            ->firstOrFail();
        $this->assertSame(1, $user->group_id);
        $this->assertFalse($user->is_admin);
    }

    public function testAddUsesAdminCheckbox(): void
    {
        $this->get('/users/add');

        $this->assertResponseOk();
        $this->assertResponseContains('type="checkbox"');
        $this->assertResponseContains('name="is_admin"');
        $this->assertResponseNotContains('name="is_admin" required');
    }

    /**
     * Test edit method
     *
     * @return void
     * @link \App\Controller\UsersController::edit()
     */
    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->post('/users/edit/1', [
            'username' => 'updateduser',
            'email' => 'updated@example.com',
            'group_id' => 2,
            'is_admin' => true,
            'first_name' => 'Updated',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $user = $this->getTableLocator()->get('Users')->get(1);
        $this->assertSame('updateduser', $user->username);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame(2, $user->group_id);
        $this->assertTrue($user->is_admin);
    }

    public function testEditAllowsUncheckedAdmin(): void
    {
        $this->enableCsrfToken();
        $this->post('/users/edit/1', [
            'username' => 'updateduser',
            'email' => 'updated@example.com',
            'group_id' => 1,
            'first_name' => 'Updated',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $user = $this->getTableLocator()->get('Users')->get(1);
        $this->assertFalse($user->is_admin);
    }

    public function testEditUsesAdminCheckbox(): void
    {
        $this->get('/users/edit/1');

        $this->assertResponseOk();
        $this->assertResponseContains('type="checkbox"');
        $this->assertResponseContains('name="is_admin"');
        $this->assertResponseNotContains('name="is_admin" required');
    }

    /**
     * Test delete method
     *
     * @return void
     * @link \App\Controller\UsersController::delete()
     */
    public function testDelete(): void
    {
        $this->enableCsrfToken();
        $this->delete('/users/delete/1');

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $users = $this->getTableLocator()->get('Users');
        $this->assertFalse($users->exists(['id' => 1]));
    }
}
