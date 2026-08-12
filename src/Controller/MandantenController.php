<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Mandanten Controller
 *
 * @property \App\Model\Table\MandantenTable $Mandanten
 */
class MandantenController extends AppController
{
    /**
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Mandanten->find();
        $mandanten = $this->paginate($query);

        $this->set(compact('mandanten'));
    }

    /**
     * @param string|null $id Mandant id.
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function view(?string $id = null)
    {
        $mandant = $this->Mandanten->get($id, contain: ['Bookings']);

        $this->set(compact('mandant'));
    }

    /**
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $mandant = $this->Mandanten->newEmptyEntity();
        if ($this->request->is('post')) {
            $mandant = $this->Mandanten->patchEntity($mandant, $this->request->getData());
            if ($this->Mandanten->save($mandant)) {
                $this->Flash->success(__('The mandant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The mandant could not be saved. Please, try again.'));
        }
        $this->set(compact('mandant'));
    }

    /**
     * @param string|null $id Mandant id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     */
    public function edit(?string $id = null)
    {
        $mandant = $this->Mandanten->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $mandant = $this->Mandanten->patchEntity($mandant, $this->request->getData());
            if ($this->Mandanten->save($mandant)) {
                $this->Flash->success(__('The mandant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The mandant could not be saved. Please, try again.'));
        }
        $this->set(compact('mandant'));
    }

    /**
     * @param string|null $id Mandant id.
     * @return \Cake\Http\Response|null Redirects to index.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $mandant = $this->Mandanten->get($id);
        if ($this->Mandanten->delete($mandant)) {
            $this->Flash->success(__('The mandant has been deleted.'));
        } else {
            $this->Flash->error(__('The mandant could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
