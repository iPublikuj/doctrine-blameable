<?php
/**
 * TEntityCreator.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec https://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           29.01.14
 */

declare(strict_types = 1);

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
	 *
	 * @return void
	 */
	public function setCreatedBy($createdBy) : void
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
