<?php
/**
 * Test: IPub\DoctrineBlameable\Extension
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           06.01.16
 */

namespace IPubTests\DoctrineBlameable;

use Nette;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrineBlameable;

require __DIR__ . '/../bootstrap.php';

/**
 * Registering doctrine blameable extension tests
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ExtensionTest extends Tester\TestCase
{
	public function testFunctional()
	{
		$dic = $this->createContainer();

		Assert::true($dic->getService('doctrineBlameable.configuration') instanceof DoctrineBlameable\Configuration);
		Assert::true($dic->getService('doctrineBlameable.driver') instanceof DoctrineBlameable\Mapping\Driver\Blameable);
		Assert::true($dic->getService('doctrineBlameable.listener') instanceof DoctrineBlameable\Events\BlameableListener);
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		DoctrineBlameable\DI\DoctrineBlameableExtension::register($config);

		return $config->createContainer();
	}
}

\run(new ExtensionTest());
