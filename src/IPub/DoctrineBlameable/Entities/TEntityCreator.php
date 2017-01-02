<?php
/**
 * TEntityCreator.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           29.01.14
 */

namespace IPub\DoctrineBlameable\Entities;

use IPub\DoctrineBlameable\Mapping\Annotation as IPub;

/**
 * Doctrine blameable author entity
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
trait TEntityCreator
{
	/**
	 * @var mixed|NULL
	 *
	 * @IPub\Blameable(on="create")
	 */
	protected $createdBy;

	/**
	 * @param mixed $createdBy
	 */
	public function setCreatedBy($createdBy)
	{
		$this->createdBy = $createdBy;
	}

	/**
	 * @return mixed|NULL
	 */
	public function getCreatedBy()
	{
		return $this->createdBy;
	}
}
