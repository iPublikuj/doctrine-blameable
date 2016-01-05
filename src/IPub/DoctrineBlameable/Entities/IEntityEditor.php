<?php
/**
 * IEntityEditor.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           11.11.15
 */

namespace IPub\DoctrineBlameable\Entities;

/**
 * Doctrine blameable editor entity interface
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IEntityEditor
{
	/**
	 * @param mixed $modifiedBy
	 *
	 * @return $this
	 */
	public function setModifiedBy($modifiedBy);

	/**
	 * @return mixed
	 */
	public function getModifiedBy();
}
