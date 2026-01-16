<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\VK;

use Haikiri\MessengerRouting\OnCommand;
use Tests\MessengerRouting\Mocked\VK\Contract\VkContract;

class AnswerCallback extends VkContract
{
	public mixed $wasCalled = null;

	public function __construct()
	{
		$this->commands_debug = true;
		parent::__construct($this);
	}

	#[OnCommand(commands: ["answer_yes"])]
	public function answer_yes(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: ["answer_no"])]
	public function answer_no(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

}
