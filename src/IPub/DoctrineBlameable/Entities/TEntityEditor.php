<?php
/**
 * TEntityEditor.php
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
 * Doctrine blameable editor entity
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
trait TEntityEditor
{
	/**
	 * @var mixed|NULL
	 *
	 * @IPub\Blameable(on="update")
	 */
	protected $updatedBy;

	/**
	 * @param mixed $updatedBy
	 */
	public function setUpdatedBy($updatedBy)
	{
		$this->updatedBy = $updatedBy;
	}

	/**
	 * @return mixed|NULL
	 */
	public function getUpdatedBy()
	{
		return $this->updatedBy;
	}
}
