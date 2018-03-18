<?php
/**
 * Test: IPub\DoctrineBlameable\Models
 * @testCase
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

declare(strict_types = 1);

namespace IPubTests\DoctrineBlameable\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class UserEntity
{
	/**
	 * @var int
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private $username;

	/**
	 * @return string
	 */
	public function getUsername() : string
	{
		return $this->username;
	}

	/**
	 * @param string $username
	 */
	public function setUsername(string $username) : void
	{
		$this->username = $username;
	}
}
