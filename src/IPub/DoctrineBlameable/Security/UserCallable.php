<?php

namespace IPub\DoctrineBlameable\Security;

use Nette;
use Nette\Security as NS;

class UserCallable
{
	/**
	 * Define class name
	 */
	const CLASS_NAME = __CLASS__;

	/**
	 * @var NS\User
	 */
	private $user;

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
