<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Agent;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Agent\AgentState;

/**
 * Tests for AgentState enum.
 *
 * @covers \PhpCliAgent\Core\Agent\AgentState
 */
final class AgentStateTest extends TestCase
{
	/**
	 * @return array<string, array{AgentState, bool}>
	 */
	public static function terminalStatesProvider(): array
	{
		return [
			'PENDING is not terminal' => [AgentState::PENDING, false],
			'THINKING is not terminal' => [AgentState::THINKING, false],
			'ACTING is not terminal' => [AgentState::ACTING, false],
			'COMPLETED is terminal' => [AgentState::COMPLETED, true],
			'CANCELLED is terminal' => [AgentState::CANCELLED, true],
			'MAX_TURNS_REACHED is terminal' => [AgentState::MAX_TURNS_REACHED, true],
			'ERROR is terminal' => [AgentState::ERROR, true],
		];
	}

	/**
	 * @dataProvider terminalStatesProvider
	 */
	public function test_isTerminal_returnsCorrectValue(AgentState $state, bool $expected): void
	{
		$this->assertSame($expected, $state->isTerminal());
	}

	/**
	 * @return array<string, array{AgentState, bool}>
	 */
	public static function processingStatesProvider(): array
	{
		return [
			'PENDING is not processing' => [AgentState::PENDING, false],
			'THINKING is processing' => [AgentState::THINKING, true],
			'ACTING is processing' => [AgentState::ACTING, true],
			'COMPLETED is not processing' => [AgentState::COMPLETED, false],
			'CANCELLED is not processing' => [AgentState::CANCELLED, false],
			'MAX_TURNS_REACHED is not processing' => [AgentState::MAX_TURNS_REACHED, false],
			'ERROR is not processing' => [AgentState::ERROR, false],
		];
	}

	/**
	 * @dataProvider processingStatesProvider
	 */
	public function test_isProcessing_returnsCorrectValue(AgentState $state, bool $expected): void
	{
		$this->assertSame($expected, $state->isProcessing());
	}

	/**
	 * @return array<string, array{AgentState, string}>
	 */
	public static function descriptionsProvider(): array
	{
		return [
			'PENDING description' => [AgentState::PENDING, 'Waiting to start'],
			'THINKING description' => [AgentState::THINKING, 'Processing request'],
			'ACTING description' => [AgentState::ACTING, 'Executing tools'],
			'COMPLETED description' => [AgentState::COMPLETED, 'Completed successfully'],
			'CANCELLED description' => [AgentState::CANCELLED, 'Cancelled by user'],
			'MAX_TURNS_REACHED description' => [AgentState::MAX_TURNS_REACHED, 'Maximum iterations reached'],
			'ERROR description' => [AgentState::ERROR, 'Error occurred'],
		];
	}

	/**
	 * @dataProvider descriptionsProvider
	 */
	public function test_getDescription_returnsCorrectDescription(AgentState $state, string $expected): void
	{
		$this->assertSame($expected, $state->getDescription());
	}

	public function test_isSuccess_returnsTrueOnlyForCompleted(): void
	{
		$this->assertTrue(AgentState::COMPLETED->isSuccess());
		$this->assertFalse(AgentState::PENDING->isSuccess());
		$this->assertFalse(AgentState::THINKING->isSuccess());
		$this->assertFalse(AgentState::ACTING->isSuccess());
		$this->assertFalse(AgentState::CANCELLED->isSuccess());
		$this->assertFalse(AgentState::MAX_TURNS_REACHED->isSuccess());
		$this->assertFalse(AgentState::ERROR->isSuccess());
	}

	/**
	 * @return array<string, array{AgentState, bool}>
	 */
	public static function failureStatesProvider(): array
	{
		return [
			'PENDING is not failure' => [AgentState::PENDING, false],
			'THINKING is not failure' => [AgentState::THINKING, false],
			'ACTING is not failure' => [AgentState::ACTING, false],
			'COMPLETED is not failure' => [AgentState::COMPLETED, false],
			'CANCELLED is failure' => [AgentState::CANCELLED, true],
			'MAX_TURNS_REACHED is not failure' => [AgentState::MAX_TURNS_REACHED, false],
			'ERROR is failure' => [AgentState::ERROR, true],
		];
	}

	/**
	 * @dataProvider failureStatesProvider
	 */
	public function test_isFailure_returnsCorrectValue(AgentState $state, bool $expected): void
	{
		$this->assertSame($expected, $state->isFailure());
	}

	public function test_value_returnsStringRepresentation(): void
	{
		$this->assertSame('pending', AgentState::PENDING->value);
		$this->assertSame('thinking', AgentState::THINKING->value);
		$this->assertSame('acting', AgentState::ACTING->value);
		$this->assertSame('completed', AgentState::COMPLETED->value);
		$this->assertSame('cancelled', AgentState::CANCELLED->value);
		$this->assertSame('max_turns_reached', AgentState::MAX_TURNS_REACHED->value);
		$this->assertSame('error', AgentState::ERROR->value);
	}

	public function test_from_createsEnumFromValue(): void
	{
		$this->assertSame(AgentState::PENDING, AgentState::from('pending'));
		$this->assertSame(AgentState::THINKING, AgentState::from('thinking'));
		$this->assertSame(AgentState::COMPLETED, AgentState::from('completed'));
	}

	public function test_tryFrom_returnsNullForInvalidValue(): void
	{
		$this->assertNull(AgentState::tryFrom('invalid'));
	}

	public function test_cases_returnsAllStates(): void
	{
		$cases = AgentState::cases();

		$this->assertCount(7, $cases);
		$this->assertContains(AgentState::PENDING, $cases);
		$this->assertContains(AgentState::THINKING, $cases);
		$this->assertContains(AgentState::ACTING, $cases);
		$this->assertContains(AgentState::COMPLETED, $cases);
		$this->assertContains(AgentState::CANCELLED, $cases);
		$this->assertContains(AgentState::MAX_TURNS_REACHED, $cases);
		$this->assertContains(AgentState::ERROR, $cases);
	}
}
