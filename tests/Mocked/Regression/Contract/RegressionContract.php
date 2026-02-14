<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\Regression\Contract;

use Haikiri\MessengerRouting\CommandsRouter;
use Haikiri\MessengerRouting\MessengerContractInterface;

abstract class RegressionContract extends CommandsRouter implements MessengerContractInterface
{
	public array $update = [];
	public bool $appAuthorized = false;
	public bool $senderAdmin = false;
	public bool $senderOwner = false;
	public bool $senderEnvAdmin = false;

	public function getBotName()
	{
		return "test_bot";
	}

	public function getSenderText(): ?string
	{
		return $this->update["message"]["text"] ?? null;
	}

	public function isAppAuthorized(): bool
	{
		return $this->appAuthorized;
	}

	public function isSenderAdmin(): bool
	{
		return $this->senderAdmin;
	}

	public function isSenderOwner(): bool
	{
		return $this->senderOwner;
	}

	public function isSenderEnvAdmin(): bool
	{
		return $this->senderEnvAdmin;
	}

	public function getSenderCallbackQueryData(): ?string
	{
		return null;
	}
}
