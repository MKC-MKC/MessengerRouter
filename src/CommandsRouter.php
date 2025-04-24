<?php

namespace Haikiri\MessengerRouting;

use ReflectionClass;
use ReflectionMethod;
use InvalidArgumentException;

abstract class CommandsRouter
{
	private ?array $attributes;
	protected MessengerContractInterface $contractInterface;

	/**
	 * @param MessengerContractInterface $controller
	 * @throws NoRouteException|InvalidArgumentException
	 */
	public function __construct(MessengerContractInterface $controller)
	{
		#	Interface that is implemented in the controller.
		$this->contractInterface = $controller;

		#	Initialize attributes.
		$this->attributes = $this->getAttributes($this);
		if (empty($this->attributes)) throw new NoRouteException("No one command found in your controller");

		#	Check for presence of text.
		$text = $this->possibleCall("getSenderCallbackQueryData", "getSenderText");
		if (empty($text)) throw new InvalidArgumentException("No one command source found in your interface");

		#	Process exact matches.
		$exactResult = $this->exactMatchDispatch($this, $text);
		if ($exactResult) return true;

		#	Process fuzzy matches.
		$fuzzyResult = $this->fuzzyMatchDispatch($this, $text);
		if ($fuzzyResult) return true;

		#	Here we process everything if mismatched.
		return $this->catch_all() ?? true;
	}

	/**
	 * Redefine this method in your class to handle events when matches is differ.
	 */
	protected function catch_all(): void
	{
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
			$attrs = $method->getAttributes(Command::class);
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
	private function checkBotName(string $text): string|false|null
	{
		if (!str_contains($text, "@")) return null;

		$parts = explode("@", $text);
		$commandBotName = strtolower(trim(end($parts)));
		$envBotName = strtolower(trim($this->possibleCall("getBotName") ?? " "));

		if ($commandBotName !== $envBotName) return false;

		array_pop($parts);
		return trim(implode("@", $parts));
	}

	/**
	 * Method checks the id's of listed admins in your getter.
	 * Только администраторы окружения смогут выполнять эти команды.
	 *
	 * @param Command $attribute
	 * @return bool
	 */
	private function canAccessByEnvAdmin(Command $attribute): bool
	{
		return !$attribute->isEnvAdmin || $this->possibleCall("isSenderEnvAdmin");
	}

	/**
	 * Method checks of the owner rights.
	 * Только создатель чата или администратор окружения сможет выполнить эти команды.
	 *
	 * @param Command $attribute
	 * @return bool
	 */
	private function canAccessByOwner(Command $attribute): bool
	{
		return !$attribute->isOwner || $this->possibleCall("isSenderOwner") || $this->canAccessByEnvAdmin($attribute);
	}

	/**
	 * Method checks of the admin rights.
	 * Только администратор окружения или чата, сможет выполнить эти команды.
	 *
	 * @param Command $attribute
	 * @return bool
	 */
	private function canAccessByAdmin(Command $attribute): bool
	{
		return !$attribute->isAdmin || $this->possibleCall("isSenderAdmin") || $this->canAccessByOwner($attribute);
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
	private function exactMatchDispatch(object $controller, string $text): bool
	{
		foreach ($this->attributes as $item) {
			/** @var Command $route */
			$route = $item["attribute"];

			error_log(PHP_EOL . __LINE__);

			#	Access rights check.
			if ($this->canAccessByEnvAdmin($route) || $this->canAccessByOwner($route) || $this->canAccessByAdmin($route)) continue;
			error_log(PHP_EOL . __LINE__);

			#	Check n trim the @NameOfBot.
			if ($route->botName && !$route->returnData) {
				$trimName = $this->checkBotName($text);
				if ($trimName === false) return false;
				if (is_string($trimName)) $text = $trimName;
			}
			error_log(PHP_EOL . __LINE__);

			#	Prepare text.
			$textParts = $this->normalizeSplit($text, $route->separator);
			error_log(PHP_EOL . __LINE__);

			#	Process command.
			$params = array_reduce(
				$route->commands,
				function ($carry, $cmd) use ($route, $textParts) {
					error_log(PHP_EOL . __LINE__);

					if ($carry !== null) return false;

					error_log(PHP_EOL . __LINE__);

					$cmdParts = $this->normalizeSplit($cmd, $route->separator);
					if (empty($cmdParts) || count($textParts) < count($cmdParts)) return null;
					if ($cmdParts !== array_slice($textParts, 0, count($cmdParts))) return null;

					$params = array_slice($textParts, count($cmdParts));
					return $route->requireData && empty($params) ? null : $params;
				}
			);
			error_log(PHP_EOL . __LINE__);

			if ($params !== null) {
				$item["method"]->invokeArgs($controller, []);
				return true;
			}
		}
		error_log(PHP_EOL . __LINE__);

		return false;
	}

	/**
	 * Process fuzzy matches.
	 *
	 * @param object $controller
	 * @param string $text
	 * @return bool
	 */
	private function fuzzyMatchDispatch(object $controller, string $text): bool
	{
		foreach ($this->attributes as $item) {
			/** @var Command $route */
			$route = $item["attribute"];

			#	If match temperature is 100% or data return is required - skip.
			if ($route->temperature === 100 || $route->returnData || $route->requireData) continue;

			#	Process command.
			$text = $this->normalizeFuzzyString($text, true);
			$matched = array_filter($route->commands, function ($command) use ($text, $route) {
				$cmd = $this->normalizeFuzzyString($command, true);
				$matched = levenshtein($cmd, $text);
				$maxLen = max(strlen($cmd), strlen($text));
				$percent = (1 - ($matched / $maxLen)) * 100;
				return $percent >= $route->temperature;
			});

			if (!empty($matched)) {
				$item["method"]->invokeArgs($controller, []);
				return true;
			}
		}

		return false;
	}

}
