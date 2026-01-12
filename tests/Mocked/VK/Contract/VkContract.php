<?php

declare(strict_types=1);

namespace Tests\MessengerRouting\Mocked\VK\Contract;

use Haikiri\MessengerRouting\CommandsRouter;
use Haikiri\MessengerRouting\MessengerContractInterface;

/**
 * Implement This Class From `Update` Object of Your Messenger.
 * @see https://dev.vk.com/ru/api/callback/getting-started#%D0%A4%D0%BE%D1%80%D0%BC%D0%B0%D1%82%20%D0%B4%D0%B0%D0%BD%D0%BD%D1%8B%D1%85
 */
class VkContract extends CommandsRouter implements MessengerContractInterface
{
	public array $update = []; # Array of Messenger `update`.
	public string $envAdmins = "1234, 5678, 9101112"; # Messenger Peer ID's of ENV admins.

	/**
	 * Реализуй свой источник `update` здесь.
	 * @return array
	 */
	public function getUpdate(): array
	{
		return $this->update;
	}

	/**
	 * Это не используется в ВК.
	 */
	public function getBotName()
	{
		return null;
	}

	/**
	 * Это текст сообщения отпавителя.
	 * @return string|null
	 */
	public function getSenderText(): ?string
	{
		return null; // TODO: Implement getSenderText() method.
	}

	/**
	 * Алиас админов.
	 * @return bool
	 */
	public function isSenderAdmin(): bool
	{
		return false; // TODO: Implement isSenderAdmin() method.
	}

	/**
	 * Эти пользователи числятся как создатели чата. Могут, например, останавливать или запускать бота.
	 * @return bool
	 */
	public function isSenderOwner(): bool
	{
		return false; // TODO: Implement isSenderOwner() method.
	}

	/**
	 * Эти администраторы имеют самые большие права бота. Полный доступ везде.
	 * @return bool
	 */
	public function isSenderEnvAdmin(): bool
	{
		return false; // TODO: Implement isSenderEnvAdmin() method.
	}

	/**
	 * Обработчик Клавиатуры или прочей Callback чухни.
	 * @return void
	 */
	public function getSenderCallbackQueryData()
	{
		return $this->getUpdate()["payload"]["action"] ?? null;
	}

}
