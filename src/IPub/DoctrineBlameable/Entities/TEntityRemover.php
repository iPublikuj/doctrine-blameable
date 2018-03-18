<?php
/**
 * TEntityRemover.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
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
 * Doctrine blameable remover entity
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
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
	 *
	 * @return void
	 */
	public function setDeletedBy($deletedBy) : void
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
