<?php
/**
 * IEntityAuthor.php
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

use Nette;
use Nette\Security as NS;

interface IEntityAuthor
{
	/**
	 * @param mixed $createdBy
	 *
	 * @return $this
	 */
	public function setCreatedBy($createdBy);

	/**
	 * @return mixed
	 */
	public function getCreatedBy();
}
