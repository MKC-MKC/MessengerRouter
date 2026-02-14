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

		$this->data = null;
		$text = $this->possibleCall("getSenderCallbackQueryData", "getSenderText");
		if (empty($text)) {
			error_log("Empty command sources retrieved from your contract interface.");
			$this->catch_all();
			return;
		}

		$exactResult = $this->exactCommandHandler($this, $text);
		if ($exactResult) return;

		$fuzzyResult = $this->fuzzyCommandHandler($this, $text);
		if ($fuzzyResult) return;

		$this->catch_all();
	}

	protected function catch_all(): void
	{
		$from = $this->attributes[0]["method"]->class . "::" . __FUNCTION__;
		error_log("[$from] - Implement this method in your command controller to handle events when no matches are found.");
	}

	protected function catch_unauthorized(): void
	{
		$from = $this->attributes[0]["method"]->class . "::" . __FUNCTION__;
		error_log("[$from] - Implement this method in your command controller to handle cases where the user fails the `isAppAuthorized()` check.");
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
		if (!preg_match("/^(\S+?)@([^\s@]+)(.*)$/u", $text, $matches)) return null;

		$commandBotName = mb_strtolower(trim($matches[2]));
		$envBotName = mb_strtolower(trim($this->possibleCall("getBotName") ?? " "));

		if ($commandBotName !== $envBotName) return false;

		return trim($matches[1] . $matches[3]);
	}

	/**
	 * Only authorized clients of your business-application will be able to use these commands.
	 * Только авторизованные клиенты вашего бизнес-приложения, смогут использовать эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function isCustomAppAuthorize(OnCommand $attribute): bool
	{
		return !$attribute->authorized || $this->possibleBoolCall("isAppAuthorized");
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
		return !$attribute->isEnvAdmin || $this->possibleBoolCall("isSenderEnvAdmin");
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
		return !$attribute->isOwner || $this->possibleBoolCall("isSenderOwner", "isSenderEnvAdmin");
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
		return !$attribute->isAdmin || $this->possibleBoolCall("isSenderAdmin", "isSenderOwner", "isSenderEnvAdmin");
	}

	/**
	 * Call first matching bool contract method with OR semantics.
	 *
	 * @param string ...$methods
	 * @return bool
	 */
	private function possibleBoolCall(string ...$methods): bool
	{
		foreach ($methods as $method) {
			if (!method_exists($this->contractInterface, $method)) continue;
			if ($this->contractInterface->$method()) return true;
		}

		return false;
	}

	/**
	 * Check command role access.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function hasAccessByRole(OnCommand $attribute): bool
	{
		return
			$this->canAccessByEnvAdmin($attribute) &&
			$this->canAccessByOwner($attribute) &&
			$this->canAccessByAdmin($attribute);
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
		if ($separator === "") {
			$text = $this->normalizeFuzzyString($text);
			return $text === "" ? [] : [$text];
		}

		return array_values(array_filter(
			array_map(fn($part) => $this->normalizeFuzzyString($part), explode($separator, $text)),
			fn($part) => $part !== ""
		));
	}

	/**
	 * Process exact matches.
	 *
	 * @param object $controller
	 * @param string $text
	 * @return bool
	 */
	private function exactCommandHandler(object $controller, string $text): bool
	{
		foreach ($this->attributes as $item) {
			/** @var OnCommand $route */
			$route = $item["attribute"];
			$routeText = $text;

			# Check and trim the @NameOfBot.
			if ($route->botName && !$route->return_data) {
				$trimName = $this->prepareBotName($routeText);
				if ($trimName === false) return true;
				if (is_string($trimName)) $routeText = $trimName;
			}

			# Prepare text.
			$textParts = $this->normalizeSplit($routeText, $route->separator);

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

				# Skip inaccessible commands and continue to next available command.
				if (!$this->hasAccessByRole($route)) continue;

				# Stop and trigger unauthorized callback for matched protected commands.
				if (!$this->isCustomAppAuthorize($route)) {
					$this->catch_unauthorized();
					return true;
				}

				$this->data = $route->return_data ? array_merge([$routeText], $params) : null;
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
	private function fuzzyCommandHandler(object $controller, string $text): bool
	{
		if ($this->commands_debug) error_log("\n" . str_repeat("=", 100) . "\n> INPUT COMMAND:\n\"$text\"\n");
		$text = $this->normalizeFuzzyString($text, true);

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
			$matched = array_filter($route->commands, function ($command) use ($text, $route) {
				$cmd = $this->normalizeFuzzyString($command, true);
				$percent = self::fuzzyMatch($cmd, $text, $route->fuzzy);

				if ($this->commands_debug) {
					error_log("... CHECK FOR \"$command\" => (" . round($percent, 3) . "% >= $route->temperature%)");
				}

				return $percent >= $route->temperature;
			});

			if (!empty($matched)) {
				if (!$this->hasAccessByRole($route)) continue;
				if (!$this->isCustomAppAuthorize($route)) {
					$this->catch_unauthorized();
					return true;
				}

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

		if (empty($command) || empty($source)) return 0.0;

		if (!$extended || $commandLength > $sourceLength) {
			return self::similarPercent($command, $source);
		}

		$percent = 0.0;
		for ($i = 0; $i <= $sourceLength - $commandLength; $i++) {
			$part = mb_substr($source, $i, $commandLength);
			$best = self::similarPercent($command, $part);
			if ($best > $percent) $percent = $best;
		}

		return $percent;
	}

	/**
	 * Calculate similarity percent for ascii and utf-8 strings.
	 *
	 * @param string $command
	 * @param string $source
	 * @return float
	 */
	private static function similarPercent(string $command, string $source): float
	{
		if (self::isAscii($command) && self::isAscii($source)) {
			similar_text($command, $source, $percent);
			return $percent;
		}

		$dictionary = [];
		$nextCode = 1;
		$commandEncoded = self::encodeUtf8ToSingleByte($command, $dictionary, $nextCode);
		$sourceEncoded = self::encodeUtf8ToSingleByte($source, $dictionary, $nextCode);

		if ($commandEncoded === null || $sourceEncoded === null) {
			return self::lcsSimilarityPercent($command, $source);
		}

		similar_text($commandEncoded, $sourceEncoded, $percent);
		return $percent;
	}

	/**
	 * Check if a string has only ascii symbols.
	 *
	 * @param string $value
	 * @return bool
	 */
	private static function isAscii(string $value): bool
	{
		return preg_match("/[^\x00-\x7F]/", $value) !== 1;
	}

	/**
	 * Encode utf-8 string into single-byte alphabet for safe similar_text compare.
	 *
	 * @param string $value
	 * @param array $dictionary
	 * @param int $nextCode
	 * @return string|null
	 */
	private static function encodeUtf8ToSingleByte(string $value, array &$dictionary, int &$nextCode): ?string
	{
		$chars = preg_split("//u", $value, -1, PREG_SPLIT_NO_EMPTY);
		if ($chars === false) return null;

		$result = "";
		foreach ($chars as $char) {
			if (!isset($dictionary[$char])) {
				if ($nextCode > 255) return null;
				$dictionary[$char] = chr($nextCode);
				$nextCode++;
			}

			$result .= $dictionary[$char];
		}

		return $result;
	}

	/**
	 * Unicode similarity based on LCS.
	 *
	 * @param string $command
	 * @param string $source
	 * @return float
	 */
	private static function lcsSimilarityPercent(string $command, string $source): float
	{
		$left = preg_split("//u", $command, -1, PREG_SPLIT_NO_EMPTY);
		$right = preg_split("//u", $source, -1, PREG_SPLIT_NO_EMPTY);
		if (empty($left) || empty($right)) return 0.0;

		$rightLength = count($right);
		$previous = array_fill(0, $rightLength + 1, 0);

		foreach ($left as $leftChar) {
			$current = [0];
			for ($j = 1; $j <= $rightLength; $j++) {
				if ($leftChar === $right[$j - 1]) {
					$current[$j] = $previous[$j - 1] + 1;
				} else {
					$current[$j] = max($current[$j - 1], $previous[$j]);
				}
			}

			$previous = $current;
		}

		$lcsLength = $previous[$rightLength];
		return (2 * $lcsLength / (count($left) + $rightLength)) * 100;
	}

}
