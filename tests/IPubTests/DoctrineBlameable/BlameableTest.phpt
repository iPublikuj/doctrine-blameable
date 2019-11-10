<?php
/**
 * Test: IPub\DoctrineBlameable\Blameable
 * @testCase
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           06.01.16
 */

declare(strict_types = 1);

namespace IPubTests\DoctrineBlameable;

use Nette;

use Tester;
use Tester\Assert;

use Doctrine;
use Doctrine\ORM;
use Doctrine\Common;

use Nettrine;

use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Events;
use IPub\DoctrineBlameable\Mapping;

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DS . 'models' . DS . 'ArticleEntity.php';
require_once __DIR__ . DS . 'models' . DS . 'ArticleMultiChangeEntity.php';
require_once __DIR__ . DS . 'models' . DS . 'TypeEntity.php';
require_once __DIR__ . DS . 'models' . DS . 'UserEntity.php';

/**
 * Registering doctrine blameable functions tests
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class BlameableTest extends Tester\TestCase
{
	/**
	 * @var \Nette\DI\Container
	 */
	private $container;

	/**
	 * @var ORM\EntityManager
	 */
	private $em;

	/**
	 * @var Events\BlameableSubscriber
	 */
	private $subscriber;

	/**
	 * @var DoctrineBlameable\Configuration
	 */
	private $configuration;

	/**
	 * {@inheritdoc}
	 */
	protected function setUp() : void
	{
		parent::setUp();

		$this->container = $this->createContainer();
		$this->em = $this->container->getByType(Nettrine\ORM\EntityManagerDecorator::class);
		$this->subscriber = $this->container->getByType(Events\BlameableSubscriber::class);
		$this->configuration = $this->container->getByType(DoctrineBlameable\Configuration::class);
	}

	public function testCreate() : void
	{
		$this->generateDbSchema();

		$this->subscriber->setUser('tester');

		$article = new Models\ArticleEntity;

		$this->em->persist($article);
		$this->em->flush();

		Assert::equal('tester', $article->getCreatedBy());
		Assert::equal('tester', $article->getUpdatedBy());
	}

	public function testUpdate() : void
	{
		$this->generateDbSchema();

		$this->subscriber->setUser('tester');

		$article = new Models\ArticleEntity;

		$this->em->persist($article);
		$this->em->flush();

		$id = $article->getId();
		$createdBy = $article->getCreatedBy();

		$this->em->clear();

		$this->subscriber->setUser('secondUser');

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);
		$article->setTitle('test'); // Need to modify at least one column to trigger onUpdate

		$this->em->flush();

		Assert::equal($createdBy, $article->getCreatedBy());
		Assert::equal('secondUser', $article->getUpdatedBy());
		Assert::notEqual($article->getCreatedBy(), $article->getUpdatedBy());

		$this->subscriber->setUser('publisher');

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$article->setType($published);

		$this->em->persist($article);
		$this->em->persist($published);
		$this->em->flush();
		$this->em->clear();

		$id = $article->getId();

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);

		Assert::equal('publisher', $article->getPublishedBy());
	}

	public function testRemove() : void
	{
		$this->generateDbSchema();

		$article = new Models\ArticleEntity;

		$this->em->persist($article);
		$this->em->flush();

		$id = $article->getId();

		$this->em->clear();

		$this->subscriber->setUser('secondUser');

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);

		$this->em->remove($article);
		$this->em->flush();
		$this->em->clear();

		Assert::equal('secondUser', $article->getDeletedBy());
	}

	public function testWithUserCallback() : void
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

		$this->subscriber->setUserCallable($userCallback);

		$this->em->persist($creator);
		$this->em->persist($tester);

		$this->em->flush();

		$article = new Models\ArticleEntity;

		$this->em->persist($article);

		$this->em->flush();

		$id = $article->getId();

		// Switch user for update
		$this->subscriber->setUser($tester);

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);
		$article->setTitle('New article title'); // Need to modify at least one column to trigger onUpdate

		$this->em->flush();

		Assert::true($article->getCreatedBy() instanceof Models\UserEntity);
		Assert::equal($creator->getUsername(), $article->getCreatedBy()->getUsername());
		Assert::equal($tester->getUsername(), $article->getUpdatedBy()->getUsername());
		Assert::null($article->getPublishedBy());
		Assert::notEqual($article->getCreatedBy(), $article->getUpdatedBy());

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$article->setType($published);

		$this->em->persist($article);
		$this->em->persist($published);
		$this->em->flush();
		$this->em->clear();

		$id = $article->getId();

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);

		Assert::equal($tester->getUsername(), $article->getPublishedBy()->getUsername());
	}

	/**
	 * @throws \IPub\DoctrineBlameable\Exceptions\InvalidArgumentException
	 */
	public function testPersistOnlyWithEntity() : void
	{
		// Define entity name
		$this->configuration->userEntity = Models\UserEntity::class;

		$this->generateDbSchema();

		$user = new Models\UserEntity;
		$user->setUsername('user');

		$userCallback = function() use($user) {
			return $user;
		};

		$this->subscriber->setUserCallable($userCallback);
		// Override user
		$this->subscriber->setUser('anonymous');

		$this->em->persist($user);
		$this->em->flush();

		$article = new Models\ArticleEntity;

		$this->em->persist($article);
		$this->em->flush();
	}

	/**
	 * @throws \IPub\DoctrineBlameable\Exceptions\InvalidArgumentException
	 */
	public function testPersistOnlyWithString() : void
	{
		$this->generateDbSchema();

		$user = new Models\UserEntity;
		$user->setUsername('user');

		$this->em->persist($user);
		$this->em->flush();

		// Override user
		$this->subscriber->setUser($user);

		$article = new Models\ArticleEntity;

		$this->em->persist($article);
		$this->em->flush();
	}

	public function testForcedValues() : void
	{
		$this->generateDbSchema();

		$this->subscriber->setUser('tester');

		$article = new Models\ArticleEntity;
		$article->setTitle('Article forced');
		$article->setCreatedBy('forcedUser');
		$article->setUpdatedBy('forcedUser');

		$this->em->persist($article);
		$this->em->flush();
		$this->em->clear();

		$id = $article->getId();

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);

		Assert::equal('forcedUser', $article->getCreatedBy());
		Assert::equal('forcedUser', $article->getUpdatedBy());
		Assert::null($article->getPublishedBy());

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$article->setType($published);
		$article->setPublishedBy('forcedUser');

		$this->em->persist($article);
		$this->em->persist($published);
		$this->em->flush();
		$this->em->clear();

		$id = $article->getId();

		$article = $this->em->getRepository(Models\ArticleEntity::class)->find($id);

		Assert::equal('forcedUser', $article->getPublishedBy());
	}

	public function testMultipleValueTrackingField() : void
	{
		$this->generateDbSchema();

		$this->subscriber->setUser('author');

		$article = new Models\ArticleMultiChangeEntity;

		$this->em->persist($article);
		$this->em->flush();

		$id = $article->getId();

		$article = $this->em->getRepository(Models\ArticleMultiChangeEntity::class)->find($id);

		Assert::equal('author', $article->getCreatedBy());
		Assert::equal('author', $article->getUpdatedBy());
		Assert::null($article->getPublishedBy());

		$draft = new Models\TypeEntity;
		$draft->setTitle('Draft');

		$article->setType($draft);

		$this->em->persist($article);
		$this->em->persist($draft);
		$this->em->flush();

		Assert::null($article->getPublishedBy());

		$this->subscriber->setUser('editor');

		$published = new Models\TypeEntity;
		$published->setTitle('Published');

		$article->setType($published);

		$this->em->persist($article);
		$this->em->persist($published);
		$this->em->flush();

		Assert::equal('editor', $article->getPublishedBy());

		$article->setType($draft);

		$this->em->persist($article);
		$this->em->flush();

		Assert::equal('editor', $article->getPublishedBy());

		$this->subscriber->setUser('remover');

		$deleted = new Models\TypeEntity;
		$deleted->setTitle('Deleted');

		$article->setType($deleted);

		$this->em->persist($article);
		$this->em->persist($deleted);
		$this->em->flush();

		Assert::equal('remover', $article->getPublishedBy());
	}

	/**
	 * @return void
	 */
	private function generateDbSchema() : void
	{
		$schema = new ORM\Tools\SchemaTool($this->em);
		$schema->createSchema($this->em->getMetadataFactory()->getAllMetadata());
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer() : Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . DS . 'files' . DS . 'config.neon');
		$config->addConfig(__DIR__ . DS . 'files' . DS . 'entities.neon');

		DoctrineBlameable\DI\DoctrineBlameableExtension::register($config);

		return $config->createContainer();
	}
}

\run(new BlameableTest());
