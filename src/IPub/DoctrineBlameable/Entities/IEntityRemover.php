<?php
/**
 * IEntityRemover.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           07.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Entities;

/**
 * Doctrine blameable remover entity interface
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
interface IEntityRemover
{
	/**
	 * @param mixed $deletedBy
	 */
	function setDeletedBy($deletedBy) : void;

	/**
	 * @return mixed
	 */
	function getDeletedBy();
}
