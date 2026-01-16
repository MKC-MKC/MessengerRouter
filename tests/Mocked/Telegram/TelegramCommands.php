<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\Telegram;

use Haikiri\MessengerRouting\OnCommand;
use Tests\MessengerRouting\Mocked\Telegram\Contract\TelegramContract;

class TelegramCommands extends TelegramContract
{
	public bool $wasCalled = false;

	public function __construct()
	{
		$this->commands_debug = true;
		parent::__construct($this);
	}

	#[OnCommand(commands: ["thank you", "спасибо"])]
	public function thanks(): void
	{
		$this->wasCalled = true; # Method matched and was called.
	}

	#[OnCommand(commands: ["detach and require", "отвязать и требовать"], return_data: true, require_data: true)]
	public function detach(): void
	{
		$this->wasCalled = true; # Method matched and was called.
	}

}
