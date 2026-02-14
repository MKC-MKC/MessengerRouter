<?php

declare(strict_types=1);

namespace Tests\MessengerRouting;

use PHPUnit\Framework\TestCase;
use Tests\MessengerRouting\Mocked\Regression\RegressionCommands;

class CommandsRouterRegressionTest extends TestCase
{

	private static function process(RegressionCommands $controller, string $text): mixed
	{
		$controller->update = [
			"message" => [
				"text" => $text,
			],
		];

		$controller->__invoke();
		return $controller->wasCalled;
	}

	public function testPublicCommandShouldNotBeBlockedByRestrictedRoutes(): void
	{
		$controller = new RegressionCommands();
		self::assertSame("public_ping", self::process($controller, "public ping"));
	}

	public function testUnauthorizedShouldBeTriggeredOnlyForMatchedProtectedCommand(): void
	{
		$publicController = new RegressionCommands();
		self::assertSame("public_ping", self::process($publicController, "public ping"));

		$secureController = new RegressionCommands();
		self::assertSame("catch_unauthorized", self::process($secureController, "secure ping"));

		$authorizedController = new RegressionCommands();
		$authorizedController->appAuthorized = true;
		self::assertSame("secure_ping", self::process($authorizedController, "secure ping"));
	}

	public function testOwnerCanExecuteAdminCommandByHierarchy(): void
	{
		$controller = new RegressionCommands();
		$controller->senderOwner = true;
		self::assertSame("admin_ping", self::process($controller, "admin ping"));
	}

	public function testEnvAdminCanExecuteOwnerCommandByHierarchy(): void
	{
		$controller = new RegressionCommands();
		$controller->senderEnvAdmin = true;
		self::assertSame("owner_ping", self::process($controller, "owner ping"));
	}

	public function testFuzzyCyrillicShouldAvoidByteLevelFalsePositive(): void
	{
		$noMatchController = new RegressionCommands();
		self::assertSame("catch_all", self::process($noMatchController, "оооо"));

		$matchController = new RegressionCommands();
		self::assertSame("cyrillic_fuzzy", self::process($matchController, "пппо"));
	}

	public function testEmptySeparatorShouldWorkWithoutCrash(): void
	{
		$controller = new RegressionCommands();
		self::assertSame("empty_separator", self::process($controller, "abc"));
	}

}
