<?php
/**
 * UserCallable.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Security
 * @since          1.0.0
 *
 * @date           05.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Security;

use Nette;
use Nette\Security as NS;

/**
 * Doctrine blameable default user callable
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Security
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class UserCallable
{
	/**
	 * Define class name
	 */
	const CLASS_NAME = __CLASS__;

	/**
	 * @var NS\User
	 */
	private $user;

	/**
	 * @param NS\User $user
	 */
	public function __construct(NS\User $user)
	{
		$this->user = $user;
	}

	/**
	 * @return mixed
	 */
	public function __invoke()
	{
		if ($this->user->isLoggedIn()) {
			return $this->user->getId();
		}

		return NULL;
	}
}
