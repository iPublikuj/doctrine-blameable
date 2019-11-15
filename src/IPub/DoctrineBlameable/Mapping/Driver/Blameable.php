<?php
/**
 * Blameable.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Driver
 * @since          1.0.0
 *
 * @date           05.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Mapping\Driver;

use Nette;

use Doctrine\Common;
use Doctrine\ORM;

use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Exceptions;
use IPub\DoctrineBlameable\Mapping;

/**
 * Doctrine blameable annotation driver
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Driver
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class Blameable
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * Annotation field is blameable
	 */
	private const EXTENSION_ANNOTATION = 'IPub\DoctrineBlameable\Mapping\Annotation\Blameable';

	/**
	 * @var DoctrineBlameable\Configuration
	 */
	private $configuration;

	/**
	 * List of cached object configurations
	 *
	 * @var array
	 */
	private static $objectConfigurations = [];

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
	 * @param DoctrineBlameable\Configuration $configuration
	 */
	public function __construct(DoctrineBlameable\Configuration $configuration)
	{
		$this->configuration = $configuration;
	}

	/**
	 * @param Common\Persistence\ObjectManager $objectManager
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function loadMetadataForObjectClass(Common\Persistence\ObjectManager $objectManager, ORM\Mapping\ClassMetadata $classMetadata) : void
	{
		if ($classMetadata->isMappedSuperclass) {
			return; // Ignore mappedSuperclasses for now
		}

		// The annotation reader accepts a ReflectionClass, which can be
		// obtained from the $classMetadata
		$reflectionClass = $classMetadata->getReflectionClass();

		$config = [];

		$useObjectName = $classMetadata->getName();

		// Collect metadata from inherited classes
		if ($reflectionClass !== NULL) {
			foreach (array_reverse(class_parents($classMetadata->getName())) as $parentClass) {
				// Read only inherited mapped classes
				if ($objectManager->getMetadataFactory()->hasMetadataFor($parentClass)) {
					/** @var ORM\Mapping\ClassMetadata $parentClassMetadata */
					$parentClassMetadata = $objectManager->getClassMetadata($parentClass);

					$config = $this->readExtendedMetadata($parentClassMetadata, $config);

					$isBaseInheritanceLevel = !$parentClassMetadata->isInheritanceTypeNone()
						&& $parentClassMetadata->parentClasses !== []
						&& $config !== [];

					if ($isBaseInheritanceLevel === TRUE) {
						$useObjectName = $reflectionClass->getName();
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
		if ($cacheDriver = $objectManager->getMetadataFactory()->getCacheDriver()) {
			$cacheDriver->save($cacheId, $config, NULL);
		}

		self::$objectConfigurations[$classMetadata->getName()] = $config;
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $metadata
	 * @param array $config
	 *
	 * @return array
	 *
	 * @throws Common\Annotations\AnnotationException
	 * @throws ORM\Mapping\MappingException
	 */
	private function readExtendedMetadata(ORM\Mapping\ClassMetadata $metadata, array $config) : array
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

			/** @var Mapping\Annotation\Blameable $blameable */
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
									],
								],
							];

							if (isset($blameable->association['column']) && $blameable->association['column'] !== NULL) {
								$entityMap['joinColumns'][0]['name'] = $blameable->columnName;
							}

							if (isset($blameable->association['referencedColumn']) && $blameable->association['referencedColumn'] !== NULL) {
								$entityMap['joinColumns'][0]['referencedColumnName'] = $blameable->referencedColumnName;
							}

							$metadata->mapManyToOne($entityMap);

						} elseif ($this->configuration->automapWithField()) {
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

				if ($metadata->hasField($field) && $this->isValidField($metadata, $field) === FALSE && $this->configuration->useLazyAssociation() === FALSE) {
					throw new Exceptions\InvalidMappingException("Field - [{$field}] type is not valid and must be 'string' or a one-to-many relation in class - {$metadata->getName()}");

				} elseif ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field) === FALSE && $this->configuration->useLazyAssociation() === FALSE) {
					throw new Exceptions\InvalidMappingException("Association - [{$field}] is not valid, it must be a one-to-many relation or a string field - {$metadata->getName()}");
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
						'value'        => is_array($blameable->value) ? $blameable->value : [$blameable->value],
					];
				}

				$config[$blameable->on][] = $field;
			}
		}

		return $config;
	}

	/**
	 * Get the configuration for specific object class
	 * if cache driver is present it scans it also
	 *
	 * @param Common\Persistence\ObjectManager $objectManager
	 * @param string $class
	 *
	 * @return array
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	public function getObjectConfigurations(Common\Persistence\ObjectManager $objectManager, string $class) : array
	{
		$config = [];

		if (isset(self::$objectConfigurations[$class])) {
			$config = self::$objectConfigurations[$class];

		} else {
			$metadataFactory = $objectManager->getMetadataFactory();
			/** @var Common\Cache\Cache $cacheDriver |NULL */
			$cacheDriver = $metadataFactory->getCacheDriver();

			if ($cacheDriver !== NULL) {
				$cacheId = self::getCacheId($class);

				if (($cached = $cacheDriver->fetch($cacheId)) !== FALSE) {
					self::$objectConfigurations[$class] = $cached;
					$config = $cached;

				} else {
					/** @var ORM\Mapping\ClassMetadata $classMetadata */
					$classMetadata = $metadataFactory->getMetadataFor($class);

					// Re-generate metadata on cache miss
					$this->loadMetadataForObjectClass($objectManager, $classMetadata);

					if (isset(self::$objectConfigurations[$class])) {
						$config = self::$objectConfigurations[$class];
					}
				}

				$objectClass = isset($config['useObjectClass']) ? $config['useObjectClass'] : $class;

				if ($objectClass !== $class) {
					$this->getObjectConfigurations($objectManager, $objectClass);
				}
			}
		}

		return $config;
	}

	/**
	 * Create default annotation reader for extensions
	 *
	 * @return Common\Annotations\CachedReader
	 *
	 * @throws Common\Annotations\AnnotationException
	 */
	private function getDefaultAnnotationReader() : Common\Annotations\CachedReader
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
	 * @param ORM\Mapping\ClassMetadata $meta
	 * @param string $field
	 *
	 * @return bool
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function isValidField(ORM\Mapping\ClassMetadata $meta, string $field) : bool
	{
		$mapping = $meta->getFieldMapping($field);

		return $mapping && in_array($mapping['type'], $this->validTypes);
	}

	/**
	 * Get the cache id
	 *
	 * @param string $className
	 *
	 * @return string
	 */
	private static function getCacheId(string $className) : string
	{
		return $className . '\\$' . strtoupper(str_replace('\\', '_', __NAMESPACE__)) . '_CLASSMETADATA';
	}

}
