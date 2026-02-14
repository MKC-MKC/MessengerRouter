<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\Regression;

use Haikiri\MessengerRouting\OnCommand;
use Tests\MessengerRouting\Mocked\Regression\Contract\RegressionContract;

class RegressionCommands extends RegressionContract
{
	public mixed $wasCalled = null;

	public function __construct()
	{
		parent::__construct($this);
	}

	protected function catch_all(): void
	{
		$this->wasCalled = __FUNCTION__; # No command was matched.
	}

	protected function catch_unauthorized(): void
	{
		$this->wasCalled = __FUNCTION__; # Matched command requires app authorization.
	}

	#[OnCommand(commands: "public ping")]
	public function public_ping(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: "admin ping", isAdmin: true)]
	public function admin_ping(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: "owner ping", isOwner: true)]
	public function owner_ping(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: "secure ping", authorized: true)]
	public function secure_ping(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: "пппп", temperature: 50)]
	public function cyrillic_fuzzy(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}

	#[OnCommand(commands: "abc", separator: "")]
	public function empty_separator(): void
	{
		$this->wasCalled = __FUNCTION__; # Method matched and was called.
	}
}
