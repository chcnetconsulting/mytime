<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    protected function currentUser(): ?array
    {
        $user = $this->request->getSession()->read('Auth.User');

        return is_array($user) ? $user : null;
    }

    protected function currentUserId(): ?int
    {
        $user = $this->currentUser();

        return empty($user['id']) ? null : (int)$user['id'];
    }

    protected function currentGroupId(): ?int
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return null;
        }

        $user = $this->fetchTable('Users')->find()
            ->select(['id', 'group_id'])
            ->where(['id' => $userId])
            ->first();

        return empty($user?->group_id) ? null : (int)$user->group_id;
    }

    protected function currentUserIsAdmin(): bool
    {
        $user = $this->currentUser();
        if (!$user) {
            return false;
        }

        $ownerEmail = (string)Configure::read('Auth.ownerEmail', '');
        if ($ownerEmail !== '' && strcasecmp((string)($user['email'] ?? ''), $ownerEmail) === 0) {
            return true;
        }

        $userId = $this->currentUserId();
        if ($userId === null) {
            return false;
        }

        $record = $this->fetchTable('Users')->find()
            ->select(['id', 'is_admin'])
            ->where(['id' => $userId])
            ->first();

        return (bool)($record?->is_admin ?? false);
    }

	/**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        if (Configure::read('Auth.disabled')) {
            return null;
        }

        if ($this->request->getParam('controller') === 'Auth') {
            return null;
        }

        // Die REST-API authentifiziert per Bearer-Token (ApiController::beforeFilter),
        // nicht über die OIDC-Session — daher hier nicht zum Login umleiten.
        if ($this->request->getParam('controller') === 'Api') {
            return null;
        }

        if ($this->request->getSession()->check('Auth.User')) {
            return null;
        }

        $event->setResult($this->redirect(['controller' => 'Auth', 'action' => 'login']));

        return null;
    }
}
