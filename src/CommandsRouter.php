<?php

declare(strict_types=1);

namespace Haikiri\MessengerRouting;

use ReflectionClass;
use ReflectionMethod;

abstract class CommandsRouter
{
	private ?array $attributes;
	protected array|null $data = null;
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
			error_log("No one command source found in your interface.");
			$this->catch_all();
			return;
		}

		$exactResult = $this->exactMatchDispatch($this, $text);
		if ($exactResult) return;

		$fuzzyResult = $this->fuzzyMatchDispatch($this, $text);
		if ($fuzzyResult) return;

		$this->catch_all();
	}

	/**
	 * Implement this method in your class to handle events when matches is differ.
	 */
	protected function catch_all(): void
	{
		error_log(__FUNCTION__ . " - No one command found in your controller.");
		error_log(__FUNCTION__ . " - Implement this method in your class to handle events when matches is differ.");
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
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByEnvAdmin(OnCommand $attribute): bool
	{
		if (!$attribute->isEnvAdmin) return true;
		return (bool)$this->possibleCall("isSenderEnvAdmin");
	}

	/**
	 * Method checks of the owner rights.
	 * Только создатель чата или администратор окружения сможет выполнить эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByOwner(OnCommand $attribute): bool
	{
		if (!$attribute->isOwner) return true;
		return $this->possibleCall("isSenderOwner") || $this->possibleCall("isSenderEnvAdmin");
	}

	/**
	 * Method checks of the admin rights.
	 * Только администратор окружения или чата, сможет выполнить эти команды.
	 *
	 * @param OnCommand $attribute
	 * @return bool
	 */
	private function canAccessByAdmin(OnCommand $attribute): bool
	{
		if (!$attribute->isAdmin) return true;
		return $this->possibleCall("isSenderAdmin")
			|| $this->possibleCall("isSenderOwner")
			|| $this->possibleCall("isSenderEnvAdmin");
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
				$trimName = $this->checkBotName($text);
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
	private function fuzzyMatchDispatch(object $controller, string $text): bool
	{
		foreach ($this->attributes as $item) {
			/** @var OnCommand $route */
			$route = $item["attribute"];

			# If match temperature is 100% or data return is required - skip.
			if ($route->temperature === 100 || $route->return_data || $route->require_data) continue;

			# Process command.
			$text = $this->normalizeFuzzyString($text, true);
			$matched = array_filter($route->commands, function ($command) use ($text, $route) {
				$cmd = $this->normalizeFuzzyString($command, true);
				$matched = levenshtein($cmd, $text);
				$maxLen = max(strlen($cmd), strlen($text));
				if (empty($maxLen)) return false;
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
