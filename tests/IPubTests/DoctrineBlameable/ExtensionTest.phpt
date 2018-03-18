<?php
/**
 * Test: IPub\DoctrineBlameable\Extension
 * @testCase
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           06.01.16
 */

declare(strict_types = 1);

namespace IPubTests\DoctrineBlameable;

use Nette;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrineBlameable;

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Registering doctrine blameable extension tests
 *
 * @package        iPublikuj:DoctrineBlameable!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class ExtensionTest extends Tester\TestCase
{
	public function testFunctional() : void
	{
		$dic = $this->createContainer();

		Assert::true($dic->getService('doctrineBlameable.configuration') instanceof DoctrineBlameable\Configuration);
		Assert::true($dic->getService('doctrineBlameable.driver') instanceof DoctrineBlameable\Mapping\Driver\Blameable);
		Assert::true($dic->getService('doctrineBlameable.subscriber') instanceof DoctrineBlameable\Events\BlameableSubscriber);
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer() : Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . DS . 'files' . DS . 'config.neon');

		DoctrineBlameable\DI\DoctrineBlameableExtension::register($config);

		return $config->createContainer();
	}
}

\run(new ExtensionTest());
