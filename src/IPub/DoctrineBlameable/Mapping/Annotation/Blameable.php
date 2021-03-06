<?php
/**
 * Blameable.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Annotation
 * @since          1.0.0
 *
 * @date           01.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Doctrine blameable annotation for Doctrine2
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Annotation
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
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
