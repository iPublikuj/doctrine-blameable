<?php
/**
 * DoctrineBlameableExtension.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           01.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\DI;

use Nette;
use Nette\DI;
use Nette\Schema;

use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Events;
use IPub\DoctrineBlameable\Mapping;
use IPub\DoctrineBlameable\Security;

/**
 * Doctrine blameable extension container
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class DoctrineBlameableExtension extends DI\CompilerExtension
{
	/**
	 * {@inheritdoc}
	 */
	public function getConfigSchema() : Schema\Schema
	{
		return Schema\Expect::structure([
			'lazyAssociation' => Schema\Expect::bool(FALSE),
			'userEntity'      => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::type(DI\Definitions\Statement::class))->nullable(),
			'automapField'    => Schema\Expect::bool(TRUE),
			'userCallable'    => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::type(DI\Definitions\Statement::class))->default(Security\UserCallable::class),
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration() : void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->getConfig();

		$builder->addDefinition($this->prefix('configuration'))
			->setType(DoctrineBlameable\Configuration::class)
			->setArguments([
				$configuration->userEntity,
				$configuration->lazyAssociation,
				$configuration->automapField,
			]);

		$userCallableDefinition = NULL;

		if ($configuration->userCallable !== NULL) {
			$userCallableDefinition = $builder->addDefinition($this->prefix('userCallable'));

			$factory = is_string($configuration->userCallable) ? new DI\Definitions\Statement($configuration->userCallable) : $configuration->userCallable;
			$userCallableDefinition->setFactory($factory);

			list($resolverClass) = (array) $this->normalizeEntity($userCallableDefinition->getFactory()->getEntity());

			if (class_exists($resolverClass)) {
				$userCallableDefinition->setType($resolverClass);
			}
		}

		$builder->addDefinition($this->prefix('driver'))
			->setType(Mapping\Driver\Blameable::class);

		$builder->addDefinition($this->prefix('subscriber'))
			->setType(Events\BlameableSubscriber::class)
			->setArguments([$userCallableDefinition instanceof DI\Definitions\ServiceDefinition ? $userCallableDefinition : NULL]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile() : void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$builder->getDefinition($builder->getByType('Doctrine\ORM\EntityManagerInterface', TRUE))
			->addSetup('?->getEventManager()->addEventSubscriber(?)', ['@self', $builder->getDefinition($this->prefix('subscriber'))]);
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'doctrineBlameable'
	) : void {
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new DoctrineBlameableExtension);
		};
	}

	/**
	 * @param $entity
	 *
	 * @return string|array  Class, @service, [Class, member], [@service, member], [, globalFunc], [Statement, member]
	 */
	private function normalizeEntity($entity)
	{
		// Get container builder
		$builder = $this->getContainerBuilder();

		if (
			is_string($entity)
			&& Nette\Utils\Strings::contains($entity, '::')
			&& !Nette\Utils\Strings::contains($entity, '?')
		) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (
			is_array($entity)
			&& $entity[0] instanceof DI\Definitions\ServiceDefinition
		) { // [ServiceDefinition, ...] -> [@serviceName, ...]
			$entity[0] = '@' . current(array_keys($builder->getDefinitions(), $entity[0], TRUE));

		} elseif (
			$entity instanceof DI\Definitions\ServiceDefinition
		) { // ServiceDefinition -> @serviceName
			$entity = '@' . current(array_keys($builder->getDefinitions(), $entity, TRUE));

		} elseif (
			is_array($entity)
			&& $entity[0] === $builder
		) { // [$builder, ...] -> [@container, ...]
			trigger_error("Replace object ContainerBuilder in Statement entity with '@container'.", E_USER_DEPRECATED);

			$entity[0] = '@' . md5($entity);
		}

		return $entity;
	}
}
