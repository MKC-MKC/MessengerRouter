<?php

declare(strict_types=1);

namespace Tests\MessengerRouting;

use PHPUnit\Framework\TestCase;
use Tests\MessengerRouting\Mocked\Telegram;

class CommandsRouterTest extends TestCase
{

	private array $update = [
		"update_id" => 283756232,
		"message" => [
			"message_id" => 123,
			"from" => [
				"id" => 123456789,
				"is_bot" => false,
				"first_name" => "Иван Иванов",
				"username" => "ivan_ivanov",
			],
			"chat" => [
				"id" => -100123456789,
				"title" => "Messenger Router",
				"type" => "supergroup",
			],
			"date" => 1700000000,
			"text" => "Some Message Text",
		],
	];

	private static function process($controller, $update)
	{
		$controller->update = $update;
		$controller->__invoke();
		return $controller->wasCalled;
	}

	public function test_ThanksCommand($expected = "thanks"): void
	{
		$this->update["message"]["text"] = "Thank You!";
		self::assertSame($expected, self::process(new Telegram\TelegramCommands(), $this->update));

		$this->update["message"]["text"] = "Спасибо!";
		self::assertSame($expected, self::process(new Telegram\TelegramCommands(), $this->update));
	}

	/**
	 * Here present two commands:
	 * 1. `detach and require`
	 * 2. `отвязать и требовать`
	 */
	public function testCommandWithRequiredData($expected = "detach"): void
	{
		$this->update["message"]["text"] = "Detach and Require: 888";
		self::assertSame($expected, self::process(new Telegram\TelegramCommands(), $this->update));

		$this->update["message"]["text"] = "Отвязать и Требовать: 888";
		self::assertSame($expected, self::process(new Telegram\TelegramCommands(), $this->update));
	}

}
