<?php
/**
 * IEntityCreator.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           10.11.15
 */

namespace IPub\DoctrineBlameable\Entities;

/**
 * Doctrine blameable author entity interface
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IEntityCreator
{
	/**
	 * @param mixed $createdBy
	 *
	 * @return $this
	 */
	function setCreatedBy($createdBy);

	/**
	 * @return mixed
	 */
	function getCreatedBy();
}
