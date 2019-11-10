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
use Nette\Utils;

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
	 * @var array
	 */
	private $defaults = [
		'lazyAssociation' => FALSE,
		'userEntity'      => NULL,
		'automapField'    => TRUE,
		'userCallable'    => Security\UserCallable::class,
	];

	/**
	 * @return void
	 *
	 * @throws Utils\AssertionException
	 */
	public function loadConfiguration() : void
	{
		// Get container builder
		$builder = $this->getContainerBuilder();

		// Merge extension default config
		$this->setConfig(DI\Config\Helpers::merge($this->config, DI\Helpers::expand($this->defaults, $builder->parameters)));

		// Get extension configuration
		$configuration = $this->getConfig();

		Utils\Validators::assert($configuration['userEntity'], 'type|null', 'userEntity');
		Utils\Validators::assert($configuration['lazyAssociation'], 'bool', 'lazyAssociation');
		Utils\Validators::assert($configuration['automapField'], 'bool', 'automapField');

		$builder->addDefinition($this->prefix('configuration'))
			->setType(DoctrineBlameable\Configuration::class)
			->setArguments([
				$configuration['userEntity'],
				$configuration['lazyAssociation'],
				$configuration['automapField']
			]);

		$userCallable = NULL;

		if ($configuration['userCallable'] !== NULL) {
			$definition = $builder->addDefinition($this->prefix(md5($configuration['userCallable'])));

			list($factory) = DI\Helpers::filterArguments([
				is_string($configuration['userCallable']) ? new DI\Statement($configuration['userCallable']) : $configuration['userCallable']
			]);

			$definition->setFactory($factory);

			list($resolverClass) = (array) $this->normalizeEntity($definition->getFactory()->getEntity());

			if (class_exists($resolverClass)) {
				$definition->setType($resolverClass);
			}

			$userCallable = $definition;
		}

		$builder->addDefinition($this->prefix('driver'))
			->setType(Mapping\Driver\Blameable::class);

		$builder->addDefinition($this->prefix('subscriber'))
			->setType(Events\BlameableSubscriber::class)
			->setArguments([$userCallable instanceof DI\ServiceDefinition ? '@' . $userCallable->getType() : NULL]);
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
	public static function register(Nette\Configurator $config, string $extensionName = 'doctrineBlameable') : void
	{
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new DoctrineBlameableExtension);
		};
	}

	/**
	 * @return string|array  Class, @service, [Class, member], [@service, member], [, globalFunc], [Statement, member]
	 */
	private function normalizeEntity($entity)
	{
		// Get container builder
		$builder = $this->getContainerBuilder();

		if (is_string($entity) && Nette\Utils\Strings::contains($entity, '::') && !Nette\Utils\Strings::contains($entity, '?')) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (is_array($entity) && $entity[0] instanceof ServiceDefinition) { // [ServiceDefinition, ...] -> [@serviceName, ...]
			$entity[0] = '@' . current(array_keys($builder->getDefinitions(), $entity[0], true));

		} elseif ($entity instanceof DI\ServiceDefinition) { // ServiceDefinition -> @serviceName
			$entity = '@' . current(array_keys($builder->getDefinitions(), $entity, true));

		} elseif (is_array($entity) && $entity[0] === $builder) { // [$builder, ...] -> [@container, ...]
			trigger_error("Replace object ContainerBuilder in Statement entity with '@container'.", E_USER_DEPRECATED);

			$entity[0] = '@' . self::THIS_CONTAINER;
		}

		return $entity;
	}
}
