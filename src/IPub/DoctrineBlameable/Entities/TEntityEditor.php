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

use Doctrine\ORM\Mapping as ORM;

use IPub\DoctrineBlameable\Mapping\Annotation as IPub;

trait TEntityEditor
{
	/**
	 * @var mixed
	 *
	 * @IPub\Blameable(on="update")
	 */
	protected $modifiedBy;

	/**
	 * {@inheritdoc}
	 */
	public function setModifiedBy($modifiedBy)
	{
		$this->modifiedBy = $modifiedBy;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getModifiedBy()
	{
		return $this->modifiedBy;
	}
}
