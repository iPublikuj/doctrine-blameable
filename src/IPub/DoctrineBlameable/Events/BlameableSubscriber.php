<?php
/**
 * BlameableSubscriber.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           01.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Events;

use Nette;

use Doctrine\Common;
use Doctrine\ORM;

use IPub\DoctrineBlameable\Exceptions;
use IPub\DoctrineBlameable\Mapping;

/**
 * Doctrine blameable subscriber
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class BlameableSubscriber implements Common\EventSubscriber
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * @var callable
	 */
	private $userCallable;

	/**
	 * @var mixed
	 */
	private $user;

	/**
	 * @var Mapping\Driver\Blameable
	 */
	private $driver;

	/**
	 * Register events
	 *
	 * @return string[]
	 */
	public function getSubscribedEvents() : array
	{
		return [
			ORM\Events::loadClassMetadata,
			ORM\Events::onFlush,
		];
	}

	/**
	 * @param callable|NULL $userCallable
	 * @param Mapping\Driver\Blameable $driver
	 */
	public function __construct(
		$userCallable = NULL,
		Mapping\Driver\Blameable $driver
	) {
		$this->driver = $driver;
		$this->userCallable = $userCallable;
	}

	/**
	 * @param ORM\Event\LoadClassMetadataEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function loadClassMetadata(ORM\Event\LoadClassMetadataEventArgs $eventArgs) : void
	{
		/** @var ORM\Mapping\ClassMetadata $classMetadata */
		$classMetadata = $eventArgs->getClassMetadata();
		$this->driver->loadMetadataForObjectClass($eventArgs->getObjectManager(), $classMetadata);

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
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function onFlush(ORM\Event\OnFlushEventArgs $eventArgs) : void
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();

		// Check all scheduled updates
		foreach ($uow->getScheduledEntityUpdates() as $object) {
			/** @var ORM\Mapping\ClassMetadata $classMetadata */
			$classMetadata = $em->getClassMetadata(get_class($object));

			if ($config = $this->driver->getObjectConfigurations($em, $classMetadata->getName())) {
				$changeSet = $uow->getEntityChangeSet($object);
				$needChanges = FALSE;

				if ($uow->isScheduledForInsert($object) && isset($config['create'])) {
					foreach ($config['create'] as $field) {
						// Field can not exist in change set, when persisting embedded document without parent for example
						$new = array_key_exists($field, $changeSet) ? $changeSet[$field][1] : FALSE;

						if ($new === NULL) { // let manual values
							$needChanges = TRUE;
							$this->updateField($uow, $object, $classMetadata, $field);
						}
					}
				}

				if (isset($config['update'])) {
					foreach ($config['update'] as $field) {
						$isInsertAndNull = $uow->isScheduledForInsert($object)
							&& array_key_exists($field, $changeSet)
							&& $changeSet[$field][1] === NULL;

						if (!isset($changeSet[$field]) || $isInsertAndNull) { // let manual values
							$needChanges = TRUE;
							$this->updateField($uow, $object, $classMetadata, $field);
						}
					}
				}

				if (isset($config['delete'])) {
					foreach ($config['delete'] as $field) {
						$isDeleteAndNull = $uow->isScheduledForDelete($object)
							&& array_key_exists($field, $changeSet)
							&& $changeSet[$field][1] === NULL;

						if (!isset($changeSet[$field]) || $isDeleteAndNull) { // let manual values
							$needChanges = TRUE;
							$this->updateField($uow, $object, $classMetadata, $field);
						}
					}
				}

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
									$objectMeta = $em->getClassMetadata(get_class($changingObject));
									$em->initializeObject($changingObject);
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
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function prePersist($entity, ORM\Event\LifecycleEventArgs $eventArgs) : void
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->driver->getObjectConfigurations($em, $classMetadata->getName())) {
			foreach(['update', 'create'] as $event) {
				if (isset($config[$event])) {
					$this->updateFields($config[$event], $uow, $entity, $classMetadata);
				}
			}
		}
	}

	/**
	 * @param mixed $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function preUpdate($entity, ORM\Event\LifecycleEventArgs $eventArgs) : void
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->driver->getObjectConfigurations($em, $classMetadata->getName())) {
			if (isset($config['update'])) {
				$this->updateFields($config['update'], $uow, $entity, $classMetadata);
			}
		}
	}

	/**
	 * @param mixed $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function preRemove($entity, ORM\Event\LifecycleEventArgs $eventArgs) : void
	{
		$em = $eventArgs->getEntityManager();
		$uow = $em->getUnitOfWork();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if ($config = $this->driver->getObjectConfigurations($em, $classMetadata->getName())) {
			if (isset($config['delete'])) {
				$this->updateFields($config['delete'], $uow, $entity, $classMetadata);
			}
		}
	}

	/**
	 * Set a custom representation of current user
	 *
	 * @param mixed $user
	 *
	 * @return void
	 */
	public function setUser($user) : void
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
			return NULL;
		}

		$callable = $this->userCallable;

		return $callable();
	}

	/**
	 * @param callable $callable
	 *
	 * @return void
	 */
	public function setUserCallable(callable $callable) : void
	{
		$this->userCallable = $callable;
	}

	/**
	 * @param array $fields
	 * @param ORM\UnitOfWork $uow
	 * @param mixed $object
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 *
	 * @return void
	 */
	private function updateFields(array $fields, ORM\UnitOfWork $uow, $object, ORM\Mapping\ClassMetadata $classMetadata) : void
	{
		foreach ($fields as $field) {
			if ($classMetadata->getReflectionProperty($field)->getValue($object) === NULL) { // let manual values
				$this->updateField($uow, $object, $classMetadata, $field);
			}
		}
	}

	/**
	 * Updates a field
	 *
	 * @param ORM\UnitOfWork $uow
	 * @param mixed $object
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $field
	 *
	 * @return void
	 */
	private function updateField(ORM\UnitOfWork $uow, $object, ORM\Mapping\ClassMetadata $classMetadata, string $field) : void
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
	private function getUserValue(ORM\Mapping\ClassMetadata $classMetadata, string $field)
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
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $eventName
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function registerEvent(ORM\Mapping\ClassMetadata $classMetadata, string $eventName) : void
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
	private static function hasRegisteredListener(ORM\Mapping\ClassMetadata $classMetadata, string $eventName, string $listenerClass) : bool 
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
}
