<?php
/**
 * Test: IPub\DoctrineBlameable\Blameable
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           06.01.16
 */

namespace IPubTests\DoctrineBlameable;

use Nette;

use Tester;
use Tester\Assert;

use Doctrine;
use Doctrine\ORM;
use Doctrine\Common;

use IPub;
use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Events;
use IPub\DoctrineBlameable\Mapping;

require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/models/BlameableEntity.php';

/**
 * Registering doctrine blameable functions tests
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class BlameableTest extends Tester\TestCase
{
	/**
	 * @var \Nette\DI\Container
	 */
	private $container;

	/**
	 * @var \Kdyby\Doctrine\EntityManager
	 */
	private $em;

	/**
	 * @var Events\BlameableListener
	 */
	private $listener;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->createContainer();
		$this->em = $this->container->getByType('Kdyby\Doctrine\EntityManager');
		$this->listener = $this->container->getByType('IPub\DoctrineBlameable\Events\BlameableListener');
	}

	public function testCreate()
	{
		$this->generateDbSchema();

		$this->listener->setUser('tester');

		$entity = new Models\BlameableEntity;

		$this->em->persist($entity);
		$this->em->flush();

		Assert::equal('tester', $entity->getCreatedBy());
		Assert::equal('tester', $entity->getModifiedBy());
	}

	public function testUpdate()
	{
		$this->generateDbSchema();

		$entity = new Models\BlameableEntity;

		$this->em->persist($entity);
		$this->em->flush();

		$id = $entity->getId();
		$createdBy = $entity->getCreatedBy();

		$this->em->clear();

		$subscribers = $this->em->getEventManager()->getListeners()['preUpdate'];
		$subscriber = array_pop($subscribers);
		$subscriber->setUser('user2');

		$entity = $this->em->getRepository('Models\BlameableEntity')->find($id);
		$entity->setTitle('test'); // Need to modify at least one column to trigger onUpdate

		$this->em->flush();
		$this->em->clear();

		Assert::equal($createdBy, $entity->getCreatedBy(), 'createdBy is constant');
		Assert::equal('user2', $entity->getUpdatedBy());
		Assert::notEqual(
			$entity->getCreatedBy(),
			$entity->getUpdatedBy(),
			'createBy and updatedBy have diverged since new update'
		);
	}

	private function generateDbSchema()
	{
		$schema = new ORM\Tools\SchemaTool($this->em);
		$schema->createSchema($this->em->getMetadataFactory()->getAllMetadata());
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer()
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5('withModel')]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/files/config.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');
		$config->addConfig(__DIR__ . '/files/entities.neon', $config::NONE);

		DoctrineBlameable\DI\DoctrineBlameableExtension::register($config);

		return $config->createContainer();
	}
}

\run(new BlameableTest());
