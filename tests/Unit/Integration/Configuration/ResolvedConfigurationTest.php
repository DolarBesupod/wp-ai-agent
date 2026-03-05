<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\Configuration\ResolvedConfiguration;

/**
 * Unit tests for ResolvedConfiguration.
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\ResolvedConfiguration
 *
 * @since n.e.x.t
 */
final class ResolvedConfigurationTest extends TestCase
{
	// -----------------------------------------------------------------------
	// getAutoConfirm()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getAutoConfirm() returns true when config has auto_confirm: true.
	 */
	public function test_getAutoConfirm_withTrueValue_returnsTrue(): void
	{
		// Arrange
		$config = new ResolvedConfiguration(['auto_confirm' => true]);

		// Act
		$result = $config->getAutoConfirm();

		// Assert
		$this->assertTrue($result);
	}

	/**
	 * Tests that getAutoConfirm() returns false when config has auto_confirm: false.
	 */
	public function test_getAutoConfirm_withFalseValue_returnsFalse(): void
	{
		// Arrange
		$config = new ResolvedConfiguration(['auto_confirm' => false]);

		// Act
		$result = $config->getAutoConfirm();

		// Assert
		$this->assertFalse($result);
	}

	/**
	 * Tests that getAutoConfirm() returns false when config has no auto_confirm key.
	 */
	public function test_getAutoConfirm_withMissingKey_returnsFalse(): void
	{
		// Arrange — no auto_confirm key in the config array
		$config = new ResolvedConfiguration(['max_turns' => 100]);

		// Act
		$result = $config->getAutoConfirm();

		// Assert
		$this->assertFalse($result);
	}
}
