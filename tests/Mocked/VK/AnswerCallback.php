<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\VK;

use Haikiri\MessengerRouting\OnCommand;
use Tests\MessengerRouting\Mocked\VK\Contract\VkContract;

class AnswerCallback extends VkContract
{
	public bool $wasCalled = false;

	public function __construct()
	{
		parent::__construct($this);
	}

	#[OnCommand(commands: ["answer_yes"])]
	public function answer_yes(): void
	{
		$this->wasCalled = true; # Method matched and was called.
	}

	#[OnCommand(commands: ["answer_no"])]
	public function answer_no(): void
	{
		$this->wasCalled = true; # Method matched and was called.
	}

}
