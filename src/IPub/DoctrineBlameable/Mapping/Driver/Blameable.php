<?php
/**
 * Blameable.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Driver
 * @since          1.0.0
 *
 * @date           05.01.16
 */

namespace IPub\DoctrineBlameable\Mapping\Driver;

use Nette;

use Doctrine;
use Doctrine\Common;
use Doctrine\ORM;

use IPub;
use IPub\DoctrineBlameable;
use IPub\DoctrineBlameable\Exceptions;
use IPub\DoctrineBlameable\Mapping;

/**
 * Doctrine blameable annotation driver
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Driver
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Blameable extends Nette\Object
{
	/**
	 * Define class name
	 */
	const CLASS_NAME = __CLASS__;

	/**
	 * Annotation field is blameable
	 */
	const EXTENSION_ANNOTATION = 'IPub\DoctrineBlameable\Mapping\Annotation\Blameable';

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
	 * @param Common\Persistence\ObjectManager $objectManager
	 */
	public function __construct(
		DoctrineBlameable\Configuration $configuration,
		Common\Persistence\ObjectManager $objectManager
	) {
		$this->objectManager = $objectManager;
		$this->configuration = $configuration;
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 */
	public function loadMetadataForObjectClass(ORM\Mapping\ClassMetadata $classMetadata)
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
				if ($this->objectManager->getMetadataFactory()->hasMetadataFor($parentClass)) {
					/** @var ORM\Mapping\ClassMetadata $parentClassMetadata */
					$parentClassMetadata = $this->objectManager->getClassMetadata($parentClass);

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
		if ($cacheDriver = $this->objectManager->getMetadataFactory()->getCacheDriver()) {
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
	 * @throws Exceptions\InvalidMappingException
	 * @throws ORM\Mapping\MappingException
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
					if ($metadata->isSingleValuedAssociation($field) === FALSE && $this->configuration->useLazyAssociation() === FALSE) {
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
						'value'        => is_array($blameable->value) ? $blameable->value : [$blameable->value],
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
	public function getObjectConfigurations($class)
	{
		$config = [];

		if (isset(self::$objectConfigurations[$class])) {
			$config = self::$objectConfigurations[$class];

		} else {
			$metadataFactory = $this->objectManager->getMetadataFactory();
			/** @var Common\Cache\Cache $cacheDriver|NULL */
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
					$this->loadMetadataForObjectClass($classMetadata);

					if (isset(self::$objectConfigurations[$class])) {
						$config = self::$objectConfigurations[$class];
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
