<?php
/**
 * UnexpectedValueException.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Exceptions
 * @since          1.0.0
 *
 * @date           04.01.16
 */

declare(strict_types = 1);

namespace IPub\DoctrineBlameable\Exceptions;

class UnexpectedValueException extends \UnexpectedValueException implements IException
{
}
