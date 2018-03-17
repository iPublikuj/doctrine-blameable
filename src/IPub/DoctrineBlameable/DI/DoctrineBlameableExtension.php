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
use Nette\PhpGenerator as Code;

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
	 */
	public function loadConfiguration() : void
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		Utils\Validators::assert($config['userEntity'], 'type|null', 'userEntity');
		Utils\Validators::assert($config['lazyAssociation'], 'bool', 'lazyAssociation');
		Utils\Validators::assert($config['automapField'], 'bool', 'automapField');

		$builder->addDefinition($this->prefix('configuration'))
			->setType(DoctrineBlameable\Configuration::class)
			->setArguments([
				$config['userEntity'],
				$config['lazyAssociation'],
				$config['automapField']
			]);

		$userCallable = NULL;

		if ($config['userCallable'] !== NULL) {
			$definition = $builder->addDefinition($this->prefix(md5($config['userCallable'])));

			list($factory) = DI\Compiler::filterArguments([
				is_string($config['userCallable']) ? new DI\Statement($config['userCallable']) : $config['userCallable']
			]);

			$definition->setFactory($factory);

			list($resolverClass) = (array) $builder->normalizeEntity($definition->getFactory()->getEntity());

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

		$builder->getDefinition($builder->getByType('Doctrine\ORM\EntityManagerInterface') ?: 'doctrine.default.entityManager')
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
}
