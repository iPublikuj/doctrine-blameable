<?php
/**
 * Test: IPub\DoctrineBlameable\Models
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

namespace IPubTests\DoctrineBlameable\Models;

use Doctrine\ORM\Mapping as ORM;

use IPub\DoctrineBlameable\Entities;
use IPub\DoctrineBlameable\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class BlameableEntity implements Entities\IEntityAuthor, Entities\IEntityEditor
{
	use Entities\TEntityAuthor;
	use Entities\TEntityEditor;

	/**
	 * @var int
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 * @ORM\Column(type="string", nullable=true)
	 */
	private $title;

	/**
	 * @var mixed
	 *
	 * @IPub\Blameable(on="delete")
	 */
	protected $deletedBy;

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * @param mixed $deletedBy
	 */
	public function setDeletedBy($deletedBy)
	{
		$this->deletedBy = $deletedBy;
	}

	/**
	 * @return mixed
	 */
	public function getDeletedBy()
	{
		return $this->deletedBy;
	}
}
