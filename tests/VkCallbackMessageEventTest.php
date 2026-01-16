<?php

declare(strict_types=1);

namespace Tests\MessengerRouting;

use PHPUnit\Framework\TestCase;
use Tests\MessengerRouting\Mocked\VK;

class VkCallbackMessageEventTest extends TestCase
{

	private array $update = [
		"group_id" => 226352349,
		"type" => "message_event",
		"event_id" => "7002b9b18469800be9ae63eb55e61395b0190e1z",
		"v" => "5.199",
		"object" => [
			"user_id" => 111111111,
			"peer_id" => 222222222,
			"event_id" => "863e06ef1e79",
			"payload" => [
				"action" => "wonderful_action_name",
				"someCustomDataHere" => 1234,
			],
			"conversation_message_id" => 900,
		],
	];

	private static function process($controller, $update)
	{
		$controller->update = $update;
		$controller->__invoke();
		return $controller->wasCalled;
	}

	public function testsForMessageEvent(): void
	{
		$this->update["object"]["payload"]["action"] = "answer_yes";
		self::assertSame("answer_yes", self::process(new VK\AnswerCallback(), $this->update["object"]));

		$this->update["object"]["payload"]["action"] = "answer_no";
		self::assertSame("answer_no", self::process(new VK\AnswerCallback(), $this->update["object"]));
	}

}
