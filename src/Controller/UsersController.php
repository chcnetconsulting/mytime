<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        if ($event->getResult() instanceof \Cake\Http\Response) {
            return;
        }

        if (Configure::read('Auth.disabled')) {
            return null;
        }

        if (!$this->currentUserIsAdmin()) {
            throw new ForbiddenException('Only admins can manage users.');
        }

        return null;
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Users->find()->contain(['Groups']);
        $users = $this->paginate($query);

        $this->set(compact('users'));
    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $user = $this->Users->get($id, contain: ['Groups']);
        $this->set(compact('user'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $user = $this->Users->newEmptyEntity();
        $groups = $this->Users->Groups->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is('post')) {
            $data = $this->userDataWithDefaults($this->request->getData());
            $user = $this->Users->patchEntity($user, $data);
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }
        $this->set(compact('user', 'groups'));
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $user = $this->Users->get($id, contain: []);
        $groups = $this->Users->Groups->find('list')->orderBy(['name' => 'ASC'])->all();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->userDataWithDefaults($this->request->getData());
            $user = $this->Users->patchEntity($user, $data);
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }
        $this->set(compact('user', 'groups'));
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Add safe defaults for optional checkbox data and stale forms.
     *
     * @param array<string, mixed> $data Request data.
     * @return array<string, mixed>
     */
    private function userDataWithDefaults(array $data): array
    {
        $data += ['is_admin' => false];

        if (empty($data['group_id'])) {
            $groupId = $this->currentGroupId() ?? $this->defaultGroupId();
            if ($groupId !== null) {
                $data['group_id'] = $groupId;
            }
        }

        return $data;
    }

    private function defaultGroupId(): ?int
    {
        $group = $this->Users->Groups->find()
            ->select(['id'])
            ->where(['name' => 'Default'])
            ->first();

        if ($group === null) {
            $group = $this->Users->Groups->find()
                ->select(['id'])
                ->orderBy(['id' => 'ASC'])
                ->first();
        }

        return empty($group?->id) ? null : (int)$group->id;
    }

}
