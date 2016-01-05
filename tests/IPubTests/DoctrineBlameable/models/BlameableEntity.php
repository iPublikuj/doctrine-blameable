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

/**
 * @ORM\Entity
 */
class BlameableEntity implements Entities\IEntityAuthor, Entities\IEntityEditor
{
	use Entities\TEntityAuthor;
	use Entities\TEntityEditor;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 */
	private $title;

	/**
	 * Returns object id
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Get title
	 *
	 * @return string title
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * Set title
	 *
	 * @param string $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}
}
