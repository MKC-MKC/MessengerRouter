<?php

namespace Haikiri\MessengerRouting;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OnCommand
{
	public array $commands;
	public bool $return_data;
	public bool $require_data;
	public string $separator;
	public int|float $temperature;
	public bool $botName;
	public bool $isOwner;
	public bool $isAdmin;
	public bool $isEnvAdmin;

	/**
	 * @param array|string $commands # Command or array of commands: "/command1" or ["/command1", "command2"].
	 * @param bool $return_data # Whether the function should return the command data.
	 * @param bool $require_data # Whether data is required. If required but missing - mismatch.
	 * @param string $separator # Data separator, space by default. For example "/ban user1 2day". Use "_" for "/ban_user1_2d"
	 * @param int|float $temperature # Text matching threshold percentage. Disabled by default when (100%).
	 * @param bool $botName # Allow to check the bot's name from your getter to support direct requests to your bot at /start@TungTungBot
	 * @param bool $isOwner # Whether creator privileges are required to execute the command.
	 * @param bool $isAdmin # Whether admin privileges are required to execute the command.
	 * @param bool $isEnvAdmin # Allow the execution of the command only on behalf of the creators of the boat listed in your getter.
	 */
	public function __construct(
		array|string $commands,
		bool         $return_data = false,
		bool         $require_data = false,
		string       $separator = " ",
		int|float    $temperature = 100,
		bool         $botName = false,
		bool         $isOwner = false,
		bool         $isAdmin = false,
		bool         $isEnvAdmin = false,
	)
	{
		$this->commands = is_array($commands) ? $commands : [$commands];
		$this->return_data = $return_data;
		$this->require_data = $require_data;
		$this->separator = $separator;
		$this->temperature = $temperature;
		$this->botName = $botName;
		$this->isOwner = $isOwner;
		$this->isAdmin = $isAdmin;
		$this->isEnvAdmin = $isEnvAdmin;
	}

}
