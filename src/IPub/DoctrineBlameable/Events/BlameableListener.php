<?php
/**
 * BlameableListener.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           01.01.16
 */

namespace IPub\DoctrineBlameable\Events;

use Nette;
use Nette\Utils;
use Nette\Security as NS;

use Doctrine;
use Doctrine\Common;
use Doctrine\ORM;

use Kdyby;
use Kdyby\Events;

use IPub;
use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Exceptions;
use IPub\DoctrineBlameable\Mapping;

/**
 * Doctrine blameable listener
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class BlameableListener extends Nette\Object implements Events\Subscriber
{
	/**
	 * Define class name
	 */
	const CLASS_NAME = __CLASS__;

	/**
	 * Annotation field is blameable
	 */
	const EXTENSION_ANNOTATION = '\IPub\DoctrineBlameable\Mapping\Annotation\Blameable';

	/**
	 * @var callable
	 */
	private $userCallable;

	/**
	 * @var mixed
	 */
	private $user;

	/**
	 * List of types which are valid for blame
	 *
	 * @var array
	 */
	private $validTypes = [
		'one',
		'string',
		'int',
	];

	/**
	 * @var Common\Persistence\ObjectManager
	 */
	private $objectManager;

	/**
	 * @var DoctrineBlameable\Configuration
	 */
	private $configuration;

	/**
	 * List of cached object configurations
	 *
	 * @var array
	 */
	private $objectConfigurations = [];

	/**
	 * Register events
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Doctrine\\ORM\\Event::loadClassMetadata',
			'Doctrine\\ORM\\Event::onFlush',
		];
	}

	/**
	 * @param callable|NULL $userCallable
	 * @param DoctrineBlameable\Configuration $configuration
	 * @param Common\Persistence\ObjectManager $objectManager
	 */
	public function __construct(
		$userCallable = NULL,
		DoctrineBlameable\Configuration $configuration,
		Common\Persistence\ObjectManager $objectManager
	) {
		$this->objectManager = $objectManager;
		$this->configuration = $configuration;
		$this->userCallable = $userCallable;
	}

	/**
	 * @param ORM\Event\LoadClassMetadataEventArgs $eventArgs
	 *
	 * @throws Exceptions\InvalidMappingException
	 */
	public function loadClassMetadata(ORM\Event\LoadClassMetadataEventArgs $eventArgs)
	{
		/** @var ORM\Mapping\ClassMetadata $classMetadata */
		$classMetadata = $eventArgs->getClassMetadata();
		$this->loadMetadataForObjectClass($classMetadata);

		// Register pre persist event
		$this->registerEvent($classMetadata, ORM\Events::prePersist);
		// Register pre update event
		$this->registerEvent($classMetadata, ORM\Events::preUpdate);
		// Register pre remove event
		$this->registerEvent($classMetadata, ORM\Events::preRemove);
	}

	/**
	 * @param ORM\Event\OnFlushEventArgs $eventArgs
	 *
	 * @throws Exceptions\UnexpectedValueException
	 */
	public function onFlush(ORM\Event\OnFlushEventArgs $eventArgs)
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();

		// Check all scheduled updates
		foreach ($uow->getScheduledEntityUpdates() as $object) {
			/** @var ORM\Mapping\ClassMetadata $classMetadata */
			$classMetadata = $this->objectManager->getClassMetadata(get_class($object));

			if ($config = $this->getObjectConfigurations($classMetadata->getName())) {
				$changeSet = $uow->getEntityChangeSet($object);
				$needChanges = FALSE;

				if (isset($config['change'])) {
					foreach ($config['change'] as $options) {
						if (isset($changeSet[$options['field']])) {
							continue; // Value was set manually
						}

						if (!is_array($options['trackedField'])) {
							$singleField = TRUE;
							$trackedFields = [$options['trackedField']];

						} else {
							$singleField = FALSE;
							$trackedFields = $options['trackedField'];
						}

						foreach ($trackedFields as $tracked) {
							$trackedChild = NULL;
							$parts = explode('.', $tracked);

							if (isset($parts[1])) {
								$tracked = $parts[0];
								$trackedChild = $parts[1];
							}

							if (isset($changeSet[$tracked])) {
								$changes = $changeSet[$tracked];

								if (isset($trackedChild)) {
									$changingObject = $changes[1];

									if (!is_object($changingObject)) {
										throw new Exceptions\UnexpectedValueException("Field - [{$options['field']}] is expected to be object in class - {$classMetadata->getName()}");
									}

									/** @var ORM\Mapping\ClassMetadata $objectMeta */
									$objectMeta = $this->objectManager->getClassMetadata(get_class($changingObject));
									$this->objectManager->initializeObject($changingObject);
									$value = $objectMeta->getReflectionProperty($trackedChild)->getValue($changingObject);

								} else {
									$value = $changes[1];
								}

								if (($singleField && in_array($value, (array) $options['value'])) || $options['value'] === NULL) {
									$needChanges = TRUE;
									$this->updateField($uow, $object, $classMetadata, $options['field']);
								}
							}
						}
					}
				}

				if ($needChanges) {
					$uow->recomputeSingleEntityChangeSet($classMetadata, $object);
				}
			}
		}
	}

	/**
	 * @param mixed $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 */
	public function prePersist($entity, Doctrine\ORM\Event\LifecycleEventArgs $eventArgs)
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->getObjectConfigurations($classMetadata->getName())) {
			if (isset($config['update'])) {
				foreach ($config['update'] as $field) {
					$this->updateField($uow, $entity, $classMetadata, $field);
				}
			}

			if (isset($config['create'])) {
				foreach ($config['create'] as $field) {
					$this->updateField($uow, $entity, $classMetadata, $field);
				}
			}
		}
	}

	/**
	 * @param mixed $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 */
	public function preUpdate($entity, Doctrine\ORM\Event\LifecycleEventArgs $eventArgs)
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->getObjectConfigurations($classMetadata->getName())) {
			if (isset($config['update'])) {
				foreach ($config['update'] as $field) {
					$this->updateField($uow, $entity, $classMetadata, $field);
				}
			}
		}
	}

	/**
	 * @param mixed $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 */
	public function preRemove($entity, Doctrine\ORM\Event\LifecycleEventArgs $eventArgs)
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->getObjectConfigurations($classMetadata->getName())) {
			if (isset($config['delete'])) {
				foreach ($config['delete'] as $field) {
					$this->updateField($uow, $entity, $classMetadata, $field);
				}
			}
		}
	}

	/**
	 * Set a custom representation of current user
	 *
	 * @param mixed $user
	 */
	public function setUser($user)
	{
		$this->user = $user;
	}

	/**
	 * Get current user, either if $this->user is present or from userCallable
	 *
	 * @return mixed The user representation
	 */
	public function getUser()
	{
		if ($this->user !== NULL) {
			return $this->user;
		}

		if ($this->userCallable === NULL) {
			return;
		}

		$callable = $this->userCallable;

		return $callable();
	}

	/**
	 * @param callable $callable
	 */
	public function setUserCallable(callable $callable)
	{
		$this->userCallable = $callable;
	}

	/**
	 * Create default annotation reader for extensions
	 *
	 * @return Common\Annotations\AnnotationReader
	 */
	private function getDefaultAnnotationReader()
	{
		$reader = new Common\Annotations\AnnotationReader;

		Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
			'IPub\\DoctrineBlameable\\Mapping\\Annotation'
		);

		$reader = new Common\Annotations\CachedReader($reader, new Common\Cache\ArrayCache);

		return $reader;
	}

	/**
	 * Checks if $field type is valid
	 *
	 * @param object $meta
	 * @param string $field
	 *
	 * @return boolean
	 */
	private function isValidField($meta, $field)
	{
		$mapping = $meta->getFieldMapping($field);

		return $mapping && in_array($mapping['type'], $this->validTypes);
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $eventName
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function registerEvent(ORM\Mapping\ClassMetadata $classMetadata, $eventName)
	{
		if (!$this->hasRegisteredListener($classMetadata, $eventName, get_called_class())) {
			$classMetadata->addEntityListener($eventName, get_called_class(), $eventName);
		}
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $eventName
	 * @param string $listenerClass
	 *
	 * @return bool
	 */
	private static function hasRegisteredListener(ORM\Mapping\ClassMetadata $classMetadata, $eventName, $listenerClass)
	{
		if (!isset($classMetadata->entityListeners[$eventName])) {
			return FALSE;
		}

		foreach ($classMetadata->entityListeners[$eventName] as $listener) {
			if ($listener['class'] === $listenerClass && $listener['method'] === $eventName) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 */
	private function loadMetadataForObjectClass(ORM\Mapping\ClassMetadata $classMetadata)
	{
		if ($classMetadata->isMappedSuperclass) {
			return; // Ignore mappedSuperclasses for now
		}

		// The annotation reader accepts a ReflectionClass, which can be
		// obtained from the $classMetadata
		$class = $classMetadata->getReflectionClass();

		$config = [];

		$useObjectName = $classMetadata->getName();

		// Collect metadata from inherited classes
		if ($class !== NULL) {
			foreach (array_reverse(class_parents($classMetadata->getName())) as $parentClass) {
				// Read only inherited mapped classes
				if ($this->objectManager->getMetadataFactory()->hasMetadataFor($parentClass)) {
					/** @var ORM\Mapping\ClassMetadata $parentClassMetadata */
					$parentClassMetadata = $this->objectManager->getClassMetadata($parentClass);

					$config = $this->readExtendedMetadata($parentClassMetadata, $config);

					$isBaseInheritanceLevel = !$parentClassMetadata->isInheritanceTypeNone()
						&& $parentClassMetadata->parentClasses !== []
						&& $config !== [];

					if ($isBaseInheritanceLevel) {
						$useObjectName = $class->name;
					}
				}
			}

			$config = $this->readExtendedMetadata($classMetadata, $config);
		}

		if ($config !== []) {
			$config['useObjectClass'] = $useObjectName;
		}

		// Cache the metadata (even if it's empty)
		// Caching empty metadata will prevent re-parsing non-existent annotations
		$cacheId = self::getCacheId($classMetadata->getName());

		/** @var Common\Cache\Cache $cacheDriver */
		if ($cacheDriver = $this->objectManager->getMetadataFactory()->getCacheDriver()) {
			$cacheDriver->save($cacheId, $config, NULL);
		}

		$this->objectConfigurations[$classMetadata->getName()] = $config;
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $metadata
	 * @param array $config
	 *
	 * @return array
	 */
	private function readExtendedMetadata(ORM\Mapping\ClassMetadata $metadata, array $config)
	{
		$class = $metadata->getReflectionClass();

		// Create doctrine annotation reader
		$reader = $this->getDefaultAnnotationReader();

		// Property annotations
		foreach ($class->getProperties() as $property) {
			if ($metadata->isMappedSuperclass && $property->isPrivate() === FALSE ||
				$metadata->isInheritedField($property->getName()) ||
				isset($metadata->associationMappings[$property->getName()]['inherited'])
			) {
				continue;
			}

			if ($blameable = $reader->getPropertyAnnotation($property, self::EXTENSION_ANNOTATION)) {
				$field = $property->getName();

				// No map field nor association
				if ($metadata->hasField($field) === FALSE && $metadata->hasAssociation($field) === FALSE && $this->configuration->useLazyAssociation() === FALSE) {
					if ($this->configuration->automapField) {
						if ($this->configuration->automapWithAssociation()) {
							$entityMap = [
								'targetEntity' => $this->configuration->userEntity,
								'fieldName'    => $field,
								'joinColumns'  => [
									[
										'onDelete' => 'SET NULL',
									]
								]
							];

							if (isset($blameable->association['column']) && $blameable->association['column'] !== NULL) {
								$entityMap['joinColumns'][0]['name'] = $blameable->columnName;
							}

							if (isset($blameable->association['referencedColumn']) && $blameable->association['referencedColumn'] !== NULL) {
								$entityMap['joinColumns'][0]['referencedColumnName'] = $blameable->referencedColumnName;
							}

							$metadata->mapManyToOne($entityMap);

						} else if ($this->configuration->automapWithField()) {
							$metadata->mapField([
								'fieldName' => $field,
								'type'      => 'string',
								'nullable'  => TRUE,
							]);

						} else {
							throw new Exceptions\InvalidMappingException("Unable to find blameable [{$field}] as mapped property in entity - {$metadata->getName()}");
						}

					} else {
						throw new Exceptions\InvalidMappingException("Unable to find blameable [{$field}] as mapped property in entity - {$metadata->getName()}");
					}
				}

				if ($metadata->hasField($field)) {
					if (!$this->isValidField($metadata, $field) && $this->configuration->useLazyAssociation() === FALSE) {
						throw new Exceptions\InvalidMappingException("Field - [{$field}] type is not valid and must be 'string' or a one-to-many relation in class - {$metadata->getName()}");
					}

				} else if ($metadata->hasAssociation($field)) {
					// association
					if ($metadata->isSingleValuedAssociation($field)  === FALSE && $this->configuration->useLazyAssociation() === FALSE) {
						throw new Exceptions\InvalidMappingException("Association - [{$field}] is not valid, it must be a one-to-many relation or a string field - {$metadata->getName()}");
					}
				}

				// Check for valid events
				if (!in_array($blameable->on, ['update', 'create', 'change', 'delete'])) {
					throw new Exceptions\InvalidMappingException("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$metadata->getName()}");
				}

				if ($blameable->on === 'change') {
					if (!isset($blameable->field)) {
						throw new Exceptions\InvalidMappingException("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$metadata->getName()}");
					}

					if (is_array($blameable->field) && isset($blameable->value)) {
						throw new Exceptions\InvalidMappingException("Blameable extension does not support multiple value changeset detection yet.");
					}

					$field = [
						'field'        => $field,
						'trackedField' => $blameable->field,
						'value'        => $blameable->value,
					];
				}

				// properties are unique and mapper checks that, no risk here
				$config[$blameable->on][] = $field;
			}
		}

		return $config;
	}

	/**
	 * Get the configuration for specific object class
	 * if cache driver is present it scans it also
	 *
	 * @param string $class
	 *
	 * @return array
	 */
	private function getObjectConfigurations($class)
	{
		$config = [];

		if (isset($this->objectConfigurations[$class])) {
			$config = $this->objectConfigurations[$class];

		} else {
			$metadataFactory = $this->objectManager->getMetadataFactory();
			$cacheDriver = $metadataFactory->getCacheDriver();

			if ($cacheDriver) {
				$cacheId = self::getCacheId($class);

				if (($cached = $cacheDriver->fetch($cacheId)) !== FALSE) {
					$this->objectConfigurations[$class] = $cached;
					$config = $cached;

				} else {
					/** @var ORM\Mapping\ClassMetadata $classMetadata */
					$classMetadata = $metadataFactory->getMetadataFor($class);

					// Re-generate metadata on cache miss
					$this->loadMetadataForObjectClass($classMetadata);

					if (isset($this->objectConfigurations[$class])) {
						$config = $this->objectConfigurations[$class];
					}
				}

				$objectClass = isset($config['useObjectClass']) ? $config['useObjectClass'] : $class;

				if ($objectClass !== $class) {
					$this->getObjectConfigurations($objectClass);
				}

			}
		}

		return $config;
	}

	/**
	 * Updates a field
	 *
	 * @param ORM\UnitOfWork $uow
	 * @param mixed $object
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $field
	 */
	private function updateField(ORM\UnitOfWork $uow, $object, ORM\Mapping\ClassMetadata $classMetadata, $field)
	{
		$property = $classMetadata->getReflectionProperty($field);

		$oldValue = $property->getValue($object);
		$newValue = $this->getUserValue($classMetadata, $field);

		$property->setValue($object, $newValue);

		$uow->propertyChanged($object, $field, $oldValue, $newValue);
		$uow->scheduleExtraUpdate($object, [
			$field => [$oldValue, $newValue],
		]);
	}

	/**
	 * Get the user value to set on a blameable field
	 *
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $field
	 *
	 * @return mixed
	 */
	private function getUserValue(ORM\Mapping\ClassMetadata $classMetadata, $field)
	{
		$user = $this->getUser();

		if ($classMetadata->hasAssociation($field)) {
			if ($user !== NULL && ! is_object($user)) {
				throw new Exceptions\InvalidArgumentException("Blame is reference, user must be an object");
			}

			return $user;
		}

		// Ok so its not an association, then it is a string
		if (is_object($user)) {
			if (method_exists($user, '__toString')) {
				return $user->__toString();
			}

			throw new Exceptions\InvalidArgumentException("Field expects string, user must be a string, or object should have method getUsername or __toString");
		}

		return $user;
	}

	/**
	 * Get the cache id
	 *
	 * @param string $className
	 *
	 * @return string
	 */
	private static function getCacheId($className)
	{
		return $className . '\\$' . strtoupper(str_replace('\\', '_', __NAMESPACE__)) . '_CLASSMETADATA';
	}
}
