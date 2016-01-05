<?php
/**
 * DoctrineBlameableExtension.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           01.01.16
 */

namespace IPub\DoctrineBlameable\DI;

use Nette;
use Nette\DI;
use Nette\Utils;
use Nette\PhpGenerator as Code;

use Kdyby;
use Kdyby\Events;

use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Security;

/**
 * Doctrine blameable extension container
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DoctrineBlameableExtension extends DI\CompilerExtension
{
	/**
	 * @var array
	 */
	private $defaults = [
		'lazyAssociation' => FALSE,
		'userEntity'      => NULL,
		'automapField'    => TRUE,
		'userCallable'    => Security\UserCallable::CLASS_NAME,
	];

	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		Utils\Validators::assert($config['userEntity'], 'type|null', 'userEntity');
		Utils\Validators::assert($config['lazyAssociation'], 'bool', 'lazyAssociation');
		Utils\Validators::assert($config['automapField'], 'bool', 'automapField');

		$builder->addDefinition($this->prefix('configuration'))
			->setClass('IPub\DoctrineBlameable\Configuration')
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
				$definition->setClass($resolverClass);
			}

			$userCallable = $definition;
		}

		$builder->addDefinition($this->prefix('driver'))
			->setClass('IPub\DoctrineBlameable\Mapping\Driver\Blameable');

		$builder->addDefinition($this->prefix('listener'))
			->setClass('IPub\DoctrineBlameable\Events\BlameableListener')
			->setArguments([$userCallable instanceof DI\ServiceDefinition ? '@' . $userCallable->getClass() : NULL])
			->addTag(Events\DI\EventsExtension::TAG_SUBSCRIBER);
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 */
	public static function register(Nette\Configurator $config, $extensionName = 'doctrineBlameable')
	{
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new DoctrineBlameableExtension);
		};
	}
}
