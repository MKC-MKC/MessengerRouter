<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\Telegram;

use Haikiri\MessengerRouting\OnCommand;
use Tests\MessengerRouting\Mocked\Telegram\Contract\TelegramContract;

class ThanksCommand extends TelegramContract
{
	public bool $wasCalled = false;

	public function __construct()
	{
		parent::__construct($this);
	}

	#[OnCommand(commands: ["thank you", "спасибо"])]
	public function thanks(): void
	{
		$this->wasCalled = true; # Method matched and was called.
	}

}
