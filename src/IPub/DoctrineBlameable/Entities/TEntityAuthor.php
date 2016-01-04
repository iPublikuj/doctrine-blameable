<?php
/**
 * TEntityAuthor.php
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

trait TEntityAuthor
{
	/**
	 * @var mixed
	 *
	 * @IPub\Blameable(on="create")
	 */
	protected $createdBy;

	/**
	 * {@inheritdoc}
	 */
	public function setCreatedBy($createdBy)
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCreatedBy()
	{
		return $this->createdBy;
	}
}
