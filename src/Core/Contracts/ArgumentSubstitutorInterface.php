<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Contracts;

use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ArgumentList;

/**
 * Interface for substituting argument placeholders in content.
 *
 * Supports positional placeholders ($1, $2, etc.) and the $ARGUMENTS
 * placeholder for the full argument string.
 *
 * @since n.e.x.t
 */
interface ArgumentSubstitutorInterface
{
	/**
	 * Substitutes argument placeholders in content.
	 *
	 * Replaces:
	 * - $1, $2, $3, etc. with corresponding positional arguments
	 * - $ARGUMENTS with the full raw argument string
	 *
	 * Missing positional arguments are replaced with empty strings.
	 *
	 * @param string       $content   The content containing placeholders.
	 * @param ArgumentList $arguments The arguments to substitute.
	 *
	 * @return string The content with placeholders replaced.
	 *
	 * @since n.e.x.t
	 */
	public function substitute(string $content, ArgumentList $arguments): string;
}
