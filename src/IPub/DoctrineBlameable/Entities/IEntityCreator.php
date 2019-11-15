<?php
/**
 * IEntityCreator.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           10.11.15
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Entities;

/**
 * Doctrine blameable author entity interface
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
interface IEntityCreator
{
	/**
	 * @param mixed $createdBy
	 */
	public function setCreatedBy($createdBy) : void;

	/**
	 * @return mixed
	 */
	public function getCreatedBy();
}
