<?php
/**
 * Blameable.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Annotation
 * @since          1.0.0
 *
 * @date           01.01.16
 */

namespace IPub\DoctrineBlameable\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Doctrine blameable annotation for Doctrine2
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Annotation
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Blameable extends Annotation
{
	/**
	 * @var string
	 */
	public $on = 'update';

	/**
	 * @var string|array
	 */
	public $field;

	/**
	 * @var mixed
	 */
	public $value;

	/**
	 * @var array|NULL
	 */
	public $association;
}
