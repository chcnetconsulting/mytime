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
            'first_name' => 'New',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $users = $this->getTableLocator()->get('Users');
        $this->assertSame(1, $users->find()->where(['username' => 'newuser'])->count());
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
            'first_name' => 'Updated',
            'last_name' => 'User',
        ]);

        $this->assertRedirect(['controller' => 'Users', 'action' => 'index']);
        $user = $this->getTableLocator()->get('Users')->get(1);
        $this->assertSame('updateduser', $user->username);
        $this->assertSame('updated@example.com', $user->email);
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
