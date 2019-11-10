<?php
/**
 * DoctrineCrudExtension.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineCrud!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           29.01.14
 */

declare(strict_types = 1);

namespace IPub\DoctrineCrud\DI;

use Doctrine\Common;

use Nette;
use Nette\DI;
use Nette\PhpGenerator;

use IPub\DoctrineCrud;
use IPub\DoctrineCrud\Crud;
use IPub\DoctrineCrud\Mapping;

/**
 * Doctrine CRUD extension container
 *
 * @package        iPublikuj:DoctrineCrud!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class DoctrineCrudExtension extends DI\CompilerExtension
{
	// Define tag string for validator
	const TAG_VALIDATOR = 'ipub.doctrine.validator';

	/**
	 * @return void
	 *
	 * @throws Common\Annotations\AnnotationException
	 */
	public function loadConfiguration() : void
	{
		// Get container builder
		$builder = $this->getContainerBuilder();

		$annotationReader = new Common\Annotations\AnnotationReader;

		Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
			'IPub\\Doctrine\\Entities\\IEntity'
		);

		$annotationReader = new Common\Annotations\CachedReader($annotationReader, new Common\Cache\ArrayCache);

		/**
		 * Extensions helpers
		 */

		$builder->addDefinition($this->prefix('entity.validator'))
			->setType(DoctrineCrud\Validation\ValidatorProxy::class)
			->setArguments([$annotationReader])
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('entity.mapper'))
			->setType(Mapping\EntityMapper::class)
			->setArguments(['@' . $this->prefix('entity.validator'), $annotationReader])
			->setAutowired(FALSE);

		/**
		 * CRUD factories
		 */

		if (method_exists($builder, 'addFactoryDefinition')) {
			$builder->addFactoryDefinition($this->prefix('entity.creator'))
				->setImplement(Crud\Create\IEntityCreator::class)
				->setAutowired(FALSE)
				->getResultDefinition()
					->setType(Crud\Create\EntityCreator::class);

			$builder->addFactoryDefinition($this->prefix('entity.updater'))
				->setImplement(Crud\Update\IEntityUpdater::class)
				->setAutowired(FALSE)
				->getResultDefinition()
					->setFactory(Crud\Update\EntityUpdater::class);

			$builder->addFactoryDefinition($this->prefix('entity.deleter'))
				->setImplement(Crud\Delete\IEntityDeleter::class)
				->setAutowired(FALSE)
				->getResultDefinition()
					->setFactory(Crud\Delete\EntityDeleter::class);

		} else {
			$builder->addDefinition($this->prefix('entity.creator'))
				->setType(Crud\Create\EntityCreator::class)
				->setImplement(Crud\Create\IEntityCreator::class)
				->setAutowired(FALSE);

			$builder->addDefinition($this->prefix('entity.updater'))
				->setType(Crud\Update\EntityUpdater::class)
				->setImplement(Crud\Update\IEntityUpdater::class)
				->setAutowired(FALSE);

			$builder->addDefinition($this->prefix('entity.deleter'))
				->setType(Crud\Delete\EntityDeleter::class)
				->setImplement(Crud\Delete\IEntityDeleter::class)
				->setAutowired(FALSE);
		}

		$builder->addDefinition($this->prefix('entity.crudFactory'))
			->setType(Crud\EntityCrudFactory::class)
			->setArguments([
				'@' . $this->prefix('entity.mapper'),
				'@' . $this->prefix('entity.creator'),
				'@' . $this->prefix('entity.updater'),
				'@' . $this->prefix('entity.deleter'),
			]);

		// Syntax sugar for config
		if (method_exists($builder, 'addFactoryDefinition')) {
			$builder->addFactoryDefinition($this->prefix('crud'))
				->setType(Crud\EntityCrud::class)
				->setParameters(['entityName'])
				->setAutowired(FALSE)
				->getResultDefinition()
					->setFactory('@IPub\DoctrineCrud\Crud\EntityCrudFactory::create', [new PhpGenerator\PhpLiteral('$entityName')]);

		} else {
			$builder->addDefinition($this->prefix('crud'))
				->setType(Crud\EntityCrud::class)
				->setFactory('@IPub\DoctrineCrud\Crud\EntityCrudFactory::create', [new PhpGenerator\PhpLiteral('$entityName')])
				->setParameters(['entityName'])
				->setAutowired(FALSE);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile() : void
	{
		parent::beforeCompile();

		// Get container builder
		$builder = $this->getContainerBuilder();

		// Get validators service
		$validator = $builder->getDefinition($this->prefix('entity.validator'));

		foreach (array_keys($builder->findByTag(self::TAG_VALIDATOR)) as $serviceName) {
			// Register validator to proxy validator
			$validator->addSetup('registerValidator', ['@' . $serviceName, $serviceName]);
		}

		$builder->getDefinition($builder->getByType('Doctrine\ORM\EntityManagerInterface', TRUE))
			->addSetup('?->getConfiguration()->addCustomStringFunction(?, ?)', ['@self', 'DATE_FORMAT', DoctrineCrud\StringFunctions\DateFormat::class]);
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(Nette\Configurator $config, string $extensionName = 'doctrineCrud') : void
	{
		$config->onCompile[] = function (Nette\Configurator $config, DI\Compiler $compiler) use ($extensionName) : void {
			$compiler->addExtension($extensionName, new DoctrineCrudExtension());
		};
	}
}
