<?php
/**
 * Test: IPub\DoctrineBlameable\Models
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           05.01.16
 */

declare(strict_types = 1);

namespace IPubTests\DoctrineBlameable\Models;

use Doctrine\ORM\Mapping as ORM;

use IPub\DoctrineBlameable\Entities;
use IPub\DoctrineBlameable\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class ArticleEntity implements Entities\IEntityCreator, Entities\IEntityEditor, Entities\IEntityRemover
{
	use Entities\TEntityCreator;
	use Entities\TEntityEditor;
	use Entities\TEntityRemover;

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
	 * @ORM\ManyToOne(targetEntity="TypeEntity", inversedBy="articles")
	 */
	private $type;

	/**
	 * @var mixed
	 *
	 * @IPub\Blameable(on="change", field="type.title", value="Published")
	 */
	protected $publishedBy;

	/**
	 * @return int
	 */
	public function getId() : int
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getTitle() : string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle(string $title) : void
	{
		$this->title = $title;
	}

	/**
	 * @param TypeEntity $type
	 */
	public function setType(TypeEntity $type) : void
	{
		$this->type = $type;
	}

	/**
	 * @param mixed $publishedBy
	 */
	public function setPublishedBy($publishedBy) : void
	{
		$this->publishedBy = $publishedBy;
	}

	/**
	 * @return mixed
	 */
	public function getPublishedBy()
	{
		return $this->publishedBy;
	}
}
