<?php

namespace Haikiri\MessengerRouting;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OnCommand
{
	public array $commands;

	/**
	 * @param array|string $commands Command or array of commands: "/command1" or ["/command1", "command2"].
	 * @param bool $return_data Whether the function should return the command data.
	 * @param bool $require_data Whether data is required. If required but missing - mismatch.
	 * @param bool $strict Require a full exact match without trailing command data after normalization.
	 * @param string $separator Data separator, space by default. For example "/ban user1 2day". Use "_" for "/ban_user1_2d"
	 * @param int|float $temperature Text matching threshold percentage. Disabled by default when (100%).
	 * @param bool $fuzzy Allow matching a command anywhere within the full text string.
	 * @param bool $botName Allow to check the bot's name from your getter to support direct requests to your bot at /start@TungTungBot
	 * @param bool $isOwner Whether creator privileges are required to execute the command.
	 * @param bool $isAdmin Whether admin privileges are required to execute the command.
	 * @param bool $isEnvAdmin Allow the execution of the command only on behalf of the creators of the boat listed in your getter.
	 * @param bool $authorized Allow execution of the command only for users authorized by a custom auth tool.
	 */
	public function __construct(
		array|string     $commands,
		public bool      $return_data = false,
		public bool      $require_data = false,
		public bool      $strict = false,
		public string    $separator = " ",
		public int|float $temperature = 100,
		public bool      $fuzzy = false,
		public bool      $botName = false,
		public bool      $isOwner = false,
		public bool      $isAdmin = false,
		public bool      $isEnvAdmin = false,
		public bool      $authorized = false,
	)
	{
		$this->commands = is_array($commands) ? $commands : [$commands];
	}

}
