<?php

declare(strict_types=1);

namespace Haikiri\MessengerRouting;

use ReflectionClass;
use ReflectionMethod;

abstract class CommandsRouter
{
	private ?array $attributes;
	protected array|null $data = null;
	public bool $commands_debug = false;
	protected MessengerContractInterface $contractInterface;

	/**
	 * @param MessengerContractInterface $controller
	 */
	public function __construct(MessengerContractInterface $controller)
	{
		$this->contractInterface = $controller;
		$this->attributes = $this->getAttributes($this);
	}

	public function __invoke(): void
	{
		if (empty($this->attributes)) {
			error_log("No one command found in your controller.");
			return;
		}

		$text = $this->possibleCall("getSenderCallbackQueryData", "getSenderText");
		if (empty($text)) {
			error_log("Empty command sources retrieved from your contract interface.");
			$this->catch_all();
			return;
		}

		$exactResult = $this->exactMatchHandler($this, $text);
		if ($exactResult) return;

		$fuzzyResult = $this->fuzzyMatchHandler($this, $text);
		if ($fuzzyResult) return;

		$this->catch_all();
	}

	/**
	 * Implement this method in your class to handle events when matches is differ.
	 */
	protected function catch_all(): void
	{
		$from = $this->attributes[0]["method"]->class . "::" . __FUNCTION__;
		error_log("[$from] - Implement this method in your command controller to handle events when no matches are found.");
	}

	/**
	 * Tries to call first available contract method.
	 *
	 * @param string ...$methods
	 * @return mixed Returns the first working method or null
	 */
	private function possibleCall(string ...$methods): mixed
	{
		return array_reduce($methods, function ($carry, $method) {
			return $carry ?? (method_exists($this->contractInterface, $method) ? $this->contractInterface->$method() : null);
		});
	}

	/**
	 * Method to get attributes.
	 *
	 * @param object $controller
	 * @return array|null
	 */
	private function getAttributes(object $controller): ?array
	{
		$reflection = new ReflectionClass($controller);
		return array_reduce($reflection->getMethods(), function ($carry, ReflectionMethod $method) {
			$attrs = $method->getAttributes(OnCommand::class);
			if (!empty($attrs)) {
				$carry[] = [
					"method" => $method,
					"attribute" => $attrs[0]->newInstance()
				];
			}
			return $carry;
		}, []);
	}

	/**
	 * String formatting.
	 *
	 * @param string $input
	 * @param bool $withSpaces
	 * @return string
	 */
	protected final function normalizeFuzzyString(string $input, bool $withSpaces = false): string
	{
		$input = mb_strtolower(trim($input));
		$regex = $withSpaces
			? "/[^\p{Cyrillic}a-zA-Z0-9-\s]/u"
			: "/[^\p{Cyrillic}a-zA-Z0-9-]/u";
		$input = preg_replace($regex, "", $input);

		if ($withSpaces) {
			$input = preg_replace("/\s+/", " ", $input);
			$input = trim($input);
		} else {
			$input = str_replace(" ", "", $input);
		}

		return $input;
	}

	/**
	 * Check and explode @NameOfBot
	 *
	 * @param string $text
	 * @return string|false|null
	 */
	private function prepareBotName(string $text): string|false|null
	{
		if (!str_contains($text, "@")) return null;

		$parts = explode("@", $text);
		$commandBotName = mb_strtolower(trim(end($parts)));
		$envBotName = mb_strtolower(trim($this->possibleCall("getBotName") ?? " "));

		if ($commandBotName !== $envBotName) return false;

		array_pop($parts);
		return trim(implode("@", $parts));
	}

	/**
	 * Only environment administrators can run these commands.
	 * Только администраторы окружения смогут выполнять эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByEnvAdmin(OnCommand $attribute): bool
	{
		return !$attribute->isEnvAdmin || $this->possibleCall("isSenderEnvAdmin");
	}

	/**
	 * Only the admin of env, and the chat creator can execute these commands.
	 * Только администратор окружения и создатель чата, смогут выполнить эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByOwner(OnCommand $attribute): bool
	{
		return !$attribute->isOwner || $this->possibleCall("isSenderOwner", "isSenderEnvAdmin");
	}

	/**
	 * Only the chat admin, env, and creator can execute these commands.
	 * Только администратор или чата, окружения и создатель, смогут выполнить эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByAdmin(OnCommand $attribute): bool
	{
		return !$attribute->isAdmin || $this->possibleCall("isSenderAdmin", "isSenderOwner", "isSenderEnvAdmin");
	}

	/**
	 * Split and normalize command text string by separator.
	 *
	 * @param string $text
	 * @param string $separator
	 * @return array
	 */
	private function normalizeSplit(string $text, string $separator): array
	{
		return array_filter(array_map(fn($part) => $this->normalizeFuzzyString($part), explode($separator, $text)), fn($part) => $part !== "");
	}

	/**
	 * Process exact matches.
	 *
	 * @param object $controller
	 * @param string $text
	 * @return bool
	 */
	private function exactMatchHandler(object $controller, string $text): bool
	{
		foreach ($this->attributes as $item) {
			/** @var OnCommand $route */
			$route = $item["attribute"];

			# Access rights check.
			if (
				($route->isEnvAdmin && !$this->canAccessByEnvAdmin($route)) ||
				($route->isOwner && !$this->canAccessByOwner($route)) ||
				($route->isAdmin && !$this->canAccessByAdmin($route))
			) continue;

			# Check n trim the @NameOfBot.
			if ($route->botName && !$route->return_data) {
				$trimName = $this->prepareBotName($text);
				if ($trimName === false) return false;
				if (is_string($trimName)) $text = $trimName;
			}

			# Prepare text.
			$textParts = $this->normalizeSplit($text, $route->separator);

			# Process command.
			$params = array_reduce(
				$route->commands,
				function ($carry, $cmd) use ($route, $textParts) {
					if ($carry !== null) return $carry;

					$cmdParts = $this->normalizeSplit($cmd, $route->separator);
					if (empty($cmdParts) || count($textParts) < count($cmdParts)) return null;
					if ($cmdParts !== array_slice($textParts, 0, count($cmdParts))) return null;

					$params = array_slice($textParts, count($cmdParts));
					return $route->require_data && empty($params) ? null : $params;
				}
			);

			if ($params !== null) {
				if ($route->require_data && empty($params)) continue;
				$this->data = $route->return_data ? array_merge([$text], $params) : null;
				$item["method"]->invokeArgs($controller, $this->data ? [$this->data] : []);
				return true;
			}
		}

		return false;
	}

	/**
	 * Process fuzzy matches.
	 *
	 * @param object $controller
	 * @param string $text
	 * @return bool
	 */
	private function fuzzyMatchHandler(object $controller, string $text): bool
	{
		if ($this->commands_debug) error_log("\n\n>> INPUT COMMAND:\n\"$text\"\n");

		foreach ($this->attributes as $item) {
			/** @var OnCommand $route */
			$route = $item["attribute"];
			$splitCommands = implode(", ", $route->commands);
			if ($this->commands_debug) error_log(PHP_EOL . "> AVAILABLE COMMANDS:\n\"$splitCommands\"");

			# If match temperature is 100% - skip.
			if (round($route->temperature) >= 100) {
				if ($this->commands_debug) error_log("<< SKIP: BECAUSE TEMPERATURE IS NOT SET\n");
				continue;
			}

			# If data or return is required - skip.
			if ($route->return_data || $route->require_data) {
				if ($this->commands_debug) error_log(PHP_EOL . "<< SKIP: BECAUSE DATA OR DATA RETURN IS REQUIRED\n\"$splitCommands\"\n");
				continue;
			}

			# Process command.
			$text = $this->normalizeFuzzyString($text, true);
			$matched = array_filter($route->commands, function ($command) use ($text, $route) {
				$cmd = $this->normalizeFuzzyString($command, true);
				$percent = self::fuzzyMatch($cmd, $text, $route->fuzzy);

				if ($this->commands_debug) {
					error_log("... CHECK FOR \"$command\" => (" . round($percent, 3) . "% >= $route->temperature%)");
				}

				return $percent >= $route->temperature;
			});

			if (!empty($matched)) {
				if ($this->commands_debug) error_log(">> Matched commands: " . var_export($matched, true));
				$item["method"]->invokeArgs($controller, []);
				return true;
			}
		}

		return false;
	}

	/**
	 * Method to calculate fuzzy match percentage.
	 *
	 * @param string $command
	 * @param string $source
	 * @param bool $extended
	 * @return float
	 */
	private static function fuzzyMatch(string $command, string $source, bool $extended): float
	{
		$commandLength = mb_strlen($command);
		$sourceLength = mb_strlen($source);

		if ($commandLength === 0 || $sourceLength === 0) return 0.0;

		if (!$extended) {
			$distance = levenshtein($command, $source);
			$maxLen = max($commandLength, $sourceLength);
			return (1 - ($distance / $maxLen)) * 100;
		}

		$percent = 0.0;
		for ($i = 0; $i <= $sourceLength - $commandLength; $i++) {
			$text = mb_substr($source, $i, $commandLength);
			$distance = levenshtein($command, $text);
			$best = (1 - ($distance / $commandLength)) * 100;
			if ($best > $percent) $percent = $best;
		}

		return $percent;
	}

}
