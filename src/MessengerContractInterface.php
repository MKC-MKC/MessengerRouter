<?php

namespace Haikiri\MessengerRouting;

interface MessengerContractInterface
{

	public function getBotName();

	public function getSenderText(): ?string;

	public function isSenderAdmin(): bool;

	public function isSenderOwner(): bool;

	public function isSenderEnvAdmin(): bool;

	public function getSenderCallbackQueryData();

}
