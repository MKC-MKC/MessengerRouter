# Messenger Router

Attribute Commands handler for your messengers, works like [Symfony/Routing](https://github.com/symfony/routing),
or like this: [luzrain/telegram-bot-bundle for Symfony](https://github.com/luzrain/telegram-bot-bundle).

But I believe this library can be used not only with Telegram, but also with your other messengers!
This code present as an idea, based on attributes introduced in PHP 8 and above.
This library is native, and has no dependencies or bindings with some external libraries.
Hope there will be cool ideas, fixes, and implementations based on this project idea!  
If you need to make any changes, write to the contact details in `composer.json`.

---

## Installation

```bash
composer require haikiri/messenger-routing
```

Или всегда последняя сборка:

```bash
composer require haikiri/messenger-routing:dev-main
```

---

## Requirements:

- Composer 2+
- PHP >= 8.0
    - `ext-mbstring`

---

## Theory, how it works:

- Create a messenger controller
    - Create an abstract model that implements the interface `\Haikiri\MessengerRouting\MessengerContractInterface`
    - Fill this model with methods from the interface.
    - Check which type of message you will handle, if needed: `["message", "edited_message", ...etc]`
        - After checking, create a handler class that will store commands.
          Your new handler must inherit from CommandsRouter. Like in this example:
          `class MyMessengerCommands extends \Haikiri\MessengerRouting\CommandsRouter`
    - Make your commands based on the example below.

---

## Errors

Your new command storage can now throw several types of exceptions by default:

- 1: `\InvalidArgumentException`

> In case you did not pass text data for command processing or the method is not implemented.

- 2: `\Haikiri\MessengerRouting\NoRouteException`

> In case no command is implemented in your command handler.

---

Please note, if no command has been found, you can catch this discrepancy and implement your behavior.
By default, your controller will return true.
This will not cause an exception if the command is not found. The code will continue to work.

Обрати внимание, если ни одна команда не была найдена, ты можешь отловить это расхождение и реализовать своё поведение.
По умолчанию ваш контроллер вернёт true.
Это не вызовет исключение, если команда не найдена. Код продолжит работу.

```php
	#   This method is optional, and will contain your implementation if no one command is found.
	protected final function catch_all(): void
	{
		echo "Realize this method if you need to handle the event in your own way if more than one command was not found.";
		echo "For example: send a message that such a command has not been found.";
	}
```

## Access rights

Your controller supports access rights restrictions. These rights are implemented by you in your model.  
This means that now you have separation between administrator, chat owner, and environment administrators.

Hierarchy of rights:

- ENV admins – like the `root` user in Linux. For example, they can disable the bot in any chat with this bot.
- Chat creator can do almost the same but only within their chat.
- Administrator can execute all commands intended only for their group.

This means that an admin cannot execute commands specific for the chat creator or environment administrator.  
At the same time, the environment administrator can do everything that others cannot.  
It expected that the list of admin’s IDs will be listed in your project's .ENV file as a string, separated by commas.

> Please note that only the environment administrator is detected in private messages!

---

# Usage

> This is the most terrible instruction in the world, but, alas, I don't like it anymore than you do!

Structure:

- /src
    - /Controller – Our web pages (Symfony) are stored here.
    - /Messenger – Everything related to messengers will be here.

First, let's make our entry point. A Webhook will be configured on this page.

`/src/Controller/ApiController.php`

```php
	// This method accepts only POST requests from Telegram.
	#[Route(path: "/api/telegram/controller", name: "controller_post", methods: ["POST"])]
	public function controller_post(): Response
	{
		(new \App\Messenger\TelegramMessenger())->handleUpdate();
		return new Response("ok");
	}
```

Now let's create a Telegram Messenger class that will process our Telegram requests.
Connect your Telegram SDK in the constructor. Please, use your real library! This is just an example!

`/src/Messenger/TelegramMessenger.php`

```php
class TelegramMessenger extends TelegramContract
{
	public \Developer\OF\MyWonderfulTelegramSDK $bot;

	public function __construct()
	{
		# Our Telegram Api Bots SDK Here: $this->bot
		$this->bot = new \Developer\OF\MyWonderfulTelegramSDK(
			your_api_server: $_ENV['TELEGRAM_API_HOST'],
			your_api_token: $_ENV['TELEGRAM_API_TOKEN'],
		);
	}

	public function handleUpdate(): void
	{

		# Check for message type.
		$messageType = $this->bot->getMessage()->getType();
		switch ($messageType) {
			case "private":
				new Telegram\PrivateRoom($this); # Pass `$this` to your private room for using MessengerContract and Tg API.
//			case "group":
//				new Telegram\GroupRoom($this); # Pass `$this` to your private room for using MessengerContract and Tg API.
//			case "supergroup":
//				new Telegram\SuperGroupRoom($this); # Pass `$this` to your private room for using MessengerContract and Tg API.
			default:
				throw new \Exception("sorry. this chat is not supported: `{$messageType}`");
		}

	}
```

Write the `extends TelegramContract` and create an abstract class, where we'll write the data for our command handler.
Connecting our interface from the new library `\Haikiri\MessengerRouting\MessengerContractInterface`:

`/src/Messenger/TelegramContract.php`

```php
namespace App\Messenger;

abstract class TelegramContract implements \Haikiri\MessengerRouting\MessengerContractInterface
{
	/**
	 * Note: that your library returns getUpdate() objects.
	 * This is just a source of your bot sdk.
	 * @see https://core.telegram.org/bots/api#update
	 */
	public function getUpdate(): object
	{
		return $this->bot->getUpdate();
	}

	public function getBotName(): string
	{
		return "MyNewBOT"; # without at `@`. your bot name must be ends at `bot` or `_bot`.
//		return $_ENV["TG_BOT_NAME"] ?? null; # or use data from your .ENV
	}

	public function isSenderAdmin(): bool
	{
		return false; // TODO: Implement verification for the chat administrator.
	}

	public function isSenderOwner(): bool
	{
		return false; // TODO: Implement verification for the chat creator.
	}

	public function isSenderEnvAdmin(): bool
	{
		return
		$env = $_ENV["TG_ADMINS_LIST"] ?? null; # use var from .ENV
		$env = "1872656523,387456823,237864528,3457862300"; # or write manually (unsafe)
		return !empty($env) && in_array($this->getUpdate()->getUser()->getId(), explode(",", $env));
	}

	public function getSenderText(): ?string
	{
		$message = $this->getUpdate()->getMessage(); # get Message object.
		return $message ? $message->getText() : null; # example for return message text
	}

	public function getSenderCallbackQueryData(): string|null
	{
		return null; // TODO: Stay null, if your messenger doesn't support callback query, or u dont work with it.
	}

}
```

After creating all the main classes, u will make a trial command handler in private messages with the bot.
See example for PrivateRoom.php:

`/src/Messenger/Telegram/PrivateRoom.php`

```php
<?php

declare(strict_types=1);

namespace App\Messenger\Telegram;

use Haikiri\MessengerRouting\Command;
use Haikiri\MessengerRouting\CommandsRouter;
use App\Messenger\Controller\TelegramMessenger;

class PrivateRoom extends CommandsRouter
{
	private TelegramMessenger $messenger;

	public function __construct(TelegramMessenger $controller)
	{
		$this->messenger = $controller; # using our parent controller for working with telegram sdk
		parent::__construct($controller); # Always at end
	}

	# Use this method for catch unhandled commands. remove it if u dnt need it.
	protected final function catch_all(): void
	{
		$chat_id = $this->messenger->bot->getUpdate()->getChat()->getId();
		$message = "Command not found: {$this->messenger->getSenderText()}";

		$this->messenger->server->sendMessage(chatId: $chat_id, text: $message);
	}

	#[Command(commands: "/start")]
	public function start()
	{
		$this->messenger->bot->sendMessage(
			chatId: $this->messenger->bot->getUpdate()->getChat()->getId(),
			text: "You are call: `/start`!",
		);
	}

	#[Command(commands: "/stop", isOwner: true)] # Only the chat creator and ENV Admins can stop this bot!
	public function start(): void
	{
		$this->messenger->bot->sendMessage(
			chatId: $this->messenger->bot->getUpdate()->getChat()->getId(),
			text: "Implement this method as u need. Only owner and ENV admin can use it.",
		);
	}

	#[Command(
		commands: [
			"/balance", "баланс", "сколько денег", "сколько денек", "мой счет", "сколько средств",
			"сколько денег", "у меня денег", "у меня бабок","остаток денег", "остаток средств",
			"сколько у меня", "скок у меня", "баланс лицевого счёта", "баланс лицевого счёта",
		],
		return_data: false, # temperature not worked if return_data is true.
	    temperature: 60, # The threshold of a match.
	)]
	public function balance()
	{
		$this->messenger->bot->sendMessage(
			chatId: $this->messenger->bot->getUpdate()->getChat()->getId(),
			text:
			"The built-in Levenshtein library allows small errors in the text." .
			"This method will work if there is a match for the specified temperature and above." . 
			"Please note: that when using `temperature`, most likely, your team will not check the administrator rights.",
		);
	}

	#[Command(
		commands: ["ban", "бан"],
		return_data: true, # temperature not worked if return_data is true.
		require_data: true, # this method of command will be ignored if there is no data with this flag if true
		isAdmin: true, # only administrators can use the command. regular users will be ignored.
	)]
	public function ban()
	{
		echo "You are call: `/ban`!";
		echo "See your data and use as u want:<br>";
		var_dump($this->data);
	}

	#[Command(
		commands: "left",
		isEnvAdmin: true, # only ENV admins can use this command. chat owner, administrators, and others will be ignored
	)]
	public function left()
	{
		echo "The creator of the bot asked the bot to get off the chat.";
	}

}
```
