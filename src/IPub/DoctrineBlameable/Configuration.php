<?php
/**
 * Configuration.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     common
 * @since          1.0.0
 *
 * @date           02.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable;

use Nette;

/**
 * Doctrine blameable extension configuration storage
 * Store basic extension settings
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class Configuration
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * Flag if use lazy association or not
	 *
	 * @var bool
	 */
	public $lazyAssociation = FALSE;

	/**
	 * User entity name
	 *
	 * @var string|NULL
	 */
	public $userEntity;

	/**
	 * Automatically map filed if not set
	 *
	 * @var bool
	 */
	public $automapField = TRUE;

	/**
	 * @param string|NULL $userEntity
	 * @param bool $lazyAssociation
	 * @param bool $automapField
	 */
	public function __construct(string $userEntity = NULL, bool $lazyAssociation = FALSE, bool $automapField = FALSE)
	{
		$this->userEntity = $userEntity;
		$this->lazyAssociation = $lazyAssociation;
		$this->automapField = $automapField;
	}

	/**
	 * @return bool
	 */
	public function automapWithAssociation() : bool
	{
		if ($this->userEntity !== NULL && class_exists($this->userEntity) && $this->automapField === TRUE) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * @return bool
	 */
	public function automapWithField() : bool
	{
		if ($this->userEntity === NULL && $this->automapField === TRUE) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * @return bool
	 */
	public function useLazyAssociation() : bool
	{
		return $this->lazyAssociation === TRUE;
	}
}
