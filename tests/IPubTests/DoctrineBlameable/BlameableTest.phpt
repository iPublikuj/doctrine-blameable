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
require_once __DIR__ . '/models/ArticleEntity.php';
require_once __DIR__ . '/models/UserEntity.php';

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

	/**
	 * @var DoctrineBlameable\Configuration
	 */
	private $configuration;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->createContainer();
		$this->em = $this->container->getByType('Kdyby\Doctrine\EntityManager');
		$this->listener = $this->container->getByType('IPub\DoctrineBlameable\Events\BlameableListener');
		$this->configuration = $this->container->getByType('IPub\DoctrineBlameable\Configuration');
	}

	public function testCreate()
	{
		$this->generateDbSchema();

		$this->listener->setUser('tester');

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);
		$this->em->flush();

		Assert::equal('tester', $entity->getCreatedBy());
		Assert::equal('tester', $entity->getUpdatedBy());
	}

	public function testUpdate()
	{
		$this->generateDbSchema();

		$this->listener->setUser('tester');

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);
		$this->em->flush();

		$id = $entity->getId();
		$createdBy = $entity->getCreatedBy();

		$this->em->clear();

		$this->listener->setUser('secondUser');

		$entity = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);
		$entity->setTitle('test'); // Need to modify at least one column to trigger onUpdate

		$this->em->flush();
		$this->em->clear();

		Assert::equal($createdBy, $entity->getCreatedBy());
		Assert::equal('secondUser', $entity->getUpdatedBy());
		Assert::notEqual($entity->getCreatedBy(), $entity->getUpdatedBy());
	}

	public function testRemove()
	{
		$this->generateDbSchema();

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);
		$this->em->flush();

		$id = $entity->getId();

		$this->em->clear();

		$this->listener->setUser('secondUser');

		$entity = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);

		$this->em->remove($entity);
		$this->em->flush();
		$this->em->clear();

		Assert::equal('secondUser', $entity->getDeletedBy());
	}

	public function testWithUserCallback()
	{
		// Define entity name
		$this->configuration->userEntity = 'IPubTests\DoctrineBlameable\Models\UserEntity';

		$this->generateDbSchema();

		$creator = new Models\UserEntity;
		$creator->setUsername('user');

		$tester = new Models\UserEntity;
		$tester->setUsername('tester');

		$userCallback = function() use($creator) {
			return $creator;
		};

		$this->listener->setUserCallable($userCallback);

		$this->em->persist($creator);
		$this->em->persist($tester);

		$this->em->flush();

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);

		$this->em->flush();

		$id = $entity->getId();

		// Switch user for update
		$this->listener->setUser($tester);

		$entity = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);
		$entity->setTitle('New article title'); // Need to modify at least one column to trigger onUpdate

		$this->em->flush();

		Assert::true($entity->getCreatedBy() instanceof Models\UserEntity);
		Assert::equal($creator->getUsername(), $entity->getCreatedBy()->getUsername());
		Assert::equal($tester->getUsername(), $entity->getUpdatedBy()->getUsername());
		Assert::null($entity->getPublishedBy());
		Assert::notEqual($entity->getCreatedBy(), $entity->getUpdatedBy());

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$entity->setType($published);

		$this->em->persist($entity);
		$this->em->persist($published);
		$this->em->flush();
		$this->em->clear();

		$id = $entity->getId();

		$entity = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);

		Assert::equal($tester->getUsername(), $entity->getPublishedBy()->getUsername());
	}

	/**
	 * @throws \IPub\DoctrineBlameable\Exceptions\InvalidArgumentException
	 */
	public function testPersistOnlyWithEntity()
	{
		// Define entity name
		$this->configuration->userEntity = 'IPubTests\DoctrineBlameable\Models\UserEntity';

		$this->generateDbSchema();

		$user = new Models\UserEntity;
		$user->setUsername('user');

		$userCallback = function() use($user) {
			return $user;
		};

		$this->listener->setUserCallable($userCallback);
		// Override user
		$this->listener->setUser('anonymous');

		$this->em->persist($user);
		$this->em->flush();

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);
		$this->em->flush();
	}

	/**
	 * @throws \IPub\DoctrineBlameable\Exceptions\InvalidArgumentException
	 */
	public function testPersistOnlyWithString()
	{
		$this->generateDbSchema();

		$user = new Models\UserEntity;
		$user->setUsername('user');

		$this->em->persist($user);
		$this->em->flush();

		// Override user
		$this->listener->setUser($user);

		$entity = new Models\ArticleEntity;

		$this->em->persist($entity);
		$this->em->flush();
	}

	public function testForcedValues()
	{
		$this->generateDbSchema();

		$this->listener->setUser('tester');

		$sport = new Models\ArticleEntity;
		$sport->setTitle('sport forced');
		$sport->setCreatedBy('forceduser');
		$sport->setUpdatedBy('forceduser');

		$this->em->persist($sport);
		$this->em->flush();
		$this->em->clear();

		$id = $sport->getId();

		$sport = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);

		Assert::equal('forceduser', $sport->getCreatedBy());
		Assert::equal('forceduser', $sport->getUpdatedBy());
		Assert::null($sport->getPublishedBy());

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$sport->setType($published);
		$sport->setPublishedBy('forceduser');

		$this->em->persist($sport);
		$this->em->persist($published);
		$this->em->flush();
		$this->em->clear();

		$id = $sport->getId();

		$sport = $this->em->getRepository('IPubTests\DoctrineBlameable\Models\ArticleEntity')->find($id);

		Assert::equal('forceduser', $sport->getPublishedBy());
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
