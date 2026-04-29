<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MandantenTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MandantenTable Test Case
 */
class MandantenTableTest extends TestCase
{
    /**
     * @var \App\Model\Table\MandantenTable
     */
    protected $Mandanten;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Mandanten',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Mandanten') ? [] : ['className' => MandantenTable::class];
        $this->Mandanten = $this->getTableLocator()->get('Mandanten', $config);
    }

    protected function tearDown(): void
    {
        unset($this->Mandanten);

        parent::tearDown();
    }

    public function testValidationDefault(): void
    {
        $valid = $this->Mandanten->newEntity([
            'name' => 'Initech',
        ]);
        $this->assertEmpty($valid->getErrors());

        $invalid = $this->Mandanten->newEntity([
            'name' => '',
        ]);
        $this->assertNotEmpty($invalid->getError('name'));
    }

    public function testUniqueNameRule(): void
    {
        $duplicate = $this->Mandanten->newEntity([
            'name' => 'ACME',
        ]);

        $this->assertFalse($this->Mandanten->save($duplicate));
        $this->assertNotEmpty($duplicate->getError('name'));
    }
}
