<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\Telegram\Contract;

use Haikiri\MessengerRouting\CommandsRouter;
use Haikiri\MessengerRouting\MessengerContractInterface;

/**
 * Implement This Class From `Update` Object of Your Messenger.
 * @see https://core.telegram.org/bots/api#update
 */
class TelegramContract extends CommandsRouter implements MessengerContractInterface
{
	public array $update = []; # Array of Messenger `update`.
	public string $envAdmins = "1234, 5678, 9101112"; # Messenger Peer ID's of ENV admins.
	public string $botName = "NameOf_bot"; # Telegram Bot Name `@NameOf_bot`.

	/**
	 * Implement your `update` source here.
	 * Реализуй свой источник `update` здесь.
	 * @return array
	 */
	public function getUpdate(): array
	{
		return $this->update;
	}

	/**
	 * Name of Bot on Messenger. Only for groups. Add support for listening to `/start@NameOfBot`
	 * Имя Бота в Мессенджере. Только для групп. Добавляет прослушку `/start@NameOfBot`
	 * @return string
	 */
	public function getBotName(): string
	{
		return $this->botName;
	}

	/**
	 * Это текст сообщения отпавителя.
	 * @return string|null
	 */
	public function getSenderText(): ?string
	{
		return $this->getUpdate()["message"]["text"];
	}

	public function isAppAuthorized(): bool
	{
		return false; // TODO: Implement isAppAuthorized() method.
	}

	/**
	 * Admins. No access to `owner` and `env admin` commands.
	 * Админы. Не имеет доступа к командам `owner` и `env admin`.
	 * @return bool
	 */
	public function isSenderAdmin(): bool
	{
		return false; // TODO: Implement isSenderAdmin() method.
	}

	/**
	 * These admins are listed as chat creators and can, for example, stop or start the bot.
	 * Эти админы числятся как создатели чата. Могут, например, останавливать или запускать бота.
	 * @return bool
	 */
	public function isSenderOwner(): bool
	{
		return false; // TODO: Implement isSenderOwner() method.
	}

	/**
	 * These admins have the most powerful bot rights. Full access everywhere.
	 * Эти админы имеют самые большие права бота. Полный доступ везде.
	 * @return bool
	 */
	public function isSenderEnvAdmin(): bool
	{
		$env = $this->envAdmins ?? null;
		return !empty($env) && in_array($this->getUpdate()["message"]["from"]["id"], explode(",", $env));
	}

	/**
	 * Handler of Keyboard or other Callback sources.
	 * Обработчик Клавиатуры или прочей Callback чухни.
	 * @return string|null
	 */
	public function getSenderCallbackQueryData(): string|null
	{
		return null; // TODO: Implement getSenderCallbackQueryData() method.
	}

}
