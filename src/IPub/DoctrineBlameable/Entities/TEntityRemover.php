<?php
/**
 * TEntityRemover.php
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
 * Doctrine blameable remover entity
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
trait TEntityRemover
{
	/**
	 * @var mixed|NULL
	 *
	 * @IPub\Blameable(on="delete")
	 */
	protected $deletedBy;

	/**
	 * @param mixed $deletedBy
	 */
	public function setDeletedBy($deletedBy)
	{
		$this->deletedBy = $deletedBy;
	}

	/**
	 * @return mixed|NULL
	 */
	public function getDeletedBy()
	{
		return $this->deletedBy;
	}
}
